<?php
/**
 * Encrypt stored Assinafy credentials with a secret outside the database.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP;

defined( 'ABSPATH' ) || exit;

use WP_Error;

/** Compatible secret-box encryption for legacy API keys and OAuth tokens. */
final class CredentialCipher {

	/** Key-derivation version stamped into every blob. */
	private const KEY_VERSION = 1;

	/** Encrypt one value for storage. */
	public function encrypt( string $plaintext ): string {
		if ( '' !== $plaintext && ! self::is_file_backed() ) {
			throw new \RuntimeException( 'Assinafy encryption requires a secret in wp-config.php' );
		}

		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$key   = $this->derive_key();
		$blob  = chr( self::KEY_VERSION ) . $nonce . sodium_crypto_secretbox( $plaintext, $nonce, $key );

		sodium_memzero( $key );

		return bin2hex( $blob );
	}

	/** Decrypt old blobs even if WordPress used its database-stored salt fallback. */
	public function decrypt( string $stored ): string|WP_Error {
		$minimum = 1 + SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES;
		if ( '' === $stored || 0 !== strlen( $stored ) % 2 || ! ctype_xdigit( $stored ) || strlen( $stored ) / 2 < $minimum ) {
			return self::unreadable();
		}

		$blob = (string) hex2bin( $stored );
		if ( self::KEY_VERSION !== ord( $blob[0] ) ) {
			return new WP_Error(
				'assinafy_credentials_key_version',
				__( 'A stored Assinafy credential was written by a different version of this plugin and cannot be read. Reconnect Assinafy.', 'assinafy' )
			);
		}

		$nonce      = substr( $blob, 1, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$ciphertext = substr( $blob, 1 + SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$key        = $this->derive_key();
		$plaintext  = sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );

		sodium_memzero( $key );

		return false === $plaintext ? self::unreadable() : $plaintext;
	}

	/** Whether the encryption key contains at least one file-backed secret. */
	public static function is_file_backed(): bool {
		if ( defined( 'ASSINAFY_ENCRYPTION_KEY' ) && is_string( constant( 'ASSINAFY_ENCRYPTION_KEY' ) ) && '' !== constant( 'ASSINAFY_ENCRYPTION_KEY' ) ) {
			return true;
		}

		$constants = array();
		foreach ( array( 'AUTH', 'SECURE_AUTH', 'LOGGED_IN', 'NONCE', 'SECRET' ) as $prefix ) {
			foreach ( array( 'KEY', 'SALT' ) as $suffix ) {
				$name = "{$prefix}_{$suffix}";
				if ( defined( $name ) && is_string( constant( $name ) ) ) {
					$constants[ $name ] = constant( $name );
				}
			}
		}

		return self::has_unique_logged_in_secret( $constants );
	}

	/** @param array<string, string> $constants WordPress security constants. */
	private static function has_unique_logged_in_secret( array $constants ): bool {
		$counts = array_count_values( $constants );
		foreach ( array( 'SECRET_KEY', 'LOGGED_IN_KEY', 'LOGGED_IN_SALT' ) as $name ) {
			$value = $constants[ $name ] ?? '';
			// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Match core's translated sample wp-config salt.
			if ( '' !== $value && 'put your unique phrase here' !== $value && __( 'put your unique phrase here', 'default' ) !== $value && 1 === $counts[ $value ] ) {
				return true;
			}
		}

		return false;
	}

	/** The 32-byte key derived from the same source as 1.0.1. */
	private function derive_key(): string {
		return sodium_crypto_generichash( $this->key_material(), '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}

	/** A dedicated secret takes precedence over the WordPress logged-in salt. */
	private function key_material(): string {
		if ( defined( 'ASSINAFY_ENCRYPTION_KEY' ) && is_string( constant( 'ASSINAFY_ENCRYPTION_KEY' ) ) && '' !== constant( 'ASSINAFY_ENCRYPTION_KEY' ) ) {
			return (string) constant( 'ASSINAFY_ENCRYPTION_KEY' );
		}

		return wp_salt( 'logged_in' );
	}

	/** An existing credential that cannot be decrypted needs admin repair. */
	public static function unreadable(): WP_Error {
		return new WP_Error(
			'assinafy_credentials_unreadable',
			__( 'A stored Assinafy credential could not be decrypted. Check the site security salts, then reconnect Assinafy.', 'assinafy' )
		);
	}
}
