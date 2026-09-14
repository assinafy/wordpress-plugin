<?php
/**
 * Native WPForms builder settings.
 *
 * @package Assinafy\WP\Addons\WPForms
 */

declare(strict_types=1);

namespace Assinafy\WP\Addons\WPForms;

defined( 'ABSPATH' ) || exit;

/**
 * One PDF and one mapped signer, saved through WPForms' authorized form save.
 */
final class FormSettings {

	/** Register the native builder callbacks. */
	public function register(): void {
		add_filter( 'wpforms_builder_settings_sections', [ $this, 'sections' ] );
		add_action( 'wpforms_form_settings_panel_content', [ $this, 'render' ] );
		add_filter( 'wpforms_save_form_args', [ $this, 'save' ], 20, 2 );
		add_action( 'wpforms_builder_enqueues', [ $this, 'assets' ] );
	}

	/**
	 * @param array<string, string> $sections Native settings sections.
	 * @return array<string, string> Sections including Assinafy.
	 */
	public function sections( array $sections ): array {
		$sections['assinafy'] = __( 'Assinafy', 'assinafy-wpforms' );
		return $sections;
	}

	/** Enqueue the media-library picker in the form builder. */
	public function assets(): void {
		wp_enqueue_media();
		wp_enqueue_script( 'assinafy-wpforms-builder', plugins_url( '../assets/builder.js', __FILE__ ), [ 'jquery', 'media-views' ], '0.1.0', true );
	}

	/**
	 * @param object{form_data: array<string, mixed>} $panel WPForms settings panel.
	 */
	public function render( object $panel ): void {
		$data = $panel->form_data;
		echo '<div class="wpforms-panel-content-section wpforms-panel-content-section-assinafy">';
		echo '<div class="wpforms-panel-content-section-title">' . esc_html__( 'Assinafy', 'assinafy-wpforms' ) . '</div>';
		if ( ! self::can_manage( (int) ( $data['id'] ?? 0 ) ) ) {
			echo '<p>' . esc_html__( 'You need permission to edit this form and send Assinafy documents to change this workflow.', 'assinafy-wpforms' ) . '</p></div>';
			return;
		}
		echo '<p>' . esc_html__( 'Send the selected PDF to one signer after an accepted, non-spam submission. This does not wait for payment or guarantee notification email delivery. Explain this signature request and obtain any required consent in your form.', 'assinafy-wpforms' ) . '</p>';
		wpforms_panel_field( 'toggle', 'assinafy', 'enabled', $data, __( 'Enable signature requests', 'assinafy-wpforms' ), [ 'parent' => 'settings' ] );
		wpforms_panel_field(
			'text',
			'assinafy',
			'attachment_id',
			$data,
			__( 'PDF attachment ID', 'assinafy-wpforms' ),
			[
				'parent' => 'settings',
				'type'   => 'number',
				'min'    => 0,
			]
		);
		echo '<button type="button" class="wpforms-btn wpforms-btn-sm wpforms-btn-light-grey" id="assinafy-wpforms-pdf">' . esc_html__( 'Choose PDF', 'assinafy-wpforms' ) . '</button>';
		wpforms_panel_field(
			'select',
			'assinafy',
			'name_field',
			$data,
			__( 'Signer name field', 'assinafy-wpforms' ),
			[
				'parent'  => 'settings',
				'options' => self::fields( $data, [ 'name', 'text' ] ),
			]
		);
		wpforms_panel_field(
			'select',
			'assinafy',
			'email_field',
			$data,
			__( 'Signer email field', 'assinafy-wpforms' ),
			[
				'parent'  => 'settings',
				'options' => self::fields( $data, [ 'email' ] ),
			]
		);
		wpforms_panel_field( 'textarea', 'assinafy', 'message', $data, __( 'Invitation message', 'assinafy-wpforms' ), [ 'parent' => 'settings' ] );
		echo '<p>' . esc_html__( 'Use required name and email fields. The message is plain text; smart tags are not expanded. An empty message uses the Assinafy default. Invalid PDF or field mappings disable this workflow when saved.', 'assinafy-wpforms' ) . '</p>';
		do_action( 'assinafy_wpforms_requests', (int) ( $data['id'] ?? 0 ) );
		echo '</div>';
	}

