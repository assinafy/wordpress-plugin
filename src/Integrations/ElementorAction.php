<?php
/**
 * Native Elementor Pro Forms action for signature requests.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Integrations;

defined( 'ABSPATH' ) || exit;

use Closure;
use Assinafy\WP\Capabilities;
use Assinafy\WP\Log;
use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use ElementorPro\Modules\Forms\Classes\Action_Base;
use ElementorPro\Modules\Forms\Classes\Ajax_Handler;
use ElementorPro\Modules\Forms\Classes\Form_Record;
use WP_Error;
use WeakMap;

/**
 * Send one configured agreement after Elementor validates the visitor's form.
 *
 * Elementor owns form access, validation and spam checks. Administrators configure a
 * fixed media-library PDF and map validated fields; submitted URLs and paths are unused.
 * One opaque source identifies each accepted Form_Record. A repeated callback on that
 * record reuses its result, while a distinct submission can request the same agreement.
 * This does not require Collect Submissions or assume its optional database action ran.
 * Cross-request retries need the original source and mapped arguments; this adapter does
 * not implement a submissions retry screen or infer identity from matching personal data.
 */
final class ElementorAction extends Action_Base {

	/** @var WeakMap<Form_Record, int|WP_Error> Results of accepted callbacks in this request. */
	private WeakMap $results;

	/**
	 * @param Closure(array<string, mixed>): (int|WP_Error) $sender Core send dispatch, resolved after init.
	 */
	public function __construct( private readonly Closure $sender ) {
		$this->results = new WeakMap();
	}

	/**
	 * Native action identifier used in Actions After Submit.
	 */
	public function get_name(): string {
		return 'assinafy';
	}

	/**
	 * Label displayed by Elementor's editor.
	 */
	public function get_label(): string {
		return __( 'Assinafy', 'assinafy' );
	}

	/**
	 * Configure the PDF and field mapping using Elementor's native editor controls.
	 *
	 * @param Widget_Base $widget Form widget being configured.
	 */
	public function register_settings_section( $widget ): void {
		if ( ! current_user_can( Capabilities::SEND ) ) {
			return;
		}
		$widget->start_controls_section(
			'assinafy_section',
			array(
				'label'     => $this->get_label(),
				'condition' => array( 'submit_actions' => $this->get_name() ),
			)
		);
		$widget->add_control(
			'assinafy_pdf',
			array(
				'label'       => __( 'Agreement PDF', 'assinafy' ),
				'type'        => Controls_Manager::MEDIA,
				'media_types' => array( 'application/pdf' ),
			)
		);
		foreach ( array(
			'name'  => __( 'Signer name field ID', 'assinafy' ),
			'email' => __( 'Signer email field ID', 'assinafy' ),
		) as $field => $label ) {
			$widget->add_control(
				'assinafy_' . $field . '_field',
				array(
					'label'   => $label,
					'type'    => Controls_Manager::TEXT,
					'default' => $field,
				)
			);
		}
		$widget->add_control(
			'assinafy_workflow',
			array(
				'label'       => __( 'Agreement workflow and revision', 'assinafy' ),
				'type'        => Controls_Manager::TEXT,
				'description' => __( 'Required: a unique name for this form and agreement revision, using letters, numbers, dots, dashes or underscores.', 'assinafy' ),
			)
		);
		$widget->add_control(
			'assinafy_message',
			array(
				'label' => __( 'Message to signer', 'assinafy' ),
				'type'  => Controls_Manager::TEXTAREA,
			)
		);
		$widget->end_controls_section();
	}

	/**
	 * Run after native form validation and report failure through Elementor's field errors.
	 *
	 * The core performs the account-authenticated signer lookup, PDF upload and assignment.
	 * Normal return lets Elementor render its configured success message; no document URL,
	 * API diagnostics or signature identity is added to a public response.
	 *
	 * @param Form_Record  $record Native validated form record.
	 * @param Ajax_Handler $ajax_handler Native form response handler.
	 */
	public function run( $record, $ajax_handler ): void {
		$result = $this->send_once( $record );
		if ( $result instanceof WP_Error ) {
			$fields = $record->get( 'fields' );
			$field  = is_array( $fields ) && array() !== $fields ? (string) array_key_first( $fields ) : 'assinafy';
			$ajax_handler->add_error( $field, __( 'The signature request could not be sent. Please contact the site administrator.', 'assinafy' ) );
		}
	}

