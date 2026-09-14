<?php
/**
 * The two admin-ajax endpoints on the settings screen.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Integration;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Credentials;
use Assinafy\WP\Settings;
use WP_Ajax_UnitTestCase;

/**
 * Both handlers take an unauthenticated request and turn it into an authenticated API call
 * using the site's stored credentials, so the nonce and the capability check are the only
 * things standing between a visitor and the workspace. They are exercised through
 * `_handleAjax()` rather than by calling the method, because that is the only path that runs
 * `check_ajax_referer()` the way WordPress runs it.
 *
 * These carry `@group ajax`: WordPress's own bootstrap excludes that group unless it is asked
 * for, so the suite must be run with `--group ajax` to reach them.
 *
 * @group ajax
 *
 * @covers \Assinafy\WP\Settings
 */
final class SettingsAjaxTest extends WP_Ajax_UnitTestCase {

	/**
	 * Account id used throughout. Placeholder; the real one is never written to a file.
	 */
	private const ACCOUNT_ID = '104618a0000000000000000001';

	/**
	 * Every URL the plugin may address.
	 */
	private const API_HOST = 'https://api.assinafy.com.br/';

	/**
	 * Requests the fake intercepted.
	 *
	 * @var array<int, string>
	 */
	private array $requests = array();

	/**
	 * Arm the HTTP fake.
	 *
	 * `Settings` is not registered here: the plugin already did that during boot, and
	 * registering a second instance would attach a second callback to each action, so every
	 * handler would run twice and `_last_response` would hold two concatenated payloads.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->requests = array();

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
	 * Answer requests to the Assinafy host and pass everything else through untouched.
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

		$this->requests[] = $url;

		$body = wp_json_encode(
			array(
				'status'  => 200,
				'message' => '',
				'data'    => array(
					'resource' => 'account',
					'id'       => self::ACCOUNT_ID,
					'name'     => 'Example Workspace',
				),
			)
		);

		return array(
			'headers'  => array(),
			'body'     => (string) $body,
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Give the plugin usable credentials.
	 */
	private function configure_plugin(): void {
		update_option( Settings::OPTION_ACCOUNT_ID, self::ACCOUNT_ID );
		( new Credentials() )->set_api_key( 'test-api-key' );
	}

	/**
	 * Run one handler and return its decoded JSON response.
	 *
	 * @param string $action admin-ajax action name.
	 *
	 * @return array<string, mixed>
	 */
	private function dispatch( string $action ): array {
		try {
			$this->_handleAjax( $action );
		} catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- the response is read from the buffer below.
			unset( $e );
		}

		$decoded = json_decode( $this->_last_response, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * A request without a valid nonce must die before anything else happens — in particular
	 * before the capability check, and before any request reaches the API.
	 */
	public function test_test_connection_requires_a_nonce(): void {
		$this->configure_plugin();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$_POST['nonce'] = 'not-a-valid-nonce';

		$this->expectException( \WPAjaxDieStopException::class );

		try {
			$this->_handleAjax( 'assinafy_test_connection' );
		} finally {
			$this->assertSame( array(), $this->requests, 'A request reached the API without a valid nonce.' );
		}
	}

	/**
	 * A valid nonce is not authorisation. A subscriber holding one must still be refused,
	 * and must not cause an API call.
	 */
	public function test_test_connection_requires_the_capability(): void {
		$this->configure_plugin();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$_POST['nonce'] = wp_create_nonce( 'assinafy_admin' );

		$response = $this->dispatch( 'assinafy_test_connection' );

		$this->assertFalse( $response['success'] ?? true );
		$this->assertSame( array(), $this->requests, 'A subscriber caused a request to the API.' );
	}

	/**
	 * An administrator with a valid nonce reaches the API, and the account name comes back.
	 */
	public function test_test_connection_reports_the_account(): void {
		$this->configure_plugin();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$_POST['nonce'] = wp_create_nonce( 'assinafy_admin' );

		$response = $this->dispatch( 'assinafy_test_connection' );

		$this->assertTrue( $response['success'] ?? false );
		$this->assertStringContainsString( 'Example Workspace', (string) ( $response['data']['message'] ?? '' ) );
		$this->assertCount( 1, $this->requests );
	}

	/**
	 * With no credentials stored there is nothing to test, and the handler must say so
	 * rather than build a client and let the API answer 401.
	 */
	public function test_test_connection_without_credentials_makes_no_request(): void {
		// The factory memoises its client for the life of the request, and the plugin's object
		// graph outlives a single test, so a client another test built is still held here.
		// Writing the options and then removing them drops the memo through the `added_option`
		// and `deleted_option` hooks `Settings` listens on — deleting an option that was never
		// stored fires nothing.
		update_option( Settings::OPTION_ACCOUNT_ID, self::ACCOUNT_ID );
		update_option( Settings::OPTION_API_KEY, 'placeholder' );
		delete_option( Settings::OPTION_API_KEY );
		delete_option( Settings::OPTION_ACCOUNT_ID );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$_POST['nonce'] = wp_create_nonce( 'assinafy_admin' );

		$response = $this->dispatch( 'assinafy_test_connection' );

		$this->assertFalse( $response['success'] ?? true );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * The webhook registration endpoint is gated the same way, and a subscriber holding a
	 * valid nonce must not be able to point the account's single webhook at anything.
	 */
	public function test_register_webhook_requires_the_capability(): void {
		$this->configure_plugin();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$_POST['nonce'] = wp_create_nonce( 'assinafy_admin' );

		$response = $this->dispatch( 'assinafy_register_webhook' );

		$this->assertFalse( $response['success'] ?? true );
		$this->assertSame( array(), $this->requests, 'A subscriber reached the webhook subscription.' );
	}

	/**
	 * And it must reject a missing nonce before the capability check.
	 */
	public function test_register_webhook_requires_a_nonce(): void {
		$this->configure_plugin();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$_POST['nonce'] = 'not-a-valid-nonce';

		$this->expectException( \WPAjaxDieStopException::class );

		try {
			$this->_handleAjax( 'assinafy_register_webhook' );
		} finally {
			$this->assertSame( array(), $this->requests );
		}
	}
}
