<?php
/**
 * The site-wide lock around OAuth token rotation and revocation.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP;

defined( 'ABSPATH' ) || exit;

use WP_Error;

/**
 * Lets one request at a time refresh or revoke the OAuth grant, and keeps a refresh token that
 * may already be spent from being sent again.
 *
 * The lock is an options row taken with INSERT IGNORE, as in core's WP_Upgrader::create_lock()
 * (add_option() upserts, so two requests could both win), and freed by compare-and-delete. Its
 * value is a lease expiry and a nonce, so only its holder frees it and a crashed holder's lease
 * can be recovered. Before a refresh token leaves, the holder appends the token's hash. A lease
 * that ran out while that mark still matches the stored token means the token may be retired,
 * and Assinafy ends the whole connection when a retired token comes back: the connection is
 * dropped instead, and never resent.
 */
final class RefreshLock {

	/** How long a holder is trusted before another request may recover the lock. */
	public const LEASE_SECONDS = 45;

	/** This request's lock value: lease expiry, nonce, then the mark once a token is sent. */
	private string $value = '';

	/** Whether release() must leave the mark because its token could not be dropped. */
	private bool $kept = false;

	/** @param Credentials $credentials Encrypted credential store. */
	public function __construct( private readonly Credentials $credentials ) {
	}

	/**
	 * Take the lock, recovering one whose lease ran out.
	 *
	 * @return bool|WP_Error False while another request holds it; an error when the expired
	 *                       holder may have spent the stored refresh token, which is dropped.
	 */
	public function claim(): bool|WP_Error {
		$this->value = ( time() + self::LEASE_SECONDS ) . ':' . bin2hex( random_bytes( 16 ) );
		if ( $this->insert() ) {
			return true;
		}

		global $wpdb;
		$stored = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", OAuthTokens::OPTION_REFRESH_LOCK ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The lock never goes through the options cache.
		if ( ! is_string( $stored ) || (int) strtok( $stored, ':' ) >= time() ) {
			return $this->insert();
		}

		// The holder marked the lock with the token it sent, then died or is still waiting on
		// Assinafy. While that token is stored, nobody can know whether it was rotated: drop the
		// connection rather than send it again. The mark stays until the token is gone.
		$snapshot = $this->credentials->fresh_oauth_connection_snapshot();
		if ( is_array( $snapshot ) && str_ends_with( $stored, ':' . hash( 'sha256', $snapshot['connection']['refresh_token'] ) ) ) {
			if ( $this->credentials->clear_oauth_connection( $snapshot['ciphertext'] ) ) {
				self::delete( $stored );
			}

			return new WP_Error( 'assinafy_oauth_reconnect', __( 'Assinafy needs to be reconnected.', 'assinafy' ) );
		}
		self::delete( $stored );

		return $this->insert();
	}

	/**
	 * Mark the lock with the refresh token about to be sent.
	 *
	 * @param string $refresh_token Token the next request sends.
	 * @return bool False when the lease ran out and another request took the lock.
	 */
	public function mark( #[\SensitiveParameter] string $refresh_token ): bool {
		global $wpdb;
		$marked = $this->value . ':' . hash( 'sha256', $refresh_token );
		if ( 1 !== $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $marked, OAuthTokens::OPTION_REFRESH_LOCK, $this->value ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Compare-and-swap keeps the lock this request's own.
			return false;
		}
		$this->value = $marked;

		return true;
	}

	/**
	 * Drop the connection whose marked token may be spent. While it cannot be dropped, the mark
	 * stays, so no other request sends that token.
	 *
	 * @param string $ciphertext Encrypted connection the marked token came from.
	 */
	public function drop( string $ciphertext ): void {
		$this->kept = ! $this->credentials->clear_oauth_connection( $ciphertext );
	}

	/** Free the lock, and any mark with it, unless the marked token could not be dropped. */
	public function release(): void {
		if ( ! $this->kept ) {
			self::delete( $this->value );
		}
	}

	/** Take the lock only when no other request holds it. */
	private function insert(): bool {
		global $wpdb;

		return 1 === $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", OAuthTokens::OPTION_REFRESH_LOCK, $this->value ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic insert-if-absent is the lock.
	}

	/**
	 * Delete the lock only while it still holds this value, so no request frees another's lock.
	 *
	 * @param string $value Lock value expected in the row.
	 */
	private static function delete( string $value ): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", OAuthTokens::OPTION_REFRESH_LOCK, $value ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Compare-and-delete is required for a rotating-token lock.
		wp_cache_delete( OAuthTokens::OPTION_REFRESH_LOCK, 'options' );
	}
}
