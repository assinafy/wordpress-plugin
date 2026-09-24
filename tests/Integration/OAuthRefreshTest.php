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
use Assinafy\WP\OAuthTokens;

/** @covers \Assinafy\WP\OAuthTokens */
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
	}
}
