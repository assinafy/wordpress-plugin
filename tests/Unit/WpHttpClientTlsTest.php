<?php
/**
 * The TLS 1.2 floor on the plugin's own requests.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Unit;

use Assinafy\SDK\Exceptions\NetworkException;
use Assinafy\WP\Tests\Unit\Support\FakeHttp;
use Assinafy\WP\Tests\Unit\Support\TransportTestCase;

/**
 * The WordPress HTTP API has no minimum-TLS argument, so the transport sets one on the cURL
 * handle through `http_api_curl` for the duration of its own request, and only for its URL.
 * Without cURL, a proxy would break TLS end to end, so a proxied request is refused.
 *
 * @covers \Assinafy\WP\Http\WpHttpClient
 */
final class WpHttpClientTlsTest extends TransportTestCase {

	protected function tearDown(): void {
		unset( $GLOBALS['assinafy_test_proxy'], $GLOBALS['assinafy_test_curl'] );

		parent::tearDown();
	}

	/** Streams would hand a proxied request to the proxy in plain text, so none reaches the transport. */
	public function test_refuses_a_proxy_without_curl_before_opening_a_socket(): void {
		$GLOBALS['assinafy_test_proxy'] = true;
		$GLOBALS['assinafy_test_curl']  = false;

		try {
			$this->client()->get( 'documents' );
			$this->fail( 'A proxied request without cURL must be refused.' );
		} catch ( NetworkException $e ) {
			$this->assertSame( array( 'request_sent' => false ), $e->getContext() );
			$this->assertStringContainsString( 'Enable cURL, or add sandbox.assinafy.com.br to WP_PROXY_BYPASS_HOSTS.', $e->getMessage() );
		}

		$this->assertSame( array(), FakeHttp::$requests, 'The request reached the transport.' );
	}

	/** cURL tunnels HTTPS through a proxy with CONNECT; without a proxy, streams connect directly. */
	public function test_allows_a_proxy_with_curl_and_streams_without_a_proxy(): void {
		FakeHttp::queue( 200, '{"status":200,"data":[]}' );
		FakeHttp::queue( 200, '{"status":200,"data":[]}' );

		$GLOBALS['assinafy_test_proxy'] = true;
		$this->client()->get( 'documents' );
		$GLOBALS['assinafy_test_proxy'] = false;
		$GLOBALS['assinafy_test_curl']  = false;
		$this->client()->get( 'documents' );

		$this->assertCount( 2, FakeHttp::$requests );
	}

	public function test_requires_tls_1_2_on_its_own_curl_request_only(): void {
		FakeHttp::queue( 200, '{"status":200,"data":[]}' );
		$registered                = array();
		FakeHttp::$before_response = static function () use ( &$registered ): void {
			$registered = $GLOBALS['wp_stub_actions']['http_api_curl'] ?? array();
		};

		$this->client()->get( 'documents' );

		$this->assertCount( 1, $registered, 'exactly one hook while the request runs' );
		$this->assertSame( PHP_INT_MAX, $registered[0][1], 'runs after every other http_api_curl hook' );
		$this->assertSame( array(), $GLOBALS['wp_stub_actions']['http_api_curl'] ?? array(), 'removed after the request' );

		$require_tls12 = $registered[0][0];
		// Another request's handle is left alone: a non-handle here would make curl_setopt throw.
		$require_tls12( new \stdClass(), array(), 'https://example.com/' );
		// The plugin's own URL reaches curl_setopt, which accepts only a real handle.
		$this->expectException( \TypeError::class );
		$require_tls12( new \stdClass(), array(), (string) FakeHttp::last()['url'] );
	}

	/** Without cURL, WordPress opens an `ssl://` socket; the plugin's own host gets `tlsv1.2://`. */
	public function test_requires_tls_1_2_on_its_own_streams_socket_only(): void {
		FakeHttp::queue( 200, '{"status":200,"data":[]}' );
		$registered                = array();
		FakeHttp::$before_response = static function () use ( &$registered ): void {
			$registered = $GLOBALS['wp_stub_actions']['requests-fsockopen.remote_socket'] ?? array();
		};

		$this->client()->get( 'documents' );

		$this->assertCount( 1, $registered, 'exactly one hook while the request runs' );
		$this->assertSame( PHP_INT_MAX, $registered[0][1] );
		$this->assertSame( array(), $GLOBALS['wp_stub_actions']['requests-fsockopen.remote_socket'] ?? array(), 'removed after the request' );

		$require_tls12 = $registered[0][0];
		$own           = 'ssl://sandbox.assinafy.com.br:443';
		$other         = 'ssl://example.com:443';
		$require_tls12( $own );
		$require_tls12( $other );
		$this->assertSame( 'tlsv1.2://sandbox.assinafy.com.br:443', $own );
		$this->assertSame( 'ssl://example.com:443', $other );
	}
}
