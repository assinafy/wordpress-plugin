<?php
/**
 * Registering this site's webhook endpoint from the settings screen.
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
 * An Assinafy account has one webhook endpoint, or up to three on paid plans, and every
 * active endpoint subscribed to an event receives it — so this site gets an endpoint of its
 * own and nobody else's is touched. Registering finds this site's endpoint by URL (the prefix
 * match survives token rotation) and updates it in place, or creates one when none matches.
 * A full plan is an error to report, never an invitation to take a foreign endpoint over.
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
	 * The signing secret the fake endpoint hands out. Arbitrary test material.
	 */
	private const SECRET = 'whsec_d2ViaG9vay1yZWdpc3RhdGlvbi10ZXN0LWtleQ';

	/**
	 * Requests the fake intercepted, oldest first.
	 *
	 * @var array<int, array{method: string, url: string, body: array<string, mixed>}>
	 */
	private array $requests = array();

	/**
	 * The endpoints the account currently has on file.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $endpoints = array();

	/**
	 * Status the create route answers with.
	 */
	private int $post_status = 200;

	/**
	 * Arm the HTTP fake and give the plugin credentials and an endpoint.
	 */
	public function set_up(): void {
		parent::set_up();
		add_filter( 'pre_option_home', static fn(): string => 'https://example.test' );

		$this->requests    = array();
		$this->endpoints   = array();
		$this->post_status = 200;

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
	 * Serve the endpoint routes and pass every other host through untouched.
	 *
	 * `GET` on the collection answers {@see self::$endpoints}; `POST` records the body and
	 * appends the created endpoint; `PUT` records the body and merges it into the endpoint it
	 * addresses; the secret route answers {@see self::SECRET}. All of which is what the real
	 * routes do.
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
		if ( str_ends_with( $url, '/webhooks/event-types' ) ) {
			return $this->json_response(
				200,
				array(
					'status'  => 200,
					'message' => '',
					'data'    => array_map( static fn( string $event ): array => array( 'id' => $event ), Route::EVENTS ),
				)
			);
		}

		if ( 'POST' === $method ) {
			if ( 200 !== $this->post_status ) {
				return $this->json_response(
					$this->post_status,
					array(
						'status'  => $this->post_status,
						'message' => 'Limite de endpoints de webhook atingido.',
						'data'    => null,
					)
				);
			}

			$created           = array_merge( is_array( $body ) ? $body : array(), array( 'id' => 'ep_' . ( count( $this->endpoints ) + 1 ) ) );
			$this->endpoints[] = $created;

			return $this->json_response(
				200,
				array(
					'status'  => 200,
					'message' => '',
					'data'    => $created,
				)
			);
		}

		if ( 'PUT' === $method ) {
			foreach ( $this->endpoints as $index => $endpoint ) {
				if ( str_ends_with( $url, '/webhooks/endpoints/' . $endpoint['id'] ) ) {
					$this->endpoints[ $index ] = array_merge( $endpoint, is_array( $body ) ? $body : array() );

					return $this->json_response(
						200,
						array(
							'status'  => 200,
							'message' => '',
							'data'    => $this->endpoints[ $index ],
						)
					);
				}
			}

			return $this->json_response(
				404,
				array(
					'status'  => 404,
					'message' => 'Endpoint não encontrado.',
					'data'    => null,
				)
			);
		}

		if ( str_ends_with( $url, '/secret' ) ) {
			return $this->json_response(
				200,
				array(
					'status'  => 200,
					'message' => '',
					'data'    => array( 'secret' => self::SECRET ),
				)
			);
		}

		return $this->json_response(
			200,
			array(
				'status'  => 200,
				'message' => '',
				'data'    => $this->endpoints,
			)
		);
	}

	/**
	 * An account whose endpoints all belong elsewhere: this site's own is created, active and
	 * signed, and the foreign one is never written.
	 */
	public function test_a_first_registration_creates_this_sites_endpoint(): void {
		$this->endpoints = array( $this->foreign_endpoint() );

		$response = $this->register();

		$this->assertTrue( $response['success'] ?? false );

		$creates = $this->writes( 'POST' );

		$this->assertCount( 1, $creates );
		$this->assertSame( array(), $this->writes( 'PUT' ), 'A foreign endpoint was updated.' );

		$body = $creates[0]['body'];

		$this->assertSame( Route::url(), $body['url'] );
		$this->assertSame( (string) get_option( 'admin_email' ), $body['email'] );
		$this->assertSame( Route::DEFAULT_EVENTS, $body['events'] );
		$this->assertTrue( $body['is_active'] );
		$this->assertTrue( $body['signing_enabled'] );
		$this->assertSame( (string) wp_parse_url( Route::url(), PHP_URL_HOST ), $body['name'] );

		$this->assertSame( 'ep_2', get_option( Settings::OPTION_WEBHOOK_ENDPOINT_ID ) );
		$this->assertTrue( (bool) get_option( Settings::OPTION_WEBHOOK_ENABLED ) );
		$this->assertSame( self::SECRET, Route::secret(), 'The endpoint secret was not stored decryptably.' );
	}

	/**
	 * The endpoint already pointing at this site is updated in place, keeping every event it
	 * already had — even when the token in its URL is one this site has since rotated away
	 * from. Matching on the endpoint prefix rather than the whole URL is what makes rotation
	 * possible.
	 */
	public function test_registering_updates_this_sites_endpoint_and_keeps_its_events(): void {
		$stale = rest_url( Route::REST_NAMESPACE . '/webhook/' . str_repeat( 'f', Route::TOKEN_LENGTH ) );

		$this->endpoints = array(
			$this->own_endpoint(
				array(
					'url'    => $stale,
					'events' => array( 'signature_requested', 'document_prepared', 'assignment_created' ),
				)
			),
		);

		$response = $this->register();
		$updates  = $this->writes( 'PUT' );

		$this->assertTrue( $response['success'] ?? false );
		$this->assertCount( 1, $updates );
		$this->assertStringEndsWith( '/webhooks/endpoints/ep_own', $updates[0]['url'] );

		$body = $updates[0]['body'];

		$this->assertSame( Route::url(), $body['url'] );

		foreach ( array( 'signature_requested', 'document_prepared', 'assignment_created' ) as $kept ) {
			$this->assertContains( $kept, $body['events'], $kept . ' was dropped from the endpoint.' );
		}

		foreach ( Route::DEFAULT_EVENTS as $wanted ) {
			$this->assertContains( $wanted, $body['events'] );
		}

		$this->assertTrue( $body['is_active'] );
		$this->assertTrue( $body['signing_enabled'] );
		$this->assertSame( 'ep_own', get_option( Settings::OPTION_WEBHOOK_ENDPOINT_ID ) );
		$this->assertTrue( (bool) get_option( Settings::OPTION_WEBHOOK_ENABLED ) );
	}

	/**
	 * Only the fifteen subscribable events are ever sent.
	 *
	 * `template_created`, `template_processed` and `template_processing_failed` exist as SDK
	 * constants and in the published catalog, but `GET /webhooks/event-types` does not offer
	 * them — so one left on an endpoint by another tool must not be carried back in the name
	 * of keeping what was there.
	 */
	public function test_events_the_api_does_not_offer_are_dropped(): void {
		$this->endpoints = array(
			$this->own_endpoint( array( 'events' => array( 'template_created', 'document_ready', 'not_an_event', 'template_processed' ) ) ),
		);

		$this->register();
		$updates = $this->writes( 'PUT' );

		$this->assertCount( 1, $updates );
		$this->assertSame(
			array(),
			array_diff( $updates[0]['body']['events'], Route::EVENTS ),
			'An event outside the subscribable fifteen was sent.'
		);
	}

	/**
	 * An endpoint with an empty URL is not this site's and never produces one.
	 */
	public function test_an_endpoint_without_a_url_is_treated_as_absent(): void {
		$this->endpoints = array(
			array(
				'id'     => 'ep_empty',
				'url'    => '',
				'events' => array( 'document_ready' ),
			),
		);

		$response = $this->register();

		$this->assertTrue( $response['success'] ?? false );
		$this->assertCount( 1, $this->writes( 'POST' ) );
		$this->assertSame( array(), $this->writes( 'PUT' ), 'The URL-less endpoint was updated.' );
	}

	/**
	 * A full plan is an error naming the remedy, not a takeover: nothing is created, updated
	 * or deleted, and deliveries stay switched off locally.
	 */
	public function test_a_full_plan_is_an_error_not_a_takeover(): void {
		$this->endpoints   = array(
			$this->foreign_endpoint(),
			$this->foreign_endpoint( 'ep_two' ),
			$this->foreign_endpoint( 'ep_three' ),
		);
		$this->post_status = 403;

		$response = $this->register();

		$this->assertFalse( $response['success'] ?? true );
		$this->assertSame( 'Limite de endpoints de webhook atingido.', $response['data']['message'] ?? '' );
		$this->assertCount( 1, $this->writes( 'POST' ) );
		$this->assertSame( array(), $this->writes( 'PUT' ) );
		$this->assertFalse( (bool) get_option( Settings::OPTION_WEBHOOK_ENABLED ) );
		$this->assertSame( '', (string) get_option( Settings::OPTION_WEBHOOK_ENDPOINT_ID, '' ) );
	}

	/**
	 * A registration the API refuses leaves deliveries switched off.
	 *
	 * Recording the endpoint as armed after a failed write would leave the site answering
	 * deliveries the account was never told to send.
	 */
	public function test_a_refused_write_does_not_arm_the_endpoint(): void {
		$this->post_status = 422;

		$response = $this->register();

		$this->assertFalse( $response['success'] ?? true );
		$this->assertFalse( (bool) get_option( Settings::OPTION_WEBHOOK_ENABLED ) );
		$this->assertSame( '', (string) get_option( Settings::OPTION_WEBHOOK_ENDPOINT_ID, '' ) );
	}

	/**
	 * An endpoint list that cannot be read is reported, not assumed absent.
	 *
	 * Treating an unreadable list as "there isn't one" would create a second endpoint for a
	 * site that already has one.
	 */
	public function test_an_unreadable_endpoint_list_is_not_treated_as_absent(): void {
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
		$this->assertSame( array(), $this->writes( 'POST' ), 'An endpoint was created without reading the list.' );
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
	 * An endpoint this site owns, as the API returns it.
	 *
	 * @param array<string, mixed> $overrides Keys to replace.
	 *
	 * @return array<string, mixed>
	 */
	private function own_endpoint( array $overrides = array() ): array {
		return array_merge(
			array(
				'id'              => 'ep_own',
				'name'            => 'localhost',
				'url'             => Route::url(),
				'email'           => 'ops@example.com',
				'events'          => array( 'document_ready' ),
				'is_active'       => true,
				'signing_enabled' => true,
			),
			$overrides
		);
	}

	/**
	 * The endpoint another integration on this account already owns.
	 *
	 * @param string $id Endpoint id.
	 *
	 * @return array<string, mixed>
	 */
	private function foreign_endpoint( string $id = 'ep_foreign' ): array {
		return array(
			'id'              => $id,
			'name'            => 'ERP',
			'url'             => self::FOREIGN_URL,
			'email'           => 'ops@example.com',
			'events'          => array( 'signature_requested', 'document_prepared', 'assignment_created' ),
			'is_active'       => false,
			'signing_enabled' => false,
		);
	}

	/**
	 * Run the registration handler and return its decoded JSON response.
	 *
	 * @return array<string, mixed>
	 */
	private function register(): array {
		$_POST['nonce'] = wp_create_nonce( 'assinafy_admin' );

		try {
			$this->_handleAjax( 'assinafy_register_webhook' );
		} catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- the response is read from the buffer below.
			unset( $e );
		}

		$decoded = json_decode( $this->_last_response, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/** A database failure must not be reported as a fully configured signed endpoint. */
	public function test_registration_reports_a_secret_storage_failure(): void {
		$refuse = static fn( mixed $value, mixed $old ): mixed => $old;
		add_filter( 'pre_update_option_' . Settings::OPTION_WEBHOOK_SECRET_ENC, $refuse, PHP_INT_MAX, 2 );
		try {
			$response = $this->register();
		} finally {
			remove_filter( 'pre_update_option_' . Settings::OPTION_WEBHOOK_SECRET_ENC, $refuse, PHP_INT_MAX );
		}
		$this->assertFalse( $response['success'] ?? true );
		$this->assertStringContainsString( 'signing secret could not be stored', $response['data']['message'] ?? '' );
		$this->assertFalse( (bool) get_option( Settings::OPTION_WEBHOOK_ENABLED ) );
	}

	/**
	 * Every write the handler sent.
	 *
	 * @param string $method HTTP method to select.
	 *
	 * @return array<int, array{method: string, url: string, body: array<string, mixed>}>
	 */
	private function writes( string $method ): array {
		return array_values(
			array_filter(
				$this->requests,
				static fn( array $request ): bool => $method === $request['method']
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
