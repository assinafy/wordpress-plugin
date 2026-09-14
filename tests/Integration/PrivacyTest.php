<?php
/**
 * The personal-data exporter and eraser.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Integration;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Documents\DocumentPostType;
use Assinafy\WP\Documents\DocumentIndex;
use Assinafy\WP\Documents\DocumentRecord;
use Assinafy\WP\Documents\SendService;
use Assinafy\WP\Log;
use Assinafy\WP\Privacy;

/**
 * An eraser that misses a store is worse than no eraser at all, because it reports success.
 * Three stores hold something about a signer — the mirror's signer list, the plugin log and
 * the send-lock transients — so each one is written to here and then checked afterwards.
 *
 * The other half is what must survive: an electronic signature is only worth anything while
 * it can still be shown to be that person's, so the document id, the assignment id and every
 * timestamp are asserted unchanged after the redaction.
 *
 * @covers \Assinafy\WP\Privacy
 */
final class PrivacyTest extends AssinafyTestCase {

	/**
	 * The address being exported and erased.
	 */
	private const SIGNER_EMAIL = 'jane@example.com';

	/**
	 * A second signer on the same document, who must come through untouched.
	 */
	private const OTHER_EMAIL = 'sam@example.com';

	/**
	 * Document id the mirror holds. Evidence: it survives erasure.
	 */
	private const DOCUMENT_ID = '104618d0d63884bc446c534e5ff5';

	/**
	 * Assignment id the mirror holds. Evidence: it survives erasure.
	 */
	private const ASSIGNMENT_ID = '1a09c15990f0144256b98ff38aa';

	/**
	 * Signer id the mirror holds. Evidence: it survives erasure.
	 */
	private const SIGNER_ID = '19e6b92e7895332ed9708535d8c';

	/**
	 * Typed access to the mirror.
	 */
	private DocumentRecord $records;

	/**
	 * The queries that locate records, as opposed to reading one.
	 */
	private DocumentIndex $index;

	/**
	 * The plugin event log.
	 */
	private Log $log;

	/**
	 * The tools under test.
	 */
	private Privacy $privacy;

	/**
	 * Build the tools on their own collaborators.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->records = new DocumentRecord();
		$this->index   = new DocumentIndex();
		$this->log     = new Log();
		$this->privacy = new Privacy( $this->records, $this->log );
	}

	/** Pending receipt erasure survives a later assignment, with native meta escaping. */
	public function test_pending_erasure_survives_assignment_hydration(): void {
		$post_id = self::factory()->post->create( array( 'post_type' => DocumentPostType::POST_TYPE ) );
		$signer  = array(
			'id'          => '',
			'name'        => 'Jane \\ Doe',
			'email'       => self::SIGNER_EMAIL,
			'step'        => 1,
			'notified'    => false,
			'completed'   => false,
			'signing_url' => '',
		);
		$this->records->set_signers( $post_id, array( $signer ) );
		$this->assertSame( $signer, $this->records->signers( $post_id )[0] );
		$this->assertTrue( $this->privacy->erase( self::SIGNER_EMAIL )['items_removed'] );
		$token = $this->records->signers( $post_id )[0]['email'];
		$this->records->hydrate_from_api(
			$post_id,
			array(
				'id'         => self::DOCUMENT_ID,
				'assignment' => null,
			)
		);
		$this->assertSame( $token, $this->records->signers( $post_id )[0]['email'] );
		$this->records->hydrate_from_api(
			$post_id,
			array(
				'id'         => self::DOCUMENT_ID,
				'assignment' => array(
					'id'      => self::ASSIGNMENT_ID,
					'signers' => array(
						array(
							'id'        => self::SIGNER_ID,
							'email'     => self::SIGNER_EMAIL,
							'full_name' => 'Jane Doe',
						),
					),
				),
			)
		);
		$this->assertSame( $token, $this->records->signers( $post_id )[0]['email'] );
		$this->assertSame( $token, $this->records->signers( $post_id )[0]['name'] );
	}

	/**
	 * Both tools appear under Tools without anything else having to register them.
	 */
	public function test_the_plugin_registers_both_tools(): void {
		$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );
		$erasers   = apply_filters( 'wp_privacy_personal_data_erasers', array() );

