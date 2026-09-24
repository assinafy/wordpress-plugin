<?php
/**
 * Assinafy OAuth token exchange, storage, and rotation.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP;

defined( 'ABSPATH' ) || exit;

use Assinafy\SDK\AssinafyClient;
use Assinafy\SDK\Configuration;
use Assinafy\SDK\Exceptions\NetworkException;
use Assinafy\SDK\Resources\OAuthResource;
use Assinafy\WP\Http\WpHttpClient;
use WP_Error;

/** Keeps an OAuth connection current and protects rotating refresh tokens. */
final class OAuthTokens {

	public const OPTION_REFRESH_LOCK = 'assinafy_oauth_refresh_lock';

	/** @param Credentials $credentials Encrypted credential store. */
	public function __construct( private readonly Credentials $credentials ) {
	}

	/**
	 * A current Bearer token, refreshing under a database lock when needed.
	 *
	 * @return string|WP_Error Empty string when no OAuth connection exists.
	 */
	public function access_token(): string|WP_Error {
		$connection = $this->credentials->oauth_connection();
		if ( ! is_array( $connection ) ) {
			return $connection ?? '';
		}
		if ( time() >= $connection['connected_at'] + 30 * 86400 ) {
			return new WP_Error( 'assinafy_oauth_expired', __( 'Reconnect Assinafy to continue.', 'assinafy' ) );
		}
		if ( $connection['expires_at'] > time() + 120 ) {
			return $connection['access_token'];
		}

		$owner = ( time() + 45 ) . ':' . bin2hex( random_bytes( 16 ) );
		if ( ! $this->claim_lock( $owner ) ) {
			return $connection['expires_at'] > time() + 15
				? $connection['access_token']
				: new WP_Error( 'assinafy_oauth_refresh_busy', __( 'Assinafy is renewing the connection. Try again shortly.', 'assinafy' ) );
		}

		try {
			return $this->refresh_under_lock();
		} finally {
			if ( $owner === get_option( self::OPTION_REFRESH_LOCK ) ) {
				delete_option( self::OPTION_REFRESH_LOCK );
			}
		}
	}

	/** Claim one site-wide refresh lock, recovering a crashed request's expired lock. */
	private function claim_lock( string $owner ): bool {
		if ( add_option( self::OPTION_REFRESH_LOCK, $owner, '', false ) ) {
			return true;
		}
		$this->remove_stale_lock();

		return add_option( self::OPTION_REFRESH_LOCK, $owner, '', false );
	}

	/** Re-read after locking so a concurrent request cannot reuse a rotated refresh token. */
	private function refresh_under_lock(): string|WP_Error {
		$snapshot = $this->credentials->oauth_connection_snapshot();
		if ( ! is_array( $snapshot ) ) {
			return $snapshot ?? '';
		}
		$latest = $snapshot['connection'];
		if ( $latest['expires_at'] > time() + 120 ) {
			return $latest['access_token'];
		}

		try {
			$tokens  = $this->oauth()->refresh( $latest['refresh_token'] );
			$updated = self::updated_tokens( $latest, $tokens );
			if ( ! $this->replace_if_unchanged( $snapshot['ciphertext'], $updated ) ) {
				return new WP_Error( 'assinafy_oauth_changed', __( 'Assinafy was reconnected during renewal. Try again.', 'assinafy' ) );
			}

			return $updated['access_token'];
		} catch ( \Throwable $e ) {
			if ( $e instanceof NetworkException && false === ( $e->getContext()['request_sent'] ?? null ) ) {
				return new WP_Error( 'assinafy_oauth_retry', __( 'Assinafy could not be reached. Try again shortly.', 'assinafy' ) );
			}

			// A timed-out refresh may have rotated the token. Never replay the old one.
			$this->discard_if_unchanged( $snapshot['ciphertext'] );

			return new WP_Error( 'assinafy_oauth_reconnect', __( 'Assinafy needs to be reconnected.', 'assinafy' ) );
		}
	}

