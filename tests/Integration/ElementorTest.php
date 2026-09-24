<?php
/**
 * Elementor adapter contracts against real WordPress and explicit host doubles.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Integration;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\ClientFactory;
use Assinafy\WP\Credentials;
use Assinafy\WP\Documents\DocumentRecord;
use Assinafy\WP\Documents\SendService;
use Assinafy\WP\Integrations\Elementor;
use Assinafy\WP\Integrations\ElementorAction;
use Assinafy\WP\Integrations\ElementorPermissions;
use Assinafy\WP\Log;
use Elementor\Controls_Manager;
use Elementor\Core\Base\Document;
use Elementor\Widget_Base;
use ElementorPro\Modules\Forms\Classes\Action_Base;
use ElementorPro\Modules\Forms\Classes\Ajax_Handler;
use ElementorPro\Modules\Forms\Classes\Form_Record;
use ElementorPro\Modules\Forms\Registrars\Form_Actions_Registrar;
use WP_Error;

if ( ! class_exists( Controls_Manager::class ) && ! class_exists( Action_Base::class ) ) {
	require_once dirname( __DIR__ ) . '/stubs/elementor.php';
}

/**
 * This suite does not establish Elementor Pro version compatibility. The doubles expose
 * only the documented registrar, control, record, error and document-save contracts.
 * Every send is replaced by a recording closure or an unconfigured core service.
 *
 * @group elementor-contract
 * @covers \Assinafy\WP\Integrations\Elementor
 * @covers \Assinafy\WP\Integrations\ElementorAction
 * @covers \Assinafy\WP\Integrations\ElementorPermissions
 */
final class ElementorTest extends AssinafyTestCase {

	private int $attachment;

	public function set_up(): void {
		parent::set_up();
		if ( ! defined( 'ASSINAFY_ELEMENTOR_CONTRACT_STUBS' ) ) {
			$this->markTestSkipped( 'Contract doubles are not loaded when Elementor is installed.' );
		}
		$this->attachment = self::factory()->attachment->create_upload_object( dirname( __DIR__ ) . '/fixtures/sample.pdf' );
	}

	public function test_mapping_uses_the_configured_pdf_and_only_the_selected_fields(): void {
		$calls  = array();
		$action = new ElementorAction(
			static function ( array $args ) use ( &$calls ): int {
				$calls[] = $args;
				return 81;
			}
		);
		$ajax   = new Ajax_Handler();
		wp_set_current_user( 0 );
		$action->run( $this->record(), $ajax );

		$this->assertCount( 1, $calls );
		$this->assertSame( $this->attachment, $calls[0]['attachment_id'] );
		$this->assertSame(
			array(
				array(
					'name'  => 'Jane Doe',
					'email' => 'jane@example.com',
				),
			),
			$calls[0]['signers']
		);
		$this->assertSame( 'Please sign this agreement.', $calls[0]['message'] );
		$this->assertSame( 'elementor', $calls[0]['source']['integration'] );
		$this->assertMatchesRegularExpression( '/\Aagreement-v1:[a-f0-9]{32}\z/', $calls[0]['source']['record_id'] );
		$this->assertSame( 'elementor:' . $calls[0]['source']['record_id'], $calls[0]['idempotency_key'] );
		$this->assertArrayNotHasKey( 'file_path', $calls[0] );
		$this->assertSame( array(), $ajax->errors );
		$this->assertSame( array(), $this->requests );
	}

	public function test_a_repeated_callback_reuses_its_result_but_a_distinct_identical_submission_is_new(): void {
		$calls  = array();
		$action = new ElementorAction(
			static function ( array $args ) use ( &$calls ): int {
				$calls[] = $args;
				return 81;
			}
		);
		$first  = $this->record();
		$action->run( $first, new Ajax_Handler() );
		$action->run( $first, new Ajax_Handler() );
		$this->assertCount( 1, $calls );

		$action->run( $this->record(), new Ajax_Handler() );
		$this->assertCount( 2, $calls );
		$this->assertNotSame( $calls[0]['source'], $calls[1]['source'] );
		$this->assertNotSame( $calls[0]['idempotency_key'], $calls[1]['idempotency_key'] );
	}

