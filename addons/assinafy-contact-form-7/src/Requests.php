<?php
/**
 * Durable request receipts and retries on the core document record.
 *
 * @package Assinafy\WP\Addons\ContactForm7
 */

declare(strict_types=1);

namespace Assinafy\WP\Addons\ContactForm7;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Documents\DocumentPostType;
use Assinafy\WP\Documents\DocumentRecord;
use Assinafy\WP\Documents\SendService;
use WP_Error;
use WP_Post;

/** Retry configuration contains no copied submission or duplicate signer store. */
final class Requests {

	private const CONFIG = '_assinafy_cf7_retry';

	/**
	 * @param SendService $send Shared core sender.
	 * @param DocumentRecord $records Shared core mirror accessor.
	 */
	public function __construct( private readonly SendService $send, private readonly DocumentRecord $records ) {
	}

	/** Use the existing core document editor and its privacy-managed signer store. */
	public function register(): void {
		add_action( 'add_meta_boxes_' . DocumentPostType::POST_TYPE, array( $this, 'metabox' ) );
		add_action( 'admin_post_assinafy_cf7_retry', array( $this, 'retry' ) );
	}

	/**
	 * Reserve one accepted submission before any remote request.
	 *
	 * @param int $form_id Host form ID.
	 * @param array<string, mixed> $args Minimal signer and document snapshot.
	 * @return int|WP_Error Core receipt ID or storage failure.
	 */
	public function create( int $form_id, array $args ): int|WP_Error {
		$id = wp_insert_post(
			array(
				'post_type'   => DocumentPostType::POST_TYPE,
				'post_status' => 'private',
				'post_title'  => get_the_title( (int) $args['attachment_id'] ),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		if ( $id < 1 ) {
			return new WP_Error( 'assinafy_cf7_storage', __( 'The signature request could not be saved.', 'assinafy-contact-form-7' ) );
		}
		$source = array(
			'integration' => 'contact-form-7',
			'record_id'   => $form_id . ':' . $id,
		);
		$config = array(
			'attachment_id'   => $args['attachment_id'],
			'message'         => $args['message'] ?? '',
			'post_id'         => $id,
			'source'          => $source,
			'idempotency_key' => 'contact-form-7-form-' . $form_id . '-request-' . $id . '-attachment-' . $args['attachment_id'],
		);
		$signer = $args['signers'][0];
		$this->records->set_signers(
			$id,
			array(
				array(
					'id'          => '',
					'name'        => $signer['full_name'],
					'email'       => $signer['email'],
					'step'        => 1,
					'notified'    => false,
					'completed'   => false,
					'signing_url' => '',
				),
			)
		);
		update_post_meta( $id, self::CONFIG, wp_slash( $config ) );
		if ( ! $this->records->set_source( $id, $source ) || get_post_meta( $id, self::CONFIG, true ) !== $config ) {
			wp_delete_post( $id, true );
			return new WP_Error( 'assinafy_cf7_storage', __( 'The retry data could not be saved. Nothing was sent.', 'assinafy-contact-form-7' ) );
		}
		return $id;
	}

	/**
	 * Send or resume the saved request; core manages recovery and signing state.
	 *
	 * @param int $id Core receipt ID.
	 * @return int|WP_Error Core document ID or error.
	 */
	public function send( int $id ): int|WP_Error {
		$args = $this->payload( $id );
		if ( array() === $args ) {
			return new WP_Error( 'assinafy_cf7_no_request', __( 'No valid retry data remains. Erased signer details cannot be reused.', 'assinafy-contact-form-7' ) );
		}
		try {
			$result = $this->send->send( $args );
		} catch ( \Throwable $error ) {
			$result = new WP_Error( 'assinafy_cf7_send_failed', $error->getMessage() );
		}
		if ( is_wp_error( $result ) ) {
			$this->records->set_last_error( $id, $result->get_error_message() );
			return $result;
		}
		delete_post_meta( $id, self::CONFIG );
		$this->records->set_last_error( $id, '' );
		return $result;
	}

	/**
	 * Read only this adapter's configuration and still-usable core signer details.
	 *
	 * @param int $id Core receipt ID.
	 * @return array<string, mixed> Original arguments, or none after success/erasure.
	 */
	public function payload( int $id ): array {
		if ( DocumentPostType::POST_TYPE !== get_post_type( $id ) || 'trash' === get_post_status( $id ) ) {
			return array();
		}
		$config = get_post_meta( $id, self::CONFIG, true );
		if ( ! is_array( $config ) || 'contact-form-7' !== ( $config['source']['integration'] ?? '' ) || $this->records->source( $id ) !== $config['source'] ) {
			return array();
		}
		$signer = $this->records->signers( $id )[0] ?? array();
		if ( ! is_email( $signer['email'] ?? '' ) || empty( $signer['name'] ) || str_starts_with( $signer['name'], '[redacted-' ) ) {
			return array();
		}
		$config['signers'] = array(
			array(
				'full_name' => $signer['name'],
				'email'     => $signer['email'],
			),
		);
		return $config;
	}

	/**
	 * @param WP_Post $post Core document being edited.
	 */
	public function metabox( WP_Post $post ): void {
		if ( $this->can_retry( (int) $post->ID ) ) {
			add_meta_box( 'assinafy-cf7-retry', __( 'Contact Form 7 request', 'assinafy-contact-form-7' ), array( $this, 'render' ), DocumentPostType::POST_TYPE, 'side' );
		}
	}

	/**
	 * Place the action form outside WordPress's form#post, with native form ownership.
	 *
	 * @param WP_Post $post Core document being edited.
	 */
	public function render( WP_Post $post ): void {
		$id = (int) $post->ID;
		if ( ! $this->can_retry( $id ) ) {
			return;
		}
		$form = 'assinafy-cf7-retry-' . $id;
		echo '<p>' . esc_html__( 'Retry uses the original PDF selection, signer, source and request key. It does not resubmit the contact form.', 'assinafy-contact-form-7' ) . '</p><button type="submit" class="button" form="' . esc_attr( $form ) . '">' . esc_html__( 'Retry original request', 'assinafy-contact-form-7' ) . '</button>';
		add_action(
			'admin_footer',
			static function () use ( $id, $form ): void {
				echo '<form id="' . esc_attr( $form ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="assinafy_cf7_retry"><input type="hidden" name="request_id" value="' . esc_attr( (string) $id ) . '">';
				wp_nonce_field( 'assinafy_cf7_retry_' . $id );
				echo '</form>';
			}
		);
	}

	/** Retry through a nonce-protected admin POST, without accepting replacement arguments. */
	public function retry(): void {
		$id = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;
		check_admin_referer( 'assinafy_cf7_retry_' . $id );
		if ( ! $this->can_retry( $id ) ) {
			wp_die( esc_html__( 'You cannot retry signature requests.', 'assinafy-contact-form-7' ), '', array( 'response' => 403 ) );
		}
		$this->send( $id );
		wp_safe_redirect( admin_url( 'post.php?post=' . $id . '&action=edit' ) );
		exit;
	}

	/**
	 * Apply the same document, host and send permissions to the UI and POST handler.
	 *
	 * @param int $id Core receipt ID.
	 */
	private function can_retry( int $id ): bool {
		$args    = $this->payload( $id );
		$form_id = absint( explode( ':', $args['source']['record_id'] ?? '' )[0] );
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- Native Contact Form 7 form-edit capability.
		return current_user_can( 'wpcf7_edit_contact_form', $form_id ) && array() !== $args
			&& current_user_can( 'assinafy_manage' ) && current_user_can( 'assinafy_send' )
			&& current_user_can( 'edit_post', $id ) && 'wpcf7_contact_form' === get_post_type( $form_id );
	}
}
