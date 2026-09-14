<?php
/**
 * Native Contact Form 7 editor configuration.
 *
 * @package Assinafy\WP\Addons\ContactForm7
 */

declare(strict_types=1);

namespace Assinafy\WP\Addons\ContactForm7;

defined( 'ABSPATH' ) || exit;

use WPCF7_ContactForm;

/** The host owns editor authorization; these fields only configure the adapter. */
final class FormSettings {

	public const SETTINGS      = '_assinafy_cf7_settings';
	public const ERROR         = '_assinafy_cf7_error';
	private const PICKER_LIMIT = 200;

	/** Attach the native form editor panel and save callback. */
	public function register(): void {
		add_filter( 'wpcf7_editor_panels', array( $this, 'panels' ) );
		add_action( 'wpcf7_after_save', array( $this, 'save' ) );
	}

	/**
	 * @param array<string, mixed> $panels CF7 editor panels.
	 * @return array<string, mixed> Panels with the signature settings added.
	 */
	public function panels( array $panels ): array {
		if ( current_user_can( 'assinafy_manage' ) && current_user_can( 'assinafy_send' ) ) {
			$panels['assinafy-panel'] = array(
				'title'    => __( 'Assinafy', 'assinafy-contact-form-7' ),
				'callback' => array( $this, 'render' ),
			);
		}
		return $panels;
	}

