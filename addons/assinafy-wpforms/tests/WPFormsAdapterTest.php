<?php
/**
 * Real WPForms Lite lifecycle and core recovery regressions.
 *
 * @package Assinafy\WP\Addons\WPForms
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Integration;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Addons\WPForms\Adapter;
use Assinafy\WP\Addons\WPForms\FormSettings;
use Assinafy\WP\Addons\WPForms\RequestStore;
use Assinafy\WP\ClientFactory;
use Assinafy\WP\Credentials;
use Assinafy\WP\Documents\DocumentRecord;
use Assinafy\WP\Documents\SendService;
use Assinafy\WP\Log;
use Assinafy\WP\Privacy;
use WP_Error;

/** @covers \Assinafy\WP\Addons\WPForms\Adapter @covers \Assinafy\WP\Addons\WPForms\RequestStore @covers \Assinafy\WP\Addons\WPForms\FormSettings */
final class WPFormsAdapterTest extends AssinafyTestCase {

	private DocumentRecord $records;
	private Adapter $adapter;
	private RequestStore $store;
	private int $attachment;
	private int $admin;

	public function set_up(): void {
		parent::set_up();
		if ( ! function_exists( 'wpforms' ) ) {
			$this->markTestSkipped( 'Set ASSINAFY_WPFORMS_FILE to the real WPForms Lite plugin.' );
		}
		$this->configure_plugin();
		$this->records = new DocumentRecord();
		$this->adapter = $this->new_adapter();
		$this->store   = new RequestStore( $this->records );
		$this->admin   = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->admin );
		$this->attachment = self::factory()->attachment->create_upload_object( dirname( __DIR__, 3 ) . '/tests/fixtures/sample.pdf' );
		add_filter( 'pre_wp_mail', '__return_false' );
	}

	public function tear_down(): void {
		remove_filter( 'pre_wp_mail', '__return_false' );
		parent::tear_down();
	}

	public function test_real_lite_processing_records_accepted_submission_without_inventing_entry(): void {
		$form = $this->form();
		$this->fake_send();
		wpforms()->process->process( $this->entry( $form ) );
		$this->assertEmpty( wpforms()->process->errors, (string) wp_json_encode( wpforms()->process->errors ) );
		$this->assertSame( 0, wpforms()->process->entry_id );
		$ids = $this->store->for_form( $form['id'] );
		$this->assertCount( 1, $ids );
		$this->assertSame(
			[
				'integration' => 'wpforms',
				'record_id'   => 'form-' . $form['id'] . ':submission-' . $ids[0],
			],
			$this->records->source( $ids[0] )
		);
		$this->assertSame( '1a09c15990f0144256b98ff38aa', $this->records->assignment_id( $ids[0] ) );
		$this->assertSame( 0, $this->store->get( $ids[0] )['entry_id'] );
	}

	public function test_real_validation_and_disabled_workflow_do_not_send(): void {
		$form               = $this->form();
		$entry              = $this->entry( $form );
		$entry['fields'][1] = 'invalid email';
		wpforms()->process->process( $entry );
		$this->assertNotEmpty( wpforms()->process->errors );
		$this->assertSame( [], $this->store->for_form( $form['id'] ) );
		$form = $this->form( [ 'enabled' => false ] );
		wpforms()->process->process( $this->entry( $form ) );
		$this->assertEmpty( wpforms()->process->errors, (string) wp_json_encode( wpforms()->process->errors ) );
		$this->assertSame( [], $this->requests );
	}

	public function test_mapped_optional_fields_become_required_before_acceptance(): void {
		$form               = $this->form();
		$entry              = $this->entry( $form );
		$entry['fields'][0] = '';
		wpforms()->process->process( $entry );
		$this->assertNotEmpty( wpforms()->process->errors[ $form['id'] ]['header'] ?? null, (string) wp_json_encode( wpforms()->process->errors ) );
		$this->assertSame( [], $this->requests );
		$this->assertSame( [], $this->store->for_form( $form['id'] ) );
	}

	public function test_real_antispam_failure_never_sends(): void {
		$form                         = $this->form();
		$form['settings']['antispam'] = '1';
		wp_update_post(
			[
				'ID'           => $form['id'],
				'post_content' => wpforms_encode( $form ),
			]
		);
		wpforms()->process->process( $this->entry( $form ) );
		$this->assertNotEmpty( wpforms()->process->errors );
		$this->assertSame( [], $this->requests );
		$this->assertSame( [], $this->store->for_form( $form['id'] ) );
	}

	public function test_failed_notification_email_does_not_cancel_accepted_signature_request(): void {
		$form                                    = $this->form();
		$form['settings']['notification_enable'] = '1';
		$form['settings']['notifications']       = [
			'1' => [
				'notification_name' => 'Admin',
				'email'             => 'admin@example.com',
				'subject'           => 'Accepted form',
				'sender_name'       => 'Test',
				'sender_address'    => 'sender@example.com',
				'message'           => '{all_fields}',
				'email_display'     => 'html',
			],
		];
		wp_update_post(
			[
				'ID'           => $form['id'],
				'post_content' => wpforms_encode( $form ),
			]
		);
		$calls = 0;
		$mail  = static function () use ( &$calls ): bool {
			++$calls;
			return false;
		};
		add_filter( 'pre_wp_mail', $mail, 20 );
		$this->fake_send();
		try {
			wpforms()->process->process( $this->entry( $form ) );
		} finally {
			remove_filter( 'pre_wp_mail', $mail, 20 );
		}
		$this->assertGreaterThan( 0, $calls );
		$this->assertEmpty( wpforms()->process->errors, (string) wp_json_encode( wpforms()->process->errors ) );
		$this->assertCount( 1, $this->store->for_form( $form['id'] ) );
		$this->assertSame( 1, $this->uploads() );
	}

	public function test_repeated_lite_callback_deduplicates_within_one_request(): void {
		$form = $this->form();
		$this->fake_send();
		$this->adapter->complete( $this->fields(), [], $form, 0 );
		$requests = count( $this->requests );
		$this->adapter->complete( $this->fields(), [], $form, 0 );
		$this->assertCount( 1, $this->store->for_form( $form['id'] ) );
		$this->assertCount( $requests, $this->requests );
	}

	public function test_separate_lite_processing_requests_have_distinct_receipts(): void {
		$form = $this->form( [ 'attachment_id' => 0 ] );
		$this->adapter->complete( $this->fields(), [], $form, 0 );
		$this->new_adapter()->complete( $this->fields(), [], $form, 0 );
		$ids = $this->store->for_form( $form['id'] );
		$this->assertCount( 2, $ids );
		$this->assertNotSame( $this->records->source( $ids[0] ), $this->records->source( $ids[1] ) );
		$this->assertSame( [], $this->requests );
	}

	public function test_positive_entry_callback_contract_deduplicates_across_requests(): void {
		$form = $this->form();
		$this->fake_send();
		$this->adapter->complete( $this->fields(), [], $form, 87 );
		$requests = count( $this->requests );
		$this->new_adapter()->complete( $this->fields(), [], $form, 87 );
		$ids = $this->store->for_form( $form['id'] );
		$this->assertCount( 1, $ids );
		$this->assertSame( 'form-' . $form['id'] . ':entry-87', $this->records->source( $ids[0] )['record_id'] );
		$this->assertCount( $requests, $this->requests );
	}

	public function test_assignment_failure_keeps_source_and_contact_then_retries_without_upload(): void {
		$form = $this->form();
		$this->fake_send();
		$this->fake_response(
			'/assignments',
			422,
			[
				'status'  => 422,
				'message' => 'Temporary assignment refusal',
				'data'    => null,
			]
		);
		$source_before_upload = [];
		$observe              = function ( $preempt, $args, $url ) use ( &$source_before_upload, $form ) {
			if ( str_contains( $url, '/accounts/' . self::ACCOUNT_ID . '/documents' ) ) {
				$id                   = $this->store->for_form( $form['id'] )[0];
				$source_before_upload = $this->records->source( $id );
			}
			return $preempt;
		};
		add_filter( 'pre_http_request', $observe, 20, 3 );
		try {
			$this->adapter->complete( $this->fields(), [], $form, 0 );
		} finally {
			remove_filter( 'pre_http_request', $observe, 20 );
		}
		$id = $this->store->for_form( $form['id'] )[0];
		$this->assertSame( $source_before_upload, $this->records->source( $id ) );
		$this->assertSame( 'jane@example.com', $this->records->signers( $id )[0]['email'] );
		$this->assertSame( '', $this->records->assignment_id( $id ) );
		$this->assertNotSame( '', $this->records->last_error( $id ) );
		$this->fake_send();
		$this->fake_response(
			'/documents/104618d0d63884bc446c534e5ff5',
			200,
			[
				'status'  => 200,
				'message' => '',
				'data'    => [
					'id'     => '104618d0d63884bc446c534e5ff5',
					'name'   => 'sample.pdf',
					'status' => 'uploaded',
				],
			]
		);
		$this->assertSame( $id, $this->new_adapter()->dispatch( $id ) );
		$this->assertSame( 1, $this->uploads() );
		$this->assertSame( '', $this->records->last_error( $id ) );
		$this->assertArrayNotHasKey( 'signers', $this->store->get( $id ) );
	}

	public function test_erased_pending_contact_blocks_retry_and_removes_retry_link(): void {
		$form = $this->form( [ 'attachment_id' => 0 ] );
		$this->adapter->complete( $this->fields(), [], $form, 0 );
		$id = $this->store->for_form( $form['id'] )[0];
		$this->assertTrue( ( new Privacy( $this->records, new Log() ) )->erase( 'jane@example.com' )['items_removed'] );
		$result = $this->adapter->dispatch( $id );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'assinafy_wpforms_erased', $result->get_error_code() );
		$this->assertSame( [], $this->requests );
		ob_start();
		$this->adapter->render_requests( $form['id'] );
		$html = ob_get_clean();
		$this->assertStringNotContainsString( 'assinafy_wpforms_retry', $html );
		$this->assertStringNotContainsString( 'jane@example.com', $html );
	}

	public function test_native_form_save_normalizes_mappings_and_preserves_unauthorized_settings(): void {
		$form                                       = $this->form();
		$form['settings']['assinafy']['name_field'] = '00';
		$form['settings']['assinafy']['message']    = '<b>Please sign.</b>';
		wpforms()->obj( 'form' )->update( $form['id'], $form );
		$saved = wpforms_decode( get_post_field( 'post_content', $form['id'] ) );
		$this->assertSame( '0', $saved['settings']['assinafy']['name_field'] );
		$this->assertSame( 'Please sign.', $saved['settings']['assinafy']['message'] );
		$this->assertTrue( $saved['settings']['assinafy']['enabled'] );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$form['settings']['assinafy']['enabled'] = false;
		$args                                    = ( new FormSettings() )->save(
			[
				'ID'           => $form['id'],
				'post_content' => wpforms_encode( $form ),
			],
			$form
		);
		$preserved                               = json_decode( wp_unslash( $args['post_content'] ), true );
		$this->assertSame( $saved['settings']['assinafy'], $preserved['settings']['assinafy'] );
	}

	public function test_concurrent_persisted_entry_receipt_creation_is_atomic(): void {
		$form    = $this->form();
		$nested  = null;
		$observe = function ( $data ) use ( $form, &$nested ) {
			if ( $data['post_type'] === 'assinafy_document' && $nested === null ) {
				$nested = $this->store->create(
					$form,
					88,
					FormSettings::read( $form ),
					[
						'full_name' => 'Jane Doe',
						'email'     => 'jane@example.com',
					]
				);
			}
			return $data;
		};
		add_filter( 'wp_insert_post_data', $observe );
		try {
			$id = $this->store->create(
				$form,
				88,
				FormSettings::read( $form ),
				[
					'full_name' => 'Jane Doe',
					'email'     => 'jane@example.com',
				]
			);
		} finally {
			remove_filter( 'wp_insert_post_data', $observe );
		}
		$this->assertIsInt( $id );
		$this->assertInstanceOf( WP_Error::class, $nested );
		$this->assertSame( 'assinafy_wpforms_in_progress', $nested->get_error_code() );
		$this->assertSame( [ $id ], $this->store->for_form( $form['id'] ) );
		$this->assertSame(
			$id,
			$this->store->create(
				$form,
				88,
				FormSettings::read( $form ),
				[
					'full_name' => 'Jane Doe',
					'email'     => 'jane@example.com',
				]
			)
		);
		$this->assertSame( [], $this->requests );
	}

	public function test_retry_handler_denies_user_without_sending_capability(): void {
		$form = $this->form( [ 'attachment_id' => 0 ] );
		$this->adapter->complete( $this->fields(), [], $form, 0 );
		$id = $this->store->for_form( $form['id'] )[0];
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$_GET['post_id'] = $id;
		$handler         = static function (): \Closure {
			return static function (): void {
				throw new \RuntimeException( 'retry-denied' );
			};
		};
		add_filter( 'wp_die_handler', $handler );
		try {
			$this->adapter->retry();
			$this->fail( 'Unauthorized retry must stop.' );
		} catch ( \RuntimeException $error ) {
			$this->assertSame( 'retry-denied', $error->getMessage() );
		} finally {
			remove_filter( 'wp_die_handler', $handler );
			unset( $_GET['post_id'] );
		}
		$this->assertSame( [], $this->requests );
	}

	public function test_retry_handler_rejects_invalid_nonce_before_http(): void {
		$form                 = $this->form();
		$id                   = $this->store->create(
			$form,
			0,
			FormSettings::read( $form ),
			[
				'full_name' => 'Jane Doe',
				'email'     => 'jane@example.com',
			]
		);
		$_GET['post_id']      = $id;
		$_REQUEST['_wpnonce'] = 'invalid';
		$handler              = static function (): \Closure {
			return static function (): void {
				throw new \RuntimeException( 'nonce-denied' );
			};
		};
		add_filter( 'wp_die_handler', $handler );
		try {
			$this->adapter->retry();
			$this->fail( 'Invalid nonce must stop.' );
		} catch ( \RuntimeException $error ) {
			$this->assertSame( 'nonce-denied', $error->getMessage() );
		} finally {
			remove_filter( 'wp_die_handler', $handler );
			unset( $_GET['post_id'], $_REQUEST['_wpnonce'] );
		}
		$this->assertSame( [], $this->requests );
	}

	public function test_authorized_retry_sends_original_receipt_and_returns_to_form(): void {
		$form                 = $this->form();
		$id                   = $this->store->create(
			$form,
			0,
			FormSettings::read( $form ),
			[
				'full_name' => 'Jane Doe',
				'email'     => 'jane@example.com',
			]
		);
		$_GET['post_id']      = $id;
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'assinafy_wpforms_retry_' . $id );
		$this->fake_send();
		$redirect = static function ( string $location ): never {
			throw new \RuntimeException( $location );
		};
		add_filter( 'wp_redirect', $redirect );
		try {
			$this->adapter->retry();
			$this->fail( 'Expected the form redirect.' );
		} catch ( \RuntimeException $error ) {
			$this->assertStringContainsString( 'page=wpforms-builder', $error->getMessage() );
			$this->assertStringContainsString( 'form_id=' . $form['id'], $error->getMessage() );
		} finally {
			remove_filter( 'wp_redirect', $redirect );
			unset( $_GET['post_id'], $_REQUEST['_wpnonce'] );
		}
		$this->assertSame( '1a09c15990f0144256b98ff38aa', $this->records->assignment_id( $id ) );
		$this->assertSame( [ $id ], $this->store->for_form( $form['id'] ) );
		$this->assertSame( 1, $this->uploads() );
	}

	private function new_adapter(): Adapter {
		return new Adapter( new SendService( new ClientFactory( new Credentials(), new Log() ), $this->records, new Log() ), $this->records );
	}

	/** @param array<string, mixed> $overrides @return array<string, mixed> */
	private function form( array $overrides = [] ): array {
		$id   = self::factory()->post->create(
			[
				'post_type'   => 'wpforms',
				'post_status' => 'publish',
				'post_title'  => 'Signature form',
			]
		);
		$form = [
			'id'       => $id,
			'fields'   => [
				[
					'id'    => '0',
					'type'  => 'text',
					'label' => 'Name',
				],
				[
					'id'    => '1',
					'type'  => 'email',
					'label' => 'Email',
				],
			],
			'settings' => [
				'form_title'    => 'Signature form',
				'ajax_submit'   => 0,
				'antispam'      => 0,
				'antispam_v3'   => 0,
				'notifications' => [],
				'assinafy'      => array_replace(
					[
						'enabled'       => true,
						'attachment_id' => $this->attachment,
						'name_field'    => '0',
						'email_field'   => '1',
						'message'       => 'Please sign.',
					],
					$overrides
				),
			],
		];
		wp_update_post(
			[
				'ID'           => $id,
				'post_content' => wpforms_encode( $form ),
			]
		);
		return $form;
	}

	/** @param array<string, mixed> $form @return array<string, mixed> */
	private function entry( array $form ): array {
		return [
			'id'     => $form['id'],
			'nonce'  => wp_create_nonce( 'wpforms::form_' . $form['id'] ),
			'fields' => [ 'Jane Doe', 'jane@example.com' ],
		];
	}

	/** @return array<int, array<string, string>> */
	private function fields(): array {
		return [ [ 'value' => 'Jane Doe' ], [ 'value' => 'jane@example.com' ] ];
	}

	private function uploads(): int {
		return count( array_filter( $this->requests, static fn( array $request ): bool => str_contains( $request['url'], '/accounts/' . self::ACCOUNT_ID . '/documents' ) && $request['args']['method'] === 'POST' ) );
	}

	private function fake_send(): void {
		$this->fake_response(
			'/signers?',
			200,
			[
				'status'  => 200,
				'message' => '',
				'data'    => [
					[
						'resource'  => 'signer',
						'id'        => '19e6b92e7895332ed9708535d8c',
						'full_name' => 'Jane Doe',
						'email'     => 'jane@example.com',
					],
				],
			]
		);
		$this->fake_response(
			'/accounts/' . self::ACCOUNT_ID . '/documents',
			200,
			[
				'status'  => 200,
				'message' => '',
				'data'    => [
					'resource'  => 'document',
					'id'        => '104618d0d63884bc446c534e5ff5',
					'name'      => 'sample.pdf',
					'status'    => 'uploaded',
					'is_closed' => false,
				],
			]
		);
		$this->fake_response(
			'/assignments',
			200,
			[
				'status'  => 200,
				'message' => '',
				'data'    => [
					'resource' => 'assignment',
					'id'       => '1a09c15990f0144256b98ff38aa',
					'method'   => 'virtual',
					'signers'  => [
						[
							'id'        => '19e6b92e7895332ed9708535d8c',
							'full_name' => 'Jane Doe',
							'email'     => 'jane@example.com',
							'step'      => 1,
							'notified'  => true,
							'completed' => false,
						],
					],
				],
			]
		);
	}
}
