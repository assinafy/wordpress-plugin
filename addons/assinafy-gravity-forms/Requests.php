<?php
/**
 * Gravity Forms request identity and recoverable dispatch.
 *
 * @package Assinafy\WP\Addons\GravityForms
 */
declare(strict_types=1);
namespace Assinafy\WP\Addons\GravityForms;

defined( 'ABSPATH' ) || exit;
use Assinafy\WP\Documents\DocumentRecord;
use Assinafy\WP\Documents\DocumentPostType;
use Assinafy\WP\Documents\SendService;
use WP_Error;
/** Keeps native feed presentation separate from recoverable send state. */
final class Requests {
	/** @param \GFFeedAddOn $addon Native field formatter. @param SendService $sender Core sender. */
	public function __construct( private readonly \GFFeedAddOn $addon, private readonly SendService $sender ) {}
	/**
	 * The framework invokes this only for eligible feeds after validation and conditions.
	 * @param array<string, mixed> $feed Saved native feed.
	 * @param array<array-key, mixed> $entry Saved native entry.
	 * @param array<string, mixed> $form Saved form.
	 * @return true|WP_Error Native success/failure status.
	 */
	public function process( $feed, $entry, $form ): bool|WP_Error {
		$entry_id = (int) ( $entry['id'] ?? 0 );
		$feed_id  = (int) ( $feed['id'] ?? 0 );
		$form_id  = (int) ( $form['id'] ?? 0 );
		if ( $entry_id < 1 || $feed_id < 1 || $form_id < 1 || $form_id !== (int) ( $entry['form_id'] ?? 0 ) || $form_id !== (int) ( $feed['form_id'] ?? 0 ) || 'active' !== ( $entry['status'] ?? '' ) || empty( $feed['is_active'] ) ) {
			return $this->problem();
		}
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$lock = 'assinafy_gf_' . $entry_id . '_' . $feed_id;
		if ( ! \WP_Upgrader::create_lock( $lock, 300 ) ) {
			return new WP_Error( 'assinafy_send_in_progress', __( 'This feed is already being processed.', 'assinafy-gravity-forms' ) );
		}
		try {
			return $this->send_feed( $feed, $entry, $form );
		} finally {
			\WP_Upgrader::release_lock( $lock );
		}
	}

	/**
	 * Persist only a request fingerprint and core mirror ID; Gravity Forms keeps the entry.
	 * A changed entry/feed may never retarget a partial remote request.
	 * @param array<string, mixed> $feed Native feed.
	 * @param array<array-key, mixed> $entry Native entry.
	 * @param array<string, mixed> $form Native form.
	 * @return true|WP_Error Send result.
	 */
	private function send_feed( array $feed, array $entry, array $form ): bool|WP_Error {
		$entry_id = (int) $entry['id'];
		$feed_id  = (int) $feed['id'];
		$key      = 'assinafy_feed_' . $feed_id;
		$receipt  = gform_get_meta( $entry_id, $key );
		$receipt  = is_array( $receipt ) ? $receipt : array();
		$records  = new DocumentRecord();
		$post_id  = (int) ( $receipt['post_id'] ?? 0 );
		$existing = $this->existing( $post_id, $entry_id, $records );
		if ( null !== $existing ) {
			return $existing;
		}
		$args        = $this->arguments( $feed, $entry, $form );
		$fingerprint = $this->fingerprint( $args );
		if ( $fingerprint instanceof WP_Error ) {
			return $fingerprint;
		}
		if ( isset( $receipt['fingerprint'] ) && ! hash_equals( (string) $receipt['fingerprint'], $fingerprint ) ) {
			return new WP_Error( 'assinafy_feed_changed', __( 'The entry or feed changed after sending began. Restore the original mapping and values before retrying.', 'assinafy-gravity-forms' ) );
		}
		$receipt = array(
			'fingerprint' => $fingerprint,
			'post_id'     => $post_id,
		);
		gform_update_meta( $entry_id, $key, $receipt, (int) $form['id'] );
		if ( gform_get_meta( $entry_id, $key ) !== $receipt ) {
			return $this->problem();
		}
		$args['post_id'] = $post_id;
		$result          = $this->sender->send( $args );
		gform_update_meta( $entry_id, $key, $this->receipt_after( $result, $receipt ), (int) $form['id'] );
		return $result instanceof WP_Error ? $result : true;
	}

