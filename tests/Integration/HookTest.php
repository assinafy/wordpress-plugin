<?php
/**
 * The public send hook.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Integration;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Documents\DocumentPostType;
use Assinafy\WP\Documents\DocumentRecord;
use Assinafy\WP\Integrations\Hook;
use WP_Error;

/**
 * `do_action( 'assinafy_send_document', $args )` is the one thing third-party code binds to,
 * so these run against the listener the plugin itself registered on boot rather than against
 * a hand-built instance: a contract that only works when the test wires it up is not a
 * contract.
 *
 * Every case asserts through `assinafy_send_document_result`, which is the only channel an
 * integration has. Nothing is thrown back at the caller, by design — a form plugin firing an
 * action has no `try` around it.
 *
 * @covers \Assinafy\WP\Integrations\Hook
 */
final class HookTest extends AssinafyTestCase {

	/**
	 * Document id the fake upload hands back.
	 */
	private const DOCUMENT_ID = '104618d0d63884bc446c534e5ff5';

	/**
	 * Assignment id the fake assignment create hands back.
	 */
	private const ASSIGNMENT_ID = '1a09c15990f0144256b98ff38aa';

	/**
	 * Signer id the fake lookup hands back.
	 */
	private const SIGNER_ID = '19e6b92e7895332ed9708535d8c';

	/**
	 * Everything `assinafy_send_document_result` announced, oldest first.
	 *
	 * @var array<int, array{result: mixed, args: array<string, mixed>}>
	 */
	private array $results = array();

	/**
	 * Give the plugin credentials and listen on the result action.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->configure_plugin();

		$this->results = array();

		add_action(
			Hook::ACTION_RESULT,
			function ( mixed $result, array $args ): void {
				$this->results[] = array(
					'result' => $result,
					'args'   => $args,
				);
			},
			10,
			2
		);
	}

	/**
	 * Both public actions carry a listener from the moment the plugin boots.
	 */
	public function test_the_plugin_listens_on_both_public_actions(): void {
		$this->assertNotFalse( has_action( Hook::ACTION ) );
		$this->assertNotFalse( has_action( Hook::ACTION_ASYNC ) );
	}

	/**
	 * The documented payload uploads, assigns, mirrors and reports the post id.
	 */
	public function test_a_valid_payload_sends_and_reports_the_local_record(): void {
		$this->fake_successful_send();

		do_action( Hook::ACTION, $this->args() );

		$post_id = $this->only_result();

		$this->assertIsInt( $post_id );
		$this->assertSame( DocumentPostType::POST_TYPE, get_post_type( $post_id ) );

		$records = new DocumentRecord();

		$this->assertSame( self::DOCUMENT_ID, $records->document_id( $post_id ) );
		$this->assertSame( self::ASSIGNMENT_ID, $records->assignment_id( $post_id ) );
	}

	/**
	 * The original `$args` are handed back beside the result so a listener can tell which of
	 * its own sends this one was.
	 */
	public function test_the_result_carries_the_arguments_it_was_fired_with(): void {
		$this->fake_successful_send();

		$args = $this->args( array( 'idempotency_key' => 'contact-form-7-entry-1187' ) );

		do_action( Hook::ACTION, $args );

		$this->assertCount( 1, $this->results );
		$this->assertSame( $args, $this->results[0]['args'] );
	}

