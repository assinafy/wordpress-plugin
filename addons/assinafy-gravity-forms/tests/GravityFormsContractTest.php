<?php
/**
 * Adapter contract checks with real WordPress/core and documented host API doubles.
 *
 * @package Assinafy\WP\Tests
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Integration;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Addons\GravityForms\Addon;
use Assinafy\WP\ClientFactory;
use Assinafy\WP\Documents\DocumentRecord;
use Assinafy\WP\Documents\SendService;
use Assinafy\WP\Log;

/**
 * @covers \Assinafy\WP\Addons\GravityForms\Addon
 * @covers \Assinafy\WP\Addons\GravityForms\Requests
 */
final class GravityFormsContractTest extends AssinafyTestCase {
	private Addon $addon;
	/** @var array<string,mixed> */
	private array $feed;
	/** @var array<array-key,mixed> */
	private array $entry;

	public function set_up(): void {
		parent::set_up();
		if ( '1' !== getenv( 'ASSINAFY_GRAVITY_FORMS_CONTRACT' ) ) {
			$this->markTestSkipped( 'Set ASSINAFY_GRAVITY_FORMS_CONTRACT=1; this is not licensed-host validation.' );
		}
		$this->configure_plugin();
		$this->addon = new Addon();
		$this->addon->connect( new SendService( new ClientFactory( new \Assinafy\WP\Credentials(), new Log() ), new DocumentRecord(), new Log() ) );
		$file        = wp_upload_bits( 'gravity-contract.pdf', null, file_get_contents( dirname( __DIR__, 3 ) . '/tests/fixtures/sample.pdf' ) );
		$id          = self::factory()->attachment->create_object( $file['file'], 0, array( 'post_mime_type' => 'application/pdf' ) );
		$this->feed  = array(
			'id'        => 21,
			'form_id'   => 3,
			'is_active' => 1,
			'meta'      => array(
				'feedName'      => 'Contract',
				'attachment_id' => $id,
				'signer_name'   => '1',
				'signer_email'  => '2',
			),
		);
		$this->entry = array(
			'id'      => 101,
			'form_id' => 3,
			'status'  => 'active',
			'1'       => 'Jane Doe',
			'2'       => 'jane@example.com',
		);
	}

	/** Native config enables host background feeds and required mapping/conditions. */
	public function test_native_feed_contract(): void {
		$this->assertTrue( $this->addon->_async_feed_processing );
		$fields = $this->addon->feed_settings_fields()[0]['fields'];
		$this->assertContains( 'field_map', array_column( $fields, 'type' ) );
		$this->assertContains( 'feed_condition', array_column( $fields, 'type' ) );
		$this->assertContains( Addon::class, \GFAddOn::$registered );
	}

	/** Spam and forged entry/form pairing cannot consume an upload. */
	public function test_ineligible_entry_is_rejected_before_http(): void {
		$this->entry['status'] = 'spam';
		$this->assertWPError( $this->addon->process_feed( $this->feed, $this->entry, array( 'id' => 3 ) ) );
		$this->entry['status']  = 'active';
		$this->entry['form_id'] = 7;
		$this->assertWPError( $this->addon->process_feed( $this->feed, $this->entry, array( 'id' => 3 ) ) );
		$this->assertSame( array(), $this->requests );
	}

	/** An uploaded agreement freezes mapping for later assignment retries. */
	public function test_changed_entry_cannot_retarget_a_retry(): void {
		$this->fake_assignment_failure();
		$this->assertWPError( $this->addon->process_feed( $this->feed, $this->entry, array( 'id' => 3 ) ) );
		$this->assertIsArray( gform_get_meta( 101, 'assinafy_feed_21' ) );
		$this->entry['2'] = 'other@example.com';
		$count            = count( $this->requests );
		$result           = $this->addon->process_feed( $this->feed, $this->entry, array( 'id' => 3 ) );
		$this->assertWPError( $result );
		$this->assertSame( 'assinafy_feed_changed', $result->get_error_code() );
		$this->assertCount( $count, $this->requests );
	}