	/**
	 * @param WPCF7_ContactForm $form Form being edited.
	 */
	public function render( WPCF7_ContactForm $form ): void {
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- Native Contact Form 7 form-edit capability.
		if ( ! current_user_can( 'assinafy_manage' ) || ! current_user_can( 'assinafy_send' ) || ! current_user_can( 'wpcf7_edit_contact_form', $form->id() ) ) {
			return;
		}
		$settings = $this->settings( $form );
		echo '<h2>' . esc_html__( 'Request a signature after a successful submission', 'assinafy-contact-form-7' ) . '</h2><p>' . esc_html__( 'Use an existing Media Library PDF. Signing runs after Contact Form 7 accepts the submission. Failed signature requests can be retried from their document record under Assinafy → Documents.', 'assinafy-contact-form-7' ) . '</p>';
		$error = get_post_meta( $form->id(), self::ERROR, true );
		if ( is_string( $error ) && '' !== $error ) {
			echo '<p class="notice notice-warning">' . esc_html( $error ) . '</p>';
		}
		echo '<input type="hidden" name="assinafy_cf7[present]" value="1"><p><label><input type="checkbox" name="assinafy_cf7[enabled]" value="1" ' . checked( ! empty( $settings['enabled'] ), true, false ) . '> ' . esc_html__( 'Enable signature requests', 'assinafy-contact-form-7' ) . '</label></p>';
		$pdfs  = array( '' => __( 'Select a PDF', 'assinafy-contact-form-7' ) );
		$ids   = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'application/pdf',
				'numberposts'    => self::PICKER_LIMIT,
				'fields'         => 'ids',
			)
		);
		$ids[] = (int) ( $settings['attachment_id'] ?? 0 );
		foreach ( array_unique( $ids ) as $id ) {
			if ( 'application/pdf' === get_post_mime_type( $id ) ) {
				$pdfs[ (string) $id ] = get_the_title( $id );
			}
		}
		$this->select( 'attachment_id', __( 'Document', 'assinafy-contact-form-7' ), $pdfs, (string) ( $settings['attachment_id'] ?? '' ) );
		$this->select( 'name', __( 'Signer name field', 'assinafy-contact-form-7' ), $this->fields( $form, array( 'text', 'textarea' ) ), (string) ( $settings['name'] ?? '' ) );
		$this->select( 'email', __( 'Signer email field', 'assinafy-contact-form-7' ), $this->fields( $form, array( 'email' ) ), (string) ( $settings['email'] ?? '' ) );
		$this->select( 'consent', __( 'Optional acceptance field', 'assinafy-contact-form-7' ), $this->fields( $form, array( 'acceptance' ) ), (string) ( $settings['consent'] ?? '' ) );
		echo '<p>' . esc_html__( 'When an acceptance field is selected, it must be checked. Inverted acceptance fields are not supported. Explain the signature request in your form.', 'assinafy-contact-form-7' ) . '</p><p><label for="assinafy-cf7-message">' . esc_html__( 'Invitation message', 'assinafy-contact-form-7' ) . '</label><br><textarea id="assinafy-cf7-message" name="assinafy_cf7[message]" rows="4" class="large-text">' . esc_textarea( (string) ( $settings['message'] ?? '' ) ) . '</textarea></p>';
	}

	/**
	 * Reuse the host editor nonce and capabilities; REST/programmatic saves preserve settings.
	 *
	 * @param WPCF7_ContactForm $form Saved host form, including newly created forms.
	 */
	public function save( WPCF7_ContactForm $form ): void {
		if ( ! $this->authorized_editor( $form ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- authorized_editor() verifies the native CF7 nonce and required capabilities before reading these settings.
		if ( ! isset( $_POST['assinafy_cf7'] ) || ! is_array( $_POST['assinafy_cf7'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitize() whitelists text fields, applies absint to the PDF ID, and strictly normalizes enabled before storage.
		$settings = $this->sanitize( wp_unslash( $_POST['assinafy_cf7'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$valid = $this->valid_settings( $form, $settings );
		if ( ! $valid ) {
			$settings['enabled'] = false;
		}
		update_post_meta( $form->id(), self::SETTINGS, wp_slash( $settings ) );
		update_post_meta( $form->id(), self::ERROR, $valid ? '' : __( 'Select a valid PDF and existing name/email fields. Signature requests are disabled until the configuration is corrected.', 'assinafy-contact-form-7' ) );
	}

	/**
	 * @param WPCF7_ContactForm $form Host form being saved.
	 */
	private function authorized_editor( WPCF7_ContactForm $form ): bool {
		$posted = sanitize_text_field( wp_unslash( $_POST['post_ID'] ?? '' ) );
		$nonce  = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- Contact Form 7 maps this native capability in includes/capabilities.php.
		return current_user_can( 'wpcf7_edit_contact_form', $form->id() ) && current_user_can( 'assinafy_manage' ) && current_user_can( 'assinafy_send' )
			&& ( '-1' === $posted || (string) $form->id() === $posted )
			&& false !== wp_verify_nonce( $nonce, 'wpcf7-save-contact-form_' . $posted );
	}

	/**
	 * @param array<string, mixed> $input Unslashed settings controls.
	 * @return array<string, mixed> Settings with scalar values only.
	 */
	private function sanitize( array $input ): array {
		$settings = array();
		foreach ( array( 'name', 'email', 'consent', 'message' ) as $key ) {
			$settings[ $key ] = is_string( $input[ $key ] ?? null ) ? sanitize_textarea_field( $input[ $key ] ) : '';
		}
		$settings['attachment_id'] = is_scalar( $input['attachment_id'] ?? null ) ? absint( $input['attachment_id'] ) : 0;
		$settings['enabled']       = '1' === ( $input['enabled'] ?? '' );
		return $settings;
	}

	/**
	 * @param WPCF7_ContactForm $form Host form.
	 * @return array<string, mixed> Adapter-owned configuration.
	 */
	public function settings( WPCF7_ContactForm $form ): array {
		$value = get_post_meta( $form->id(), self::SETTINGS, true );
		return is_array( $value ) ? $value : array();
	}

	/**
	 * @param WPCF7_ContactForm $form Host form.
	 * @param array<string, mixed> $settings Configuration snapshot.
	 */
	public function valid_settings( WPCF7_ContactForm $form, array $settings ): bool {
		return 'application/pdf' === get_post_mime_type( (int) ( $settings['attachment_id'] ?? 0 ) )
			&& '' !== ( $settings['name'] ?? '' ) && isset( $this->fields( $form, array( 'text', 'textarea' ) )[ $settings['name'] ] )
			&& '' !== ( $settings['email'] ?? '' ) && isset( $this->fields( $form, array( 'email' ) )[ $settings['email'] ] )
			&& ( '' === ( $settings['consent'] ?? '' ) || isset( $this->fields( $form, array( 'acceptance' ) )[ $settings['consent'] ] ) );
	}

	/**
	 * @param WPCF7_ContactForm $form Host form.
	 * @param array<int, string> $types Supported scalar field types.
	 * @return array<string, string> Field names and labels.
	 */
	private function fields( WPCF7_ContactForm $form, array $types ): array {
		$fields = array( '' => __( 'None selected', 'assinafy-contact-form-7' ) );
		foreach ( $form->scan_form_tags() as $tag ) {
			if ( '' !== $tag->name && in_array( $tag->basetype, $types, true ) && ! $tag->has_option( 'invert' ) ) {
				$fields[ $tag->name ] = $tag->name;
			}
		}
		return $fields;
	}

	/**
	 * @param string $key Settings field key.
	 * @param string $label Visible label.
	 * @param array<int|string, string> $options Select options.
	 * @param string $value Current value.
	 */
	private function select( string $key, string $label, array $options, string $value ): void {
		echo '<p><label for="assinafy-cf7-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label><br><select id="assinafy-cf7-' . esc_attr( $key ) . '" name="assinafy_cf7[' . esc_attr( $key ) . ']">';
		foreach ( $options as $id => $text ) {
			echo '<option value="' . esc_attr( (string) $id ) . '" ' . selected( (string) $id, $value, false ) . '>' . esc_html( $text ) . '</option>';
		}
		echo '</select></p>';
	}
}
