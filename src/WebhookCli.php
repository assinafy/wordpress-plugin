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
	 * Inspect or change the account's webhook subscription.
	 *
	 * An Assinafy account has exactly one webhook subscription, shared by every integration
	 * on that account — there is no per-site subscription and no DELETE route. Registering
	 * from here therefore takes the subscription over from whatever it pointed at, which is
	 * why replacing a foreign URL asks for confirmation. `off` stops delivery without losing
	 * the configuration.
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
	 * [--yes]
	 * : Answer the take-over prompt without asking.
	 *
	 * ## EXAMPLES
	 *
	 *     # See where the account currently delivers.
	 *     $ wp assinafy webhook status
	 *
	 *     # Point the account at this site.
	 *     $ wp assinafy webhook register
	 *     Success: Assinafy now delivers to https://example.com/wp-json/assinafy/v1/webhook/...
	 *
	 *     # Take over another endpoint unattended, during a migration.
	 *     $ wp assinafy webhook register --email=ops@example.com --yes
	 *
	 *     # Stop deliveries, keeping the configuration on file.
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
					: 'Set an account id and an API key before touching the webhook subscription.'
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
	 * Print where the account currently delivers.
	 *
	 * @param AssinafyClient $client Connected client.
	 */
	private function show( AssinafyClient $client ): void {
		try {
			$this->print_subscription( $client->webhooks()->get(), Route::url() );
		} catch ( \Throwable $e ) {
			WP_CLI::error( $e->getMessage() );
		}
	}

	/**
	 * Stop deliveries without discarding the subscription.
	 *
	 * The API has no route that deletes a subscription; inactivating it is the only way to
	 * switch deliveries off, and the URL, email and event list stay on file so the same
	 * subscription can be switched back on.
	 *
	 * @param AssinafyClient $client Connected client.
	 */
	private function stop( AssinafyClient $client ): void {
		try {
			$client->webhooks()->deactivate();
		} catch ( \Throwable $e ) {
			WP_CLI::error( $e->getMessage() );

			return;
		}

		update_option( Settings::OPTION_WEBHOOK_ENABLED, false );

		WP_CLI::success( 'Deliveries stopped. The subscription stays on file and can be registered again.' );
	}

	/**
	 * Point the account at this site.
	 *
	 * `Route` owns the upsert here for the same reason the settings screen leaves it to it:
	 * the subscription is account-wide, so the events already on it have to survive the
	 * replacement, and a URL belonging to somebody else must not be taken over unasked.
	 * `subscribe()` reports a takeover as an error carrying the URL on file, which is the
	 * prompt below; `take_over()` is the answer to it.
	 *
	 * @param AssinafyClient        $client     Connected client.
	 * @param array<string, string> $assoc_args Associative arguments; `--yes` answers the prompt.
	 */
	private function start( AssinafyClient $client, array $assoc_args ): void {
		$url    = Route::url();
		$email  = (string) ( $assoc_args['email'] ?? get_option( 'admin_email' ) );
		$result = Route::subscribe( $client, $email, Route::DEFAULT_EVENTS );

		if ( $result instanceof WP_Error && 'assinafy_webhook_takeover' === $result->get_error_code() ) {
			$data = $result->get_error_data();

			WP_CLI::confirm(
				sprintf(
					'This account delivers to %s. Replace it with this site?',
					is_array( $data ) && is_string( $data['url'] ?? null ) ? $data['url'] : ''
				),
				$assoc_args
			);

			$result = Route::take_over( $client, $email, Route::DEFAULT_EVENTS );
		}

		if ( $result instanceof WP_Error ) {
			WP_CLI::error( $result->get_error_message() );

			return;
		}

		update_option( Settings::OPTION_WEBHOOK_ENABLED, true );

		WP_CLI::success( sprintf( 'Assinafy now delivers to %s', $url ) );
	}

	/**
	 * Print one subscription.
	 *
	 * @param array<string, mixed>|null $subscription The account's subscription, or null.
	 * @param string                    $url          This site's own endpoint.
	 */
	private function print_subscription( ?array $subscription, string $url ): void {
		WP_CLI::line( 'This site endpoint: ' . $url );

		if ( null === $subscription ) {
			WP_CLI::line( 'No webhook subscription has ever been configured on this account.' );

			return;
		}

		$events  = $subscription['events'] ?? array();
		$current = (string) ( $subscription['url'] ?? '' );

		WP_CLI::line( 'Registered URL:    ' . $current );
		WP_CLI::line( 'Delivering:        ' . ( ( $subscription['is_active'] ?? false ) ? 'yes' : 'no' ) );
		WP_CLI::line( 'Failure alerts to: ' . (string) ( $subscription['email'] ?? '' ) );
		WP_CLI::line( 'Events:            ' . ( is_array( $events ) ? implode( ', ', array_map( 'strval', $events ) ) : '' ) );
		WP_CLI::line( 'Last changed:      ' . (string) ( $subscription['updated_at'] ?? '' ) );

		if ( untrailingslashit( $current ) !== untrailingslashit( $url ) ) {
			WP_CLI::warning( 'The account delivers somewhere else. This site will not receive webhooks until it is registered.' );
		}
	}
}
