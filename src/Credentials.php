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

	public const OPTION_OAUTH_CONNECTION = 'assinafy_oauth_connection_enc';

	public const OPTION_AUTH_MODE = 'assinafy_auth_mode';

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
		if ( $this->uses_oauth() ) {
			$connection = $this->oauth_connection();

			return is_array( $connection ) ? $connection['account_id'] : '';
		}

		if ( $this->is_account_id_constant() ) {
			return (string) constant( 'ASSINAFY_ACCOUNT_ID' );
		}

		return (string) get_option( self::OPTION_ACCOUNT_ID, '' );
	}

	/**
	 * Whether production requests use OAuth. Sandbox keeps its legacy API-key path;
	 * disconnecting production cannot silently reactivate a production API key.
	 */
	public function uses_oauth(): bool {
		return 'oauth' === (string) get_option( self::OPTION_AUTH_MODE, '' )
			&& 'sandbox' !== Settings::get( Settings::OPTION_ENVIRONMENT );
	}

	/**
	 * The encrypted OAuth connection, null when absent, or an error when unreadable.
	 *
	 * @return array{account_id: string, access_token: string, refresh_token: string, expires_at: int, connected_at: int, scope: string}|WP_Error|null
	 */
	public function oauth_connection(): array|WP_Error|null {
		$snapshot = $this->oauth_connection_snapshot();

		return is_array( $snapshot ) ? $snapshot['connection'] : $snapshot;
	}

	/**
	 * Return the encrypted option alongside its value for a conditional refresh write.
	 *
	 * @return array{connection: array{account_id: string, access_token: string, refresh_token: string, expires_at: int, connected_at: int, scope: string}, ciphertext: string}|WP_Error|null
	 */
	public function oauth_connection_snapshot(): array|WP_Error|null {
		$stored = (string) get_option( self::OPTION_OAUTH_CONNECTION, '' );
		if ( '' === $stored ) {
			return null;
		}

		$json = $this->decrypt( $stored );
		if ( $json instanceof WP_Error ) {
			return $json;
		}

		$value = json_decode( $json, true );
		if ( ! is_array( $value ) || ! self::valid_oauth_connection( $value ) ) {
			return CredentialCipher::unreadable();
		}

		/** @var array{account_id: string, access_token: string, refresh_token: string, expires_at: int, connected_at: int, scope: string} $value */
		return array(
			'connection' => $value,
			'ciphertext' => $stored,
		);
	}

	/** @param array<string, mixed> $value Decrypted connection. */
	private static function valid_oauth_connection( array $value ): bool {
		foreach ( array( 'account_id', 'access_token', 'refresh_token', 'scope' ) as $field ) {
			if ( ! is_string( $value[ $field ] ?? null ) || '' === $value[ $field ] ) {
				return false;
			}
		}

		return is_int( $value['expires_at'] ?? null ) && is_int( $value['connected_at'] ?? null );
	}

	/**
	 * Replace both rotating tokens in one non-autoloaded option write.
	 *
	 * @param array{account_id: string, access_token: string, refresh_token: string, expires_at: int, connected_at: int, scope: string} $connection OAuth tokens and workspace.
	 */
	public function set_oauth_connection( array $connection ): void {
		$json = wp_json_encode( $connection );
		if ( false === $json ) {
			throw new \RuntimeException( 'Assinafy OAuth connection could not be encoded' );
		}
		if ( ! update_option( self::OPTION_OAUTH_CONNECTION, $this->encrypt( $json ), false ) ) {
			throw new \RuntimeException( 'Assinafy OAuth connection could not be stored' );
		}
		if ( 'oauth' !== get_option( self::OPTION_AUTH_MODE ) && ! update_option( self::OPTION_AUTH_MODE, 'oauth', true ) ) {
			delete_option( self::OPTION_OAUTH_CONNECTION );
			throw new \RuntimeException( 'Assinafy OAuth mode could not be stored' );
		}
	}

	/** Clear OAuth while keeping legacy credentials unused after disconnect. */
	public function clear_oauth_connection(): bool {
		if ( 'oauth' !== get_option( self::OPTION_AUTH_MODE ) && ! update_option( self::OPTION_AUTH_MODE, 'oauth', true ) ) {
			return false;
		}
		delete_option( self::OPTION_OAUTH_CONNECTION );

		return '' === (string) get_option( self::OPTION_OAUTH_CONNECTION, '' );
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
		return ( new CredentialCipher() )->encrypt( $plaintext );
	}

	/** Whether the encryption key includes a secret absent from a database dump. */
	public function has_server_key_material(): bool {
		return CredentialCipher::is_file_backed();
	}

	/** The remediation shown before an admin starts OAuth or saves an API key. */
	public static function missing_key_material_message(): string {
		return __( 'Set unique WordPress security keys and salts, or ASSINAFY_ENCRYPTION_KEY, in wp-config.php before storing Assinafy credentials.', 'assinafy' );
	}

	/**
	 * Decrypt a stored value.
	 *
	 * @param string $stored Blob previously produced by `encrypt()`.
	 *
	 * @return string|WP_Error Plaintext, or an error naming why it could not be read.
	 */
	public function decrypt( string $stored ): string|WP_Error {
		return ( new CredentialCipher() )->decrypt( $stored );
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
}
