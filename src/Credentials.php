<?php
/**
 * Storage and retrieval of the Assinafy API credentials.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP;

defined( 'ABSPATH' ) || exit;

use WP_Error;

/**
 * Reads the account id and API key, encrypting the key at rest with libsodium.
 *
 * The stored blob is `hex( version || nonce || secretbox )`. The leading version byte
 * exists so a future change of key derivation is detectable instead of silently
 * producing garbage: a blob written under a version this build does not know is
 * reported as unreadable rather than decrypted with the wrong key.
 *
 * Every failure path returns a `WP_Error`. Returning an empty string on a decryption
 * failure would be indistinguishable from "no credentials saved", which turns a site
 * whose salts were rotated into a site that silently reports itself unconfigured.
 */
final class Credentials {

	public const OPTION_API_KEY = 'assinafy_api_key_enc';

	public const OPTION_ACCOUNT_ID = 'assinafy_account_id';

	/**
	 * Key-derivation version stamped into every blob this build writes.
	 */
	private const KEY_VERSION = 1;

	/**
	 * The API key, or an empty string when none is configured.
	 *
	 * @return string|WP_Error Plaintext key, empty string when unconfigured, error when unreadable.
	 */
	public function api_key(): string|WP_Error {
		if ( $this->is_api_key_constant() ) {
			return (string) constant( 'ASSINAFY_API_KEY' );
		}

		$stored = (string) get_option( self::OPTION_API_KEY, '' );

		if ( '' === $stored ) {
			return '';
		}

		return $this->decrypt( $stored );
	}

	/**
	 * Whether the API key comes from `wp-config.php` and the settings field is read-only.
	 */
	public function is_api_key_constant(): bool {
		return defined( 'ASSINAFY_API_KEY' )
			&& is_string( constant( 'ASSINAFY_API_KEY' ) )
			&& '' !== constant( 'ASSINAFY_API_KEY' );
	}

	/**
	 * Store an API key.
	 *
	 * @param string $plaintext The API key as issued by Assinafy.
	 */
	public function set_api_key( string $plaintext ): void {
		$plaintext = trim( $plaintext );

		update_option( self::OPTION_API_KEY, $this->encrypt( $plaintext ), false );
	}

	/**
	 * The account id, or an empty string when none is configured.
	 */
	public function account_id(): string {
		if ( $this->is_account_id_constant() ) {
			return (string) constant( 'ASSINAFY_ACCOUNT_ID' );
		}

		return (string) get_option( self::OPTION_ACCOUNT_ID, '' );
	}

	/**
	 * Whether the account id comes from `wp-config.php` and the settings field is read-only.
	 */
	public function is_account_id_constant(): bool {
		return defined( 'ASSINAFY_ACCOUNT_ID' )
			&& is_string( constant( 'ASSINAFY_ACCOUNT_ID' ) )
			&& '' !== constant( 'ASSINAFY_ACCOUNT_ID' );
	}

	/**
	 * Encrypt a value for storage.
	 *
	 * @param string $plaintext Value to protect.
	 */
	public function encrypt( string $plaintext ): string {
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$key   = $this->derive_key();
		$blob  = chr( self::KEY_VERSION ) . $nonce . sodium_crypto_secretbox( $plaintext, $nonce, $key );

		sodium_memzero( $key );

		return bin2hex( $blob );
	}

	/**
	 * Decrypt a stored value.
	 *
	 * @param string $stored Blob previously produced by `encrypt()`.
	 *
	 * @return string|WP_Error Plaintext, or an error naming why it could not be read.
	 */
	public function decrypt( string $stored ): string|WP_Error {
		$minimum = 1 + SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES;

		if ( '' === $stored || 0 !== strlen( $stored ) % 2 || ! ctype_xdigit( $stored ) || strlen( $stored ) / 2 < $minimum ) {
			return $this->unreadable();
		}

		$blob = (string) hex2bin( $stored );

		if ( self::KEY_VERSION !== ord( $blob[0] ) ) {
			return new WP_Error(
				'assinafy_credentials_key_version',
				__( 'The stored Assinafy API key was written by a different version of this plugin and cannot be read. Enter the key again.', 'assinafy' )
			);
		}

		$nonce      = substr( $blob, 1, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$ciphertext = substr( $blob, 1 + SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$key        = $this->derive_key();
		$plaintext  = sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );

		sodium_memzero( $key );

		if ( false === $plaintext ) {
			return $this->unreadable();
		}

		return $plaintext;
	}

	/**
	 * Whether a blob is one this instance can already read, used to keep the
	 * option's sanitisation idempotent.
	 *
	 * @param string $value Candidate stored value.
	 */
	public function is_encrypted( string $value ): bool {
		return ! is_wp_error( $this->decrypt( $value ) );
	}

	/**
	 * The 32-byte secret-box key.
	 *
	 * `ASSINAFY_ENCRYPTION_KEY` lets a site keep the key out of the database entirely.
	 * Otherwise WordPress resolves the authentication salts, including its generated fallback
	 * when constants are missing or still contain sample values. Rotating those salts
	 * invalidates the stored key — reported, never silently swallowed.
	 */
	private function derive_key(): string {
		return sodium_crypto_generichash( $this->key_material(), '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}

	/**
	 * The secret the key is derived from.
	 */
	private function key_material(): string {
		if ( defined( 'ASSINAFY_ENCRYPTION_KEY' ) && is_string( constant( 'ASSINAFY_ENCRYPTION_KEY' ) ) && '' !== constant( 'ASSINAFY_ENCRYPTION_KEY' ) ) {
			return (string) constant( 'ASSINAFY_ENCRYPTION_KEY' );
		}

		return wp_salt( 'logged_in' );
	}

	/**
	 * The error returned for a blob that exists but cannot be decrypted.
	 */
	private function unreadable(): WP_Error {
		return new WP_Error(
			'assinafy_credentials_unreadable',
			__( 'The stored Assinafy API key could not be decrypted. This happens when the site security salts change. Enter the key again.', 'assinafy' )
		);
	}
}
