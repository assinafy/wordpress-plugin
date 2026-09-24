<?php
/**
 * OAuth manual code parsing.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Unit;

use Assinafy\WP\Credentials;
use Assinafy\WP\OAuthConnection;
use Assinafy\WP\OAuthTokens;
use Assinafy\WP\Tests\Unit\Support\FakeHttp;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

defined( 'ASSINAFY_OAUTH_CLIENT_ID' ) || define( 'ASSINAFY_OAUTH_CLIENT_ID', 'synthetic-wordpress-client' );

/** @covers \Assinafy\WP\OAuthConnection */
final class OAuthConnectionTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['assinafy_test_options'] = array();
		FakeHttp::reset();
	}

	/** The hosted relay must receive the complete authorization URL as one value. */
	public function test_start_url_encodes_nested_authorization_query(): void {
		$authorization_url = 'https://auth.assinafy.com.br/oauth/authorize?response_type=code&client_id=synthetic-wordpress-client&scope=account%3Aread%20offline_access';
		$method            = new ReflectionMethod( OAuthConnection::class, 'start_url' );
		$url               = $method->invoke(
			null,
			array(
				'state'             => 'synthetic-state',
				'authorization_url' => $authorization_url,
			)
		);
		parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $query ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Test parses a controlled URL.

		$this->assertSame(
			array(
				'state'             => 'synthetic-state',
				'authorization_url' => $authorization_url,
			),
			$query
		);
	}

	/** The token endpoint must receive the authorization code exactly as issued. */
	public function test_posted_code_preserves_a_literal_percent_sequence(): void {
		$original = $_POST;
		$_POST    = array( 'code' => 'synthetic%2Fcode' );

		try {
			$method = new ReflectionMethod( OAuthConnection::class, 'posted_code' );
			$code   = $method->invoke( new OAuthConnection( new Credentials() ) );

			$this->assertSame( 'synthetic%2Fcode', $code );
		} finally {
			$_POST = $original;
		}
	}

	/** Same-workspace reconnection may reuse a grant, while a switched workspace has its own. */
	public function test_reconnect_revokes_only_a_different_workspace(): void {
		$credentials              = new Credentials();
		$previous                 = array(
			'account_id'    => 'workspace-1',
			'access_token'  => 'old-access',
			'refresh_token' => 'old-refresh',
			'expires_at'    => time() + 3600,
			'connected_at'  => time(),
			'scope'         => 'account:read documents:read documents:write webhooks:write',
		);
		$current                  = $previous;
		$current['refresh_token'] = 'new-refresh';
		$credentials->set_oauth_connection( $current );
		$method = new ReflectionMethod( OAuthConnection::class, 'revoke_old_workspace_if_switched' );
		$this->assertTrue( $method->invoke( new OAuthConnection( $credentials ), ( new OAuthTokens( $credentials ) )->oauth(), $previous ) );
		$this->assertSame( array(), FakeHttp::$requests );

		$current['account_id'] = 'workspace-2';
		$credentials->set_oauth_connection( $current );
		FakeHttp::queue( 200 );
		$this->assertTrue( $method->invoke( new OAuthConnection( $credentials ), ( new OAuthTokens( $credentials ) )->oauth(), $previous ) );
		$this->assertCount( 1, FakeHttp::$requests );
		$this->assertStringEndsWith( '/v1/oauth/revoke', FakeHttp::$requests[0]['url'] );
		$this->assertStringContainsString( 'token=old-refresh', FakeHttp::$requests[0]['args']['body'] );
		FakeHttp::queue_error( 'http_request_failed', 'timeout' );
		$this->assertFalse( $method->invoke( new OAuthConnection( $credentials ), ( new OAuthTokens( $credentials ) )->oauth(), $previous ) );
	}

	/** A failed remote revoke cannot leave this site using the connection. */
	public function test_failed_revocation_still_disconnects_this_site(): void {
		$credentials = new Credentials();
		$credentials->set_oauth_connection(
			array(
				'account_id'    => 'workspace-1',
				'access_token'  => 'old-access',
				'refresh_token' => 'old-refresh',
				'expires_at'    => time() + 3600,
				'connected_at'  => time(),
				'scope'         => 'account:read documents:read documents:write webhooks:write',
			)
		);
		FakeHttp::queue_error( 'http_request_failed', 'timeout' );

		$method = new ReflectionMethod( OAuthConnection::class, 'disconnect_current' );
		$this->assertSame( 'remote_failed', $method->invoke( new OAuthConnection( $credentials ) ) );
		$this->assertNull( $credentials->oauth_connection() );
		$this->assertSame( 'oauth', get_option( Credentials::OPTION_AUTH_MODE ) );
	}

	/** A rotated salt can prevent revocation, but the admin can still remove the local blob. */
	public function test_unreadable_connection_can_be_disconnected(): void {
		$credentials = new Credentials();
		update_option( Credentials::OPTION_OAUTH_CONNECTION, bin2hex( chr( 1 ) . random_bytes( 64 ) ) );

		$method = new ReflectionMethod( OAuthConnection::class, 'disconnect_current' );
		$this->assertSame( 'remote_failed', $method->invoke( new OAuthConnection( $credentials ) ) );
		$this->assertNull( $credentials->oauth_connection() );
		$this->assertSame( array(), FakeHttp::$requests );
	}
}