	/** Successful repeated feed processing uses one core document and no second upload. */
	public function test_successful_feed_is_durable_and_idempotent(): void {
		$this->fake_successful_send();
		$this->assertTrue( $this->addon->process_feed( $this->feed, $this->entry, array( 'id' => 3 ) ) );
		$receipt = gform_get_meta( 101, 'assinafy_feed_21' );
		$this->assertGreaterThan( 0, $receipt['post_id'] );
		$this->assertSame(
			array(
				'integration' => 'gravity-forms',
				'record_id'   => '101',
			),
			( new DocumentRecord() )->source( $receipt['post_id'] )
		);
		$count = count( $this->requests );
		$this->assertTrue( $this->addon->process_feed( $this->feed, $this->entry, array( 'id' => 3 ) ) );
		$this->assertCount( $count, $this->requests );
	}

	/** Assignment failure recovers the saved upload via the exact original feed identity. */
	public function test_assignment_failure_resumes_without_uploading_again(): void {
		$this->fake_successful_send();
		$this->fake_response(
			'/assignments',
			500,
			array(
				'status'  => 500,
				'message' => 'Temporary failure',
				'data'    => null,
			)
		);
		$this->assertWPError( $this->addon->process_feed( $this->feed, $this->entry, array( 'id' => 3 ) ) );
		$receipt = gform_get_meta( 101, 'assinafy_feed_21' );
		$this->assertSame( 'abc123', ( new DocumentRecord() )->document_id( $receipt['post_id'] ) );
		$this->fake_successful_send();
		$this->fake_response(
			'/documents/abc123',
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array(
					'id'         => 'abc123',
					'account_id' => self::ACCOUNT_ID,
					'status'     => 'uploaded',
				),
			)
		);
		$this->assertTrue( $this->addon->process_feed( $this->feed, $this->entry, array( 'id' => 3 ) ) );
		$uploads = array_filter( $this->requests, static fn( array $request ): bool => str_contains( $request['url'], '/accounts/' . self::ACCOUNT_ID . '/documents' ) );
		$this->assertCount( 1, $uploads );
	}

	/** Queue API responses; no signature invitations leave the local test harness. */
	private function fake_successful_send(): void {
		$this->fake_response(
			'/accounts/' . self::ACCOUNT_ID . '/documents',
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array(
					'id'     => 'abc123',
					'name'   => 'contract.pdf',
					'status' => 'uploaded',
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
						'id'        => 'def456',
						'email'     => 'jane@example.com',
						'full_name' => 'Jane Doe',
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
					'id'      => 'fed987',
					'signers' => array(
						array(
							'id'        => 'def456',
							'email'     => 'jane@example.com',
							'full_name' => 'Jane Doe',
						),
					),
				),
			)
		);
	}

	/** Invalid feed pairing and disabled feeds cannot use an otherwise eligible entry. */
	public function test_disabled_or_foreign_feed_is_rejected_before_http(): void {
		$this->feed['form_id'] = 7;
		$this->assertWPError( $this->addon->process_feed( $this->feed, $this->entry, array( 'id' => 3 ) ) );
		$this->feed['form_id']   = 3;
		$this->feed['is_active'] = 0;
		$this->assertWPError( $this->addon->process_feed( $this->feed, $this->entry, array( 'id' => 3 ) ) );
		$this->assertSame( array(), $this->requests );
	}

	/** A changed feed cannot retarget its original request either. */
	public function test_changed_feed_cannot_retarget_a_retry(): void {
		$this->fake_assignment_failure();
		$this->assertWPError( $this->addon->process_feed( $this->feed, $this->entry, array( 'id' => 3 ) ) );
		$this->feed['meta']['message'] = 'A different agreement instruction';
		$count                         = count( $this->requests );
		$result                        = $this->addon->process_feed( $this->feed, $this->entry, array( 'id' => 3 ) );
		$this->assertWPError( $result );
		$this->assertSame( 'assinafy_feed_changed', $result->get_error_code() );
		$this->assertCount( $count, $this->requests );
	}

	/** A replacement PDF under the same attachment ID is a different agreement. */
	public function test_replaced_pdf_cannot_retarget_a_partial_request(): void {
		$this->fake_assignment_failure();
		$this->assertWPError( $this->addon->process_feed( $this->feed, $this->entry, array( 'id' => 3 ) ) );
		$file     = get_attached_file( $this->feed['meta']['attachment_id'] );
		$original = file_get_contents( $file );
		try {
			file_put_contents( $file, $original . "\n% A revised agreement\n" );
			$count  = count( $this->requests );
			$result = $this->addon->process_feed( $this->feed, $this->entry, array( 'id' => 3 ) );
			$this->assertWPError( $result );
			$this->assertSame( 'assinafy_feed_changed', $result->get_error_code() );
			$this->assertCount( $count, $this->requests );
		} finally {
			file_put_contents( $file, $original );
		}
	}

	/** A locally invalid email can be corrected before any durable send reservation. */
	public function test_invalid_inputs_can_be_corrected_before_a_send_is_reserved(): void {
		$this->entry['2'] = 'invalid-email';
		$this->assertWPError( $this->addon->process_feed( $this->feed, $this->entry, array( 'id' => 3 ) ) );
		$this->assertSame( array(), $this->requests );
		$this->assertArrayNotHasKey( 'fingerprint', gform_get_meta( 101, 'assinafy_feed_21' ) );
		$this->entry['2'] = 'jane@example.com';
		$this->fake_successful_send();
		$this->assertTrue( $this->addon->process_feed( $this->feed, $this->entry, array( 'id' => 3 ) ) );
	}

	/** A fake assignment failure leaves a known uploaded document for a later retry. */
	private function fake_assignment_failure(): void {
		$this->fake_successful_send();
		$this->fake_response(
			'/assignments',
			500,
			array(
				'status'  => 500,
				'message' => 'Temporary failure',
				'data'    => null,
			)
		);
	}

	/** A receipt from another entry, or a deleted document, is never a completed send. */
	public function test_foreign_and_deleted_receipts_are_rejected(): void {
		$this->fake_successful_send();
		$this->assertTrue( $this->addon->process_feed( $this->feed, $this->entry, array( 'id' => 3 ) ) );
		$receipt = gform_get_meta( 101, 'assinafy_feed_21' );
		gform_update_meta( 102, 'assinafy_feed_21', $receipt );
		$this->entry['id'] = 102;
		$count             = count( $this->requests );
		$this->assertWPError( $this->addon->process_feed( $this->feed, $this->entry, array( 'id' => 3 ) ) );
		$this->entry['id'] = 101;
		wp_delete_post( $receipt['post_id'], true );
		$this->assertWPError( $this->addon->process_feed( $this->feed, $this->entry, array( 'id' => 3 ) ) );
		$this->assertCount( $count, $this->requests );
	}

	/** Matching metadata on another WordPress post type cannot impersonate a core receipt. */
	public function test_an_unrelated_post_cannot_impersonate_a_document_receipt(): void {
		$this->fake_successful_send();
		$this->assertTrue( $this->addon->process_feed( $this->feed, $this->entry, array( 'id' => 3 ) ) );
		$receipt = gform_get_meta( 101, 'assinafy_feed_21' );
		wp_update_post(
			array(
				'ID'        => $receipt['post_id'],
				'post_type' => 'post',
			)
		);
		$count = count( $this->requests );
		$this->assertWPError( $this->addon->process_feed( $this->feed, $this->entry, array( 'id' => 3 ) ) );
		$this->assertCount( $count, $this->requests );
	}

	/** Both host viewing permission and core viewing permission protect the entry box. */
	public function test_entry_box_requires_host_and_core_viewing_permissions(): void {
		$user = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user );
		$this->assertSame( array(), $this->addon->entry_boxes( array(), $this->entry ) );
		wp_get_current_user()->add_cap( 'gravityforms_view_entries' );
		$this->assertSame( array(), $this->addon->entry_boxes( array(), $this->entry ) );
		wp_get_current_user()->add_cap( 'assinafy_view' );
		$this->assertArrayHasKey( 'assinafy', $this->addon->entry_boxes( array(), $this->entry ) );
	}

	/** PDF validation accepts a real accessible attachment and refuses coerced identifiers. */
	public function test_settings_require_an_exact_positive_pdf_attachment_id(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		wp_get_current_user()->add_cap( 'gravityforms_edit_forms' );
		$id = $this->feed['meta']['attachment_id'];
		$this->addon->validate_pdf( array(), (string) $id );
		$this->assertSame( array(), $this->addon->errors );
		foreach ( array( '-' . $id, $id . 'invalid', array( $id ) ) as $invalid ) {
			$this->addon->errors = array();
			$this->addon->validate_pdf( array(), $invalid );
			$this->assertNotEmpty( $this->addon->errors );
		}
	}

	/** PDF validation also requires the send capability, beyond native host auth. */
	public function test_settings_refuse_unprivileged_pdf_selection(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->addon->validate_pdf( array(), $this->feed['meta']['attachment_id'] );
		$this->assertNotEmpty( $this->addon->errors );
	}
}
