<?php
/**
 * Taking over the account's single webhook subscription from the settings screen.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Integration;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Credentials;
use Assinafy\WP\Settings;
use Assinafy\WP\Webhook\Route;
use WP_Ajax_UnitTestCase;

/**
 * An Assinafy account has exactly one webhook subscription: one URL, one event list, one
 * `is_active` flag, for every integration the customer runs against that account. The upsert
 * is wholesale — `PUT /accounts/{id}/webhooks/subscriptions` takes all four fields and has no
 * partial form — so pressing the button on this screen can silently stop deliveries to
 * someone else's endpoint and drop events someone else depends on.
 *
 * These tests hold the two guards that stop it: a foreign URL is not replaced until the
 * request comes back confirmed, and the events already on the subscription survive the
 * replacement.
 *
 * These carry `@group ajax`: WordPress's own bootstrap excludes that group unless it is asked
 * for, so the suite must be run with `--group ajax` to reach them.
 *
 * @group ajax
 *
 * @covers \Assinafy\WP\Settings
 * @covers \Assinafy\WP\Webhook\Route
 */
final class WebhookRegistrationAjaxTest extends WP_Ajax_UnitTestCase {

	/**
	 * Account id used throughout. Placeholder; the real one is never written to a file.
	 */
	private const ACCOUNT_ID = '104618a0000000000000000001';

	/**
	 * Every URL the plugin may address.
	 */
	private const API_HOST = 'https://api.assinafy.com.br/';

	/**
	 * The token the site has issued.
	 */
	private const TOKEN = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6';

	/**
	 * The endpoint another integration on the same account already uses.
	 */
	private const FOREIGN_URL = 'https://hooks.example.test/assinafy';

	/**
	 * Requests the fake intercepted, oldest first.
	 *
	 * @var array<int, array{method: string, url: string, body: array<string, mixed>}>
	 */
	private array $requests = array();

	/**
	 * The subscription the account currently has on file, or null when it has never had one.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $subscription = null;

	/**
	 * Status the `PUT` answers with.
	 */
	private int $put_status = 200;

	/**
	 * Arm the HTTP fake and give the plugin credentials and an endpoint.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->requests     = array();
		$this->subscription = null;
		$this->put_status   = 200;

		update_option( Settings::OPTION_ACCOUNT_ID, self::ACCOUNT_ID );
		update_option( Settings::OPTION_WEBHOOK_TOKEN, self::TOKEN, false );
		update_option( Settings::OPTION_WEBHOOK_ENABLED, false );
		( new Credentials() )->set_api_key( 'test-api-key' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		add_filter( 'pre_http_request', array( $this, 'intercept_http' ), 10, 3 );
	}

	/**
	 * Disarm the fake. Leaving a `pre_http_request` filter installed would replace the
	 * transport for every test that runs after this one.
	 */
	public function tear_down(): void {
		remove_filter( 'pre_http_request', array( $this, 'intercept_http' ), 10 );

		parent::tear_down();
	}