	/** @return array<string, array{0: array<string, mixed>}> */
	public static function invalid_settings(): array {
		return array(
			'missing PDF'       => array( array( 'assinafy_pdf' => array( 'id' => 0 ) ) ),
			'external URL'      => array( array( 'assinafy_pdf' => array( 'url' => 'https://example.com/private.pdf' ) ) ),
			'structured PDF ID' => array( array( 'assinafy_pdf' => array( 'id' => array( 12 ) ) ) ),
			'unknown email'     => array( array( 'assinafy_email_field' => 'missing' ) ),
			'missing workflow'  => array( array( 'assinafy_workflow' => '' ) ),
			'invalid workflow'  => array( array( 'assinafy_workflow' => 'workflow with spaces' ) ),
		);
	}

	/**
	 * @dataProvider invalid_settings
	 * @param array<string, mixed> $settings Configuration override.
	 */
	public function test_invalid_configuration_never_calls_the_sender( array $settings ): void {
		$calls  = 0;
		$action = new ElementorAction(
			static function () use ( &$calls ): int {
				++$calls;
				return 81;
			}
		);
		$ajax   = new Ajax_Handler();
		$action->run( $this->record( $settings ), $ajax );
		$this->assertSame( 0, $calls );
		$this->assertNotEmpty( $ajax->errors );
		$this->assertSame( array(), $this->requests );
	}

	public function test_an_error_retains_the_core_recovery_id_without_exposing_diagnostics_or_repeating_the_send(): void {
		$calls  = 0;
		$action = new ElementorAction(
			static function () use ( &$calls ): WP_Error {
				++$calls;
				return new WP_Error( 'assinafy_send_failed', 'Private API diagnostic', array( 'post_id' => 81 ) );
			}
		);
		$record = $this->record();
		$ajax   = new Ajax_Handler();
		$action->run( $record, $ajax );
		$action->run( $record, $ajax );
		$this->assertSame( 1, $calls );
		$this->assertNotEmpty( $ajax->errors );
		$this->assertStringNotContainsString( 'Private API diagnostic', implode( ' ', $ajax->errors ) );
		$entries = ( new Log() )->entries();
		$this->assertSame( 'elementor_send_failed', $entries[0]['event'] );
		$this->assertSame( 81, $entries[0]['context']['post_id'] );
	}

	public function test_an_unexpected_sender_failure_uses_the_native_field_error(): void {
		$action = new ElementorAction(
			static function (): never {
				throw new \RuntimeException( 'Private diagnostic' );
			}
		);
		$ajax   = new Ajax_Handler();
		$action->run( $this->record(), $ajax );
		$this->assertArrayHasKey( 'person', $ajax->errors );
		$this->assertStringNotContainsString( 'Private diagnostic', $ajax->errors['person'] );
	}

	public function test_registration_before_core_boot_uses_the_service_injected_later(): void {
		$bridge    = new Elementor();
		$registrar = new Form_Actions_Registrar();
		$bridge->actions_ready( $registrar );
		$this->assertInstanceOf( Action_Base::class, $registrar->actions['assinafy'] );
		$registrar->actions['assinafy']->run( $this->record(), new Ajax_Handler() );

		$bridge->core_ready( new SendService( new ClientFactory( new Credentials(), new Log() ), new DocumentRecord(), new Log() ) );
		$registrar->actions['assinafy']->run( $this->record(), new Ajax_Handler() );
		$entries = ( new Log() )->entries();
		$this->assertSame( 'assinafy_not_ready', $entries[0]['context']['error_code'] );
		$this->assertSame( 'assinafy_not_configured', $entries[1]['context']['error_code'] );
		$this->assertSame( array(), $this->requests );
	}

