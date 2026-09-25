<?php
/**
 * OAuth rotation against WordPress's real option store.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Integration;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Credentials;
use Assinafy\WP\OAuthConnection;
use Assinafy\WP\OAuthTokens;

/**
 * @covers \Assinafy\WP\OAuthTokens
 * @covers \Assinafy\WP\RefreshLock
 * @covers \Assinafy\WP\OAuthConnection
 */
final class OAuthRefreshTest extends AssinafyTestCase {

	/** The compare-and-write rotation must persist through WordPress's actual options table. */
	public function test_refresh_rotates_the_real_wordpress_option(): void {
		$credentials = new Credentials();
		$credentials->set_oauth_connection(
			array(
				'account_id'    => self::ACCOUNT_ID,
				'access_token'  => 'synthetic-old-access',
				'refresh_token' => 'synthetic-old-refresh',
				'expires_at'    => time() - 1,
				'connected_at'  => time() - 60,
				'scope'         => 'account:read documents:read documents:write webhooks:write',
			)
		);
		$this->fake_raw_response(
			'/oauth/token',
			200,
			'{"access_token":"synthetic-new-access","refresh_token":"synthetic-new-refresh","expires_in":3600,"token_type":"Bearer","scope":"account:read documents:read documents:write webhooks:write"}'
		);

		$this->assertSame( 'synthetic-new-access', ( new OAuthTokens( $credentials ) )->access_token() );
		$stored = $credentials->oauth_connection();
		$this->assertIsArray( $stored );
		$this->assertSame( 'synthetic-new-refresh', $stored['refresh_token'] );
		$this->assertNull( self::stored_lock(), 'The refresh lock was not released.' );
	}

	/** After locking, the grant is re-read from the database, not from this request's cache. */
	public function test_refresh_rereads_the_database_after_locking(): void {
		$credentials = new Credentials();
		$credentials->set_oauth_connection( self::connection( 'old', time() - 1 ) );
		$this->assertNotFalse( wp_cache_get( Credentials::OPTION_OAUTH_CONNECTION, 'options' ) );
		// Another request rotated the grant; this request's object cache still holds the old one.
		global $wpdb;
		$wpdb->update(
			$wpdb->options,
			array( 'option_value' => $credentials->encrypt( (string) wp_json_encode( self::connection( 'rotated', time() + 3600 ) ) ) ),
			array( 'option_name' => Credentials::OPTION_OAUTH_CONNECTION )
		);

		$this->assertSame( 'synthetic-rotated-access', ( new OAuthTokens( $credentials ) )->access_token() );
		$this->assertSame( array(), $this->requests, 'A retired refresh token was sent again.' );
	}

	/** add_option() would overwrite a lock another request inserted behind this request's cache. */
	public function test_a_lock_held_by_another_request_is_never_taken_over(): void {
		$credentials = new Credentials();
		$credentials->set_oauth_connection( self::connection( 'old', time() - 1 ) );
		get_option( OAuthTokens::OPTION_REFRESH_LOCK );
		$this->assertArrayHasKey( OAuthTokens::OPTION_REFRESH_LOCK, (array) wp_cache_get( 'notoptions', 'options' ) );
		$other = ( time() + 45 ) . ':another-request';
		global $wpdb;
		$wpdb->insert(
			$wpdb->options,
			array(
				'option_name'  => OAuthTokens::OPTION_REFRESH_LOCK,
				'option_value' => $other,
				'autoload'     => 'off',
			)
		);

		$result = ( new OAuthTokens( $credentials ) )->access_token();

		$this->assertWPError( $result );
		$this->assertSame( 'assinafy_oauth_refresh_busy', $result->get_error_code() );
		$this->assertSame( array(), $this->requests );
		$this->assertSame( $other, self::stored_lock() );
	}