	/**
	 * Anything that is not an array is refused before it can become a `TypeError` inside
	 * somebody else's form handler.
	 */
	public function test_a_payload_that_is_not_an_array_is_refused(): void {
		do_action( Hook::ACTION, 'not-an-array' );

		$this->assertSame( 'assinafy_send_invalid_args', $this->error_code() );
		$this->assertSame( array(), $this->results[0]['args'] );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * Neither PDF source present.
	 */
	public function test_a_payload_without_a_document_is_refused(): void {
		$args = $this->args();
		unset( $args['file_path'] );

		do_action( Hook::ACTION, $args );

		$this->assertSame( 'assinafy_send_no_document', $this->error_code() );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * An empty `file_path` is the same as no `file_path`: a form field that was left blank
	 * must not reach the API.
	 */
	public function test_an_empty_file_path_is_refused(): void {
		do_action( Hook::ACTION, $this->args( array( 'file_path' => '' ) ) );

		$this->assertSame( 'assinafy_send_no_document', $this->error_code() );
	}

	/**
	 * An attachment id of zero is no attachment id.
	 */
	public function test_a_zero_attachment_id_is_refused(): void {
		$args = $this->args( array( 'attachment_id' => 0 ) );
		unset( $args['file_path'] );

		do_action( Hook::ACTION, $args );

		$this->assertSame( 'assinafy_send_no_document', $this->error_code() );
	}

	/**
	 * No signers at all.
	 */
	public function test_a_payload_without_signers_is_refused(): void {
		$args = $this->args();
		unset( $args['signers'] );

		do_action( Hook::ACTION, $args );

		$this->assertSame( 'assinafy_send_no_signers', $this->error_code() );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * An empty signer list is refused for the same reason: it would upload a document nobody
	 * could ever sign.
	 */
	public function test_an_empty_signer_list_is_refused(): void {
		do_action( Hook::ACTION, $this->args( array( 'signers' => array() ) ) );

		$this->assertSame( 'assinafy_send_no_signers', $this->error_code() );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * A signer without a name cannot be created on the account.
	 */
	public function test_a_signer_without_a_name_is_refused(): void {
		do_action(
			Hook::ACTION,
			$this->args( array( 'signers' => array( array( 'email' => 'jane@example.com' ) ) ) )
		);

		$this->assertSame( 'assinafy_signer_incomplete', $this->error_code() );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * An address that cannot be used is caught locally. Letting it through would spend a
	 * document on a request nobody can ever act on, because the upload precedes the
	 * assignment.
	 *
	 * The refusal names the address itself rather than the missing contact detail: the sender
	 * typed something, so "that address is not valid" is what they need to read.
	 */
	public function test_an_invalid_signer_email_is_refused(): void {
		do_action(
			Hook::ACTION,
			$this->args(
				array(
					'signers' => array(
						array(
							'full_name' => 'Jane Doe',
							'email'     => 'not-an-email',
						),
					),
				)
			)
		);

		$this->assertSame( 'assinafy_signer_email', $this->error_code() );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * Addresses `sanitize_email()` does not empty but silently rewrites into a different,
	 * valid address. Each of these would otherwise be accepted and the signature request sent
	 * to whoever owns the rewritten address, with nothing shown to the sender.
	 *
	 * @dataProvider repairable_addresses
	 *
	 * @param string $typed The address as entered.
	 */
	public function test_an_address_that_only_becomes_valid_after_repair_is_refused( string $typed ): void {
		$this->assertNotSame(
			'',
			sanitize_email( $typed ),
			'This case is only meaningful while sanitize_email() still rewrites the address.'
		);

		do_action(
			Hook::ACTION,
			$this->args(
				array(
					'signers' => array(
						array(
							'full_name' => 'Jane Doe',
							'email'     => $typed,
						),
					),
				)
			)
		);

		$this->assertSame( 'assinafy_signer_email', $this->error_code() );
		$this->assertSame( array(), $this->requests, 'Nothing may be uploaded for an unreachable signer.' );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function repairable_addresses(): array {
		return array(
			'doubled @'       => array( 'jane@@example.com' ),
			'space in domain' => array( 'jane@exam ple.com' ),
			'markup in local' => array( '<script>@example.com' ),
		);
	}

	/**
	 * Legitimate address forms must survive the stricter check: plus-addressing, dots,
	 * mixed case, apostrophes and surrounding whitespace are all valid input.
	 *
	 * @dataProvider valid_addresses
	 *
	 * @param string $typed The address as entered.
	 */
	public function test_a_valid_address_is_accepted( string $typed ): void {
		do_action(
			Hook::ACTION,
			$this->args(
				array(
					'signers' => array(
						array(
							'full_name' => 'Jane Doe',
							'email'     => $typed,
						),
					),
				)
			)
		);

		$this->assertNotSame( 'assinafy_signer_email', $this->error_code() );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function valid_addresses(): array {
		return array(
			'plain'           => array( 'jane@example.com' ),
			'plus addressing' => array( 'jane+contracts@example.co.uk' ),
			'mixed case'      => array( 'Jane.Doe@Example.COM' ),
			'apostrophe'      => array( "jane_o'brien@example.com" ),
			'padded'          => array( '  jane@example.com  ' ),
		);
	}

	/**
	 * Assinafy accepts PDFs only, and a rejected upload still creates a `failed` document in
	 * the customer's workspace, so the mime type is checked here instead.
	 */
	public function test_a_non_pdf_attachment_is_refused(): void {
		$attachment_id = (int) self::factory()->post->create(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/png',
				'post_title'     => 'logo.png',
			)
		);

		$args = $this->args( array( 'attachment_id' => $attachment_id ) );
		unset( $args['file_path'] );

		do_action( Hook::ACTION, $args );

		$this->assertSame( 'assinafy_not_a_pdf', $this->error_code() );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * An attachment row whose file is not on disk — restored from a database-only backup, or
	 * pointing into a path the web user cannot open — is refused before the upload.
	 */
	public function test_an_attachment_whose_file_cannot_be_read_is_refused(): void {
		$attachment_id = (int) self::factory()->post->create(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'application/pdf',
				'post_title'     => 'missing.pdf',
			)
		);

		update_post_meta( $attachment_id, '_wp_attached_file', 'assinafy-tests/missing.pdf' );

		$args = $this->args( array( 'attachment_id' => $attachment_id ) );
		unset( $args['file_path'] );

		do_action( Hook::ACTION, $args );

		$this->assertSame( 'assinafy_invalid_pdf', $this->error_code() );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * An attachment row with no file at all reports the missing file rather than handing an
	 * empty path to the SDK.
	 */
	public function test_an_attachment_with_no_file_is_refused(): void {
		$attachment_id = (int) self::factory()->post->create(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'application/pdf',
				'post_title'     => 'nothing.pdf',
			)
		);

		$args = $this->args( array( 'attachment_id' => $attachment_id ) );
		unset( $args['file_path'] );

		do_action( Hook::ACTION, $args );

		$this->assertSame( 'assinafy_file_missing', $this->error_code() );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * The asynchronous action queues the inline one and returns without touching the API.
	 */
	public function test_the_async_action_schedules_the_inline_action(): void {
		$args = $this->args();

		do_action( Hook::ACTION_ASYNC, $args );

		$this->assertIsInt( wp_next_scheduled( Hook::ACTION, array( $args ) ) );
		$this->assertSame( array(), $this->requests, 'Scheduling must not reach the API.' );
		$this->assertSame( array(), $this->results, 'A queued send has no outcome to report yet.' );
	}

	/**
	 * The scheduled event runs through the same handler as the inline action, so a listener
	 * on the public action sees the deferred send too.
	 */
	public function test_the_scheduled_event_sends_when_cron_fires(): void {
		$this->fake_successful_send();

		$source = array(
			'integration' => 'gravity-forms',
			'record_id'   => 'entry-1187',
		);
		$args   = $this->args( array( 'source' => $source ) );

		do_action( Hook::ACTION_ASYNC, $args );

		$this->assertSame( array(), $this->results );

		// Read the persisted cron payload, then dispatch it exactly as WordPress does.
		$event = wp_get_scheduled_event( Hook::ACTION, array( $args ) );
		$this->assertIsObject( $event );
		$this->assertSame( array( $args ), $event->args );
		wp_unschedule_event( $event->timestamp, Hook::ACTION, $event->args );
		do_action_ref_array( Hook::ACTION, $event->args );

		$post_id = $this->only_result();

		$this->assertIsInt( $post_id );
		$this->assertSame( self::DOCUMENT_ID, ( new DocumentRecord() )->document_id( $post_id ) );
		$this->assertSame( $source, ( new DocumentRecord() )->source( $post_id ) );
		$this->assertSame( $args, $this->results[0]['args'] );
	}

	/**
	 * Invalid origins are refused synchronously instead of becoming failed cron events.
	 *
	 * @dataProvider invalid_sources
	 * @param mixed $source Malformed origin supplied by an integration.
	 */
	public function test_the_async_action_refuses_invalid_source_without_scheduling( mixed $source ): void {
		$args = $this->args( array( 'source' => $source ) );
		do_action( Hook::ACTION_ASYNC, $args );
		$this->assertSame( 'assinafy_invalid_source', $this->error_code() );
		$this->assertSame( $args, $this->results[0]['args'] );
		$this->assertFalse( wp_next_scheduled( Hook::ACTION, array( $args ) ) );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * @return array<string, array{0: mixed}>
	 */
	public static function invalid_sources(): array {
		return array(
			'explicit null' => array( null ),
			'malformed id'  => array(
				array(
					'integration' => 'gravity-forms',
					'record_id'   => 'entry 1187',
				),
			),
		);
	}

	/**
	 * The async action validates before it schedules, so a mistake is reported to the caller
	 * that made it rather than in a cron run nobody is watching.
	 */
	public function test_the_async_action_refuses_a_bad_payload_without_scheduling(): void {
		$args = $this->args();
		unset( $args['signers'] );

		do_action( Hook::ACTION_ASYNC, $args );

		$this->assertSame( 'assinafy_send_no_signers', $this->error_code() );
		$this->assertFalse( wp_next_scheduled( Hook::ACTION, array( $args ) ) );
	}

	/**
	 * WordPress refuses a second identical schedule inside ten minutes. That is the guard
	 * working, not a failure, so it is not reported as one — the first schedule still runs.
	 */
	public function test_a_duplicate_schedule_is_not_reported_as_a_failure(): void {
		$args = $this->args();

		do_action( Hook::ACTION_ASYNC, $args );
		do_action( Hook::ACTION_ASYNC, $args );

		$this->assertIsInt( wp_next_scheduled( Hook::ACTION, array( $args ) ) );
		$this->assertSame( array(), $this->results );
		$this->assertCount( 1, _get_cron_array()[ wp_next_scheduled( Hook::ACTION, array( $args ) ) ][ Hook::ACTION ] );
	}

	/**
	 * The single result announced, unwrapped.
	 */
	private function only_result(): mixed {
		$this->assertCount( 1, $this->results, 'Exactly one outcome must be announced per send.' );

		return $this->results[0]['result'];
	}

	/**
	 * The error code of the single result announced.
	 */
	private function error_code(): string {
		$result = $this->only_result();

		$this->assertInstanceOf( WP_Error::class, $result );

		return $result->get_error_code();
	}

	/**
	 * The documented `$args` shape, pointing at the checked-in PDF.
	 *
	 * @param array<string, mixed> $overrides Keys to replace.
	 *
	 * @return array<string, mixed>
	 */
	private function args( array $overrides = array() ): array {
		return array_merge(
			array(
				'file_path' => dirname( __DIR__ ) . '/fixtures/sample.pdf',
				'signers'   => array(
					array(
						'full_name' => 'Jane Doe',
						'email'     => 'jane@example.com',
					),
				),
				'message'   => 'Please review and sign the attached agreement.',
			),
			$overrides
		);
	}

	/**
	 * Queue the three calls one successful send makes: the upload, the signer lookup that
	 * finds the address already on the account, and the assignment create.
	 *
	 * Captured from the sandbox; the bodies are the ones in `probe-documents.md` and
	 * `probe-signatures.md` with the identifiers replaced.
	 */
	private function fake_successful_send(): void {
		$this->fake_response(
			'/accounts/' . self::ACCOUNT_ID . '/documents',
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array(
					'resource'  => 'document',
					'id'        => self::DOCUMENT_ID,
					'name'      => 'sample.pdf',
					'status'    => 'uploaded',
					'artifacts' => array( 'original' => self::API_HOST . 'v1/documents/' . self::DOCUMENT_ID . '/download/original' ),
					'is_closed' => false,
					'tags'      => array(),
					'pages'     => array(),
				),
			)
		);

		$this->fake_response(
			'/signers?',
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array(
					array(
						'resource'              => 'signer',
						'id'                    => self::SIGNER_ID,
						'full_name'             => 'Jane Doe',
						'email'                 => 'jane@example.com',
						'whatsapp_phone_number' => null,
					),
				),
			)
		);

		$this->fake_response(
			'/assignments',
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array(
					'resource'     => 'assignment',
					'id'           => self::ASSIGNMENT_ID,
					'method'       => 'virtual',
					'message'      => 'Please review and sign the attached agreement.',
					'signers'      => array(
						array(
							'id'        => self::SIGNER_ID,
							'full_name' => 'Jane Doe',
							'email'     => 'jane@example.com',
							'completed' => false,
							'step'      => 1,
							'notified'  => true,
						),
					),
					'signing_urls' => array(
						array(
							'signer_id' => self::SIGNER_ID,
							'url'       => 'https://app.assinafy.com.br/sign/' . self::DOCUMENT_ID . '?email=jane%40example.com',
						),
					),
				),
			)
		);
	}
}
