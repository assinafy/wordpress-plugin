<?php
/**
 * The settings screen's two API-backed buttons.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Admin;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\ClientFactory;
use Assinafy\WP\Log;
use Assinafy\WP\Settings;
use Assinafy\WP\Webhook\Route;
use WP_Error;

/**
 * Serves `admin-ajax.php` for the settings screen.
 *
 * Both endpoints call Assinafy, which is what separates them from the rest of the settings
 * screen: everything else there reads and writes options, and these two spend a request on
 * the API and report what it said.
 *
 * Every entry point repeats the same two checks — the `assinafy_admin` nonce and
 * `manage_options` — because `admin-ajax.php` is reachable by any logged-in user and neither
 * check is inherited from the screen that renders the button.
 */
final class SettingsAjax {

	/**
	 * @param ClientFactory $clients API client factory.
	 * @param Log           $log     Plugin event log.
	 */
	public function __construct(
		private readonly ClientFactory $clients,
		private readonly Log $log
	) {
	}

	/**
	 * Hook both endpoints.
	 */
	public function register(): void {
		add_action( 'wp_ajax_assinafy_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_assinafy_register_webhook', array( $this, 'ajax_register_webhook' ) );
	}

	/**
	 * Confirm the credentials against the API.
	 */
	public function ajax_test_connection(): void {
		check_ajax_referer( 'assinafy_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You are not allowed to do that.', 'assinafy' ) ), 403 );
		}

		$client = $this->clients->client();

		if ( null === $client ) {
			$error = $this->clients->error();

			wp_send_json_error(
				array(
					'message' => esc_html(
						null !== $error
							? $error->get_error_message()
							: __( 'Enter an account id and an API key first.', 'assinafy' )
					),
				)
			);
		}

		// Only the API call belongs inside the try. `wp_send_json_*()` ends the request through
		// `wp_die()`, and a `wp_die` handler is allowed to raise rather than halt — WordPress's
		// own test suite installs one that does. A success response emitted inside the try
		// would be caught by this `\Throwable` handler and answered again as a failure.
		try {
			$account = $client->accounts()->get();
		} catch ( \Throwable $e ) {
			$this->log->add(
				'connection_failed',
				array(
					'error' => $e->getMessage(),
					'type'  => get_debug_type( $e ),
				)
			);

			wp_send_json_error( array( 'message' => esc_html( $e->getMessage() ) ) );
		}

		$this->log->add( 'connection_tested', array( 'environment' => (string) Settings::get( Settings::OPTION_ENVIRONMENT ) ) );

		wp_send_json_success(
			array(
				'message' => esc_html(
					sprintf(
						/* translators: %s: Assinafy account name. */
						__( 'Connected to %s.', 'assinafy' ),
						is_string( $account['name'] ?? null ) ? $account['name'] : __( 'your account', 'assinafy' )
					)
				),
			)
		);
	}

	/**
	 * Point the account's webhook subscription at this site.
	 *
	 * The subscription is account-wide and singular, so registering from here replaces
	 * whatever is on file. An existing subscription pointing somewhere else is reported
	 * back and only replaced when the request repeats with an explicit confirmation.
	 */
	public function ajax_register_webhook(): void {
		check_ajax_referer( 'assinafy_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You are not allowed to do that.', 'assinafy' ) ), 403 );
		}

		$client = $this->clients->client();

		if ( null === $client ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Connect the plugin to Assinafy before registering the webhook.', 'assinafy' ) ) );
		}

		$confirmed = isset( $_POST['confirm'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['confirm'] ) );

		// `Route` owns the whole upsert: it reads the account's current subscription and keeps
		// the events already on it. Doing any of that here as well would give the screen and
		// WP-CLI two answers to the same question. The pair differs only in what happens to a
		// foreign URL — `subscribe()` reports it, `take_over()` replaces it.
		$email  = (string) get_option( 'admin_email' );
		$result = $confirmed
			? Route::take_over( $client, $email, Route::DEFAULT_EVENTS )
			: Route::subscribe( $client, $email, Route::DEFAULT_EVENTS );

		if ( $result instanceof WP_Error ) {
			$this->fail_webhook_registration( $result );
		}

		$url = Route::url();

		update_option( Settings::OPTION_WEBHOOK_ENABLED, true );

		$this->log->add( 'webhook_registered', array( 'url' => $url ) );

		wp_send_json_success( array( 'message' => esc_html__( 'Webhook registered. Assinafy will notify this site as documents progress.', 'assinafy' ) ) );
	}

	/**
	 * Answer a failed webhook registration.
	 *
	 * A takeover is not logged as a failure: it is the API reporting that the account already
	 * delivers somewhere else, and the screen answers it by asking for a second click. The
	 * subscription is account-wide and singular — one URL for the whole Assinafy account, not
	 * one per integration — so taking it over silently would stop deliveries to whatever else
	 * the customer runs.
	 *
	 * Never returns: `wp_send_json_error()` ends the request through `wp_die()`, which either
	 * halts or — under a `wp_die` handler that raises, as the test suite installs — throws.
	 *
	 * @param WP_Error $error What went wrong.
	 */
	private function fail_webhook_registration( WP_Error $error ): never {
		if ( 'assinafy_webhook_takeover' === $error->get_error_code() ) {
			$data = $error->get_error_data();

			wp_send_json_error(
				array(
					'message'              => esc_html(
						sprintf(
							/* translators: %s: currently registered webhook URL. */
							__( 'This Assinafy account already delivers webhooks to %s. Registering this site replaces that subscription.', 'assinafy' ),
							is_array( $data ) && is_string( $data['url'] ?? null ) ? $data['url'] : ''
						)
					),
					'requiresConfirmation' => true,
				)
			);
		}

		$this->log->add(
			'webhook_registration_failed',
			array(
				'error' => $error->get_error_message(),
				'code'  => $error->get_error_code(),
			)
		);

		wp_send_json_error( array( 'message' => esc_html( $error->get_error_message() ) ) );
	}
}
