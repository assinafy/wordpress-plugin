<?php
/**
 * The `wp assinafy webhook` subcommand.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP;

defined( 'ABSPATH' ) || exit;

use Assinafy\SDK\AssinafyClient;
use Assinafy\WP\Webhook\Route;
use WP_CLI;
use WP_Error;

/**
 * `wp assinafy webhook <action>`.
 *
 * Registered by {@see Cli::register()} against `webhook()`, which is the method WP-CLI reads
 * the synopsis from. Every action needs a connected client and nothing else, so the command
 * carries only the factory.
 */
final class WebhookCli {

	/**
	 * @param ClientFactory $clients API client factory.
	 */
	public function __construct( private readonly ClientFactory $clients ) {
	}

	/**
	 * Inspect or change this site's webhook endpoint on the account.
	 *
	 * The endpoint routes give every integration its own endpoint — an account has one, or up
	 * to three on paid plans — so registering adds or updates this site's own endpoint and
	 * never touches another integration's, and `off` switches only this site's endpoint off
	 * without losing its configuration.
	 *
	 * Webhooks are optional: the reconcile pass keeps a site correct without them.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : What to do.
	 * ---
	 * options:
	 *   - status
	 *   - register
	 *   - off
	 * ---
	 *
	 * [--email=<address>]
	 * : Address Assinafy alerts when a delivery fails. Defaults to the site admin email.
	 *
	 * ## EXAMPLES
	 *
	 *     # See the account's endpoints and which one is this site's.
	 *     $ wp assinafy webhook status
	 *
	 *     # Add or update this site's endpoint.
	 *     $ wp assinafy webhook register
	 *     Success: Assinafy now delivers to https://example.com/wp-json/assinafy/v1/webhook/...
	 *
	 *     # Register with a custom failure-alert address.
	 *     $ wp assinafy webhook register --email=ops@example.com
	 *
	 *     # Stop deliveries, keeping the endpoint on file.
	 *     $ wp assinafy webhook off
	 *
	 * @param array<int, string>    $args       Positional arguments: the action.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function webhook( array $args, array $assoc_args ): void {
		$client = $this->clients->client();

		if ( null === $client ) {
			$error = $this->clients->error();

			WP_CLI::error(
				null !== $error
					? $error->get_error_message()
					: 'Connect the plugin to Assinafy before touching the webhook endpoints.'
			);

			return;
		}

		$action = (string) ( $args[0] ?? '' );

		if ( 'status' === $action ) {
			$this->show( $client );

			return;
		}

		if ( 'off' === $action ) {
			$this->stop( $client );

			return;
		}

		$this->start( $client, $assoc_args );
	}

	/**
	 * Print the account's endpoints and which one is this site's.
	 *
	 * @param AssinafyClient $client Connected client.
	 */
	private function show( AssinafyClient $client ): void {
		try {
			$this->print_endpoints( $client->webhooks()->listEndpoints(), Route::url() );
		} catch ( \Throwable $e ) {
			WP_CLI::error( $e->getMessage() );
		}
	}

	/**
	 * Stop this site's deliveries without discarding the endpoint.
	 *
	 * The endpoint stays on file — URL, email, events and signing — so registering again
	 * re-enables it without resupplying anything. Prefers the endpoint id stored by
	 * registration; a site that registered before the id was recorded falls back to the URL
	 * match. Foreign endpoints are never touched.
	 *
	 * @param AssinafyClient $client Connected client.
	 */
	private function stop( AssinafyClient $client ): void {
		$id = (string) Settings::get( Settings::OPTION_WEBHOOK_ENDPOINT_ID );

		if ( '' === $id ) {
			try {
				$endpoints = $client->webhooks()->listEndpoints();
			} catch ( \Throwable $e ) {
				WP_CLI::error( $e->getMessage() );

				return;
			}

			foreach ( $endpoints as $endpoint ) {
				if ( Route::is_ours( $endpoint ) && is_string( $endpoint['id'] ?? null ) ) {
					$id = $endpoint['id'];
					break;
				}
			}
		}

		if ( '' === $id ) {
			WP_CLI::error( 'This site has no webhook endpoint registered on the account.' );

			return;
		}

		try {
			$client->webhooks()->updateEndpoint( $id, array( 'is_active' => false ) );
		} catch ( \Throwable $e ) {
			WP_CLI::error( $e->getMessage() );

			return;
		}

		update_option( Settings::OPTION_WEBHOOK_ENABLED, false );

		WP_CLI::success( 'Deliveries stopped. The endpoint stays on file and can be registered again.' );
	}

	/**
	 * Add or update this site's endpoint.
	 *
	 * `Route` owns the flow here for the same reason the settings screen leaves it to it: the
	 * events already on this site's endpoint have to survive, the endpoint id and signing
	 * secret are stored in one place, and a full plan is an error to report, not an endpoint
	 * to take over.
	 *
	 * @param AssinafyClient        $client     Connected client.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	private function start( AssinafyClient $client, array $assoc_args ): void {
		$url    = Route::url();
		$email  = (string) ( $assoc_args['email'] ?? get_option( 'admin_email' ) );
		$result = Route::subscribe( $client, $email, Route::DEFAULT_EVENTS );

		if ( $result instanceof WP_Error ) {
			WP_CLI::error( $result->get_error_message() );

			return;
		}

		update_option( Settings::OPTION_WEBHOOK_ENABLED, true );

		WP_CLI::success( sprintf( 'Assinafy now delivers to %s', $url ) );
	}

	/**
	 * Print the account's endpoints, marking this site's own.
	 *
	 * @param array<int, array<string, mixed>> $endpoints Endpoints as returned by the API.
	 * @param string                           $url       This site's own endpoint.
	 */
	private function print_endpoints( array $endpoints, string $url ): void {
		WP_CLI::line( 'This site endpoint: ' . $url );

		if ( array() === $endpoints ) {
			WP_CLI::line( 'The account has no webhook endpoints.' );

			return;
		}

		$found = false;

		foreach ( $endpoints as $endpoint ) {
			$events = $endpoint['events'] ?? array();
			$own    = Route::is_ours( $endpoint );
			$found  = $found || $own;

			WP_CLI::line( 'Endpoint ' . (string) ( $endpoint['id'] ?? '' ) . ( $own ? ' (this site)' : '' ) . ':' );
			WP_CLI::line( '  URL:      ' . (string) ( $endpoint['url'] ?? '' ) );
			WP_CLI::line( '  Active:   ' . ( ( $endpoint['is_active'] ?? false ) ? 'yes' : 'no' ) );
			WP_CLI::line( '  Signing:  ' . ( ( $endpoint['signing_enabled'] ?? false ) ? 'enabled' : 'disabled' ) );
			WP_CLI::line( '  Events:   ' . ( is_array( $events ) ? implode( ', ', array_map( 'strval', $events ) ) : '' ) );
		}

		if ( ! $found ) {
			WP_CLI::warning( 'This site has no endpoint on the account. Register it with `wp assinafy webhook register`.' );
		}
	}
}