	/**
	 * Persist rotated tokens only while the exact grant used to refresh still exists.
	 *
	 * @param string $expected_ciphertext Encrypted connection read before the remote refresh.
	 * @param array{account_id: string, access_token: string, refresh_token: string, expires_at: int, connected_at: int, scope: string} $connection Rotated connection.
	 */
	private function replace_if_unchanged( string $expected_ciphertext, array $connection ): bool {
		$json = wp_json_encode( $connection );
		if ( false === $json ) {
			throw new \RuntimeException( 'Assinafy OAuth connection could not be encoded' );
		}

		global $wpdb;
		$changed = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Conditional write prevents refresh from restoring a replaced grant.
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
				$this->credentials->encrypt( $json ),
				Credentials::OPTION_OAUTH_CONNECTION,
				$expected_ciphertext
			)
		);
		if ( false === $changed ) {
			throw new \RuntimeException( 'Assinafy OAuth connection could not be stored' );
		}
		if ( 1 !== $changed ) {
			return false;
		}

		wp_cache_delete( Credentials::OPTION_OAUTH_CONNECTION, 'options' );

		return true;
	}

	/** Discard an uncertain refresh token without deleting a concurrent reconnection. */
	private function discard_if_unchanged( string $expected_ciphertext ): void {
		global $wpdb;
		$deleted = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Conditional delete cannot erase a new grant.
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
				Credentials::OPTION_OAUTH_CONNECTION,
				$expected_ciphertext
			)
		);
		if ( 1 === $deleted ) {
			wp_cache_delete( Credentials::OPTION_OAUTH_CONNECTION, 'options' );
		}
	}

	/** @return OAuthResource The SDK flow using WordPress HTTP, never Guzzle. */
	public function oauth(): OAuthResource {
		if ( '' === OAuthConnection::client_id() ) {
			throw new \RuntimeException( 'Assinafy WordPress OAuth client is not registered' );
		}

		$config = Configuration::forPublic();
		$client = new AssinafyClient( $config, new WpHttpClient( $config ) );

		return $client->oauth( OAuthConnection::client_id() );
	}

	/** @param array<string, mixed> $tokens Validated exchange result. */
	public function save_tokens( array $tokens ): void {
		$access  = $tokens['access_token'] ?? null;
		$refresh = $tokens['refresh_token'] ?? null;
		$expiry  = $tokens['expires_in'] ?? null;
		$scope   = $tokens['scope'] ?? null;
		if ( ! self::valid_token_response( $tokens ) || ! is_string( $scope ) ) {
			throw new \RuntimeException( 'Assinafy returned incomplete OAuth credentials' );
		}
		self::require_scopes( $scope );
		/** @var string $access */
		/** @var string $refresh */
		/** @var int $expiry */

		$config     = Configuration::forPublic();
		$client     = new AssinafyClient( $config, new WpHttpClient( $config ) );
		$accounts   = $client->accounts()->list( $access );
		$account_id = $accounts['data'][0]['id'] ?? null;
		if ( ! is_string( $account_id ) || '' === $account_id ) {
			throw new \RuntimeException( 'Assinafy did not return an authorized workspace' );
		}

		$this->credentials->set_oauth_connection(
			array(
				'account_id'    => $account_id,
				'access_token'  => $access,
				'refresh_token' => $refresh,
				'expires_at'    => time() + $expiry,
				'connected_at'  => time(),
				'scope'         => $scope,
			)
		);
	}

	/**
	 * @param array{account_id: string, access_token: string, refresh_token: string, expires_at: int, connected_at: int, scope: string} $current Stored connection.
	 * @param array<string, mixed> $tokens New token response.
	 * @return array{account_id: string, access_token: string, refresh_token: string, expires_at: int, connected_at: int, scope: string}
	 */
	private static function updated_tokens( array $current, array $tokens ): array {
		if ( ! self::valid_token_response( $tokens ) ) {
			throw new \RuntimeException( 'Assinafy returned incomplete refreshed tokens' );
		}
		/** @var array{access_token: string, refresh_token: string, expires_in: int, scope?: string} $tokens */
		self::require_scopes( is_string( $tokens['scope'] ?? null ) ? $tokens['scope'] : $current['scope'] );

		$current['access_token']  = $tokens['access_token'];
		$current['refresh_token'] = $tokens['refresh_token'];
		$current['expires_at']    = time() + $tokens['expires_in'];
		$current['scope']         = is_string( $tokens['scope'] ?? null ) ? $tokens['scope'] : $current['scope'];

		return $current;
	}

	/** @param array<string, mixed> $tokens OAuth token endpoint response. */
	private static function valid_token_response( array $tokens ): bool {
		foreach ( array( 'access_token', 'refresh_token' ) as $field ) {
			if ( ! is_string( $tokens[ $field ] ?? null ) || '' === $tokens[ $field ] ) {
				return false;
			}
		}

		return is_int( $tokens['expires_in'] ?? null ) && $tokens['expires_in'] > 0
			&& is_string( $tokens['token_type'] ?? null ) && 'bearer' === strtolower( $tokens['token_type'] );
	}

	/** @param string $scope Granted access-token scopes, without offline_access. */
	private static function require_scopes( string $scope ): void {
		$required = array( 'account:read', 'documents:read', 'documents:write', 'webhooks:write' );
		if ( array_diff( $required, explode( ' ', trim( $scope ) ) ) ) {
			throw new \RuntimeException( 'Assinafy did not grant all WordPress permissions' );
		}
	}

	/** Remove a crashed request's lock only if its stored value still matches. */
	private function remove_stale_lock(): void {
		$stored = get_option( self::OPTION_REFRESH_LOCK );
		if ( ! is_string( $stored ) || (int) strtok( $stored, ':' ) >= time() ) {
			return;
		}

		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", self::OPTION_REFRESH_LOCK, $stored ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Compare-and-delete is required for a rotating-token lock.
		wp_cache_delete( self::OPTION_REFRESH_LOCK, 'options' );
	}
}