	/**
	 * Repeated callbacks for one accepted record never start a second attempt.
	 *
	 * @param Form_Record $record Validated form record.
	 * @return int|WP_Error Core result, including any known recovery post ID.
	 */
	private function send_once( Form_Record $record ): int|WP_Error {
		if ( isset( $this->results[ $record ] ) ) {
			return $this->results[ $record ];
		}
		$this->results[ $record ] = new WP_Error( 'assinafy_send_in_progress' );
		try {
			$args   = $this->arguments( $record );
			$result = $args instanceof WP_Error ? $args : ( $this->sender )( $args );
		} catch ( \Throwable ) {
			$result = new WP_Error( 'assinafy_send_failed' );
		}
		$this->results[ $record ] = $result;
		if ( $result instanceof WP_Error ) {
			$data = $result->get_error_data();
			( new Log() )->add(
				'elementor_send_failed',
				array(
					'error_code' => $result->get_error_code(),
					'post_id'    => is_array( $data ) ? (int) ( $data['post_id'] ?? 0 ) : 0,
				)
			);
		}
		return $result;
	}

	/**
	 * Imported forms must explicitly select their site's PDF and workflow identity.
	 *
	 * @param array<string, mixed> $element Exported settings.
	 * @return array<string, mixed> Settings without this site's Assinafy action configuration.
	 */
	public function on_export( $element ): array {
		foreach ( array( 'pdf', 'name_field', 'email_field', 'workflow', 'message' ) as $setting ) {
			unset( $element[ 'assinafy_' . $setting ] );
		}
		return $element;
	}

	/**
	 * Translate the native record into a stable shared send request.
	 *
	 * @param Form_Record $record Native validated submission.
	 * @return array<string, mixed>|WP_Error Mapped request or incomplete configuration.
	 */
	private function arguments( Form_Record $record ): array|WP_Error {
		$settings = $record->get( 'form_settings' );
		$fields   = $record->get( 'fields' );
		if ( ! is_array( $settings ) || ! is_array( $fields ) ) {
			return new WP_Error( 'assinafy_elementor_configuration' );
		}
		$workflow = $settings['assinafy_workflow'] ?? '';
		$pdf      = $this->attachment( $settings['assinafy_pdf'] ?? null );
		$name     = $this->field( $fields, $settings['assinafy_name_field'] ?? null );
		$email    = $this->field( $fields, $settings['assinafy_email_field'] ?? null );
		if ( ! is_string( $workflow ) || 1 !== preg_match( '/\A[a-zA-Z0-9_.-]{1,64}\z/', $workflow ) || '' === $name || '' === $email ) {
			return new WP_Error( 'assinafy_elementor_configuration' );
		}
		if ( 0 === $pdf ) {
			return new WP_Error( 'assinafy_elementor_pdf' );
		}
		$args = array(
			'attachment_id' => $pdf,
			'signers'       => array(
				array(
					'name'  => $name,
					'email' => $email,
				),
			),
			'message'       => is_string( $settings['assinafy_message'] ?? null ) ? $settings['assinafy_message'] : '',
		);
		// Generate once for this accepted record, never again for its repeated callback.
		$reference               = $workflow . ':' . bin2hex( random_bytes( 16 ) );
		$args['source']          = array(
			'integration' => 'elementor',
			'record_id'   => $reference,
		);
		$args['idempotency_key'] = 'elementor:' . $reference;
		return $args;
	}

	/**
	 * Resolve only the configured local attachment ID, never its optional URL.
	 *
	 * @param mixed $media Native media-control value.
	 */
	private function attachment( mixed $media ): int {
		$pdf = is_array( $media ) ? filter_var( $media['id'] ?? 0, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) ) : false;
		return false !== $pdf && ElementorPermissions::local_pdf( $pdf ) ? $pdf : 0;
	}

	/**
	 * Preserve a validated field's scalar text; core validates email without repairing it.
	 *
	 * @param array<string, mixed> $fields Native fields keyed by field ID.
	 * @param mixed $mapping Configured field ID.
	 */
	private function field( array $fields, mixed $mapping ): string {
		if ( ! is_string( $mapping ) || ! is_array( $fields[ $mapping ] ?? null ) ) {
			return '';
		}
		$value = $fields[ $mapping ]['value'] ?? null;
		return is_string( $value ) ? trim( $value ) : '';
	}
}