		$this->assertArrayHasKey( 'assinafy', $exporters );
		$this->assertArrayHasKey( 'assinafy', $erasers );
		$this->assertIsCallable( $exporters['assinafy']['callback'] );
		$this->assertIsCallable( $erasers['assinafy']['callback'] );
	}

	/**
	 * WordPress's policy guide explains the service transfer and retained evidence.
	 */
	public function test_privacy_policy_guidance_describes_external_data_and_retention(): void {
		require_once ABSPATH . 'wp-admin/includes/class-wp-privacy-policy-content.php';
		$this->privacy->register();
		$this->assertSame( 10, has_action( 'admin_init', array( $this->privacy, 'policy_content' ) ) );
		$screen     = get_current_screen();
		$admin_init = $GLOBALS['wp_actions']['admin_init'] ?? 0;
		set_current_screen( 'dashboard' );
		// Core requires admin_init to have fired; unrelated callbacks send HTTP headers.
		$GLOBALS['wp_actions']['admin_init'] = max( 1, $admin_init );
		try {
			$this->privacy->policy_content();
			$content = array_column( \WP_Privacy_Policy_Content::get_suggested_policy_text(), 'policy_text', 'plugin_name' );
		} finally {
			$GLOBALS['current_screen']           = $screen;
			$GLOBALS['wp_actions']['admin_init'] = $admin_init;
		}

		$this->assertArrayHasKey( 'Assinafy', $content );
		$this->assertStringContainsString( 'we send the document, signer names and email addresses', $content['Assinafy'] );
		$this->assertStringContainsString( 'are not erased by this tool', $content['Assinafy'] );
	}

	/**
	 * The export carries the signature request and the signer's own detail, and nothing from
	 * a record the address does not appear on.
	 */
	public function test_the_exporter_reports_the_records_the_address_signed(): void {
		$post_id = $this->create_signed_document();
		$this->create_signed_document( self::OTHER_EMAIL );

		$export = $this->privacy->export( self::SIGNER_EMAIL );

		$this->assertTrue( $export['done'] );
		$this->assertCount( 1, $export['data'] );
		$this->assertSame( 'assinafy-document-' . $post_id, $export['data'][0]['item_id'] );

		$values = $this->export_values( $export['data'][0] );

		$this->assertSame( self::DOCUMENT_ID, $values['Assinafy document id'] );
		$this->assertSame( 'pending_signature', $values['Status'] );
		$this->assertSame( 'Jane Doe', $values['Signer name'] );
		$this->assertSame( self::SIGNER_EMAIL, $values['Signer email'] );
		$this->assertSame( '1', $values['Signing order'] );
		$this->assertSame( (string) get_post_field( 'post_date_gmt', $post_id ), $values['Requested'] );
	}

	/**
	 * An address with nothing to export is answered with an empty, finished page rather than
	 * with a failure.
	 */
	public function test_the_exporter_reports_done_for_an_address_it_has_nothing_for(): void {
		$this->create_signed_document( self::OTHER_EMAIL );

		$export = $this->privacy->export( self::SIGNER_EMAIL );

		$this->assertSame( array(), $export['data'] );
		$this->assertTrue( $export['done'] );
	}

	/**
	 * Trashing a mirror must not hide its personal data from either WordPress tool.
	 */
	public function test_privacy_tools_include_trashed_records(): void {
		$post_id = $this->create_signed_document();
		wp_trash_post( $post_id );

		$this->assertCount( 1, $this->privacy->export( self::SIGNER_EMAIL )['data'] );
		$this->assertTrue( $this->privacy->erase( self::SIGNER_EMAIL )['items_retained'] );
		$this->assertSame( $this->token( self::SIGNER_EMAIL ), $this->records->signers( $post_id )[0]['email'] );
	}

	/**
	 * A later remote refresh updates progress without restoring the erased identity.
	 */
	public function test_erased_identity_stays_erased_after_sync(): void {
		$post_id = $this->create_signed_document();
		$before  = $this->records->signers( $post_id )[0];
		$this->privacy->erase( self::SIGNER_EMAIL );
		$this->records->hydrate_from_api(
			$post_id,
			array(
				'id'         => self::DOCUMENT_ID,
				'status'     => 'certificated',
				'assignment' => array(
					'id'      => self::ASSIGNMENT_ID,
					'signers' => array(
						array(
							'id'        => $before['id'],
							'full_name' => 'Jane Doe',
							'email'     => self::SIGNER_EMAIL,
							'completed' => true,
						),
					),
				),
			)
		);

		$signer = $this->records->signers( $post_id )[0];
		$this->assertSame( $this->token( self::SIGNER_EMAIL ), $signer['email'] );
		$this->assertSame( $this->token( self::SIGNER_EMAIL ), $signer['name'] );
		$this->assertSame( '', $signer['signing_url'] );
		$this->assertTrue( $signer['completed'] );
	}

	/**
	 * Email addresses cannot be guessed from a plain public hash of the address.
	 */
	public function test_redaction_uses_a_site_secret(): void {
		$post_id = $this->create_signed_document();
		$this->privacy->erase( self::SIGNER_EMAIL );

		$this->assertNotSame(
			'[redacted-' . substr( hash( 'sha256', self::SIGNER_EMAIL ), 0, 12 ) . ']',
			$this->records->signers( $post_id )[0]['email']
		);
	}

	/**
	 * A full page is never the last one. WordPress calls back until `done`, and a page that
	 * filled itself has to say so or the records behind it are never looked at.
	 *
	 * The walk is over every record the plugin owns, not over a query matching the address,
	 * so a page can be full and still export nothing.
	 */
	public function test_the_exporter_pages_until_it_runs_out_of_records(): void {
		$oldest = $this->create_signed_document();

		self::factory()->post->create_many(
			100,
			array( 'post_type' => DocumentPostType::POST_TYPE )
		);

		$newest = $this->create_signed_document();

		$first = $this->privacy->export( self::SIGNER_EMAIL, 1 );

		$this->assertFalse( $first['done'], 'A page of exactly the page size cannot be the last one.' );
		$this->assertCount( 1, $first['data'] );
		$this->assertSame( 'assinafy-document-' . $newest, $first['data'][0]['item_id'] );

		$second = $this->privacy->export( self::SIGNER_EMAIL, 2 );

		$this->assertTrue( $second['done'] );
		$this->assertCount( 1, $second['data'] );
		$this->assertSame( 'assinafy-document-' . $oldest, $second['data'][0]['item_id'] );
	}

	/**
	 * A page number below one is the first page, not an offset into nothing.
	 */
	public function test_the_exporter_treats_a_page_below_one_as_the_first(): void {
		$post_id = $this->create_signed_document();

		$export = $this->privacy->export( self::SIGNER_EMAIL, 0 );

		$this->assertCount( 1, $export['data'] );
		$this->assertSame( 'assinafy-document-' . $post_id, $export['data'][0]['item_id'] );
	}

	/**
	 * The eraser replaces who signed and keeps what they signed.
	 */
	public function test_the_eraser_redacts_identity_and_retains_evidence(): void {
		$post_id   = $this->create_signed_document();
		$requested = (string) get_post_field( 'post_date_gmt', $post_id );
		$before    = $this->records->signers( $post_id )[0];

		$result = $this->privacy->erase( self::SIGNER_EMAIL );

		$this->assertTrue( $result['items_removed'], 'Signer identity was removed, while evidence remains.' );
		$this->assertTrue( $result['items_retained'] );
		$this->assertTrue( $result['done'] );
		$this->assertCount( 1, $result['messages'] );

		$signer = $this->records->signers( $post_id )[0];

		$this->assertSame( $this->token( self::SIGNER_EMAIL ), $signer['name'] );
		$this->assertSame( $this->token( self::SIGNER_EMAIL ), $signer['email'] );
		$this->assertSame( '', $signer['signing_url'], 'The signing link carries the address as a query parameter.' );

		$this->assertSame( $before['id'], $signer['id'] );
		$this->assertSame( 1, $signer['step'] );
		$this->assertTrue( $signer['notified'] );
		$this->assertSame( self::DOCUMENT_ID, $this->records->document_id( $post_id ) );
		$this->assertSame( self::ASSIGNMENT_ID, $this->records->assignment_id( $post_id ) );
		$this->assertSame( $requested, (string) get_post_field( 'post_date_gmt', $post_id ) );
	}

	/**
	 * The token is derived from the address, so the same person still reads as the same
	 * person across records without the address being recoverable.
	 */
	public function test_the_same_address_redacts_to_the_same_token_whatever_its_case(): void {
		$first  = $this->create_signed_document();
		$second = $this->create_signed_document();

		$this->privacy->erase( strtoupper( self::SIGNER_EMAIL ) );

		$this->assertSame(
			$this->token( self::SIGNER_EMAIL ),
			$this->records->signers( $first )[0]['email']
		);
		$this->assertSame(
			$this->records->signers( $first )[0]['email'],
			$this->records->signers( $second )[0]['email']
		);
	}

	/**
	 * Only the signer who asked is redacted. The other signatures on the same document are
	 * somebody else's evidence.
	 */
	public function test_the_eraser_leaves_the_other_signers_on_the_document_alone(): void {
		$post_id = $this->create_signed_document( self::SIGNER_EMAIL, self::OTHER_EMAIL );

		$this->privacy->erase( self::SIGNER_EMAIL );

		$signers = $this->records->signers( $post_id );

		$this->assertSame( $this->token( self::SIGNER_EMAIL ), $signers[0]['email'] );
		$this->assertSame( self::OTHER_EMAIL, $signers[1]['email'] );
		$this->assertSame( 'Sam Roe', $signers[1]['name'] );
	}

	/**
	 * An address the site has never seen retains nothing and says nothing.
	 */
	public function test_the_eraser_reports_nothing_for_an_address_it_has_no_records_for(): void {
		$this->create_signed_document( self::OTHER_EMAIL );

		$result = $this->privacy->erase( self::SIGNER_EMAIL );

		$this->assertFalse( $result['items_removed'] );
		$this->assertFalse( $result['items_retained'] );
		$this->assertSame( array(), $result['messages'] );
		$this->assertTrue( $result['done'] );
		$this->assertSame( self::OTHER_EMAIL, $this->records->signers( $this->latest_post_id() )[0]['email'] );
	}

	/**
	 * The log is the second store: a send or a failure may have carried the address into a
	 * context array, and a redaction that stops at post meta leaves it there.
	 */
	public function test_the_eraser_redacts_the_plugin_log(): void {
		$this->create_signed_document();

		$this->log->add(
			'send_failed',
			array(
				'error'   => 'Invitation to ' . self::SIGNER_EMAIL . ' bounced.',
				'signers' => array( array( 'email' => self::SIGNER_EMAIL ) ),
			)
		);

		$this->privacy->erase( self::SIGNER_EMAIL );

		$entries = wp_json_encode( $this->log->entries() );

		$this->assertIsString( $entries );
		$this->assertStringNotContainsString( self::SIGNER_EMAIL, $entries );
		$this->assertStringContainsString( $this->token( self::SIGNER_EMAIL ), $entries );
	}

	/**
	 * The WordPress UI reports successful removal even when the log is the only match.
	 */
	public function test_log_only_erasure_reports_removed_data(): void {
		$this->log->add( 'send_failed', array( 'error' => 'Could not notify ' . self::SIGNER_EMAIL ) );

		$result = $this->privacy->erase( self::SIGNER_EMAIL );

		$this->assertTrue( $result['items_removed'] );
		$this->assertFalse( $result['items_retained'] );
		$this->assertStringNotContainsString( self::SIGNER_EMAIL, (string) wp_json_encode( $this->log->entries() ) );
	}

	/**
	 * The send locks are the third store: their names carry a hash a send may have derived
	 * from the address. They cannot be selected one by one, so they all go.
	 */
	public function test_the_eraser_clears_the_send_locks(): void {
		$this->create_signed_document();

		set_transient( SendService::LOCK_PREFIX . md5( self::SIGNER_EMAIL ), 41, 300 );
		set_transient( SendService::LOCK_PREFIX . md5( 'wc-order-1042' ), -1, 300 );
		set_transient( 'assinafy_webhook_seen_abc', 1, 300 );

		$this->privacy->erase( self::SIGNER_EMAIL );

		$this->assertFalse( get_transient( SendService::LOCK_PREFIX . md5( self::SIGNER_EMAIL ) ) );
		$this->assertFalse( get_transient( SendService::LOCK_PREFIX . md5( 'wc-order-1042' ) ) );
		$this->assertSame( 1, get_transient( 'assinafy_webhook_seen_abc' ), 'Only the send locks are cleared.' );
	}

	/**
	 * The erasure itself is logged, so a site can show what it did and when. The entry names
	 * the token rather than the address it came from.
	 */
	public function test_the_erasure_is_recorded_in_the_log(): void {
		$this->create_signed_document();

		$this->privacy->erase( self::SIGNER_EMAIL );

		$entries = $this->log->entries();
		$last    = end( $entries );

		$this->assertIsArray( $last );
		$this->assertSame( 'privacy_erasure', $last['event'] );
		$this->assertSame( 1, $last['context']['records'] );
		$this->assertSame( $this->token( self::SIGNER_EMAIL ), $last['context']['redacted_as'] );
	}

	/**
	 * The replacement token for an address, derived the way the eraser derives it.
	 *
	 * @param string $email Address being redacted.
	 */
	private function token( string $email ): string {
		return '[redacted-' . substr( hash_hmac( 'sha256', strtolower( $email ), wp_salt( 'auth' ) ), 0, 12 ) . ']';
	}

	/**
	 * One export item's values, keyed by their label.
	 *
	 * @param array<string, mixed> $item One entry of the exporter's `data`.
	 *
	 * @return array<string, string>
	 */
	private function export_values( array $item ): array {
		$values = array();

		foreach ( $item['data'] as $field ) {
			$values[ (string) $field['name'] ] = (string) $field['value'];
		}

		return $values;
	}

	/**
	 * The newest record the plugin owns.
	 */
	private function latest_post_id(): int {
		return $this->index->post_ids( 1 )[0];
	}

	/**
	 * Mirror one signature request, hydrated from the API document shape the plugin stores.
	 *
	 * @param string ...$emails One signer per address, in signing order.
	 *
	 * @return int The mirror post id.
	 */
	private function create_signed_document( string ...$emails ): int {
		$emails = array() === $emails ? array( self::SIGNER_EMAIL ) : $emails;
		$names  = array(
			self::SIGNER_EMAIL => 'Jane Doe',
			self::OTHER_EMAIL  => 'Sam Roe',
		);

		$post_id = (int) self::factory()->post->create(
			array(
				'post_type'  => DocumentPostType::POST_TYPE,
				'post_title' => 'contract.pdf',
			)
		);

		$signers      = array();
		$signing_urls = array();

		foreach ( array_values( $emails ) as $index => $email ) {
			$signer_id = self::SIGNER_ID . $index;

			$signers[] = array(
				'id'                   => $signer_id,
				'full_name'            => $names[ $email ] ?? 'Unnamed',
				'email'                => $email,
				'completed'            => false,
				'step'                 => $index + 1,
				'notified'             => true,
				'verification_method'  => 'Email',
				'notification_methods' => array( 'Email' ),
			);

			$signing_urls[] = array(
				'signer_id' => $signer_id,
				'url'       => 'https://app.assinafy.com.br/sign/' . self::DOCUMENT_ID . '?email=' . rawurlencode( $email ),
			);
		}

		( new DocumentRecord() )->hydrate_from_api(
			$post_id,
			array(
				'id'         => self::DOCUMENT_ID,
				'name'       => 'contract.pdf',
				'status'     => 'pending_signature',
				'is_closed'  => false,
				'artifacts'  => array( 'original' => self::API_HOST . 'v1/documents/' . self::DOCUMENT_ID . '/download/original' ),
				'assignment' => array(
					'id'           => self::ASSIGNMENT_ID,
					'signers'      => $signers,
					'signing_urls' => $signing_urls,
				),
			)
		);

		return $post_id;
	}
}
