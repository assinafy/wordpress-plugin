<?php
/**
 * Real Contact Form 7 lifecycle with intercepted WordPress HTTP and mail.
 *
 * @package Assinafy\WP\Addons\ContactForm7
 */

declare(strict_types=1);

namespace Assinafy\WP\Addons\ContactForm7\Tests;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Addons\ContactForm7\Adapter;
use Assinafy\WP\Addons\ContactForm7\FormSettings;
use Assinafy\WP\Addons\ContactForm7\Requests;
use Assinafy\WP\ClientFactory;
use Assinafy\WP\Credentials;
use Assinafy\WP\Documents\DocumentPostType;
use Assinafy\WP\Documents\DocumentRecord;
use Assinafy\WP\Documents\SendService;
use Assinafy\WP\Log;
use Assinafy\WP\Privacy;
use Assinafy\WP\Tests\Integration\AssinafyTestCase;
use WPCF7_ContactForm;
use WPCF7_Submission;

/**
 * @covers \Assinafy\WP\Addons\ContactForm7\Adapter
 * @covers \Assinafy\WP\Addons\ContactForm7\FormSettings
 * @covers \Assinafy\WP\Addons\ContactForm7\Requests
 */
final class ContactForm7Test extends AssinafyTestCase {

	private Adapter $adapter;
	private Requests $receipts;
	private DocumentRecord $records;
	private WPCF7_ContactForm $form;
	private int $pdf;
	private const REMOTE = '104618d0d63884bc446c534e5ff5';
	private const SIGNER = '19e6b92e7895332ed9708535d8c';

