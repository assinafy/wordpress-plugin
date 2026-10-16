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
 * when registering again; OAuth sites must restore a saved copy or recreate the endpoint if
 * it is lost. The plaintext never reaches a browser, the log or JS-localized data.
 */
final class SigningSecret {

	/**
	 * The decrypted `whsec_` secret, or an empty string when none is stored.
	 *
	 * Read on every delivery rather than cached: a rotated secret takes effect immediately.
	 * A stored blob that no longer decrypts — rotated security salts — is reported to the log
	 * and returns empty; the route refuses deliveries while the stored blob remains unreadable.
	 */
	public static function get(): string {
		$stored = (string) Settings::get( Settings::OPTION_WEBHOOK_SECRET_ENC );

		if ( '' === $stored ) {
			return '';
		}

		$plain = ( new Credentials() )->decrypt( $stored );

		if ( $plain instanceof WP_Error ) {
			( new Log() )->add( 'webhook_secret_unreadable', array( 'error' => $plain->get_error_message() ) );

			return '';
		}

		return $plain;
	}

	/**
	 * Store the creation secret, or fetch it for an API-key connection.
	 *
	 * Prefer the create response because OAuth cannot re-read a secret. Existing OAuth
	 * endpoints keep the saved value. Report a failed read or write to the registering caller.
	 *
	 * @param AssinafyClient $client Configured API client.
	 * @param string         $id     The endpoint just created or updated.
	 * @param array<string, mixed> $endpoint Created or updated endpoint, including its creation secret.
	 * @return WP_Error|null Storage failure, or null when stored or managed manually.
	 */
	public static function store( AssinafyClient $client, string $id, #[\SensitiveParameter] array $endpoint = array() ): ?WP_Error {
		$credentials = new Credentials();
		$secret      = is_string( $endpoint['secret'] ?? null ) ? $endpoint['secret'] : null;

		if ( null === $secret && $credentials->uses_oauth() ) {
			return null;
		}

		try {
			$secret ??= $client->webhooks()->endpointSecret( $id );
			update_option( Settings::OPTION_WEBHOOK_SECRET_ENC, $credentials->encrypt( $secret ), false );
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
}
