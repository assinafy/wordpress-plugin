<?php
/**
 * The response pipeline.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Unit;

use Assinafy\SDK\Exceptions\ApiException;
use Assinafy\SDK\Exceptions\NetworkException;
use Assinafy\WP\Tests\Unit\Support\FakeHttp;
use Assinafy\WP\Tests\Unit\Support\RecordingLogger;
use Assinafy\WP\Tests\Unit\Support\TransportTestCase;

/**
 * The Assinafy API answers HTTP 200 with a failing envelope `status`. A transport that trusts
 * the status line reports those failures as successes — a refused send would look like a sent
 * document. Everything else in this class exists to keep that check in the right place in the
 * pipeline.
 *
 * @covers \Assinafy\WP\Http\WpHttpClient
 */
final class WpHttpClientEnvelopeTest extends TransportTestCase {

	/**
	 * The single most important assertion in the suite.
	 *
	 * The body is the shape a rejected assignment create really returns:
	 * `{"status":400,"message":"O signatário informado não existe.","data":null}` over an
	 * HTTP 200 status line.
	 */
	public function test_http_200_carrying_envelope_status_400_raises_an_api_exception(): void {
		FakeHttp::queue(
			200,
			'{"status":400,"message":"O signatário informado não existe.","data":null}'
		);

		try {
			$this->client()->post( 'documents/104618b275d321f5de22240ebfda/assignments', array() );
			$this->fail( 'Expected an ApiException for a failing envelope status.' );
		} catch ( ApiException $e ) {
			$this->assertSame( 400, $e->getStatusCode() );
			$this->assertSame( 400, $e->getCode(), 'getCode() is the envelope status, not the HTTP status.' );
			$this->assertSame( 'O signatário informado não existe.', $e->getMessage() );
			$this->assertSame( 400, $e->getResponseData()['status'] );
		}
	}

	/**
	 * Envelope statuses in the 5xx band fail the same way.
	 */
	public function test_http_200_carrying_envelope_status_500_raises_an_api_exception(): void {
		FakeHttp::queue( 200, '{"status":500,"message":"Erro interno.","data":null}' );

		$this->expectException( ApiException::class );
		$this->expectExceptionCode( 500 );

		$this->client()->get( 'documents' );
	}

	/**
	 * A successful envelope passes straight through.
	 */
	public function test_a_successful_envelope_is_returned(): void {
		FakeHttp::queue(
			200,
			'{"status":200,"message":"","data":[{"code":"uploading","deletable":false}]}'
		);

		$response = $this->client()->get( 'documents/statuses' );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( 'uploading', $response->getData()['data'][0]['code'] );
	}

