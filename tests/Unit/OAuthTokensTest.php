<?php
/**
 * OAuth token storage and rotation.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Unit;

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

/** @covers \Assinafy\WP\OAuthTokens */
final class OAuthTokensTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['assinafy_test_options'] = array();
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
	public function test_disconnect_during_refresh_cannot_restore_tokens(): void {
		$credentials = new Credentials();
		$credentials->set_oauth_connection( self::connection( time() - 1 ) );
		FakeHttp::queue( 200, '{"access_token":"access-new","refresh_token":"refresh-new","expires_in":3600,"token_type":"Bearer","scope":"account:read documents:read documents:write webhooks:write"}' );
		FakeHttp::$before_response = static function () use ( $credentials ): void {
			$credentials->clear_oauth_connection();
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