	/** A holder that died after sending leaves its mark in the lock row: never resend that token. */
	public function test_an_expired_lock_marked_with_the_stored_token_is_never_resent(): void {
		$credentials = new Credentials();
		$credentials->set_oauth_connection( self::connection( 'old', time() - 1 ) );
		self::insert_lock( ( time() - 1 ) . ':crashed-request:' . hash( 'sha256', 'synthetic-old-refresh' ) );

		$result = ( new OAuthTokens( $credentials ) )->access_token();

		$this->assertWPError( $result );
		$this->assertSame( 'assinafy_oauth_reconnect', $result->get_error_code() );
		$this->assertSame( array(), $this->requests, 'A possibly spent refresh token was sent again.' );
		$this->assertNull( $credentials->oauth_connection() );
		$this->assertNull( self::stored_lock() );
	}

	/** Disconnect revokes the token the database holds, not an older one this request cached. */
	public function test_disconnect_revokes_the_latest_token_behind_a_stale_cache(): void {
		$credentials = new Credentials();
		$credentials->set_oauth_connection( self::connection( 'old', time() + 3600 ) );
		$this->assertNotFalse( wp_cache_get( Credentials::OPTION_OAUTH_CONNECTION, 'options' ) );
		// Another request rotated the grant; this request's object cache still holds the old one.
		global $wpdb;
		$wpdb->update(
			$wpdb->options,
			array( 'option_value' => $credentials->encrypt( (string) wp_json_encode( self::connection( 'rotated', time() + 3600 ) ) ) ),
			array( 'option_name' => Credentials::OPTION_OAUTH_CONNECTION )
		);
		$this->fake_raw_response( '/oauth/revoke', 200, '' );

		$this->assertSame( 'success', ( new OAuthConnection( $credentials ) )->disconnect_current() );

		$this->assertCount( 1, $this->requests );
		$this->assertStringContainsString( 'token=synthetic-rotated-refresh', (string) $this->requests[0]['args']['body'] );
		$this->assertNull( $credentials->oauth_connection() );
		$this->assertNull( self::stored_lock() );
	}

	/** While a refresh holds the lock, disconnect revokes and deletes nothing, and asks for a retry. */
	public function test_disconnect_while_a_refresh_holds_the_lock_changes_nothing(): void {
		$credentials = new Credentials();
		$credentials->set_oauth_connection( self::connection( 'old', time() + 3600 ) );
		$held = ( time() + 45 ) . ':refreshing-request:' . hash( 'sha256', 'synthetic-old-refresh' );
		self::insert_lock( $held );

		$this->assertSame( 'busy', ( new OAuthConnection( $credentials ) )->disconnect_current() );

		$this->assertSame( array(), $this->requests );
		$this->assertIsArray( $credentials->oauth_connection() );
		$this->assertSame( $held, self::stored_lock() );
	}

	/**
	 * Insert the lock row the way another request would, behind this request's cache.
	 *
	 * @param string $value Lock value.
	 */
	private static function insert_lock( string $value ): void {
		global $wpdb;
		$wpdb->insert(
			$wpdb->options,
			array(
				'option_name'  => OAuthTokens::OPTION_REFRESH_LOCK,
				'option_value' => $value,
				'autoload'     => 'off',
			)
		);
	}

	/**
	 * @param string $name       Token name prefix.
	 * @param int    $expires_at Access-token expiry.
	 * @return array{account_id: string, access_token: string, refresh_token: string, expires_at: int, connected_at: int, scope: string}
	 */
	private static function connection( string $name, int $expires_at ): array {
		return array(
			'account_id'    => self::ACCOUNT_ID,
			'access_token'  => "synthetic-{$name}-access",
			'refresh_token' => "synthetic-{$name}-refresh",
			'expires_at'    => $expires_at,
			'connected_at'  => time() - 60,
			'scope'         => 'account:read documents:read documents:write webhooks:write',
		);
	}

	/** The lock row as the database holds it, bypassing every cache. */
	private static function stored_lock(): ?string {
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", OAuthTokens::OPTION_REFRESH_LOCK ) );
	}
}
