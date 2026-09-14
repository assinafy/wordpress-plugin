<?php
/**
 * Accepted-submission receipts on core document mirrors.
 *
 * @package Assinafy\WP\Addons\WPForms
 */

declare(strict_types=1);

namespace Assinafy\WP\Addons\WPForms;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Documents\DocumentPostType;
use Assinafy\WP\Documents\DocumentRecord;
use WP_Error;

/**
 * Keep retry configuration here; signer contact data stays in the core privacy-aware mirror.
 */
final class RequestStore {

	private const META_REQUEST    = '_assinafy_wpforms_request';
	private const META_FORM       = '_assinafy_wpforms_form';
	private const META_SUBMISSION = '_assinafy_wpforms_submission';

	/**
	 * @param DocumentRecord $records Core document accessors.
	 */
	public function __construct( private readonly DocumentRecord $records ) {
	}

	/**
	 * @param int $form_id Native form ID.
	 * @param int $entry_id Native entry ID, zero in Lite.
	 */
	public function find_entry( int $form_id, int $entry_id ): int {
		if ( $entry_id < 1 ) {
			return 0;
		}
		$posts = $this->query( self::META_SUBMISSION, "form-{$form_id}:entry-{$entry_id}", 1 );
		return $posts[0] ?? 0;
	}

	/**
	 * @param array<string, mixed> $form Native accepted form.
	 * @param int $entry_id Native entry ID, zero in Lite.
	 * @param array{attachment_id: int, message: string} $config Retry configuration.
	 * @param array{full_name: string, email: string} $signer Mapped accepted signer.
	 * @return int|WP_Error Durable receipt ID or storage failure.
	 */
	public function create( array $form, int $entry_id, array $config, array $signer ): int|WP_Error {
		if ( $entry_id < 1 ) {
			return $this->insert( $form, $entry_id, $config, $signer );
		}
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$key = 'assinafy_wpforms_entry_' . get_current_blog_id() . '_' . (int) $form['id'] . '_' . $entry_id;
		if ( ! \WP_Upgrader::create_lock( $key, 60 ) ) {
			return new WP_Error( 'assinafy_wpforms_in_progress', __( 'This entry is already being recorded. Check its existing signature request.', 'assinafy-wpforms' ) );
		}
		try {
			$existing = $this->find_entry( (int) $form['id'], $entry_id );
			return $existing > 0 ? $existing : $this->insert( $form, $entry_id, $config, $signer );
		} finally {
			\WP_Upgrader::release_lock( $key );
		}
	}

	/**
	 * @param array<string, mixed> $form Native accepted form.
	 * @param int $entry_id Native entry ID, zero in Lite.
	 * @param array{attachment_id: int, message: string} $config Retry configuration.
	 * @param array{full_name: string, email: string} $signer Mapped accepted signer.
	 * @return int|WP_Error Durable receipt ID or storage failure.
	 */
	private function insert( array $form, int $entry_id, array $config, array $signer ): int|WP_Error {
		$form_id = (int) $form['id'];
		$post_id = wp_insert_post(
			[
				'post_type'   => DocumentPostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => sprintf(
					/* translators: %d: WPForms form ID. */
					__( 'WPForms form %d signature request', 'assinafy-wpforms' ),
					$form_id
				),
				'post_author' => get_current_user_id(),
			],
			true
		);
		if ( is_wp_error( $post_id ) || $post_id < 1 ) {
			return new WP_Error( 'assinafy_wpforms_storage', __( 'The signature request could not be recorded. Nothing was sent.', 'assinafy-wpforms' ) );
		}
		$submission = $entry_id > 0 ? "form-{$form_id}:entry-{$entry_id}" : "form-{$form_id}:submission-{$post_id}";
		$request    = [
			'form_id'       => $form_id,
			'entry_id'      => $entry_id,
			'attachment_id' => $config['attachment_id'],
			'message'       => $config['message'],
		];
		update_post_meta( $post_id, self::META_FORM, $form_id );
		update_post_meta( $post_id, self::META_SUBMISSION, $submission );
		update_post_meta( $post_id, self::META_REQUEST, wp_slash( $request ) );
		$this->records->set_signers( $post_id, [ self::pending_signer( $signer ) ] );
		if ( ! $this->records->set_source(
			$post_id,
			[
				'integration' => 'wpforms',
				'record_id'   => $submission,
			]
		) || $this->get( $post_id ) !== $request ) {
			return new WP_Error( 'assinafy_wpforms_storage', __( 'The signature request could not be saved completely. Nothing was sent.', 'assinafy-wpforms' ), [ 'post_id' => $post_id ] );
		}
		return $post_id;
	}

	/**
	 * @param int $post_id Core mirror ID.
	 * @return array{}|array{form_id: int, entry_id: int, attachment_id: int, message: string} Stored retry configuration.
	 */
	public function get( int $post_id ): array {
		$value = get_post_meta( $post_id, self::META_REQUEST, true );
		if ( ! is_array( $value ) || ! isset( $value['form_id'], $value['entry_id'], $value['attachment_id'], $value['message'] ) ) {
			return [];
		}
		return [
			'form_id'       => (int) $value['form_id'],
			'entry_id'      => (int) $value['entry_id'],
			'attachment_id' => (int) $value['attachment_id'],
			'message'       => (string) $value['message'],
		];
	}

	/**
	 * @param int $form_id Native form ID.
	 * @return array<int, int> The newest twenty receipts, including failures before upload.
	 */
	public function for_form( int $form_id ): array {
		return $this->query( self::META_FORM, (string) $form_id, 20 );
	}

	/**
	 * @param array{full_name: string, email: string} $signer Accepted mapped contact.
	 * @return array{id: string, name: string, email: string, step: int, notified: bool, completed: bool, signing_url: string} Pending core projection.
	 */
	private static function pending_signer( array $signer ): array {
		return [
			'id'          => '',
			'name'        => $signer['full_name'],
			'email'       => $signer['email'],
			'step'        => 1,
			'notified'    => false,
			'completed'   => false,
			'signing_url' => '',
		];
	}

	/**
	 * @param string $key Addon-owned metadata key.
	 * @param string $value Exact value.
	 * @param int $limit Maximum records.
	 * @return array<int, int> Core mirror IDs.
	 */
	private function query( string $key, string $value, int $limit ): array {
		$args = [
			'post_type'   => DocumentPostType::POST_TYPE,
			'post_status' => array_values( get_post_stati() ),
			'numberposts' => $limit,
			'fields'      => 'ids',
			'orderby'     => 'ID',
			'order'       => 'DESC',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Bounded lookup of this adapter's receipt index.
			'meta_key'    => $key,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Exact match on the adapter key.
			'meta_value'  => $value,
		];
		return array_map( 'intval', get_posts( $args ) );
	}
}
