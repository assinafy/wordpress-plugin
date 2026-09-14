<?php
/**
 * Header assembly and the credentialless route list.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Unit;

use Assinafy\SDK\Configuration;
use Assinafy\WP\Http\WpHttpClient;
use Assinafy\WP\Tests\Unit\Support\FakeHttp;
use Assinafy\WP\Tests\Unit\Support\TransportTestCase;

/**
 * Getting the credentialless list wrong leaks the workspace API key to routes a signer can
 * reach with nothing but a link.
 *
 * @covers \Assinafy\WP\Http\WpHttpClient
 */
final class WpHttpClientHeadersTest extends TransportTestCase {

	/**
	 * Queue a bland success so the request under inspection completes.
	 */
	private function queue_ok(): void {
		FakeHttp::queue( 200, '{"status":200,"message":"","data":[]}' );
	}

	public function test_the_user_agent_is_forced(): void {
		$this->queue_ok();

		$this->client()->get( 'documents' );

		$this->assertSame(
			'Assinafy-PHP-SDK/v' . Configuration::SDK_VERSION,
			FakeHttp::last_headers()['user-agent']
		);
		$this->assertSame(
			'Assinafy-PHP-SDK/v' . Configuration::SDK_VERSION,
			FakeHttp::last()['args']['user-agent'],
			'WordPress reads the transport-level user-agent argument, not only the header.'
		);
	}

	/**
	 * A caller cannot dress the request up as something else, whatever case it spells the
	 * header in.
	 */
	public function test_a_caller_supplied_user_agent_is_stripped_case_insensitively(): void {
		$this->queue_ok();

		$this->client()->get( 'documents', array(), array( 'user-agent' => 'Mozilla/5.0' ) );

		$headers = FakeHttp::last_headers();

		$this->assertSame( 'Assinafy-PHP-SDK/v' . Configuration::SDK_VERSION, $headers['user-agent'] );
		$this->assertStringNotContainsString( 'Mozilla', (string) wp_json_encode( $headers ) );
	}

	public function test_two_competing_credentials_are_refused_before_the_request(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'A request cannot contain both Authorization and X-Api-Key headers' );