	public function test_export_removes_local_configuration_and_controls_require_send_permission(): void {
		$action = new ElementorAction( static fn(): int => 81 );
		$widget = new Widget_Base();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$action->register_settings_section( $widget );
		$this->assertSame( array(), $widget->controls );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$action->register_settings_section( $widget );
		$this->assertSame( array( 'application/pdf' ), $widget->controls['assinafy_pdf']['media_types'] );
		$this->assertArrayHasKey( 'assinafy_name_field', $widget->controls );
		$this->assertArrayHasKey( 'assinafy_email_field', $widget->controls );
		$this->assertSame( array( 'unrelated' => 'keep' ), $action->on_export( $this->settings() + array( 'unrelated' => 'keep' ) ) );
	}

	public function test_an_editor_without_send_permission_cannot_enable_a_signature_form(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$this->expectException( \RuntimeException::class );
		( new ElementorPermissions() )->before_save( array( 'elements' => $this->elements() ), $this->document( array() ) );
	}

	public function test_unauthorized_changes_to_fields_and_dynamic_settings_are_blocked(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$old = $this->elements();
		$new = $old;
		$new[0]['elements'][0]['settings']['__dynamic__'] = array( 'assinafy_pdf' => 'visitor-supplied-value' );
		$this->expectException( \RuntimeException::class );
		( new ElementorPermissions() )->before_save( array( 'elements' => $new ), $this->document( $old ) );
	}

	public function test_duplicate_widget_ids_cannot_hide_a_new_unauthorized_form(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$old                  = $this->elements();
		$new                  = $old;
		$new[0]['elements'][] = $new[0]['elements'][0];
		$this->expectException( \RuntimeException::class );
		( new ElementorPermissions() )->before_save( array( 'elements' => $new ), $this->document( $old ) );
	}

	public function test_unrelated_page_edits_preserve_an_existing_authorized_form(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$old   = $this->elements();
		$new   = $old;
		$new[] = array(
			'id'         => 'heading',
			'widgetType' => 'heading',
			'settings'   => array( 'title' => 'Updated' ),
		);
		$data  = array( 'elements' => $new );
		$this->assertSame( $data, ( new ElementorPermissions() )->before_save( $data, $this->document( $old ) ) );
	}

	public function test_a_user_with_send_permission_can_configure_an_accessible_local_pdf(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$data = array( 'elements' => $this->elements() );
		$this->assertSame( $data, ( new ElementorPermissions() )->before_save( $data, $this->document( array() ) ) );
	}

	public function test_even_a_sender_cannot_configure_an_external_pdf_url(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$elements = $this->elements();
		$elements[0]['elements'][0]['settings']['assinafy_pdf'] = array( 'url' => 'https://example.com/contract.pdf' );
		$this->expectException( \RuntimeException::class );
		( new ElementorPermissions() )->before_save( array( 'elements' => $elements ), $this->document( array() ) );
	}

	/**
	 * @param array<string, mixed> $overrides Configuration overrides.
	 */
	private function record( array $overrides = array() ): Form_Record {
		return new Form_Record(
			array(
				'form_settings' => array_replace( $this->settings(), $overrides ),
				'fields'        => array(
					'person'  => array( 'value' => ' Jane Doe ' ),
					'address' => array( 'value' => 'jane@example.com' ),
					'file'    => array( 'value' => 'https://example.com/untrusted.pdf' ),
				),
			)
		);
	}

	/** @return array<string, mixed> */
	private function settings(): array {
		return array(
			'assinafy_pdf'         => array( 'id' => $this->attachment ),
			'assinafy_name_field'  => 'person',
			'assinafy_email_field' => 'address',
			'assinafy_workflow'    => 'agreement-v1',
			'assinafy_message'     => 'Please sign this agreement.',
		);
	}

	/** @return array<mixed> */
	private function elements(): array {
		return array(
			array(
				'id'       => 'container',
				'elType'   => 'container',
				'elements' => array(
					array(
						'id'         => 'form-one',
						'widgetType' => 'form',
						'settings'   => $this->settings() + array( 'submit_actions' => array( 'email', 'assinafy' ) ),
					),
				),
			),
		);
	}

	/** @param array<mixed> $elements Native document element tree. */
	private function document( array $elements ): Document {
		return new class( $elements ) extends Document {};
	}
}