	/**
	 * Preserve other extensions' form data and authorize changes to this workflow.
	 * WPForms validates its builder nonce and edit permission before this filter.
	 *
	 * @param array<string, mixed> $post_data The pending wp_update_post arguments.
	 * @param array<string, mixed> $form_data Native form configuration.
	 * @return array<string, mixed> Arguments with normalized workflow settings.
	 */
	public function save( array $post_data, array $form_data ): array {
		$data = json_decode( wp_unslash( (string) ( $post_data['post_content'] ?? '' ) ), true );
		if ( ! is_array( $data ) ) {
			return $post_data;
		}
		$form_id  = (int) ( $post_data['ID'] ?? 0 );
		$original = wpforms_decode( (string) get_post_field( 'post_content', $form_id ) );
		$config   = self::read( is_array( $original ) ? $original : [] );
		if ( self::can_manage( $form_id ) ) {
			$config            = self::read( $form_data );
			$config['enabled'] = $config['enabled'] && self::usable( $config, $form_data );
		}
		$data['settings']['assinafy'] = $config;
		$post_data['post_content']    = wpforms_encode( $data );
		return $post_data;
	}

	/**
	 * @param array<string, mixed> $data Native form data.
	 * @return array{enabled: bool, attachment_id: int, name_field: string, email_field: string, message: string} Workflow settings.
	 */
	public static function read( array $data ): array {
		$config = is_array( $data['settings']['assinafy'] ?? null ) ? $data['settings']['assinafy'] : [];
		return [
			'enabled'       => ! empty( $config['enabled'] ),
			'attachment_id' => absint( $config['attachment_id'] ?? 0 ),
			'name_field'    => self::field_id( $config['name_field'] ?? '' ),
			'email_field'   => self::field_id( $config['email_field'] ?? '' ),
			'message'       => is_string( $config['message'] ?? null ) ? sanitize_textarea_field( $config['message'] ) : '',
		];
	}

	/**
	 * @param int $form_id Native form ID.
	 */
	public static function can_manage( int $form_id ): bool {
		return current_user_can( 'assinafy_send' ) && wpforms_current_user_can( 'edit_form_single', $form_id );
	}

	/**
	 * @param array{attachment_id: int, name_field: string, email_field: string} $config Selected workflow.
	 * @param array<string, mixed> $data Native field definitions.
	 */
	private static function usable( array $config, array $data ): bool {
		return $config['attachment_id'] > 0
		&& current_user_can( 'read_post', $config['attachment_id'] )
		&& get_post_mime_type( $config['attachment_id'] ) === 'application/pdf'
		&& $config['name_field'] !== '' && isset( self::fields( $data, [ 'name', 'text' ] )[ $config['name_field'] ] )
		&& $config['email_field'] !== '' && isset( self::fields( $data, [ 'email' ] )[ $config['email_field'] ] );
	}

	/**
	 * @param mixed $value A native field ID; zero is valid.
	 */
	private static function field_id( mixed $value ): string {
		return ( is_string( $value ) || is_int( $value ) ) && ctype_digit( (string) $value ) ? (string) (int) $value : '';
	}

	/**
	 * @param array<string, mixed> $data Native form definition.
	 * @param array<int, string> $types Supported field types.
	 * @return array<int|string, string> Select options.
	 */
	private static function fields( array $data, array $types ): array {
		$options = [ '' => __( 'Select a field', 'assinafy-wpforms' ) ];
		foreach ( (array) ( $data['fields'] ?? [] ) as $id => $field ) {
			if ( is_array( $field ) && in_array( $field['type'] ?? '', $types, true ) ) {
				$options[ $id ] = sanitize_text_field( (string) ( $field['label'] ?? $id ) );
			}
		}
		return $options;
	}
}