		try {
			$this->client()->get(
				'documents',
				array(),
				array(
					'Authorization' => 'Bearer token',
					'X-Api-Key'     => 'another-key',
				)
			);
		} finally {
			$this->assertSame( array(), FakeHttp::$requests );
		}
	}

	public function test_a_private_route_carries_the_api_key(): void {
		$this->queue_ok();

		$this->client()->get( 'documents' );

		$headers = FakeHttp::last_headers();

		$this->assertSame( self::API_KEY, $headers['x-api-key'] );
		$this->assertSame( 'application/json', $headers['accept'] );
		$this->assertArrayNotHasKey( 'authorization', $headers );
	}

	/**
	 * Routes that the SDK reaches on behalf of a signer or an unauthenticated visitor. The
	 * list is the transport's, copied from `GuzzleHttpClient::isCredentiallessRequest()`.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function credentialless_routes(): array {
		return array(
			'login'                  => array( 'POST', 'login' ),
			'social login'           => array( 'POST', 'authentication/social-login' ),
			'password reset'         => array( 'PUT', 'authentication/reset-password' ),
			'signer landing'         => array( 'GET', 'sign' ),
			'code verification'      => array( 'POST', 'verify' ),
			'signature submission'   => array( 'POST', 'signature' ),
			'signer self'            => array( 'GET', 'signers/self' ),
			'accept terms'           => array( 'PUT', 'signers/accept-terms' ),
			'sign multiple'          => array( 'PUT', 'signers/documents/sign-multiple' ),
			'decline multiple'       => array( 'PUT', 'signers/documents/decline-multiple' ),
			'document verify'        => array( 'GET', 'documents/104618b275d321f5de22240ebfda/verify' ),
			'public document'        => array( 'GET', 'public/documents/104618b275d321f5de22240ebfda' ),
			'send token'             => array( 'PUT', 'public/documents/104618b275d321f5de22240ebfda/send-token' ),
			'signature by id'        => array( 'GET', 'signature/19e6b92e7895332ed9708535d8c' ),
			'confirm signer data'    => array( 'PUT', 'documents/104618b275d321f5de22240ebfda/signers/confirm-data' ),
			'signer signs'           => array( 'POST', 'documents/104618b275d321f5de22240ebfda/assignments/19e6b92e7895332ed9708535d8c' ),
			'signer rejects'         => array( 'PUT', 'documents/104618b275d321f5de22240ebfda/assignments/1a09c15990f0144256b98ff38aa/reject' ),
			'signer document'        => array( 'GET', 'signers/19e6b92e7895332ed9708535d8c/document' ),
			'signer documents'       => array( 'GET', 'signers/19e6b92e7895332ed9708535d8c/documents' ),
			'signer document search' => array( 'GET', 'signers/19e6b92e7895332ed9708535d8c/documents/search' ),
			'signer download'        => array(
				'GET',
				'signers/19e6b92e7895332ed9708535d8c/documents/104618b275d321f5de22240ebfda/download/original',
			),
		);
	}

	/**
	 * @dataProvider credentialless_routes
	 *
	 * @param string $method HTTP method.
	 * @param string $uri    Request URI.
	 */
	public function test_a_credentialless_route_receives_neither_credential( string $method, string $uri ): void {
		FakeHttp::queue( 200, '{"status":200,"message":"","data":[]}', array( 'Content-Type' => 'application/json' ) );

		$client = $this->client();

		match ( strtoupper( $method ) ) {
			'GET'  => $client->get( $uri ),
			'POST' => $client->post( $uri, array() ),
			'PUT'  => $client->put( $uri, array() ),
		};

		$headers = FakeHttp::last_headers();

		$this->assertArrayNotHasKey( 'x-api-key', $headers, "{$method} {$uri} must not carry the API key." );
		$this->assertArrayNotHasKey( 'authorization', $headers, "{$method} {$uri} must not carry a bearer token." );
		$this->assertSame( 'application/json', $headers['accept'], 'Non-credential defaults still apply.' );
	}

	/**
	 * `estimate-cost` sits at the same depth as the signer sign route and is excluded from it
	 * by a negative lookahead. It is a workspace call and needs the key.
	 */
	public function test_estimate_cost_is_not_treated_as_a_signer_route(): void {
		$this->queue_ok();

		$this->client()->post(
			'documents/104618b275d321f5de22240ebfda/assignments/estimate-cost',
			array( 'signers' => array() )
		);

		$this->assertSame( self::API_KEY, FakeHttp::last_headers()['x-api-key'] );
	}

	/**
	 * The credentialless list is matched on the path alone; a query string must not let a
	 * private route pass as a public one, nor push a public one back into carrying the key.
	 */
	public function test_the_route_match_ignores_the_query_string(): void {
		$this->queue_ok();

		$this->client()->get( 'public/documents/104618b275d321f5de22240ebfda', array( 'code' => '123456' ) );

		$this->assertArrayNotHasKey( 'x-api-key', FakeHttp::last_headers() );
	}

	/**
	 * A bearer token replaces the API key rather than accompanying it.
	 */
	public function test_an_explicit_bearer_token_suppresses_the_configured_api_key(): void {
		$this->queue_ok();

		$this->client()->get( 'documents', array(), array( 'Authorization' => 'Bearer session-token' ) );

		$headers = FakeHttp::last_headers();

		$this->assertSame( 'Bearer session-token', $headers['authorization'] );
		$this->assertArrayNotHasKey( 'x-api-key', $headers );
	}

	/**
	 * A configuration built with an access token sends `Authorization` and no `X-Api-Key`.
	 */
	public function test_a_bearer_configuration_sends_no_api_key(): void {
		$this->queue_ok();

		( new WpHttpClient( $this->config( 'oauth-access-token' ) ) )->get( 'documents' );

		$headers = FakeHttp::last_headers();

		$this->assertSame( 'Bearer oauth-access-token', $headers['authorization'] );
		$this->assertArrayNotHasKey( 'x-api-key', $headers );
	}

	/**
	 * Redirects are refused so `X-Api-Key` is never replayed to another origin, and TLS
	 * verification is passed explicitly so the `https_ssl_verify` filter cannot turn it off.
	 */
	public function test_every_request_refuses_redirects_and_verifies_tls(): void {
		$this->queue_ok();

		$this->client()->get( 'documents' );

		$args = FakeHttp::last()['args'];

		$this->assertSame( 0, $args['redirection'] );
		$this->assertTrue( $args['sslverify'] );
		$this->assertSame( 30, $args['timeout'], 'WordPress defaults to 5s; the configured timeout must be explicit.' );
	}

	/**
	 * `__debugInfo()` is what `var_dump()` and most error handlers print.
	 */
	public function test_debug_info_exposes_header_names_only(): void {
		$dump = ( new WpHttpClient( $this->config() ) )->__debugInfo();

		$this->assertSame( 'wp_remote_request', $dump['transport'] );
		$this->assertSame( Configuration::SANDBOX_BASE_URL . '/', $dump['base_url'] );
		$this->assertContains( 'X-Api-Key', $dump['default_headers'] );
		$this->assertStringNotContainsString( self::API_KEY, (string) wp_json_encode( $dump ) );
	}
}