	/** Set up a real host form and isolate the request-local host singleton. */
	public function set_up(): void {
		parent::set_up();
		if ( ! class_exists( WPCF7_ContactForm::class ) ) {
			$this->markTestSkipped( 'Set ASSINAFY_CF7_FILE to the official Contact Form 7 plugin.' );
		}
		$this->reset_submission();
		$this->configure_plugin();
		$this->records  = new DocumentRecord();
		$send           = new SendService( new ClientFactory( new Credentials(), new Log() ), $this->records, new Log() );
		$this->adapter  = new Adapter( $send, $this->records );
		$this->receipts = new Requests( $send, $this->records );
		remove_all_actions( 'wpcf7_submit' );
		$this->adapter->register();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->pdf = self::factory()->post->create(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'application/pdf',
				'post_title'     => 'Agreement',
			)
		);
		update_post_meta( $this->pdf, '_wp_attached_file', dirname( __DIR__, 3 ) . '/tests/fixtures/sample.pdf' );
		$this->form = WPCF7_ContactForm::get_template();
		$this->form->set_properties( array( 'form' => '[text* your-name] [email* your-email] [acceptance consent optional]Agree[/acceptance] [submit "Send"]' ) );
		$this->form->save();
		update_post_meta(
			$this->form->id(),
			Adapter::SETTINGS,
			array(
				'enabled'       => true,
				'attachment_id' => $this->pdf,
				'name'          => 'your-name',
				'email'         => 'your-email',
				'consent'       => '',
				'message'       => 'Please sign.',
			)
		);
		$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
		$_SERVER['HTTP_USER_AGENT'] = 'Assinafy integration test';
		add_filter( 'pre_wp_mail', '__return_true' );
	}

	/** Release the host's request singleton after each simulated submission. */
	public function tear_down(): void {
		if ( class_exists( WPCF7_Submission::class ) ) {
			$this->reset_submission();
		}
		$_POST    = array();
		$_REQUEST = array();
		parent::tear_down();
	}

	/** A true accepted submission sends once and repeated callbacks reuse its receipt. */
	public function test_successful_submission_and_duplicate_callback_share_one_request(): void {
		$this->fake_send();
		$result = $this->submit();
		$this->assertSame( 'mail_sent', $result['status'] );
		$ids = $this->ids();
		$this->assertCount( 1, $ids );
		$id = $ids[0];
		$this->assertSame(
			array(
				'integration' => 'contact-form-7',
				'record_id'   => $this->form->id() . ':' . $id,
			),
			$this->records->source( $id )
		);
		$this->assertSame( self::REMOTE, $this->records->document_id( $id ) );
		$this->assertSame( array(), $this->receipts->payload( $id ) );
		$this->assertStringNotContainsString( 'unmapped-private-value', (string) wp_json_encode( get_post_meta( $id ) ) );
		$count = count( $this->requests );
		do_action( 'wpcf7_submit', $this->form, $result );
		$this->assertSame( $ids, $this->ids() );
		$this->assertCount( $count, $this->requests );
	}

	/** Invalid, spam, aborted, failed-mail and demo submissions cannot spend a document. */
	public function test_non_successful_results_never_send(): void {
		foreach ( array( 'validation_failed', 'acceptance_missing', 'spam', 'aborted', 'mail_failed' ) as $status ) {
			$this->adapter->submitted( $this->form, array( 'status' => $status ) );
		}
		$result = $this->submit( array( 'your-email' => 'not-an-email' ) );
		$this->assertSame( 'validation_failed', $result['status'] );
		$this->reset_submission();
		$this->form->set_properties( array( 'additional_settings' => 'demo_mode: on' ) );
		$this->assertSame( 'mail_sent', $this->submit()['status'] );
		$this->assertSame( array(), $this->ids() );
		$this->assertSame( array(), $this->requests );
	}

	/** Explicit spam and aborted host hooks run before the adapter's successful boundary. */
	public function test_real_spam_and_abort_checks_prevent_requests(): void {
		add_filter( 'wpcf7_spam', '__return_true' );
		$this->assertSame( 'spam', $this->submit()['status'] );
		remove_filter( 'wpcf7_spam', '__return_true' );
		$this->reset_submission();
		add_action(
			'wpcf7_before_send_mail',
			static function ( $form, &$abort ): void {
				$abort = true;
			},
			10,
			2
		);
		$this->assertSame( 'aborted', $this->submit()['status'] );
		$this->assertSame( array(), $this->ids() );
	}

	/** An optional mapped acceptance field gates signatures without changing CF7 success. */
	public function test_consent_must_be_checked_when_mapped(): void {
		$settings            = $this->adapter->settings( $this->form );
		$settings['consent'] = 'consent';
		update_post_meta( $this->form->id(), Adapter::SETTINGS, $settings );
		$this->assertSame( 'mail_sent', $this->submit( array( 'consent' => '' ) )['status'] );
		$this->assertSame( array(), $this->ids() );
		$this->reset_submission();
		$this->fake_send();
		$this->assertSame( 'mail_sent', $this->submit( array( 'consent' => '1' ) )['status'] );
		$this->assertCount( 1, $this->ids() );
	}

	/** Native storage consent blocks the receipt even when no adapter acceptance is mapped. */
	public function test_native_storage_consent_is_respected(): void {
		$this->form->set_properties( array( 'form' => '[text* your-name] [email* your-email] [acceptance consent optional consent_for:storage]Store my data[/acceptance]' ) );
		$this->assertSame( 'mail_sent', $this->submit( array( 'consent' => '' ) )['status'] );
		$this->assertTrue( WPCF7_Submission::get_instance()->get_meta( 'do_not_store' ) );
		$this->assertSame( array(), $this->ids() );
		$this->assertSame( array(), $this->requests );
	}

	/** A genuine failed wp_mail result cannot create a signature request. */
	public function test_real_mail_failure_does_not_send(): void {
		add_filter( 'wpcf7_skip_mail', '__return_false' );
		remove_filter( 'pre_wp_mail', '__return_true' );
		add_filter( 'pre_wp_mail', '__return_false' );
		$this->assertSame( 'mail_failed', $this->submit()['status'] );
		$this->assertSame( array(), $this->ids() );
		$this->assertSame( array(), $this->requests );
	}

	/** The host's editor nonce and capability protect the settings; absent fields preserve it. */
	public function test_settings_reuse_host_nonce_and_permissions(): void {
		$original = $this->adapter->settings( $this->form );
		$_POST    = array(
			'post_ID'      => (string) $this->form->id(),
			'assinafy_cf7' => array(
				'enabled'       => '1',
				'attachment_id' => (string) $this->pdf,
				'name'          => 'your-name',
				'email'         => 'your-email',
				'message'       => 'New invitation',
			),
		);
		$this->form->save();
		$this->assertSame( $original, $this->adapter->settings( $this->form ) );
		$_POST['_wpnonce'] = wp_create_nonce( 'wpcf7-save-contact-form_' . $this->form->id() );
		$this->form->save();
		$this->assertSame( 'New invitation', $this->adapter->settings( $this->form )['message'] );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$_POST['_wpnonce']                = wp_create_nonce( 'wpcf7-save-contact-form_' . $this->form->id() );
		$_POST['assinafy_cf7']['message'] = 'Forbidden replacement';
		$this->form->save();
		$this->assertSame( 'New invitation', $this->adapter->settings( $this->form )['message'] );
	}

	/** A role allowed to manage configuration still needs permission to authorize sending. */
	public function test_manage_without_send_cannot_configure_the_workflow(): void {
		wp_get_current_user()->add_cap( 'assinafy_send', false );
		$this->assertTrue( current_user_can( 'assinafy_manage' ) );
		$original = $this->adapter->settings( $this->form );
		$_POST    = array(
			'post_ID'      => (string) $this->form->id(),
			'_wpnonce'     => wp_create_nonce( 'wpcf7-save-contact-form_' . $this->form->id() ),
			'assinafy_cf7' => array_merge( $original, array( 'message' => 'Unauthorized invitation' ) ),
		);
		$this->form->save();
		$this->assertSame( $original, $this->adapter->settings( $this->form ) );
		$settings = new FormSettings();
		$this->assertArrayNotHasKey( 'assinafy-panel', $settings->panels( array() ) );
		ob_start();
		$settings->render( $this->form );
		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * No valid nonce can bypass the send, receipt or originating form permission.
	 *
	 * @dataProvider retry_permissions
	 * @param string $denied_cap Capability denied independently.
	 */
	public function test_retry_requires_every_permission( string $denied_cap ): void {
		$this->submit();
		$id    = $this->ids()[0];
		$count = count( $this->requests );
		add_filter(
			'map_meta_cap',
			static fn( array $caps, string $cap ): array => $denied_cap === $cap ? array( 'do_not_allow' ) : $caps,
			99,
			2
		);
		ob_start();
		$this->receipts->render( get_post( $id ) );
		$this->assertSame( '', ob_get_clean() );
		$_POST['request_id']  = $id;
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'assinafy_cf7_retry_' . $id );
		$this->expectException( \WPDieException::class );
		try {
			$this->receipts->retry();
		} finally {
			$this->assertCount( $count, $this->requests );
		}
	}

	/** @return array<string, array{string}> Independent admin retry boundaries. */
	public static function retry_permissions(): array {
		return array(
			'configure' => array( 'assinafy_manage' ),
			'send'      => array( 'assinafy_send' ),
			'receipt'   => array( 'edit_post' ),
			'host form' => array( 'wpcf7_edit_contact_form' ),
		);
	}

	/** Even an authorized editor must submit the original receipt's valid nonce. */
	public function test_retry_rejects_invalid_nonce(): void {
		$this->submit();
		$count                = count( $this->requests );
		$_POST['request_id']  = $this->ids()[0];
		$_REQUEST['_wpnonce'] = 'invalid';
		$this->expectException( \WPDieException::class );
		try {
			$this->receipts->retry();
		} finally {
			$this->assertCount( $count, $this->requests );
		}
	}

	/** Pending signer projection participates in core privacy even before it has a remote ID. */
	public function test_pending_receipt_erasure_prevents_retry(): void {
		$this->submit(); // No HTTP fixture: signer lookup fails, leaving a receipt.
		$id = $this->ids()[0];
		$this->assertNotSame( array(), $this->receipts->payload( $id ) );
		$privacy = new Privacy( $this->records, new Log() );
		$this->assertCount( 1, $privacy->export( 'jane@example.com' )['data'] );
		$this->assertTrue( $privacy->erase( 'jane@example.com' )['items_removed'] );
		$count = count( $this->requests );
		$this->assertWPError( $this->receipts->send( $id ) );
		$this->assertCount( $count, $this->requests );
		$this->assertStringStartsWith( '[redacted-', $this->records->signers( $id )[0]['email'] );
	}

	/** Assignment failure keeps the same uploaded document and original retry arguments. */
	public function test_partial_failure_resumes_same_upload(): void {
		$this->fake_send();
		$this->fake_response(
			'/assignments',
			503,
			array(
				'status'  => 503,
				'message' => 'Unavailable',
				'data'    => null,
			)
		);
		$this->assertSame( 'mail_sent', $this->submit()['status'] );
		$id = $this->ids()[0];
		$this->assertSame( self::REMOTE, $this->records->document_id( $id ) );
		$this->assertNotSame( array(), $this->receipts->payload( $id ) );
		$this->fake_send();
		$this->fake_response(
			'/documents/' . self::REMOTE,
			200,
			array(
				'status' => 200,
				'data'   => array(
					'id'         => self::REMOTE,
					'account_id' => self::ACCOUNT_ID,
					'status'     => 'uploaded',
				),
			)
		);
		$this->assertSame( $id, $this->receipts->send( $id ) );
		$uploads = array_filter( $this->requests, static fn( array $request ): bool => str_ends_with( $request['url'], '/accounts/' . self::ACCOUNT_ID . '/documents' ) );
		$this->assertCount( 1, $uploads );
		$this->assertSame( array(), $this->receipts->payload( $id ) );
	}

	/** Retry controls belong to their own footer form, not the enclosing WordPress editor. */
	public function test_retry_form_uses_native_form_ownership(): void {
		$this->submit();
		$id = $this->ids()[0];
		ob_start();
		$this->receipts->render( get_post( $id ) );
		$panel = ob_get_clean();
		$this->assertStringNotContainsString( '<form', $panel );
		$this->assertStringContainsString( 'form="assinafy-cf7-retry-' . $id . '"', $panel );
		ob_start();
		do_action( 'admin_footer' );
		$footer = ob_get_clean();
		$this->assertStringContainsString( 'id="assinafy-cf7-retry-' . $id . '"', $footer );
		$this->assertStringContainsString( 'name="_wpnonce"', $footer );
	}

	/**
	 * @param array<string, string> $overrides Submitted controls to change.
	 * @return array<string, mixed> Native host result.
	 */
	private function submit( array $overrides = array() ): array {
		$_POST = array_merge(
			array(
				'_wpcf7'          => (string) $this->form->id(),
				'_wpcf7_unit_tag' => 'wpcf7-f' . $this->form->id() . '-o1',
				'your-name'       => 'Jane Doe',
				'your-email'      => 'jane@example.com',
				'private-field'   => 'unmapped-private-value',
				'consent'         => '1',
			),
			$overrides
		);
		return $this->form->submit( array( 'skip_mail' => true ) );
	}

	/** Reset only CF7's per-HTTP-request singleton between simulated requests. */
	private function reset_submission(): void {
		( new \ReflectionProperty( WPCF7_Submission::class, 'instance' ) )->setValue( null, null );
	}

	/** @return array<int, int> Local receipts created in this test transaction. */
	private function ids(): array {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'   => DocumentPostType::POST_TYPE,
					'post_status' => 'any',
					'numberposts' => -1,
					'fields'      => 'ids',
				)
			)
		);
	}

	/** Intercept every API step; no signature invitation leaves this test environment. */
	private function fake_send(): void {
		$this->fake_response(
			'/signers?',
			200,
			array(
				'status' => 200,
				'data'   => array(
					array(
						'id'        => self::SIGNER,
						'full_name' => 'Jane Doe',
						'email'     => 'jane@example.com',
					),
				),
			)
		);
		$this->fake_response(
			'/accounts/' . self::ACCOUNT_ID . '/documents',
			200,
			array(
				'status' => 200,
				'data'   => array(
					'id'     => self::REMOTE,
					'name'   => 'agreement.pdf',
					'status' => 'uploaded',
				),
			)
		);
		$this->fake_response(
			'/assignments',
			200,
			array(
				'status' => 200,
				'data'   => array(
					'id'      => '1a09c15990f0144256b98ff38aa',
					'signers' => array(
						array(
							'id'        => self::SIGNER,
							'full_name' => 'Jane Doe',
							'email'     => 'jane@example.com',
							'step'      => 1,
							'notified'  => true,
							'completed' => false,
						),
					),
				),
			)
		);
	}
}
