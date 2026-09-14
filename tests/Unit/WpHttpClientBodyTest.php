<?php
/**
 * Request-body construction, multipart upload and the rate-limit snapshot.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Unit;

use Assinafy\SDK\Exceptions\ApiException;
use Assinafy\WP\Http\RateLimit;
use Assinafy\WP\Http\WpHttpClient;
use Assinafy\WP\Tests\Unit\Support\FakeHttp;
use Assinafy\WP\Tests\Unit\Support\TransportTestCase;

/**
 * `null` and `[]` are different requests, not two spellings of "empty": two endpoints —
 * `POST accounts/{id}/fields/validate-multiple` and the signer sign route — require a literal
 * JSON `[]` and reject a bodyless request.
 *
 * @covers \Assinafy\WP\Http\WpHttpClient
 */
final class WpHttpClientBodyTest extends TransportTestCase {

	private function queue_ok(): void {
		FakeHttp::queue( 200, '{"status":200,"message":"","data":[]}' );
	}

	public function test_a_null_body_sends_no_body_and_no_content_type(): void {
		$this->queue_ok();

		$this->client()->post( 'documents/104618b275d321f5de22240ebfda/notify' );

		$this->assertArrayNotHasKey( 'body', FakeHttp::last()['args'] );
		$this->assertArrayNotHasKey( 'content-type', FakeHttp::last_headers() );
	}

	public function test_an_empty_array_body_sends_a_literal_json_array(): void {
		$this->queue_ok();

		$this->client()->post( 'accounts/0e3a1c4b9f2d47a8b6c05e19/fields/validate-multiple', array() );

		$this->assertSame( '[]', FakeHttp::last()['args']['body'] );
		$this->assertSame( 'application/json', FakeHttp::last_headers()['content-type'] );
	}

	public function test_a_populated_body_is_json_encoded(): void {
		$this->queue_ok();

		$this->client()->put( 'documents/104618b275d321f5de22240ebfda', array( 'name' => 'contract.pdf' ) );

		$this->assertSame( '{"name":"contract.pdf"}', FakeHttp::last()['args']['body'] );
		$this->assertSame( 'PUT', FakeHttp::last()['args']['method'] );
	}

	public function test_delete_sends_a_body_only_when_one_is_given(): void {
		$this->queue_ok();
		$this->client()->delete( 'documents/104618b275d321f5de22240ebfda' );

		$this->assertArrayNotHasKey( 'body', FakeHttp::last()['args'] );
		$this->assertArrayNotHasKey( 'content-type', FakeHttp::last_headers() );

		$this->queue_ok();
		$this->client()->delete( 'documents', array(), array(), array( 'ids' => array( '104618b275d321f5de22240ebfda' ) ) );

		$this->assertSame( '{"ids":["104618b275d321f5de22240ebfda"]}', FakeHttp::last()['args']['body'] );
		$this->assertSame( 'application/json', FakeHttp::last_headers()['content-type'] );
	}

	public function test_a_payload_that_cannot_be_encoded_is_refused(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Request payload could not be encoded as JSON' );

		try {
			$this->client()->post( 'documents', array( 'name' => "\xB1\x31invalid utf-8" ) );
		} finally {
			$this->assertSame( array(), FakeHttp::$requests );
		}
	}

	public function test_query_parameters_are_appended(): void {
		$this->queue_ok();

		$this->client()->get(
			'documents',
			array(
				'page'     => 2,
				'per-page' => 50,
			)
		);

		$url = FakeHttp::last()['url'];

		$this->assertStringStartsWith( 'https://sandbox.assinafy.com.br/v1/documents?', $url );
		$this->assertStringContainsString( 'page=2', $url );
		$this->assertStringContainsString( 'per-page=50', $url );
	}

	public function test_post_raw_sets_the_given_content_type(): void {
		$this->queue_ok();

		$this->client()->postRaw( 'documents/104618b275d321f5de22240ebfda/pages', '%PDF-1.7', 'application/pdf' );

		$this->assertSame( '%PDF-1.7', FakeHttp::last()['args']['body'] );
		$this->assertSame( 'application/pdf', FakeHttp::last_headers()['content-type'] );
	}