	/**
	 * Serve the subscription endpoints and pass every other host through untouched.
	 *
	 * `GET` answers whatever {@see self::$subscription} currently holds; `PUT` records the
	 * body and echoes it back, which is what the real endpoint does.
	 *
	 * @param mixed                $preempt Whatever an earlier filter decided.
	 * @param array<string, mixed> $args    Parsed request arguments.
	 * @param string               $url     Request URL.
	 *
	 * @return mixed A response array for the Assinafy host, `$preempt` for anything else.
	 */
	public function intercept_http( mixed $preempt, array $args, string $url ): mixed {
		if ( ! str_starts_with( $url, self::API_HOST ) ) {
			return $preempt;
		}

		$method = strtoupper( (string) ( $args['method'] ?? 'GET' ) );
		$body   = json_decode( (string) ( $args['body'] ?? '' ), true );

		$this->requests[] = array(
			'method' => $method,
			'url'    => $url,
			'body'   => is_array( $body ) ? $body : array(),
		);

		if ( 'PUT' === $method ) {
			if ( 200 !== $this->put_status ) {
				return $this->json_response(
					$this->put_status,
					array(
						'status'  => $this->put_status,
						'message' => 'A URL do webhook é inválida.',
						'data'    => null,
					)
				);
			}

			return $this->json_response(
				200,
				array(
					'status'  => 200,
					'message' => '',
					'data'    => is_array( $body ) ? $body : array(),
				)
			);
		}

		return $this->json_response(
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => $this->subscription ?? array(),
			)
		);
	}

	/**
	 * Nothing is replaced until the operator confirms, and nothing is enabled either.
	 */
	public function test_a_foreign_subscription_is_not_replaced_without_confirmation(): void {
		$this->subscription = $this->foreign_subscription();

		$response = $this->register();

		$this->assertFalse( $response['success'] ?? true );
		$this->assertTrue( $response['data']['requiresConfirmation'] ?? false );
		$this->assertStringContainsString( self::FOREIGN_URL, (string) ( $response['data']['message'] ?? '' ) );
		$this->assertSame( array(), $this->puts(), 'The subscription was replaced without a confirmation.' );
		$this->assertFalse( (bool) get_option( Settings::OPTION_WEBHOOK_ENABLED ) );
	}

	/**
	 * A confirmed takeover replaces the URL and keeps every event the account already had.
	 *
	 * The upsert has no partial form, so the event list has to be sent in full. Sending only
	 * this plugin's own defaults would quietly unsubscribe whatever else the account feeds.
	 */
	public function test_a_confirmed_takeover_keeps_the_events_already_on_file(): void {
		$this->subscription = $this->foreign_subscription();

		$response = $this->register( true );
		$put      = $this->puts();

		$this->assertTrue( $response['success'] ?? false );
		$this->assertCount( 1, $put );
		$this->assertSame( Route::url(), $put[0]['body']['url'] );

		foreach ( array( 'signature_requested', 'document_prepared', 'assignment_created' ) as $kept ) {
			$this->assertContains( $kept, $put[0]['body']['events'], $kept . ' was dropped from the account subscription.' );
		}

		foreach ( Route::DEFAULT_EVENTS as $wanted ) {
			$this->assertContains( $wanted, $put[0]['body']['events'] );
		}

		$this->assertTrue( (bool) get_option( Settings::OPTION_WEBHOOK_ENABLED ) );
	}

	/**
	 * A subscription already pointing at this site is replaced without asking, even when the
	 * token in it is one the site has since rotated away from.
	 *
	 * Matching on the endpoint prefix rather than the whole URL is what makes rotation
	 * possible: after a rotation the subscription still names this site, and asking the
	 * operator to confirm a takeover of their own endpoint would both be wrong and print the
	 * retired token back at them.
	 */
	public function test_this_site_under_a_rotated_token_is_not_a_takeover(): void {
		$stale = rest_url( Route::REST_NAMESPACE . '/webhook/' . str_repeat( 'f', Route::TOKEN_LENGTH ) );

		$this->subscription = array(
			'events'     => array( 'document_ready' ),
			'is_active'  => true,
			'url'        => $stale,
			'email'      => 'ops@example.com',
			'updated_at' => '2026-08-27T17:55:12Z',
		);

		$response = $this->register();
		$put      = $this->puts();

		$this->assertTrue( $response['success'] ?? false, 'Re-registering this site asked for a takeover confirmation.' );
		$this->assertCount( 1, $put );
		$this->assertSame( Route::url(), $put[0]['body']['url'] );
	}

	/**
	 * An account that has never configured a subscription needs no confirmation.
	 */
	public function test_a_first_registration_needs_no_confirmation(): void {
		$response = $this->register();
		$put      = $this->puts();

		$this->assertTrue( $response['success'] ?? false );
		$this->assertCount( 1, $put );
		$this->assertSame( Route::DEFAULT_EVENTS, $put[0]['body']['events'] );
		$this->assertSame( Route::url(), $put[0]['body']['url'] );
		$this->assertTrue( $put[0]['body']['is_active'] );
	}

	/**
	 * Only the fifteen subscribable events are ever sent.
	 *
	 * `template_created`, `template_processed` and `template_processing_failed` exist as SDK
	 * constants and in the published catalog, but `GET /webhooks/event-types` does not offer
	 * them and the upsert rejects them — so one left on a subscription by another tool must
	 * not be carried back in the name of keeping what was there.
	 */
	public function test_events_the_api_does_not_offer_are_dropped(): void {
		$this->subscription = array(
			'events'     => array( 'template_created', 'document_ready', 'not_an_event', 'template_processed' ),
			'is_active'  => false,
			'url'        => self::FOREIGN_URL,
			'email'      => 'ops@example.com',
			'updated_at' => '2026-08-27T17:55:12Z',
		);

		$this->register( true );
		$put = $this->puts();

		$this->assertCount( 1, $put );
		$this->assertSame(
			array(),
			array_diff( $put[0]['body']['events'], Route::EVENTS ),
			'An event outside the subscribable fifteen was sent.'
		);
	}

	/**
	 * A registration the API refuses leaves deliveries switched off.
	 *
	 * Recording the endpoint as armed after a failed upsert would leave the site answering
	 * deliveries the account was never told to send.
	 */
	public function test_a_refused_upsert_does_not_arm_the_endpoint(): void {
		$this->put_status = 422;

		$response = $this->register();

		$this->assertFalse( $response['success'] ?? true );
		$this->assertArrayNotHasKey( 'requiresConfirmation', $response['data'] ?? array() );
		$this->assertFalse( (bool) get_option( Settings::OPTION_WEBHOOK_ENABLED ) );
	}

	/**
	 * A subscription that cannot be read is reported, not assumed absent.
	 *
	 * Treating an unreadable subscription as "there isn't one" would replace a foreign
	 * endpoint without ever asking.
	 */
	public function test_an_unreadable_subscription_is_not_treated_as_absent(): void {
		remove_filter( 'pre_http_request', array( $this, 'intercept_http' ), 10 );
		add_filter(
			'pre_http_request',
			function ( mixed $preempt, array $args, string $url ): mixed {
				if ( ! str_starts_with( $url, self::API_HOST ) ) {
					return $preempt;
				}

				$this->requests[] = array(
					'method' => strtoupper( (string) ( $args['method'] ?? 'GET' ) ),
					'url'    => $url,
					'body'   => array(),
				);

				return new \WP_Error( 'http_request_failed', 'Connection timed out.' );
			},
			10,
			3
		);

		$response = $this->register();

		$this->assertFalse( $response['success'] ?? true );
		$this->assertSame( array(), $this->puts(), 'A subscription that could not be read was overwritten anyway.' );
		$this->assertFalse( (bool) get_option( Settings::OPTION_WEBHOOK_ENABLED ) );
	}

	/**
	 * Registration without an endpoint to register makes no request at all.
	 */
	public function test_a_site_without_a_token_registers_nothing(): void {
		update_option( Settings::OPTION_WEBHOOK_TOKEN, '', false );

		$response = $this->register();

		$this->assertFalse( $response['success'] ?? true );
		$this->assertSame( array(), $this->requests );
		$this->assertFalse( (bool) get_option( Settings::OPTION_WEBHOOK_ENABLED ) );
	}

	/**
	 * The subscription another integration on this account already owns.
	 *
	 * @return array<string, mixed>
	 */
	private function foreign_subscription(): array {
		return array(
			'events'     => array( 'signature_requested', 'document_prepared', 'assignment_created' ),
			'is_active'  => false,
			'url'        => self::FOREIGN_URL,
			'email'      => 'ops@example.com',
			'updated_at' => '2026-08-27T17:55:12Z',
		);
	}

	/**
	 * Run the registration handler and return its decoded JSON response.
	 *
	 * @param bool $confirmed Whether the operator has confirmed a takeover.
	 *
	 * @return array<string, mixed>
	 */
	private function register( bool $confirmed = false ): array {
		$_POST['nonce'] = wp_create_nonce( 'assinafy_admin' );

		if ( $confirmed ) {
			$_POST['confirm'] = '1';
		} else {
			unset( $_POST['confirm'] );
		}

		try {
			$this->_handleAjax( 'assinafy_register_webhook' );
		} catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- the response is read from the buffer below.
			unset( $e );
		}

		$decoded = json_decode( $this->_last_response, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Every upsert the handler sent.
	 *
	 * @return array<int, array{method: string, url: string, body: array<string, mixed>}>
	 */
	private function puts(): array {
		return array_values(
			array_filter(
				$this->requests,
				static fn( array $request ): bool => 'PUT' === $request['method']
			)
		);
	}

	/**
	 * Build the array shape `WP_Http` hands back to `wp_remote_request()`.
	 *
	 * @param int                  $code     HTTP status line.
	 * @param array<string, mixed> $envelope Response body.
	 *
	 * @return array<string, mixed>
	 */
	private function json_response( int $code, array $envelope ): array {
		return array(
			'headers'  => array( 'content-type' => 'application/json; charset=UTF-8' ),
			'body'     => (string) wp_json_encode( $envelope ),
			'response' => array(
				'code'    => $code,
				'message' => get_status_header_desc( $code ),
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}
}
