<?php
/**
 * OAuth token storage and rotation.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Unit;

use Assinafy\SDK\Exceptions\ApiException;
use Assinafy\SDK\Resources\OAuthResource;
use Assinafy\WP\ClientFactory;
use Assinafy\WP\Credentials;
use Assinafy\WP\Log;
use Assinafy\WP\OAuthConnection;
use Assinafy\WP\OAuthTokens;
use Assinafy\WP\Settings;
use Assinafy\WP\Tests\Unit\Support\FakeHttp;
use PHPUnit\Framework\TestCase;
use WP_Error;

defined( 'ASSINAFY_OAUTH_CLIENT_ID' ) || define( 'ASSINAFY_OAUTH_CLIENT_ID', 'synthetic-wordpress-client' );

/**
 * @covers \Assinafy\WP\OAuthTokens
 * @covers \Assinafy\WP\RefreshLock
 */
final class OAuthTokensTest extends TestCase {

	private const ROTATED = '{"access_token":"access-new","refresh_token":"refresh-new","expires_in":3600,"token_type":"Bearer","scope":"account:read documents:read documents:write webhooks:write"}';

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['assinafy_test_options'] = array();
		unset( $GLOBALS['assinafy_test_proxy'], $GLOBALS['assinafy_test_curl'] );
		FakeHttp::reset();
	}

	/** OAuth takes precedence over an old API key and stores tokens encrypted. */
	public function test_a_connection_uses_bearer_authentication(): void {
		$credentials = new Credentials();
		$credentials->set_api_key( 'old-static-key' );
		$credentials->set_oauth_connection( self::connection( time() + 3600 ) );

		$client = ( new ClientFactory( $credentials, new Log() ) )->client();

		$this->assertNotNull( $client );
		$this->assertTrue( $client->getConfig()->isBearerAuthenticated() );
		$this->assertSame( 'workspace-1', $client->getConfig()->getAccountId() );
		$this->assertSame( '', $client->getConfig()->getApiKey() );
		$this->assertStringNotContainsString( 'access-old', (string) get_option( Credentials::OPTION_OAUTH_CONNECTION ) );
	}

	/** Sandbox continues to use its own API key after production OAuth is connected. */
	public function test_sandbox_uses_legacy_credentials_with_a_production_oauth_connection(): void {
		$credentials = new Credentials();
		$credentials->set_api_key( 'sandbox-key' );
		$credentials->set_oauth_connection( self::connection( time() + 3600 ) );
		update_option( Credentials::OPTION_ACCOUNT_ID, 'sandbox-workspace' );
		update_option( Settings::OPTION_ENVIRONMENT, 'sandbox' );

		$client = ( new ClientFactory( $credentials, new Log() ) )->client();

		$this->assertNotNull( $client );
		$this->assertFalse( $client->getConfig()->isBearerAuthenticated() );
		$this->assertSame( 'sandbox-key', $client->getConfig()->getApiKey() );
		$this->assertSame( 'sandbox-workspace', $client->getConfig()->getAccountId() );
	}

	/** The public code exchange uses PKCE, discovers the granted workspace, and saves no plaintext tokens. */
	public function test_initial_code_exchange_saves_the_authorized_workspace(): void {
		$credentials = new Credentials();
		$manager     = new OAuthTokens( $credentials );
		$oauth       = $manager->oauth();
		$transaction = $oauth->startAuthorization(
			OAuthConnection::REDIRECT_URI,
			array(
				OAuthResource::SCOPE_ACCOUNT_READ,
				OAuthResource::SCOPE_DOCUMENTS_READ,
				OAuthResource::SCOPE_DOCUMENTS_WRITE,
				OAuthResource::SCOPE_WEBHOOKS_WRITE,
				OAuthResource::SCOPE_OFFLINE_ACCESS,
			)
		);

		$this->assertSame( OAuthConnection::REDIRECT_URI, $transaction['redirect_uri'] );
		$this->assertSame( 'https://auth.assinafy.com.br', $transaction['issuer'] );
		FakeHttp::queue( 200, '{"access_token":"synthetic-access","refresh_token":"synthetic-refresh","expires_in":3600,"token_type":"Bearer","scope":"account:read documents:read documents:write webhooks:write"}' );
		$tokens = $oauth->exchangeCode( 'synthetic-code', $transaction );
		$this->assertStringContainsString( 'grant_type=authorization_code', FakeHttp::$requests[0]['args']['body'] );
		$this->assertStringContainsString( 'code_verifier=' . $transaction['code_verifier'], FakeHttp::$requests[0]['args']['body'] );
		$this->assertStringContainsString( 'redirect_uri=' . rawurlencode( OAuthConnection::REDIRECT_URI ), FakeHttp::$requests[0]['args']['body'] );
		$this->assertArrayNotHasKey( 'authorization', FakeHttp::last_headers() );

		FakeHttp::queue( 200, '{"status":200,"message":"","data":[{"id":"workspace-1"}]}' );
		$manager->save_tokens( $tokens );
		$stored = $credentials->oauth_connection();
		$this->assertIsArray( $stored );
		$this->assertSame( 'workspace-1', $stored['account_id'] );
		$this->assertSame( 'synthetic-refresh', $stored['refresh_token'] );
		$this->assertStringNotContainsString( 'synthetic-refresh', (string) get_option( Credentials::OPTION_OAUTH_CONNECTION ) );
		$this->assertSame( 'oauth', get_option( Credentials::OPTION_AUTH_MODE ) );
	}

	/** Refresh replaces both tokens in one encrypted option and never sends the old token as a key. */
	public function test_refresh_rotates_the_refresh_token_once(): void {
		$credentials = new Credentials();
		$credentials->set_oauth_connection( self::connection( time() - 1 ) );
		FakeHttp::queue( 200, '{"access_token":"access-new","refresh_token":"refresh-new","expires_in":3600,"token_type":"Bearer","scope":"account:read documents:read documents:write webhooks:write"}' );

		$this->assertSame( 'access-new', ( new OAuthTokens( $credentials ) )->access_token() );
		$stored = $credentials->oauth_connection();
		$this->assertIsArray( $stored );
		$this->assertSame( 'refresh-new', $stored['refresh_token'] );
		$this->assertSame( 'workspace-1', $stored['account_id'] );
		$this->assertCount( 1, FakeHttp::$requests );
		$this->assertStringEndsWith( '/v1/oauth/token', FakeHttp::$requests[0]['url'] );
		$this->assertStringContainsString( 'grant_type=refresh_token', FakeHttp::$requests[0]['args']['body'] );
		$this->assertStringContainsString( 'refresh_token=refresh-old', FakeHttp::$requests[0]['args']['body'] );
		$this->assertArrayNotHasKey( 'authorization', FakeHttp::last_headers() );
		$this->assertArrayNotHasKey( 'x-api-key', FakeHttp::last_headers() );
	}

	/** A timeout may have consumed a rotating token, so no second call may replay it. */
	public function test_a_failed_refresh_requires_reconnection_without_replaying_the_old_token(): void {
		$credentials = new Credentials();
		$credentials->set_oauth_connection( self::connection( time() - 1 ) );
		FakeHttp::queue_error( 'http_request_failed', 'timeout' );

		$result = ( new OAuthTokens( $credentials ) )->access_token();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'assinafy_oauth_reconnect', $result->get_error_code() );
		$this->assertNull( $credentials->oauth_connection() );
		$this->assertCount( 1, FakeHttp::$requests );
		$this->assertSame( '', ( new OAuthTokens( $credentials ) )->access_token() );
		$this->assertCount( 1, FakeHttp::$requests );
	}

	/** A failure before send leaves the rotating token available for a later retry. */
	public function test_an_unsent_refresh_keeps_the_connection(): void {
		$credentials = new Credentials();
		$credentials->set_oauth_connection( self::connection( time() - 1 ) );
		FakeHttp::queue_error( 'http_request_failed', 'cURL error 6: Could not resolve host' );

		$result = ( new OAuthTokens( $credentials ) )->access_token();
		$stored = $credentials->oauth_connection();
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'assinafy_oauth_retry', $result->get_error_code() );
		$this->assertIsArray( $stored );
		$this->assertSame( 'refresh-old', $stored['refresh_token'] );

		FakeHttp::queue( 200, '{"access_token":"access-new","refresh_token":"refresh-new","expires_in":3600,"token_type":"Bearer","scope":"account:read documents:read documents:write webhooks:write"}' );
		$this->assertSame( 'access-new', ( new OAuthTokens( $credentials ) )->access_token() );
	}

	/** A second request cannot send the same rotating refresh token. */
	public function test_a_busy_refresh_lock_never_replays_an_expired_token(): void {
		$credentials = new Credentials();
		$credentials->set_oauth_connection( self::connection( time() - 1 ) );
		update_option( OAuthTokens::OPTION_REFRESH_LOCK, ( time() + 45 ) . ':another-request' );

		$result = ( new OAuthTokens( $credentials ) )->access_token();
		$stored = $credentials->oauth_connection();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'assinafy_oauth_refresh_busy', $result->get_error_code() );
		$this->assertIsArray( $stored );
		$this->assertSame( 'refresh-old', $stored['refresh_token'] );
		$this->assertSame( array(), FakeHttp::$requests );
	}

	/** A refresh response cannot restore a connection removed during its HTTP call. */
	public function test_a_connection_removed_during_refresh_cannot_be_restored(): void {
		$credentials = new Credentials();
		$credentials->set_oauth_connection( self::connection( time() - 1 ) );
		FakeHttp::queue( 200, '{"access_token":"access-new","refresh_token":"refresh-new","expires_in":3600,"token_type":"Bearer","scope":"account:read documents:read documents:write webhooks:write"}' );
		FakeHttp::$before_response = static function () use ( $credentials ): void {
			$credentials->clear_oauth_connection( (string) get_option( Credentials::OPTION_OAUTH_CONNECTION ) );
		};

		$result = ( new OAuthTokens( $credentials ) )->access_token();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'assinafy_oauth_changed', $result->get_error_code() );
		$this->assertNull( $credentials->oauth_connection() );
	}

	/** A failed old refresh cannot clear a newer authorization. */
	public function test_failed_refresh_cannot_delete_a_concurrent_reconnection(): void {
		$credentials = new Credentials();
		$credentials->set_oauth_connection( self::connection( time() - 1 ) );
		FakeHttp::queue_error( 'http_request_failed', 'timeout' );
		FakeHttp::$before_response = static function () use ( $credentials ): void {
			$new                  = self::connection( time() + 3600 );
			$new['refresh_token'] = 'new-authorization';
			$credentials->set_oauth_connection( $new );
		};

		$result = ( new OAuthTokens( $credentials ) )->access_token();
		$stored = $credentials->oauth_connection();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertIsArray( $stored );
		$this->assertSame( 'new-authorization', $stored['refresh_token'] );
	}

	/** Each refresh starts a new 30 days, so the approval date alone never forces a reconnect. */
	public function test_a_connection_approved_over_30_days_ago_keeps_refreshing(): void {
		$credentials                = new Credentials();
		$connection                 = self::connection( time() - 1 );
		$connection['connected_at'] = time() - 31 * 86400;
		$credentials->set_oauth_connection( $connection );
		FakeHttp::queue( 200, self::ROTATED );

		$this->assertSame( 'access-new', ( new OAuthTokens( $credentials ) )->access_token() );
		$this->assertCount( 1, FakeHttp::$requests );
	}

	/** A crashed request's expired lock is recovered, and the new holder releases it. */
	public function test_an_expired_lock_is_recovered_and_released(): void {
		$credentials = new Credentials();
		$credentials->set_oauth_connection( self::connection( time() - 1 ) );
		update_option( OAuthTokens::OPTION_REFRESH_LOCK, ( time() - 1 ) . ':crashed-request' );
		FakeHttp::queue( 200, self::ROTATED );

		$this->assertSame( 'access-new', ( new OAuthTokens( $credentials ) )->access_token() );
		$this->assertFalse( get_option( OAuthTokens::OPTION_REFRESH_LOCK ) );
	}

	/** The lock names the token before it leaves, so a request finding the lock expired cannot resend it. */
	public function test_the_lock_is_marked_with_the_refresh_token_before_it_is_sent(): void {
		$credentials = new Credentials();
		$credentials->set_oauth_connection( self::connection( time() - 1 ) );
		FakeHttp::queue( 200, self::ROTATED );
		$lock                      = null;
		FakeHttp::$before_response = static function () use ( &$lock ): void {
			$lock = get_option( OAuthTokens::OPTION_REFRESH_LOCK );
		};

		$this->assertSame( 'access-new', ( new OAuthTokens( $credentials ) )->access_token() );
		$this->assertIsString( $lock );
		$this->assertStringEndsWith( ':' . hash( 'sha256', 'refresh-old' ), $lock );
		$this->assertFalse( get_option( OAuthTokens::OPTION_REFRESH_LOCK ) );
	}

	/** A holder that died after sending leaves its mark: that token may be spent, so it is never resent. */
	public function test_an_expired_lock_marked_with_the_stored_token_asks_to_reconnect(): void {
		$credentials = new Credentials();
		$credentials->set_oauth_connection( self::connection( time() - 1 ) );
		update_option( OAuthTokens::OPTION_REFRESH_LOCK, ( time() - 1 ) . ':crashed-request:' . hash( 'sha256', 'refresh-old' ) );

		$result = ( new OAuthTokens( $credentials ) )->access_token();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'assinafy_oauth_reconnect', $result->get_error_code() );
		$this->assertSame( array(), FakeHttp::$requests, 'A possibly spent refresh token was sent again.' );
		$this->assertNull( $credentials->oauth_connection() );
		$this->assertFalse( get_option( OAuthTokens::OPTION_REFRESH_LOCK ) );
	}

	/** A refresh still waiting on Assinafy when its lease runs out is never joined by a second one. */
	public function test_a_refresh_outliving_its_lease_is_never_sent_twice(): void {
		$credentials = new Credentials();
		$credentials->set_oauth_connection( self::connection( time() - 1 ) );
		FakeHttp::queue( 200, self::ROTATED );
		$second                    = null;
		FakeHttp::$before_response = static function () use ( $credentials, &$second ): void {
			FakeHttp::$before_response = null;
			$lock                      = (string) get_option( OAuthTokens::OPTION_REFRESH_LOCK );
			update_option( OAuthTokens::OPTION_REFRESH_LOCK, preg_replace( '/^\d+/', (string) ( time() - 1 ), $lock ) );
			$second = ( new OAuthTokens( $credentials ) )->access_token();
		};

		$first = ( new OAuthTokens( $credentials ) )->access_token();

		$this->assertCount( 1, FakeHttp::$requests, 'The same refresh token was sent twice.' );
		$this->assertInstanceOf( WP_Error::class, $second );
		$this->assertSame( 'assinafy_oauth_reconnect', $second->get_error_code() );
		$this->assertInstanceOf( WP_Error::class, $first );
		$this->assertSame( 'assinafy_oauth_changed', $first->get_error_code() );
	}

	/** A mark for a token no longer stored means that refresh was saved, so the lock is recovered. */
	public function test_an_expired_lock_marked_with_a_replaced_token_is_recovered(): void {
		$credentials = new Credentials();
		$credentials->set_oauth_connection( self::connection( time() - 1 ) );
		update_option( OAuthTokens::OPTION_REFRESH_LOCK, ( time() - 1 ) . ':finished-request:' . hash( 'sha256', 'refresh-older' ) );
		FakeHttp::queue( 200, self::ROTATED );

		$this->assertSame( 'access-new', ( new OAuthTokens( $credentials ) )->access_token() );
		$this->assertCount( 1, FakeHttp::$requests );
	}

	/** A refresh refused before any socket opens, as behind a proxy without cURL, keeps its token. */
	public function test_a_refresh_refused_by_the_proxy_guard_keeps_the_connection(): void {
		$credentials = new Credentials();
		$credentials->set_oauth_connection( self::connection( time() - 1 ) );
		$GLOBALS['assinafy_test_proxy'] = true;
		$GLOBALS['assinafy_test_curl']  = false;

		$result = ( new OAuthTokens( $credentials ) )->access_token();
		$stored = $credentials->oauth_connection();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'assinafy_oauth_retry', $result->get_error_code() );
		$this->assertSame( array(), FakeHttp::$requests );
		$this->assertIsArray( $stored );
		$this->assertSame( 'refresh-old', $stored['refresh_token'] );
		$this->assertFalse( get_option( OAuthTokens::OPTION_REFRESH_LOCK ) );
	}

	/** Disconnecting mid-refresh would revoke the token being retired and orphan its replacement. */
	public function test_disconnect_during_a_refresh_waits_then_revokes_the_new_token(): void {
		$credentials = new Credentials();
		$credentials->set_oauth_connection( self::connection( time() - 1 ) );
		FakeHttp::queue( 200, self::ROTATED );
		$during                    = null;
		FakeHttp::$before_response = static function () use ( $credentials, &$during ): void {
			FakeHttp::$before_response = null;
			$during                    = ( new OAuthConnection( $credentials ) )->disconnect_current();
		};

		$this->assertSame( 'access-new', ( new OAuthTokens( $credentials ) )->access_token() );
		$this->assertSame( 'busy', $during );
		$this->assertCount( 1, FakeHttp::$requests );

		FakeHttp::queue( 200 );
		$this->assertSame( 'success', ( new OAuthConnection( $credentials ) )->disconnect_current() );
		$this->assertCount( 2, FakeHttp::$requests );
		$this->assertStringEndsWith( '/v1/oauth/revoke', FakeHttp::$requests[1]['url'] );
		$this->assertStringContainsString( 'token=refresh-new', FakeHttp::$requests[1]['args']['body'] );
		$this->assertNull( $credentials->oauth_connection() );
	}

	/** While a disconnect revokes the latest token, no refresh may rotate it. */
	public function test_no_refresh_starts_while_a_disconnect_revokes(): void {
		$credentials = new Credentials();
		$credentials->set_oauth_connection( self::connection( time() - 1 ) );
		FakeHttp::queue( 200 );
		$during                    = null;
		FakeHttp::$before_response = static function () use ( $credentials, &$during ): void {
			FakeHttp::$before_response = null;
			$during                    = ( new OAuthTokens( $credentials ) )->access_token();
		};

		$this->assertSame( 'success', ( new OAuthConnection( $credentials ) )->disconnect_current() );

		$this->assertInstanceOf( WP_Error::class, $during );
		$this->assertSame( 'assinafy_oauth_refresh_busy', $during->get_error_code() );
		$this->assertCount( 1, FakeHttp::$requests );
		$this->assertStringContainsString( 'token=refresh-old', FakeHttp::$requests[0]['args']['body'] );
		$this->assertNull( $credentials->oauth_connection() );
	}

	/** A grant saved while the previous one is being revoked is not deleted with it. */
	public function test_disconnect_keeps_a_grant_saved_during_revocation(): void {
		$credentials = new Credentials();
		$credentials->set_oauth_connection( self::connection( time() + 3600 ) );
		FakeHttp::queue( 200 );
		FakeHttp::$before_response = static function () use ( $credentials ): void {
			$new                  = self::connection( time() + 3600 );
			$new['refresh_token'] = 'new-authorization';
			$credentials->set_oauth_connection( $new );
		};

		$this->assertSame( 'local_failed', ( new OAuthConnection( $credentials ) )->disconnect_current() );

		$this->assertStringContainsString( 'token=refresh-old', FakeHttp::$requests[0]['args']['body'] );
		$stored = $credentials->oauth_connection();
		$this->assertIsArray( $stored );
		$this->assertSame( 'new-authorization', $stored['refresh_token'] );
	}

	/** An API 401 refreshes once, then resends the same request with the new token. */
	public function test_an_api_401_refreshes_once_and_resends_the_request(): void {
		$credentials = new Credentials();
		$credentials->set_oauth_connection( self::connection( time() + 3600 ) );
		FakeHttp::queue( 401, '{"status":401,"message":"Unauthorized","data":null}' );
		FakeHttp::queue( 200, self::ROTATED );
		FakeHttp::queue( 200, '{"status":200,"message":"","data":{"id":"workspace-1"}}' );

		$client = ( new ClientFactory( $credentials, new Log() ) )->client();
		$this->assertNotNull( $client );
		$this->assertSame( 'workspace-1', $client->accounts()->get()['id'] );

		$this->assertCount( 3, FakeHttp::$requests );
		$this->assertSame( 'Bearer access-old', FakeHttp::$requests[0]['args']['headers']['Authorization'] );
		$this->assertStringContainsString( 'refresh_token=refresh-old', FakeHttp::$requests[1]['args']['body'] );
		$this->assertSame( FakeHttp::$requests[0]['url'], FakeHttp::$requests[2]['url'] );
		$this->assertSame( 'Bearer access-new', FakeHttp::last_headers()['authorization'] );
		$stored = $credentials->oauth_connection();
		$this->assertIsArray( $stored );
		$this->assertSame( 'refresh-new', $stored['refresh_token'] );
	}

	/** When the refresh after a 401 fails, the connection ends and the user is asked to reconnect. */
	public function test_a_401_whose_refresh_fails_asks_to_reconnect(): void {
		$credentials = new Credentials();
		$credentials->set_oauth_connection( self::connection( time() + 3600 ) );
		FakeHttp::queue( 401, '{"status":401,"message":"Unauthorized","data":null}' );
		FakeHttp::queue( 400, '{"error":"invalid_grant","error_description":"Token revoked"}' );

		$client = ( new ClientFactory( $credentials, new Log() ) )->client();
		$this->assertNotNull( $client );
		try {
			$client->accounts()->get();
			$this->fail( 'A revoked connection must not succeed.' );
		} catch ( ApiException $e ) {
			$this->assertSame( 401, $e->getStatusCode() );
			$this->assertSame( 'Assinafy needs to be reconnected.', $e->getMessage() );
		}

		$this->assertCount( 2, FakeHttp::$requests );
		$this->assertNull( $credentials->oauth_connection() );
	}

	/** @return array{account_id: string, access_token: string, refresh_token: string, expires_at: int, connected_at: int, scope: string} */
	private static function connection( int $expires_at ): array {
		return array(
			'account_id'    => 'workspace-1',
			'access_token'  => 'access-old',
			'refresh_token' => 'refresh-old',
			'expires_at'    => $expires_at,
			'connected_at'  => time() - 60,
			'scope'         => 'documents:read',
		);
	}
}
