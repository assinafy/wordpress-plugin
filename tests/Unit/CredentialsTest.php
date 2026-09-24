<?php
/**
 * Credential storage.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Unit;

use Assinafy\WP\Credentials;
use Assinafy\WP\CredentialCipher;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use WP_Error;

/**
 * The failure that matters is a key that cannot be read. Returning an empty string for that is
 * indistinguishable from "no credentials saved", which turns a site whose security salts were
 * rotated into a site that silently reports itself unconfigured — and then quietly stops
 * sending documents.
 *
 * @covers \Assinafy\WP\Credentials
 */
final class CredentialsTest extends TestCase {

	private Credentials $credentials;

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['assinafy_test_options'] = array();

		$this->credentials = new Credentials();
	}

	public function test_a_key_survives_a_round_trip(): void {
		$plaintext = 'ak_live_not_a_real_key_0e3a1c4b9f2d47a8';

		$this->assertSame( $plaintext, $this->credentials->decrypt( $this->credentials->encrypt( $plaintext ) ) );
	}

	/** Missing, sample, and duplicate wp-config secrets must not secure a database dump. */
	public function test_only_a_unique_file_backed_logged_in_secret_allows_new_credentials(): void {
		$check = new ReflectionMethod( CredentialCipher::class, 'has_unique_logged_in_secret' );
		$this->assertFalse( $check->invoke( null, array() ) );
		$this->assertFalse( $check->invoke( null, array( 'LOGGED_IN_KEY' => 'put your unique phrase here' ) ) );
		$this->assertFalse(
			$check->invoke(
				null,
				array(
					'LOGGED_IN_KEY' => 'duplicate',
					'AUTH_KEY'      => 'duplicate',
				)
			)
		);
		$this->assertTrue( $check->invoke( null, array( 'LOGGED_IN_SALT' => 'unique-salt' ) ) );
		$this->assertTrue( $check->invoke( null, array( 'SECRET_KEY' => 'unique-secret' ) ) );
		$this->assertTrue( $this->credentials->has_server_key_material() );
	}

	public function test_a_key_survives_a_round_trip_through_the_option(): void {
		$plaintext = 'ak_live_not_a_real_key_0e3a1c4b9f2d47a8';

		$this->credentials->set_api_key( $plaintext );

		$this->assertSame( $plaintext, $this->credentials->api_key() );
	}

	public function test_the_stored_option_is_not_the_plaintext(): void {
		$plaintext = 'ak_live_not_a_real_key_0e3a1c4b9f2d47a8';

		$this->credentials->set_api_key( $plaintext );

		$stored = (string) get_option( Credentials::OPTION_API_KEY, '' );

		$this->assertNotSame( '', $stored );
		$this->assertStringNotContainsString( $plaintext, $stored );
		$this->assertTrue( ctype_xdigit( $stored ) );
	}

	/**
	 * A fresh nonce per write, so two encryptions of the same key are not comparable.
	 */
	public function test_two_encryptions_of_the_same_value_differ(): void {
		$first  = $this->credentials->encrypt( 'same-value' );
		$second = $this->credentials->encrypt( 'same-value' );

		$this->assertNotSame( $first, $second );
		$this->assertSame( 'same-value', $this->credentials->decrypt( $first ) );
		$this->assertSame( 'same-value', $this->credentials->decrypt( $second ) );
	}

	/**
	 * The distinguishable-failure requirement, stated as the thing it prevents: a blob written
	 * under different salts must report itself unreadable, never decrypt to an empty string.
	 */
	public function test_a_blob_written_under_a_different_key_is_reported_unreadable(): void {
		$nonce     = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$other_key = sodium_crypto_generichash( 'some other salts entirely', '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
		$foreign   = chr( 1 ) . $nonce . sodium_crypto_secretbox( 'ak_live_not_a_real_key', $nonce, $other_key );
		$result    = $this->credentials->decrypt( bin2hex( $foreign ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'assinafy_credentials_unreadable', $result->get_error_code() );
		$this->assertNotSame( '', $result->get_error_message() );
	}

	public function test_an_unreadable_key_reaches_the_caller_as_an_error_not_an_empty_string(): void {
		update_option( Credentials::OPTION_API_KEY, bin2hex( chr( 1 ) . random_bytes( 64 ) ) );

		$result = $this->credentials->api_key();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'assinafy_credentials_unreadable', $result->get_error_code() );
	}

	public function test_an_unconfigured_key_is_an_empty_string_not_an_error(): void {
		$this->assertSame( '', $this->credentials->api_key() );
	}

	/**
	 * The version byte exists so a future change of key derivation is detectable instead of
	 * silently producing garbage, and it reports a different code from a wrong key.
	 */
	public function test_a_blob_from_an_unknown_key_version_is_reported_separately(): void {
		$blob = $this->credentials->encrypt( 'ak_live_not_a_real_key' );
		$raw  = (string) hex2bin( $blob );
		$raw  = chr( 99 ) . substr( $raw, 1 );

		$result = $this->credentials->decrypt( bin2hex( $raw ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'assinafy_credentials_key_version', $result->get_error_code() );
	}

	public function test_tampered_ciphertext_is_rejected(): void {
		$blob         = $this->credentials->encrypt( 'ak_live_not_a_real_key' );
		$raw          = (string) hex2bin( $blob );
		$last         = strlen( $raw ) - 1;
		$raw[ $last ] = chr( ord( $raw[ $last ] ) ^ 0xff );

		$result = $this->credentials->decrypt( bin2hex( $raw ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'assinafy_credentials_unreadable', $result->get_error_code() );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function malformed_blobs(): array {
		return array(
			'empty'          => array( '' ),
			'plaintext key'  => array( 'ak_live_not_a_real_key' ),
			'odd length hex' => array( 'abc' ),
			'non hex'        => array( str_repeat( 'zz', 64 ) ),
			'too short'      => array( bin2hex( str_repeat( "\x01", 8 ) ) ),
		);
	}

	/**
	 * @dataProvider malformed_blobs
	 *
	 * @param string $stored Stored value.
	 */
	public function test_a_malformed_blob_is_reported_unreadable( string $stored ): void {
		$result = $this->credentials->decrypt( $stored );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'assinafy_credentials_unreadable', $result->get_error_code() );
	}

	/**
	 * `is_encrypted()` keeps the option's sanitisation idempotent: a value already in storage
	 * form must not be encrypted a second time.
	 */
	public function test_is_encrypted_recognises_its_own_output_only(): void {
		$this->assertTrue( $this->credentials->is_encrypted( $this->credentials->encrypt( 'ak_live_not_a_real_key' ) ) );
		$this->assertFalse( $this->credentials->is_encrypted( 'ak_live_not_a_real_key' ) );
		$this->assertFalse( $this->credentials->is_encrypted( '' ) );
	}

	public function test_surrounding_whitespace_is_trimmed_before_storage(): void {
		$this->credentials->set_api_key( "  ak_live_not_a_real_key\n" );

		$this->assertSame( 'ak_live_not_a_real_key', $this->credentials->api_key() );
	}

	/**
	 * Clearing the key must read back as "unconfigured", not as the unreadable error. The two
	 * are different failures — one is an empty settings field, the other is a rotated salt —
	 * and the caller branches on which it got.
	 */
	public function test_clearing_the_key_reads_back_as_unconfigured(): void {
		$this->credentials->set_api_key( 'ak_live_not_a_real_key' );

		$this->credentials->set_api_key( '' );

		$stored = (string) get_option( Credentials::OPTION_API_KEY, '' );
		$this->assertNotSame( '', $stored );
		$this->assertStringNotContainsString( 'ak_live_not_a_real_key', $stored );
		$this->assertSame( '', $this->credentials->api_key() );
	}

	public function test_a_whitespace_only_key_clears_the_stored_one(): void {
		$this->credentials->set_api_key( 'ak_live_not_a_real_key' );

		$this->credentials->set_api_key( "   \n" );

		$this->assertSame( '', $this->credentials->api_key() );
	}

	public function test_the_account_id_is_stored_in_the_clear(): void {
		update_option( Credentials::OPTION_ACCOUNT_ID, '0e3a1c4b9f2d47a8b6c05e19' );

		$this->assertSame( '0e3a1c4b9f2d47a8b6c05e19', $this->credentials->account_id() );

		delete_option( Credentials::OPTION_ACCOUNT_ID );

		$this->assertSame( '', $this->credentials->account_id() );
	}

	public function test_no_constant_is_defined_in_this_environment(): void {
		$this->assertFalse( $this->credentials->is_api_key_constant() );
		$this->assertFalse( $this->credentials->is_account_id_constant() );
	}
}
