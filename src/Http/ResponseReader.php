<?php
/**
 * Everything that turns a transport result into a `Response` or the failure it reports.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Http;

defined( 'ABSPATH' ) || exit;

use Assinafy\SDK\Exceptions\ApiException;
use Assinafy\SDK\Exceptions\NetworkException;
use Assinafy\SDK\Http\Response;
use Assinafy\WP\Vendor\Psr\Log\LoggerInterface;

/**
 * Reads one response, and refuses to call a failure a success.
 *
 * The API answers HTTP 200 with a failing envelope status, so the status line alone is not
 * the verdict: the envelope's own `status` is checked as well, and an envelope status that is
 * not an HTTP status code at all is treated as a malformed response rather than ignored.
 *
 * Nothing here logs a body. Diagnostics carry the status and the body's size, because a
 * response body can hold a signer's access code.
 */
final class ResponseReader {

	/**
	 * Content types whose bodies are bytes, not JSON.
	 */
	private const BINARY_CONTENT_TYPE = '~^(?:image/|application/(?:pdf|octet-stream|zip))(?:[^,]*)(?:,|$)~i';

	/**
	 * @param LoggerInterface $logger Structure-only diagnostics.
	 */
	public function __construct( private readonly LoggerInterface $logger ) {
	}

	/**
	 * Turn a transport result into a `Response`, or into the failure it reports.
	 *
	 * Guarantees the rate-limit snapshot is recorded before any exception can leave; that a
	 * non-2xx status line raises `ApiException` carrying the response headers; that the envelope
	 * checks run only on a response the status line already calls a success; that an undecodable
	 * JSON body is reported as a network failure rather than handed on as an empty result, while
	 * a download response — whose body is bytes — is exempt; and that an envelope `data` key
	 * holds an array or nothing, never the scalar the API never documents.
	 *
	 * @param array<string, mixed> $result       Raw `wp_remote_request()` result.
	 * @param string               $safe_request Redacted method and path, for diagnostics.
	 *
	 * @throws NetworkException When the response shape is invalid.
	 * @throws ApiException When the HTTP status or the envelope status reports a failure.
	 */
	public function read( array $result, string $safe_request ): Response {
		$status_code = (int) wp_remote_retrieve_response_code( $result );
		$headers     = Headers::from_result( $result );
		$body        = (string) wp_remote_retrieve_body( $result );

		$this->logger->debug(
			"Assinafy API Response: {$status_code}",
			array( 'body_bytes' => strlen( $body ) )
		);

		RateLimit::record( $status_code, $headers );

		$response = new Response( $status_code, $headers, $body );
		$data     = $response->getData();

		if ( ! $response->isSuccess() ) {
			$this->logger->error(
				"Assinafy API Error: {$safe_request}",
				array(
					'status_code' => $status_code,
					'body_bytes'  => strlen( $body ),
				)
			);

			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- SDK exception data is not HTML; consumers escape messages when displaying them.
			throw ApiException::fromResponse( $status_code, $data ?? array(), null, $headers );
		}

		if ( '' !== $body && null === $data && ! self::has_binary_content_type( $headers ) ) {
			throw new NetworkException( 'Assinafy API returned an invalid JSON response' );
		}

		$this->assert_envelope_status( $safe_request, $status_code, strlen( $body ), $headers, $data );

		if (
			is_array( $data )
			&& array_key_exists( 'data', $data )
			&& null !== $data['data']
			&& ! is_array( $data['data'] )
		) {
			throw new NetworkException( 'Assinafy API returned an invalid data envelope' );
		}

		return $response;
	}


	/**
	 * Enforce the `status` the API reports inside the response envelope.
	 *
	 * The API answers HTTP 200 with a failing envelope status. Trusting the status line alone
	 * would report those failures as successes, so this guarantees a non-2xx envelope status
	 * raises `ApiException`, and that an envelope status which is not an HTTP status code at all
	 * is rejected as a malformed response.
	 *
	 * @param string                                   $safe_request Redacted method and path.
	 * @param int                                      $status_code  HTTP status from the status line.
	 * @param int                                      $body_bytes   Size of the response body.
	 * @param array<string, array<int, string>|string> $headers      Response headers.
	 * @param mixed                                    $data         Decoded response body.
	 *
	 * @throws NetworkException When the envelope status is not an HTTP status code.
	 * @throws ApiException When the envelope status is outside the 2xx range.
	 */
	private function assert_envelope_status(
		string $safe_request,
		int $status_code,
		int $body_bytes,
		array $headers,
		mixed $data
	): void {
		if ( ! is_array( $data ) || ! array_key_exists( 'status', $data ) ) {
			return;
		}

		$envelope_status = $data['status'];

		if ( ! is_int( $envelope_status ) || $envelope_status < 100 || $envelope_status > 599 ) {
			throw new NetworkException( 'Assinafy API returned an invalid response envelope status' );
		}

		if ( $envelope_status >= 200 && $envelope_status < 300 ) {
			return;
		}

		$this->logger->error(
			"Assinafy API Error: {$safe_request}",
			array(
				'status_code'     => $status_code,
				'envelope_status' => $envelope_status,
				'body_bytes'      => $body_bytes,
			)
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- SDK exception data is not HTML; consumers escape messages when displaying them.
		throw ApiException::fromResponse( $envelope_status, $data, null, $headers );
	}


	/**
	 * True when the response carries bytes rather than JSON, exempting it from the JSON check.
	 *
	 * @param array<string, array<int, string>|string> $headers Response headers.
	 */
	private static function has_binary_content_type( array $headers ): bool {
		$content_type = Headers::line( $headers, 'Content-Type' );

		return '' !== $content_type && 1 === preg_match( self::BINARY_CONTENT_TYPE, trim( $content_type ) );
	}
}
