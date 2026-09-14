<?php
/**
 * Assinafy API client construction.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP;

defined( 'ABSPATH' ) || exit;

use Assinafy\SDK\AssinafyClient;
use Assinafy\SDK\Configuration;
use Assinafy\WP\Http\WpHttpClient;
use WP_Error;

/**
 * Builds and memoises the SDK client.
 *
 * This is the plugin's only construction site for `AssinafyClient`. The SDK's static
 * factories (`create()`, `fromArray()`, `forAuth()`, `forBearer()`) all fall back to the
 * bundled Guzzle transport, and Guzzle is not installed — the plugin replaces it with
 * `WpHttpClient`. Calling one of them is therefore a fatal error, not a style choice.
 *
 * The base URL is derived from the environment setting and never from user input:
 * `Configuration` rejects a non-HTTPS base URL outright unless the host is loopback, and a
 * free-text field is an invitation to point a credentialled request at another origin.
 */
final class ClientFactory {

	/**
	 * Memoised client for this request.
	 */
	private ?AssinafyClient $client = null;

	/**
	 * Why the last `client()` call returned null, when that reason is worth showing.
	 */
	private ?WP_Error $error = null;

	/**
	 * @param Credentials $credentials Credential store.
	 * @param Log         $log         Plugin event log.
	 */
	public function __construct(
		private readonly Credentials $credentials,
		private readonly Log $log
	) {
	}

	/**
	 * The configured client, or null when the plugin has no usable credentials.
	 *
	 * Callers must handle null. Missing credentials are an ordinary state — the plugin is
	 * installed but not yet set up — and must not fatal a page load.
	 */
	public function client(): ?AssinafyClient {
		if ( null !== $this->client ) {
			return $this->client;
		}

		$this->error = null;

		$api_key = $this->credentials->api_key();

		if ( $api_key instanceof WP_Error ) {
			$this->error = $api_key;

			return null;
		}

		$account_id = $this->credentials->account_id();

		if ( '' === $api_key || '' === $account_id ) {
			return null;
		}

		try {
			$config = new Configuration( $api_key, $account_id, $this->base_url(), 30, 10 );

			$this->client = new AssinafyClient( $config, new WpHttpClient( $config ) );
		} catch ( \Throwable $e ) {
			$this->error = new WP_Error( 'assinafy_client_unavailable', $e->getMessage() );

			$this->log->add(
				'client_unavailable',
				array(
					'error' => $e->getMessage(),
					'type'  => get_debug_type( $e ),
				)
			);

			return null;
		}

		return $this->client;
	}

	/**
	 * The reason the last `client()` call failed, or null when it simply is not configured.
	 */
	public function error(): ?WP_Error {
		return $this->error;
	}

	/**
	 * The API base URL for the selected environment.
	 */
	public function base_url(): string {
		return 'sandbox' === (string) Settings::get( Settings::OPTION_ENVIRONMENT )
			? Configuration::SANDBOX_BASE_URL
			: Configuration::DEFAULT_BASE_URL;
	}

	/**
	 * Drop the memoised client so a credential or environment change takes effect
	 * within the same request that saved it.
	 */
	public function reset(): void {
		$this->client = null;
		$this->error  = null;
	}
}
