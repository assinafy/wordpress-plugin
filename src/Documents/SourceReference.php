<?php
/**
 * The shared adapter-to-document source contract.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Documents;

defined( 'ABSPATH' ) || exit;

use WP_Error;

/**
 * Validate references before scheduling or sending, and keep retries on their origin.
 */
final class SourceReference {

	/**
	 * Validate opaque identifiers without silently rewriting them into another record.
	 *
	 * @param mixed $source Candidate source reference.
	 * @phpstan-assert-if-true array{integration: string, record_id: string} $source
	 */
	public static function is_valid( mixed $source ): bool {
		return is_array( $source ) && 2 === count( $source )
			&& is_string( $source['integration'] ?? null )
			&& is_string( $source['record_id'] ?? null )
			&& 1 === preg_match( '/\A[a-z0-9_-]{1,64}\z/', $source['integration'] )
			&& 1 === preg_match( '/\A[\x21-\x7e]{1,191}\z/', $source['record_id'] );
	}

	/**
	 * Refuse malformed source data before it reaches the queue, database or network.
	 *
	 * @param array<string, mixed> $args Send arguments.
	 */
	public static function problem( array $args ): ?WP_Error {
		if ( ! array_key_exists( 'source', $args ) ) {
			return null;
		}
		if ( ! self::is_valid( $args['source'] ) ) {
			return new WP_Error( 'assinafy_invalid_source', __( 'The integration source must contain a valid integration slug and record id.', 'assinafy' ) );
		}
		$post_id = (int) ( $args['post_id'] ?? 0 );
		$stored  = ( new DocumentRecord() )->source( $post_id );
		$source  = array(
			'integration' => $args['source']['integration'],
			'record_id'   => $args['source']['record_id'],
		);
		if ( array() !== $stored && $stored !== $source ) {
			return self::conflict( $post_id );
		}

		return null;
	}

	/**
	 * Adopt a legacy record's source or verify the immutable reference on a retry.
	 *
	 * @param DocumentRecord $records Document storage.
	 * @param int $post_id Existing document record.
	 * @param array<string, mixed> $args Validated send arguments.
	 * @return int|WP_Error Record id or a refusal to retarget its source.
	 */
	public static function bind( DocumentRecord $records, int $post_id, array $args ): int|WP_Error {
		if ( isset( $args['source'] ) && ! $records->set_source( $post_id, $args['source'] ) ) {
			return self::conflict( $post_id );
		}

		return $post_id;
	}

	/**
	 * The shared refusal when an origin cannot be safely preserved.
	 *
	 * @param int $post_id Local document record.
	 */
	private static function conflict( int $post_id ): WP_Error {
		return new WP_Error(
			'assinafy_source_conflict',
			__( 'The integration source could not be saved or differs from the original request. Reuse the original source and idempotency key.', 'assinafy' ),
			array( 'post_id' => $post_id )
		);
	}
}
