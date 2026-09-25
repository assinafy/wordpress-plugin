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
	 * There is no local connection deadline: every refresh returns a refresh token valid for a
	 * new 30 days, and Assinafy answers `invalid_grant` once one has gone unused for 30 days.
	 *
	 * @param string|null $rejected Access token the API just answered 401 for. It is refreshed
	 *                              once, unless another request has already replaced it.
	 * @return string|WP_Error Empty string when no OAuth connection exists.
	 */
	public function access_token( #[\SensitiveParameter] ?string $rejected = null ): string|WP_Error {
		$connection = $this->credentials->oauth_connection();
		if ( ! is_array( $connection ) ) {
			return $connection ?? '';
		}
		$usable = $rejected !== $connection['access_token'];
		if ( $usable && $connection['expires_at'] > time() + 120 ) {
			return $connection['access_token'];
		}

		$lock    = new RefreshLock( $this->credentials );
		$claimed = $lock->claim();
		if ( $claimed instanceof WP_Error ) {
			return $claimed;
		}
		if ( ! $claimed ) {
			return $usable && $connection['expires_at'] > time() + 15 ? $connection['access_token'] : self::busy();
		}

		try {
			return $this->refresh_under_lock( $rejected, $lock );
		} finally {
			$lock->release();
		}
	}

	/**
	 * Re-read after locking so a concurrent request cannot reuse a rotated refresh token.
	 *
	 * @param string|null $rejected Access token the API answered 401 for, if any.
	 * @param RefreshLock $lock     The lock this request holds.
	 */
	private function refresh_under_lock( #[\SensitiveParameter] ?string $rejected, RefreshLock $lock ): string|WP_Error {
		$snapshot = $this->credentials->fresh_oauth_connection_snapshot();
		if ( ! is_array( $snapshot ) ) {
			return $snapshot ?? '';
		}
		$latest = $snapshot['connection'];
		if ( $rejected !== $latest['access_token'] && $latest['expires_at'] > time() + 120 ) {
			return $latest['access_token'];
		}
		// Mark the lock before the token leaves: a request that finds it expired never resends it.
		if ( ! $lock->mark( $latest['refresh_token'] ) ) {
			return self::busy();
		}

		try {
			$tokens  = $this->oauth()->refresh( $latest['refresh_token'] );
			$updated = self::updated_tokens( $latest, $tokens );
			if ( ! $this->credentials->replace_oauth_connection( $snapshot['ciphertext'], $updated ) ) {
				return new WP_Error( 'assinafy_oauth_changed', __( 'Assinafy was reconnected during renewal. Try again.', 'assinafy' ) );
			}

			return $updated['access_token'];
		} catch ( \Throwable $e ) {
			// Releasing the lock removes the mark, so a token that never left can be retried.
			if ( self::never_sent( $e ) ) {
				return new WP_Error( 'assinafy_oauth_retry', __( 'Assinafy could not be reached. Try again shortly.', 'assinafy' ) );
			}

			// A timed-out refresh may have rotated the token. Never replay the old one.
			$lock->drop( $snapshot['ciphertext'] );

			return self::reconnect();
		}
	}

	/**
	 * Only a failure proven to come before the request left, such as DNS, a refused connection
	 * or a TLS handshake, keeps a refresh token that may be sent again.
	 *
	 * @param \Throwable $e Refresh failure.
	 */
	private static function never_sent( \Throwable $e ): bool {
		return $e instanceof NetworkException && false === ( $e->getContext()['request_sent'] ?? null );
	}

	/** Another request is refreshing or disconnecting. */
	private static function busy(): WP_Error {
		return new WP_Error( 'assinafy_oauth_refresh_busy', __( 'Assinafy is renewing the connection. Try again shortly.', 'assinafy' ) );
	}

	/** The refresh token may be spent, so only a new authorization can continue. */
	private static function reconnect(): WP_Error {
		return new WP_Error( 'assinafy_oauth_reconnect', __( 'Assinafy needs to be reconnected.', 'assinafy' ) );
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
}
