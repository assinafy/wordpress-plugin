<?php
/**
 * WordPress-native credential key derivation.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Integration;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Credentials;
use WP_Error;

/**
 * Credential encryption must use WordPress's resolved salt, including generated fallbacks.
 *
 * @covers \Assinafy\WP\Credentials
 */
final class CredentialsTest extends AssinafyTestCase {

	/**
	 * Using bare constants would ignore a WordPress salt replacement and fail this check.
	 */
	public function test_encryption_uses_the_salt_resolved_by_wordpress(): void {
		$credentials = new Credentials();
		$stored      = $credentials->encrypt( 'test-api-key' );
		$replacement = static fn( string $salt, string $scheme ): string => 'logged_in' === $scheme ? 'replacement-generated-salt' : $salt;
		add_filter( 'salt', $replacement, 10, 2 );
		try {
			$this->assertInstanceOf( WP_Error::class, $credentials->decrypt( $stored ) );
			$this->assertSame( 'test-api-key', $credentials->decrypt( $credentials->encrypt( 'test-api-key' ) ) );
		} finally {
			remove_filter( 'salt', $replacement, 10 );
		}
		$this->assertSame( 'test-api-key', $credentials->decrypt( $stored ) );
	}
}
