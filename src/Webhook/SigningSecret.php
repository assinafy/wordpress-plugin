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
 * API-key sites read the secret from the API at registration and store it encrypted with the
 * same cipher as the API key. The secret routes are not available to OAuth applications, so
 * an OAuth site's operator pastes the value from the Assinafy dashboard into the settings
 * field instead. The plaintext never reaches a browser, the log or JS-localized data.
 */
final class SigningSecret {

	/**
	 * The decrypted `whsec_` secret, or an empty string when none is stored.
	 *
	 * Read on every delivery rather than cached: a rotated secret takes effect immediately.
	 * A stored blob that no longer decrypts — rotated security salts — is reported to the log
	 * and treated as absent, so the endpoint degrades to token-only authentication instead of
	 * refusing every delivery.
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
	 * Read the endpoint's secret from the API and store it encrypted.
	 *
	 * The secret routes are not available to OAuth applications, so an OAuth site is skipped
	 * silently. A failed read is logged rather than failing the registration: the endpoint
	 * itself is already live, and the settings screen shows whether a secret is stored.
	 *
	 * @param AssinafyClient $client Configured API client.
	 * @param string         $id     The endpoint just created or updated.
	 */
	public static function store( AssinafyClient $client, string $id ): void {
		$credentials = new Credentials();

		if ( $credentials->uses_oauth() ) {
			return;
		}

		try {
			$secret = $client->webhooks()->endpointSecret( $id );
		} catch ( \Throwable $e ) {
			( new Log() )->add(
				'webhook_secret_fetch_failed',
				array(
					'error' => $e->getMessage(),
					'type'  => get_debug_type( $e ),
				)
			);

			return;
		}

		update_option( Settings::OPTION_WEBHOOK_SECRET_ENC, $credentials->encrypt( $secret ), false );
	}
}