	/**
	 * A path that exists and holds a small PDF-shaped file.
	 */
	private function fixture_pdf(): string {
		$path = (string) tempnam( sys_get_temp_dir(), 'assinafy' );
		$pdf  = $path . '.pdf';

		rename( $path, $pdf );
		file_put_contents( $pdf, "%PDF-1.7\n\x00\x01binary body\n%%EOF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		return $pdf;
	}

	/**
	 * WordPress has no multipart helper, so the body is hand-assembled. The file part must be
	 * named exactly `file`: any other name answers 400 "O parâmetro \"file\" não está presente."
	 */
	public function test_upload_builds_a_multipart_body_with_the_file_part_named_file(): void {
		$pdf = $this->fixture_pdf();

		$this->queue_ok();

		$this->client()->uploadFile(
			'accounts/0e3a1c4b9f2d47a8b6c05e19/documents',
			$pdf,
			array(
				'name' => 'contract.pdf',
				'tags' => array( 'contracts', 'q3' ),
			)
		);

		$content_type = FakeHttp::last_headers()['content-type'];
		$body         = FakeHttp::last()['args']['body'];

		$this->assertStringStartsWith( 'multipart/form-data; boundary=----AssinafyFormBoundary', $content_type );

		$boundary = substr( $content_type, strlen( 'multipart/form-data; boundary=' ) );

		$this->assertStringContainsString(
			'Content-Disposition: form-data; name="file"; filename="' . basename( $pdf ) . '"',
			$body
		);
		$this->assertStringContainsString( 'Content-Type: application/pdf', $body );
		$this->assertStringContainsString( "\x00\x01binary body", $body, 'The file is sent byte for byte.' );
		$this->assertStringContainsString( 'Content-Disposition: form-data; name="name"', $body );
		$this->assertStringContainsString( '["contracts","q3"]', $body, 'Array fields are JSON-encoded.' );
		$this->assertStringEndsWith( '--' . $boundary . "--\r\n", $body );

		unlink( $pdf );
	}

	/**
	 * The boundary lives in the Content-Type. Letting a caller-supplied one through would
	 * strip it and the API answers 400 "The Content-Type header must be multipart/form-data.".
	 */
	public function test_upload_replaces_a_caller_supplied_content_type(): void {
		$pdf = $this->fixture_pdf();

		$this->queue_ok();

		$this->client()->uploadFile(
			'accounts/0e3a1c4b9f2d47a8b6c05e19/documents',
			$pdf,
			array(),
			array( 'content-type' => 'application/json' )
		);

		$headers = FakeHttp::last_headers();

		$this->assertCount( 1, array_filter( array_keys( $headers ), static fn( $n ) => 'content-type' === $n ) );
		$this->assertStringStartsWith( 'multipart/form-data; boundary=', $headers['content-type'] );

		unlink( $pdf );
	}

	/**
	 * A part name is interpolated into a MIME header, so the characters that would end that
	 * header are removed rather than escaped.
	 */
	public function test_a_part_name_cannot_forge_a_mime_header(): void {
		$pdf = $this->fixture_pdf();

		$this->queue_ok();

		$this->client()->uploadFile(
			'accounts/0e3a1c4b9f2d47a8b6c05e19/documents',
			$pdf,
			array( "name\"\r\nX-Injected: yes\r\n" => 'value' )
		);

		$body = FakeHttp::last()['args']['body'];

		/*
		 * The characters are stripped, not escaped, so the inert residue "nameX-Injected: yes"
		 * survives inside the quoted name. What must not survive is the CRLF that would end the
		 * Content-Disposition header and start a new one, or the quote that would end the name
		 * attribute early — those are the two ways a part name becomes a forged header.
		 */
		$this->assertStringNotContainsString( "\r\nX-Injected:", $body );
		$this->assertStringContainsString( 'name="nameX-Injected: yes"', $body );
		$this->assertSame(
			2,
			substr_count( $body, 'Content-Disposition:' ),
			'One Content-Disposition per part — a third would mean the name forged a header.'
		);

		unlink( $pdf );
	}

	public function test_upload_refuses_a_missing_file(): void {
		$missing = sys_get_temp_dir() . '/assinafy-does-not-exist.pdf';

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( "File not found: {$missing}" );

		$this->client()->uploadFile( 'accounts/0e3a1c4b9f2d47a8b6c05e19/documents', $missing );
	}

	/**
	 * No SDK resource method returns the `Response`, so the transport is the only place the
	 * rate-limit headers can be read. The reconcile cron backs off on the snapshot, so a 429 —
	 * the one failure that reports a spent budget — has to be recorded before the exception
	 * leaves, not discarded with the rest of the failing statuses.
	 */
	public function test_the_rate_limit_budget_is_recorded_from_a_success_and_from_a_429(): void {
		$this->assertNull( RateLimit::snapshot(), 'Nothing is reported before a call.' );

		FakeHttp::queue(
			200,
			'{"status":200,"message":"","data":[]}',
			array(
				'X-Rate-Limit-Remaining' => '57',
				'X-Rate-Limit-Reset'     => '43',
			)
		);

		$this->client()->get( 'documents' );

		$snapshot = RateLimit::snapshot();

		$this->assertSame( 57, $snapshot['remaining'] );
		$this->assertSame( 43, $snapshot['reset'] );
		$this->assertGreaterThan( 0, $snapshot['recorded'] );

		FakeHttp::queue(
			429,
			'{"status":429,"message":"Too Many Requests","data":null}',
			array(
				'X-Rate-Limit-Remaining' => '0',
				'X-Rate-Limit-Reset'     => '31',
			)
		);

		try {
			$this->client()->get( 'documents' );
			$this->fail( 'Expected an ApiException.' );
		} catch ( ApiException $e ) {
			$this->assertSame( 429, $e->getStatusCode() );
		}

		$throttled = RateLimit::snapshot();

		$this->assertSame( 0, $throttled['remaining'], 'The throttled budget is recorded, not thrown away with the failure.' );
		$this->assertSame( 31, $throttled['reset'] );
	}

	public function test_a_response_without_rate_limit_headers_records_nothing(): void {
		FakeHttp::queue( 200, '{"status":200,"message":"","data":[]}' );

		$this->client()->get( 'documents' );

		$this->assertNull( RateLimit::snapshot() );
	}
}