	/**
	 * A 201 envelope is a success too — the band is 200-299, not the literal 200.
	 */
	public function test_envelope_status_201_is_a_success(): void {
		FakeHttp::queue( 201, '{"status":201,"message":"","data":{"id":"104618b275d321f5de22240ebfda"}}' );

		$response = $this->client()->post( 'documents', array( 'name' => 'contract.pdf' ) );

		$this->assertSame( 201, $response->getStatusCode() );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function invalid_envelope_statuses(): array {
		return array(
			'string'      => array( '{"status":"400","message":"","data":null}' ),
			'float'       => array( '{"status":400.5,"message":"","data":null}' ),
			'null'        => array( '{"status":null,"message":"","data":null}' ),
			'below range' => array( '{"status":99,"message":"","data":null}' ),
			'above range' => array( '{"status":600,"message":"","data":null}' ),
			'boolean'     => array( '{"status":true,"message":"","data":null}' ),
		);
	}

	/**
	 * An envelope status that is not an HTTP status is a broken response, not an API error —
	 * treating it as one would invent a status code the server never sent.
	 *
	 * @dataProvider invalid_envelope_statuses
	 *
	 * @param string $body Response body.
	 */
	public function test_an_out_of_range_envelope_status_raises_a_network_exception( string $body ): void {
		FakeHttp::queue( 200, $body );

		$this->expectException( NetworkException::class );
		$this->expectExceptionMessage( 'Assinafy API returned an invalid response envelope status' );

		$this->client()->get( 'documents' );
	}

	public function test_a_successful_response_with_an_unparseable_body_raises_a_network_exception(): void {
		FakeHttp::queue( 200, '<html><body>502 Bad Gateway</body></html>', array( 'Content-Type' => 'text/html' ) );

		$this->expectException( NetworkException::class );
		$this->expectExceptionMessage( 'Assinafy API returned an invalid JSON response' );

		$this->client()->get( 'documents' );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function binary_content_types(): array {
		return array(
			'pdf'              => array( 'application/pdf' ),
			'pdf with charset' => array( 'application/pdf; charset=binary' ),
			'octet stream'     => array( 'application/octet-stream' ),
			'zip'              => array( 'application/zip' ),
			'png thumbnail'    => array( 'image/png' ),
		);
	}

	/**
	 * `documents()->download()` returns bytes. Those are exempt from the JSON check, and the
	 * body must survive byte for byte.
	 *
	 * @dataProvider binary_content_types
	 *
	 * @param string $content_type Response content type.
	 */
	public function test_a_binary_body_is_exempt_from_the_json_check( string $content_type ): void {
		$bytes = "%PDF-1.7\n\x00\x01\x02\xffbinary";

		FakeHttp::queue( 200, $bytes, array( 'Content-Type' => $content_type ) );

		$response = $this->client()->get( 'documents/104618b275d321f5de22240ebfda/download/original' );

		$this->assertSame( $bytes, $response->getBody() );
	}

	public function test_an_empty_successful_body_is_not_an_error(): void {
		FakeHttp::queue( 204, '' );

		$response = $this->client()->delete( 'documents/104618b275d321f5de22240ebfda' );

		$this->assertSame( 204, $response->getStatusCode() );
		$this->assertNull( $response->getData() );
	}

	public function test_a_scalar_data_envelope_raises_a_network_exception(): void {
		FakeHttp::queue( 200, '{"status":200,"message":"","data":"not-an-object"}' );

		$this->expectException( NetworkException::class );
		$this->expectExceptionMessage( 'Assinafy API returned an invalid data envelope' );

		$this->client()->get( 'documents' );
	}

	public function test_a_null_data_envelope_is_accepted(): void {
		FakeHttp::queue( 200, '{"status":200,"message":"","data":null}' );

		$response = $this->client()->get( 'documents' );

		$this->assertNull( $response->getData()['data'] );
	}

	public function test_a_failing_http_status_raises_an_api_exception_with_the_body_message(): void {
		FakeHttp::queue(
			404,
			'{"status":404,"message":"Documento não encontrado.","data":null}',
			array( 'X-Request-Id' => 'req-0e3a1c4b' )
		);

		try {
			$this->client()->get( 'documents/104618b275d321f5de22240ebfda' );
			$this->fail( 'Expected an ApiException.' );
		} catch ( ApiException $e ) {
			$this->assertSame( 404, $e->getStatusCode() );
			$this->assertSame( 'Documento não encontrado.', $e->getMessage() );
			$this->assertSame( 'req-0e3a1c4b', $e->getResponseHeaderLine( 'x-request-id' ) );
		}
	}

	public function test_a_failing_http_status_with_no_body_still_raises_an_api_exception(): void {
		FakeHttp::queue( 500, '' );

		try {
			$this->client()->get( 'documents' );
			$this->fail( 'Expected an ApiException.' );
		} catch ( ApiException $e ) {
			$this->assertSame( 500, $e->getStatusCode() );
			$this->assertSame( 'API request failed', $e->getMessage() );
		}
	}

	/**
	 * A request URI can carry a signer access code, and the `WP_Error` a failed transport
	 * produces quotes the URL it failed on. The original is therefore discarded rather than
	 * chained onto the exception that surfaces to an administrator.
	 */
	public function test_a_transport_failure_becomes_a_network_exception_with_no_uri_in_the_chain(): void {
		FakeHttp::queue_error(
			'http_request_failed',
			'cURL error 28: Operation timed out after 30000 ms — https://sandbox.assinafy.com.br/v1/sign/SECRET-CODE'
		);

		try {
			$this->client()->get( 'documents' );
			$this->fail( 'Expected a NetworkException.' );
		} catch ( NetworkException $e ) {
			$this->assertSame( 'Network error while calling the Assinafy API', $e->getMessage() );
			$this->assertSame( array(), $e->getContext() );

			$previous = $e->getPrevious();
			$this->assertInstanceOf( \RuntimeException::class, $previous );
			$this->assertSame( 'Underlying HTTP transport error (WP_Error: http_request_failed)', $previous->getMessage() );
			$this->assertStringNotContainsString( 'SECRET-CODE', $previous->getMessage() );
			$this->assertStringNotContainsString( 'sandbox.assinafy.com.br', $previous->getMessage() );
		}
	}

	/**
	 * A site running `WP_HTTP_BLOCK_EXTERNAL` gets a message naming the fix rather than a
	 * generic timeout.
	 */
	public function test_a_blocked_external_request_produces_an_actionable_message(): void {
		FakeHttp::queue_error( 'http_request_not_executed', 'User has blocked requests through HTTP.' );

		try {
			$this->client()->get( 'documents' );
			$this->fail( 'Expected a NetworkException.' );
		} catch ( NetworkException $e ) {
			$this->assertStringContainsString( 'WP_ACCESSIBLE_HOSTS', $e->getMessage() );
			$this->assertStringContainsString( 'WP_HTTP_BLOCK_EXTERNAL', $e->getMessage() );
			$this->assertSame( array( 'request_sent' => false ), $e->getContext() );
		}
	}

	/**
	 * Pagination exists only in the four `X-Pagination-*` response headers — there is no `meta`
	 * key anywhere in this API — so a repeated header must survive capture as a list rather
	 * than being flattened to its last value.
	 */
	public function test_pagination_headers_survive_capture(): void {
		FakeHttp::queue(
			200,
			'{"status":200,"message":"","data":[]}',
			array(
				'X-Pagination-Page'        => '2',
				'X-Pagination-Page-Count'  => '7',
				'X-Pagination-Per-Page'    => '50',
				'X-Pagination-Total-Count' => '312',
				'X-Multi-Value'            => array( 'first', 'second' ),
			)
		);

		$headers = $this->client()->get( 'documents', array( 'page' => 2 ) )->getHeaders();

		$this->assertSame( '7', $headers['X-Pagination-Page-Count'] );
		$this->assertSame( '312', $headers['X-Pagination-Total-Count'] );
		$this->assertSame( array( 'first', 'second' ), $headers['X-Multi-Value'] );
	}

	/**
	 * Diagnostics are structural. A log line that quoted a request body or a header value
	 * would put the workspace API key into `debug.log`.
	 */
	public function test_nothing_logged_carries_a_credential_a_body_or_a_query_value(): void {
		$logger = new RecordingLogger();

		FakeHttp::queue( 200, '{"status":200,"message":"","data":{"secret":"leak-me"}}' );

		( new \Assinafy\WP\Http\WpHttpClient( $this->config(), $logger ) )->post(
			'documents',
			array( 'name' => 'contract.pdf' ),
			array(),
			array( 'expand' => 'assignment' )
		);

		$dump = $logger->dump();

		$this->assertNotSame( '', $dump );
		$this->assertStringNotContainsString( self::API_KEY, $dump );
		$this->assertStringNotContainsString( 'contract.pdf', $dump );
		$this->assertStringNotContainsString( 'leak-me', $dump );
		$this->assertStringContainsString( 'header_names', $dump );
		$this->assertStringContainsString( 'json_keys', $dump );
	}
}