	/**
	 * Replacing a Media Library file must not change an unfinished agreement.
	 * @param array<string, mixed> $args Mapped core arguments.
	 * @return string|WP_Error Fingerprint including the PDF bytes, or an unavailable file.
	 */
	private function fingerprint( array $args ): string|WP_Error {
		$file = get_attached_file( (int) $args['attachment_id'] );
		if ( ! is_string( $file ) || ! is_file( $file ) || ! is_readable( $file ) ) {
			return $this->problem();
		}
		$digest = hash_file( 'sha256', $file );
		return false === $digest
			? $this->problem()
			: hash_hmac( 'sha256', (string) wp_json_encode( array( $args, $digest ) ), wp_salt() );
	}

	/**
	 * Keep recovery state, releasing invalid inputs only after a definite pre-reservation refusal.
	 * @param int|WP_Error $result Send result.
	 * @param array{fingerprint?: string, post_id: int} $receipt Original receipt.
	 * @return array{fingerprint?: string, post_id: int} Updated receipt.
	 */
	private function receipt_after( int|WP_Error $result, array $receipt ): array {
		if ( is_int( $result ) ) {
			$receipt['post_id'] = $result;
			return $receipt;
		}
		$data               = $result->get_error_data();
		$recovered          = is_array( $data ) ? (int) ( $data['post_id'] ?? 0 ) : 0;
		$receipt['post_id'] = $recovered > 0 ? $recovered : $receipt['post_id'];
		if ( 0 === $receipt['post_id'] && 'assinafy_send_in_progress' !== $result->get_error_code() ) {
			// Interruptions never reach here, so their original fingerprint stays durable.
			unset( $receipt['fingerprint'] );
		}
		return $receipt;
	}

	/**
	 * @param int $post_id Existing mirror.
	 * @param int $entry_id Original entry.
	 * @param DocumentRecord $records Mirror reader.
	 * @return true|WP_Error|null Completed, refused, or safe to resume.
	 */
	private function existing( int $post_id, int $entry_id, DocumentRecord $records ): bool|WP_Error|null {
		if ( $post_id < 1 ) {
			return null;
		}
		if ( DocumentPostType::POST_TYPE !== get_post_type( $post_id ) || $records->source( $post_id ) !== array(
			'integration' => 'gravity-forms',
			'record_id'   => (string) $entry_id,
		) || 'trash' === get_post_status( $post_id ) ) {
			return $this->problem();
		}
		if ( '' !== $records->assignment_id( $post_id ) ) {
			return true;
		}
		foreach ( $records->signers( $post_id ) as $signer ) {
			if ( str_starts_with( $signer['email'], '[redacted-' ) ) {
				return $this->problem();
			}
		}
		return null;
	}

	/**
	 * Use the host's Name field formatting and compound input mapping.
	 * @param array<string, mixed> $feed Native feed.
	 * @param array<array-key, mixed> $entry Native entry.
	 * @param array<string, mixed> $form Native form.
	 * @return array<string, mixed> Core API arguments.
	 */
	private function arguments( array $feed, array $entry, array $form ): array {
		$meta  = is_array( $feed['meta'] ?? null ) ? $feed['meta'] : array();
		$name  = $this->addon->get_field_value( $form, $entry, (string) ( $meta['signer_name'] ?? '' ) );
		$email = $this->addon->get_field_value( $form, $entry, (string) ( $meta['signer_email'] ?? '' ) );
		return array(
			'attachment_id'   => (int) ( $meta['attachment_id'] ?? 0 ),
			'signers'         => array(
				array(
					'name'  => is_string( $name ) ? sanitize_text_field( $name ) : '',
					'email' => is_string( $email ) ? trim( $email ) : '',
				),
			),
			'message'         => sanitize_textarea_field( (string) ( $meta['message'] ?? '' ) ),
			'source'          => array(
				'integration' => 'gravity-forms',
				'record_id'   => (string) $entry['id'],
			),
			'idempotency_key' => 'gravity-forms:form:' . (int) $form['id'] . ':entry:' . (int) $entry['id'] . ':feed:' . (int) $feed['id'],
		);
	}

	/** @return WP_Error Safe refusal for absent or changed local prerequisites. */
	private function problem(): WP_Error {
		return new WP_Error( 'assinafy_feed_unavailable', __( 'This signature feed or its original record is unavailable. Check the active entry, feed and Assinafy configuration.', 'assinafy-gravity-forms' ) );
	}
}
