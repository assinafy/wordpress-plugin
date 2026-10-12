<?php
/**
 * Webhook endpoint registration and delivery signing.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Unit;

use Assinafy\SDK\AssinafyClient;
use Assinafy\SDK\Configuration;
use Assinafy\WP\ClientFactory;
use Assinafy\WP\Credentials;
use Assinafy\WP\Documents\DocumentRecord;
use Assinafy\WP\Documents\StatusSync;
use Assinafy\WP\Http\WpHttpClient;
use Assinafy\WP\Log;
use Assinafy\WP\Settings;
use Assinafy\WP\Tests\Unit\Support\FakeHttp;
use Assinafy\WP\Webhook\Handler;
use Assinafy\WP\Webhook\Route;
use PHPUnit\Framework\TestCase;
use WP_Error;
use WP_REST_Request;

/**
 * Drives the endpoint routes over the {@see FakeHttp} transport double, and the signature
 * gate with real signatures computed the way the platform computes them.
 *
 * @covers \Assinafy\WP\Webhook\Route
 * @covers \Assinafy\WP\Settings
 */
final class WebhookEndpointTest extends TestCase {

	/**
	 * The token the site has issued. Thirty-two alphanumerics, as the route pattern demands.
	 */
	private const TOKEN = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6';

	/**
	 * Placeholder account id, shaped like a real one: opaque lowercase hex.
	 */
	private const ACCOUNT_ID = '0e3a1c4b9f2d47a8b6c05e19';

	/**
	 * Placeholder credential. The real key never appears in this repository.
	 */
	private const API_KEY = 'test-api-key-not-a-real-credential';

	/**
	 * The raw key behind the test `whsec_` secret. Arbitrary test material.
	 */
	private const SIGNING_KEY = 'unit-test-signing-key-32-bytes-!';

	private Credentials $credentials;

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['assinafy_test_options']         = array();
		$GLOBALS['assinafy_test_transients']      = array();
		$GLOBALS['assinafy_test_settings_errors'] = array();
		FakeHttp::reset();

		update_option( Settings::OPTION_WEBHOOK_TOKEN, self::TOKEN, false );

