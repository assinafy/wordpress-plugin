<?php
/**
 * Serialization and ordering for document projection writes.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Documents;

defined( 'ABSPATH' ) || exit;

/** Keeps privacy redaction and remote refreshes from overwriting one another. */
final class ProjectionLock {

	private const META_REMOTE_UPDATED_AT = '_assinafy_remote_updated_at';
	private const LOCK_PREFIX            = 'assinafy_document_projection_';
	private const LOCK_TTL               = 30;
	private const LOCK_WAIT_MICROSECONDS = 5000000;

	/** @var array<int, true> Re-entrant locks held by this request. */
	private static array $held = array();

	/** Save one projection field and verify the requested value is present. */
	public static function store( int $post_id, string $key, mixed $value ): void {
		update_post_meta( $post_id, $key, $value );
		$expected = wp_unslash( $value );
		$stored   = get_post_meta( $post_id, $key, true );
		$matches  = is_array( $expected ) ? $expected === $stored : (string) $expected === (string) $stored;
		if ( ! $matches ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The eventual UI consumer escapes this translated failure.
			throw new \RuntimeException( __( 'The local document projection could not be saved.', 'assinafy' ) );
		}
	}

	/** Keep the local title on the API's normalized document name. */
	public static function rename( int $post_id, string $name ): void {
		if ( '' === $name || $name === get_post_field( 'post_title', $post_id ) ) {
			return;
		}

		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => $name,
			),
			true
		);
		if ( $name !== get_post_field( 'post_title', $post_id ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The eventual UI consumer escapes this translated failure.
			throw new \RuntimeException( __( 'The local document name could not be saved.', 'assinafy' ) );
		}
	}

	/**
	 * Serialize an arbitrary projection mutation.
	 *
	 * @template T
	 * @param \Closure(): T $callback Mutation to serialize.
	 * @return T
	 */
	public static function run( int $post_id, \Closure $callback ): mixed {
		if ( isset( self::$held[ $post_id ] ) ) {
			return $callback();
		}

		if ( ! class_exists( \WP_Upgrader::class ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}

		$lock     = self::LOCK_PREFIX . $post_id;
		$deadline = microtime( true ) + ( self::LOCK_WAIT_MICROSECONDS / 1000000 );
		do {
			$acquired = \WP_Upgrader::create_lock( $lock, self::LOCK_TTL );
			if ( ! $acquired ) {
				usleep( 50000 );
			}
		} while ( ! $acquired && microtime( true ) < $deadline );

		if ( ! $acquired ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The eventual UI consumer escapes this translated failure.
			throw new \RuntimeException( __( 'This document is being updated by another request. Try again.', 'assinafy' ) );
		}

		self::$held[ $post_id ] = true;
		try {
			return $callback();
		} finally {
			unset( self::$held[ $post_id ] );
			\WP_Upgrader::release_lock( $lock );
		}
	}

	/** Apply one API response unless a newer response already won. */
	public static function project( int $post_id, string $updated_at, string $previous, \Closure $callback ): string {
		return self::run(
			$post_id,
			static function () use ( $post_id, $updated_at, $previous, $callback ): string {
				$stored = self::value( $post_id );
				if ( self::is_older( $updated_at, $stored ) ) {
					return $previous;
				}
				if ( '' !== $updated_at ) {
					update_post_meta( $post_id, self::META_REMOTE_UPDATED_AT, $updated_at );
					if ( $updated_at !== self::value( $post_id ) ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The eventual UI consumer escapes this translated failure.
						throw new \RuntimeException( __( 'The local document projection could not be saved.', 'assinafy' ) );
					}
				}

				return $callback();
			}
		);
	}

	private static function is_older( string $incoming, string $stored ): bool {
		if ( '' === $incoming || '' === $stored ) {
			return false;
		}

		$incoming_time = strtotime( $incoming );
		$stored_time   = strtotime( $stored );

		return false !== $incoming_time && false !== $stored_time && $incoming_time < $stored_time;
	}

	private static function value( int $post_id ): string {
		$value = get_post_meta( $post_id, self::META_REMOTE_UPDATED_AT, true );

		return is_string( $value ) ? $value : '';
	}
}
