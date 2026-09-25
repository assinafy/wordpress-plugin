<?php
/**
 * The TLS 1.2 floor on the plugin's own requests.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Unit;

use Assinafy\WP\Tests\Unit\Support\FakeHttp;
use Assinafy\WP\Tests\Unit\Support\TransportTestCase;

/**
 * The WordPress HTTP API has no minimum-TLS argument, so the transport sets one on the cURL
 * handle through `http_api_curl` for the duration of its own request, and only for its URL.
 *
 * @covers \Assinafy\WP\Http\WpHttpClient
 */
final class WpHttpClientTlsTest extends TransportTestCase {

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
}
