<?php
/**
 * The webhook endpoint's signing secret, stored encrypted.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Webhook;

defined( 'ABSPATH' ) || exit;

use Assinafy\SDK\AssinafyClient;
use Assinafy\WP\Credentials;
use Assinafy\WP\Log;
use Assinafy\WP\Settings;
use WP_Error;

/**
 * Reads and stores the `whsec_` secret that signs deliveries to this site's endpoint.
 *
 * Creation returns the secret for both authentication modes. API-key sites can also read it
 * when registering again; OAuth sites rotate a missing or unreadable secret when registering
 * again. The plaintext never reaches a browser, the log or JS-localized data.
 */
final class SigningSecret {

	public const LOCK_NAME = 'assinafy_webhook_secret';
	private const LOCK_TTL = 180;

	/**
	 * The decrypted `whsec_` secret, or an empty string when none is stored.
	 *
	 * Read on every delivery rather than cached: a rotated secret takes effect immediately.
	 * A stored blob that no longer decrypts — rotated security salts — is reported to the log
	 * and returns empty; the route refuses deliveries while the stored blob remains unreadable.
	 */
	public static function get(): string {
		$value = self::read();
		if ( null === $value || ( isset( $value['context'] ) && $value['context'] !== self::context( (string) Settings::get( Settings::OPTION_WEBHOOK_ENDPOINT_ID ) ) ) ) {
			return '';
		}

		return $value['secret'];
	}

	/** @return array{secret: string, context?: mixed}|null Decrypted secret and its connection context. */
	private static function read(): ?array {
		$stored = (string) Settings::get( Settings::OPTION_WEBHOOK_SECRET_ENC );

		if ( '' === $stored ) {
			return null;
		}

		$plain = ( new Credentials() )->decrypt( $stored );

		if ( $plain instanceof WP_Error ) {
			( new Log() )->add( 'webhook_secret_unreadable', array( 'error' => $plain->get_error_message() ) );

			return null;
		}

		if ( self::valid( $plain ) ) {
			return array( 'secret' => $plain );
		}
		$value = json_decode( $plain, true );
		if ( ! is_array( $value ) || ! is_string( $value['secret'] ?? null ) || ! self::valid( $value['secret'] ) ) {
			return null;
		}

		return $value;
	}

	/** Bind a pasted or returned secret to the same connection as its endpoint. */
	public static function encrypt( string $secret, string $id ): string {
		$value = wp_json_encode(
			array(
				'secret'  => $secret,
				'context' => self::context( $id ),
			)
		);
		if ( false === $value ) {
			throw new \RuntimeException( 'The webhook signing secret could not be encoded' );
		}

		return ( new Credentials() )->encrypt( $value );
	}

	/** @return array{endpoint: string, environment: string, account: string} */
	private static function context( string $id ): array {
		return array(
			'endpoint'    => $id,
			'environment' => (string) Settings::get( Settings::OPTION_ENVIRONMENT ),
			'account'     => ( new Credentials() )->account_id(),
		);
	}

	/** The parser requires a nonempty base64 key after whsec_. */
	private static function valid( string $secret ): bool {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Standard Webhooks encodes the key.
		$key = str_starts_with( $secret, 'whsec_' ) ? base64_decode( substr( $secret, 6 ), true ) : false;

		return is_string( $key ) && '' !== $key;
	}

	/**
	 * Store the creation secret, or fetch it for an API-key connection.
	 *
	 * Prefer the create response because OAuth cannot re-read a secret. Existing OAuth
	 * endpoints keep a readable saved value or rotate a lost one. Report failures to the caller.
	 *
	 * @param AssinafyClient $client Configured API client.
	 * @param string         $id     The endpoint just created or updated.
	 * @param array<string, mixed> $endpoint Created or updated endpoint, including its creation secret.
	 * @return WP_Error|null Storage failure, or null when stored.
	 */
	public static function store( AssinafyClient $client, string $id, #[\SensitiveParameter] array $endpoint = array() ): ?WP_Error {
		if ( ! class_exists( \WP_Upgrader::class ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}
		if ( ! \WP_Upgrader::create_lock( self::LOCK_NAME, self::LOCK_TTL ) ) {
			return new WP_Error(
				'assinafy_webhook_secret_busy',
				__( 'Another request is registering this webhook signing secret. Try again when it finishes.', 'assinafy' ),
				array(
					'status'    => 409,
					'retryable' => true,
				)
			);
		}
		try {
			return self::store_locked( $client, $id, $endpoint );
		} finally {
			\WP_Upgrader::release_lock( self::LOCK_NAME );
		}
	}

	/**
	 * Rotate and save under one lock so a later rotation cannot overtake this response.
	 *
	 * @param AssinafyClient $client Configured API client.
	 * @param string $id Remote endpoint ID.
	 * @param array<string, mixed> $endpoint Created or updated endpoint.
	 */
	private static function store_locked( AssinafyClient $client, string $id, #[\SensitiveParameter] array $endpoint ): ?WP_Error {
		$secret = is_string( $endpoint['secret'] ?? null ) ? $endpoint['secret'] : null;

		try {
			update_option( Settings::OPTION_WEBHOOK_ENDPOINT_ID, $id, false );
			if ( $id !== Settings::get( Settings::OPTION_WEBHOOK_ENDPOINT_ID ) ) {
				throw new \RuntimeException( 'The webhook endpoint ID could not be stored' );
			}
			$secret = self::fetch( $client, $id, $secret );
			if ( ! self::valid( $secret ) ) {
				throw new \RuntimeException( 'The endpoint did not return a valid signing secret' );
			}
			update_option( Settings::OPTION_WEBHOOK_SECRET_ENC, self::encrypt( $secret, $id ), false );
			if ( hash_equals( $secret, self::get() ) ) {
				return null;
			}
		} catch ( \Throwable $e ) {
			( new Log() )->add(
				'webhook_secret_fetch_failed',
				array(
					'error' => $e->getMessage(),
					'type'  => get_debug_type( $e ),
				)
			);
		}
		return new WP_Error( 'assinafy_webhook_secret_failed', __( 'The webhook endpoint was registered, but its signing secret could not be stored. Register this site again or restore its signing secret before accepting deliveries.', 'assinafy' ) );
	}

	/** Keep only a secret already bound to this endpoint and connection; recover every other one. */
	private static function fetch( AssinafyClient $client, string $id, ?string $secret ): string {
		if ( null === $secret && ( new Credentials() )->uses_oauth() ) {
			$saved = self::read();

			return ( $saved['context'] ?? null ) === self::context( $id )
				? $saved['secret']
				: self::rotate( $client, $id );
		}

		return $secret ?? $client->webhooks()->endpointSecret( $id );
	}

	/** Persist a closed gate before rotation so a failed save cannot retain the retired key. */
	private static function rotate( AssinafyClient $client, string $id ): string {
		$pending = ( new Credentials() )->encrypt( '' );
		update_option( Settings::OPTION_WEBHOOK_SECRET_ENC, $pending, false );
		if ( $pending !== Settings::get( Settings::OPTION_WEBHOOK_SECRET_ENC ) ) {
			throw new \RuntimeException( 'The previous webhook signing secret could not be invalidated' );
		}

		return $client->webhooks()->rotateEndpointSecret( $id );
	}
}