		$this->credentials = new Credentials();
	}

	protected function tearDown(): void {
		FakeHttp::reset();

		parent::tearDown();
	}

	/**
	 * With no secret stored, the token alone authenticates, exactly as it always has.
	 */
	public function test_a_delivery_passes_with_the_token_alone_when_no_secret_is_configured(): void {
		$result = $this->route()->check_token( new WP_REST_Request( array( 'token' => self::TOKEN ), '{"id":30022}' ) );

		$this->assertTrue( $result );
	}

	/**
	 * A delivery signed with the stored secret passes.
	 */
	public function test_a_correctly_signed_delivery_passes(): void {
		$this->store_secret();
		$body = '{"id":30022,"event":"document_ready"}';

		$result = $this->route()->check_token(
			new WP_REST_Request( array( 'token' => self::TOKEN ), $body, $this->signed_headers( $body ) )
		);

		$this->assertTrue( $result );
	}

	/**
	 * A signature that does not match the body fails closed.
	 */
	public function test_a_wrong_signature_is_refused(): void {
		$this->store_secret();
		$body    = '{"id":30022,"event":"document_ready"}';
		$headers = $this->signed_headers( '{"id":30022,"event":"signer_signed_document"}' );

		$result = $this->route()->check_token( new WP_REST_Request( array( 'token' => self::TOKEN ), $body, $headers ) );

		$this->assert_bad_signature( $result );
	}

	/**
	 * A correctly signed delivery outside the 300-second tolerance is a replay, not news.
	 */
	public function test_a_stale_timestamp_is_refused(): void {
		$this->store_secret();
		$body = '{"id":30022}';

		$result = $this->route()->check_token(
			new WP_REST_Request( array( 'token' => self::TOKEN ), $body, $this->signed_headers( $body, time() - 301 ) )
		);

		$this->assert_bad_signature( $result );
	}

	/**
	 * Once a secret is configured, an unsigned delivery no longer authenticates.
	 */
	public function test_an_unsigned_delivery_is_refused_while_a_secret_is_configured(): void {
		$this->store_secret();

		$result = $this->route()->check_token( new WP_REST_Request( array( 'token' => self::TOKEN ), '{"id":30022}' ) );

		$this->assert_bad_signature( $result );
	}

	/**
	 * A stored value that is not a `whsec_` secret makes the parser raise; the gate catches
	 * that and refuses rather than letting the exception escape or the delivery through.
	 */
	public function test_a_malformed_stored_secret_fails_closed(): void {
		update_option( Settings::OPTION_WEBHOOK_SECRET_ENC, $this->credentials->encrypt( 'not-a-whsec-secret' ), false );
		$body = '{"id":30022}';

		$result = $this->route()->check_token(
			new WP_REST_Request( array( 'token' => self::TOKEN ), $body, $this->signed_headers( $body ) )
		);

		$this->assert_bad_signature( $result );
	}

	/**
	 * A blob the cipher can no longer read — rotated salts — degrades to token-only
	 * authentication rather than refusing every delivery, and the reason is logged.
	 */
	public function test_an_unreadable_stored_secret_degrades_to_the_token(): void {
		update_option( Settings::OPTION_WEBHOOK_SECRET_ENC, bin2hex( chr( 1 ) . random_bytes( 64 ) ), false );

		$this->assertSame( '', Route::secret() );

		$result = $this->route()->check_token( new WP_REST_Request( array( 'token' => self::TOKEN ), '{"id":30022}' ) );

		$this->assertTrue( $result );

		$events = array_map(
			static fn( array $entry ): string => (string) ( $entry['event'] ?? '' ),
			( new Log() )->entries()
		);
		$this->assertContains( 'webhook_secret_unreadable', $events );
	}

	/**
	 * The signature layer never substitutes for the token.
	 */
	public function test_a_wrong_token_is_refused_even_with_a_valid_signature(): void {
		$this->store_secret();
		$body = '{"id":30022}';

		$result = $this->route()->check_token(
			new WP_REST_Request( array( 'token' => str_repeat( 'f', Route::TOKEN_LENGTH ) ), $body, $this->signed_headers( $body ) )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'assinafy_webhook_forbidden', $result->get_error_code() );
	}

	/**
	 * No endpoint on the account names this site: a new one is created, active and signed,
	 * and its id and secret are stored.
	 */
	public function test_subscribe_creates_an_endpoint_when_none_matches(): void {
		$this->queue_envelope( 200, array( $this->foreign_endpoint() ) );
		$this->queue_envelope( 200, $this->own_endpoint( array( 'id' => 'ep_new' ) ) );
		$this->queue_secret();

		$result = Route::subscribe( $this->client(), 'ops@example.com' );

		$this->assertIsArray( $result );
		$this->assertCount( 3, FakeHttp::$requests );
		$this->assertStringEndsWith( '/webhooks/endpoints', (string) FakeHttp::$requests[1]['url'] );

		$body = $this->request_body( 1 );
		$this->assertSame( 'POST', strtoupper( (string) FakeHttp::$requests[1]['args']['method'] ) );
		$this->assertSame( Route::url(), $body['url'] );
		$this->assertSame( 'ops@example.com', $body['email'] );
		$this->assertSame( Route::DEFAULT_EVENTS, $body['events'] );
		$this->assertTrue( $body['is_active'] );
		$this->assertTrue( $body['signing_enabled'] );
		$this->assertSame( 'example.test', $body['name'] );

		$this->assertSame( 'ep_new', get_option( Settings::OPTION_WEBHOOK_ENDPOINT_ID ) );
		$this->assertStringEndsWith( '/webhooks/endpoints/ep_new/secret', (string) FakeHttp::$requests[2]['url'] );
		$this->assertSame( self::whsec(), Route::secret(), 'The fetched secret was not stored decryptably.' );
	}

	/**
	 * The endpoint already pointing at this site — even under a rotated-away token — is
	 * updated in place, keeping the events it already had.
	 */
	public function test_subscribe_updates_the_matching_endpoint_and_merges_events(): void {
		$this->queue_envelope(
			200,
			array(
				$this->own_endpoint(
					array(
						'id'     => 'ep_own',
						'url'    => rest_url( Route::REST_NAMESPACE . '/webhook/' . str_repeat( 'f', Route::TOKEN_LENGTH ) ),
						'events' => array( 'signature_requested', 'template_created' ),
					)
				),
			)
		);
		$this->queue_envelope( 200, $this->own_endpoint( array( 'id' => 'ep_own' ) ) );
		$this->queue_secret();

		$result = Route::subscribe( $this->client(), 'ops@example.com', Route::DEFAULT_EVENTS );

		$this->assertIsArray( $result );
		$this->assertStringEndsWith( '/webhooks/endpoints/ep_own', (string) FakeHttp::$requests[1]['url'] );
		$this->assertSame( 'PUT', strtoupper( (string) FakeHttp::$requests[1]['args']['method'] ) );

		$body = $this->request_body( 1 );
		$this->assertSame( Route::url(), $body['url'] );
		$this->assertContains( 'signature_requested', $body['events'] );
		foreach ( Route::DEFAULT_EVENTS as $wanted ) {
			$this->assertContains( $wanted, $body['events'] );
		}
		$this->assertNotContains( 'template_created', $body['events'], 'An event outside the subscribable fifteen was sent.' );
		$this->assertTrue( $body['is_active'] );
		$this->assertTrue( $body['signing_enabled'] );

		$this->assertSame( 'ep_own', get_option( Settings::OPTION_WEBHOOK_ENDPOINT_ID ) );
	}

	/**
	 * An endpoint with an empty URL cannot be this site's, so it neither matches nor blocks
	 * the creation of this site's own endpoint.
	 */
	public function test_an_endpoint_without_a_url_is_treated_as_absent(): void {
		$this->queue_envelope(
			200,
			array(
				array(
					'id'     => 'ep_empty',
					'url'    => '',
					'events' => array( 'document_ready' ),
				),
			)
		);
		$this->queue_envelope( 200, $this->own_endpoint( array( 'id' => 'ep_new' ) ) );
		$this->queue_secret();

		$result = Route::subscribe( $this->client(), 'ops@example.com' );

		$this->assertIsArray( $result );
		$this->assertSame( 'POST', strtoupper( (string) FakeHttp::$requests[1]['args']['method'] ) );
		$this->assertSame( Route::url(), $this->request_body( 1 )['url'] );
	}

	/**
	 * A 403 from the create route is the plan's endpoint limit, reported as such rather than
	 * as a generic failure — and no foreign endpoint is touched to make room.
	 */
	public function test_a_full_plan_is_reported_as_the_endpoint_limit(): void {
		$this->queue_envelope( 200, array( $this->foreign_endpoint(), $this->foreign_endpoint( 'ep_two' ) ) );
		$this->queue_envelope( 403, null );

		$result = Route::subscribe( $this->client(), 'ops@example.com' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'assinafy_webhook_limit', $result->get_error_code() );
		$this->assertSame( '', get_option( Settings::OPTION_WEBHOOK_ENDPOINT_ID, '' ) );
	}

	/**
	 * The secret routes are not available to OAuth applications, so an OAuth site stores the
	 * endpoint id and leaves the secret to the operator.
	 */
	public function test_oauth_sites_skip_the_secret_fetch(): void {
		update_option( Credentials::OPTION_AUTH_MODE, 'oauth' );

		$this->queue_envelope( 200, array() );
		$this->queue_envelope( 200, $this->own_endpoint( array( 'id' => 'ep_new' ) ) );

		$result = Route::subscribe( $this->client( true ), 'ops@example.com' );

		$this->assertIsArray( $result );
		$this->assertCount( 2, FakeHttp::$requests, 'An OAuth site asked for the endpoint secret.' );
		$this->assertSame( 'ep_new', get_option( Settings::OPTION_WEBHOOK_ENDPOINT_ID ) );
		$this->assertSame( '', get_option( Settings::OPTION_WEBHOOK_SECRET_ENC, '' ) );
	}

	/**
	 * A site that has never generated an endpoint makes no API call at all.
	 */
	public function test_subscribe_without_a_token_makes_no_request(): void {
		update_option( Settings::OPTION_WEBHOOK_TOKEN, '', false );

		$result = Route::subscribe( $this->client(), 'ops@example.com' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'assinafy_webhook_no_token', $result->get_error_code() );
		$this->assertSame( array(), FakeHttp::$requests );
	}

	/**
	 * An endpoint list that cannot be read is reported, not treated as "no endpoints" —
	 * assuming absence would create a second endpoint for a site that already has one.
	 */
	public function test_an_unreadable_endpoint_list_is_reported(): void {
		FakeHttp::queue_error( 'http_request_failed', 'Connection timed out.' );

		$result = Route::subscribe( $this->client(), 'ops@example.com' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'assinafy_webhook_request_failed', $result->get_error_code() );
		$this->assertSame( '', get_option( Settings::OPTION_WEBHOOK_ENDPOINT_ID, '' ) );
	}

	/**
	 * The sanitiser encrypts a pasted secret, passes an encrypted one back untouched, and the
	 * keep-when-blank filter keeps the stored value across empty submissions.
	 */
	public function test_the_secret_sanitiser_encrypts_once_and_blank_keeps_the_stored_value(): void {
		$settings = new Settings( $this->credentials, new ClientFactory( $this->credentials, new Log() ), new Log() );

		$encrypted = $settings->sanitize_webhook_secret( '  ' . self::whsec() . ' ' );

		$this->assertNotSame( self::whsec(), $encrypted );
		$this->assertSame( self::whsec(), $this->credentials->decrypt( $encrypted ) );
		$this->assertSame( $encrypted, $settings->sanitize_webhook_secret( $encrypted ), 'Sanitisation is not idempotent.' );

		update_option( Settings::OPTION_WEBHOOK_SECRET_ENC, $encrypted, false );

		$this->assertSame( $encrypted, $settings->keep_credential_when_blank( '', $encrypted ) );
		$this->assertSame( $encrypted, $settings->keep_credential_when_blank( null, $encrypted ) );
		$this->assertSame( 'whsec_replacement', $settings->keep_credential_when_blank( 'whsec_replacement', $encrypted ) );
	}

	/**
	 * Both new options live in the registry — which is also what uninstall.php iterates —
	 * as non-autoloaded strings.
	 */
	public function test_the_webhook_options_are_registered(): void {
		foreach ( array( Settings::OPTION_WEBHOOK_ENDPOINT_ID, Settings::OPTION_WEBHOOK_SECRET_ENC ) as $option ) {
			$this->assertArrayHasKey( $option, Settings::OPTIONS );
			$this->assertSame( '', Settings::OPTIONS[ $option ]['default'] );
			$this->assertFalse( Settings::OPTIONS[ $option ]['autoload'] );
			$this->assertSame( 'string', Settings::OPTIONS[ $option ]['type'] );
			$this->assertSame( '', Settings::get( $option ) );
		}
	}

	/**
	 * A configured client over the transport double.
	 */
	private function client( bool $oauth = false ): AssinafyClient {
		$config = $oauth
			? Configuration::forBearer( 'test-access-token', self::ACCOUNT_ID, Configuration::SANDBOX_BASE_URL )
			: new Configuration( self::API_KEY, self::ACCOUNT_ID, Configuration::SANDBOX_BASE_URL );

		return new AssinafyClient( $config, new WpHttpClient( $config ) );
	}

	/**
	 * The route with its handler wired the way `Plugin` wires it.
	 */
	private function route(): Route {
		$log = new Log();

		return new Route( new Handler( new StatusSync( new ClientFactory( $this->credentials, $log ), new DocumentRecord() ), $log ) );
	}

	/**
	 * Queue one JSON envelope for the transport double.
	 *
	 * @param int   $status HTTP status line.
	 * @param mixed $data   Envelope `data`.
	 */
	private function queue_envelope( int $status, mixed $data ): void {
		FakeHttp::queue(
			$status,
			(string) wp_json_encode(
				array(
					'status'  => $status,
					'message' => $status >= 400 ? 'Limite de endpoints de webhook atingido.' : '',
					'data'    => $data,
				)
			)
		);
	}

	/**
	 * Queue the endpoint-secret response for the endpoint just registered.
	 */
	private function queue_secret(): void {
		$this->queue_envelope( 200, array( 'secret' => self::whsec() ) );
	}

	/**
	 * Store the test secret the way registration stores it, encrypted.
	 */
	private function store_secret(): void {
		update_option( Settings::OPTION_WEBHOOK_SECRET_ENC, $this->credentials->encrypt( self::whsec() ), false );
	}

	/**
	 * The test `whsec_` value: the prefix plus the base64-encoded key.
	 */
	private static function whsec(): string {
		return 'whsec_' . base64_encode( self::SIGNING_KEY ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- The whsec_ format is base64 by definition.
	}

	/**
	 * Sign a body exactly as the platform does: HMAC-SHA256 over `{id}.{timestamp}.{body}`,
	 * keyed by the base64-decoded `whsec_` secret.
	 *
	 * @param string   $body      Raw delivery body.
	 * @param int|null $timestamp Delivery timestamp; the current time when null.
	 * @return array<string, array<int, string>>
	 */
	private function signed_headers( string $body, ?int $timestamp = null ): array {
		$id        = 'msg_unit_test_delivery';
		$timestamp = $timestamp ?? time();
		$signature = base64_encode( hash_hmac( 'sha256', "{$id}.{$timestamp}.{$body}", self::SIGNING_KEY, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- The v1 signature format is base64 by definition.

		return array(
			'webhook-id'        => array( $id ),
			'webhook-timestamp' => array( (string) $timestamp ),
			'webhook-signature' => array( 'v1,' . $signature ),
		);
	}

	/**
	 * An endpoint this site owns, as the API returns it.
	 *
	 * @param array<string, mixed> $overrides Keys to replace.
	 * @return array<string, mixed>
	 */
	private function own_endpoint( array $overrides = array() ): array {
		return array_merge(
			array(
				'id'              => 'ep_own',
				'name'            => 'example.test',
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
	 * An endpoint another integration on the same account owns.
	 *
	 * @return array<string, mixed>
	 */
	private function foreign_endpoint( string $id = 'ep_foreign' ): array {
		return array(
			'id'              => $id,
			'name'            => 'ERP',
			'url'             => 'https://hooks.example.test/assinafy',
			'email'           => 'ops@example.com',
			'events'          => array( 'document_ready' ),
			'is_active'       => true,
			'signing_enabled' => false,
		);
	}

	/**
	 * The decoded JSON body of one recorded request.
	 *
	 * @param int $index Request index in {@see FakeHttp::$requests}.
	 * @return array<string, mixed>
	 */
	private function request_body( int $index ): array {
		$body = json_decode( (string) ( FakeHttp::$requests[ $index ]['args']['body'] ?? '' ), true );

		return is_array( $body ) ? $body : array();
	}

	/**
	 * Assert the signature-gate refusal.
	 *
	 * @param true|WP_Error $result The gate's answer.
	 */
	private function assert_bad_signature( true|WP_Error $result ): void {
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'assinafy_webhook_bad_signature', $result->get_error_code() );
		$data = $result->get_error_data();
		$this->assertSame( 401, is_array( $data ) ? ( $data['status'] ?? null ) : null );
	}
}
