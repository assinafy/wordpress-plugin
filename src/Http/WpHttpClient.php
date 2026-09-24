<?php
/**
 * WordPress-native transport for the Assinafy PHP SDK.
 *
 * @package Assinafy\WP
 */

declare( strict_types=1 );

namespace Assinafy\WP\Http;

use Assinafy\SDK\Configuration;
use Assinafy\SDK\Exceptions\ApiException;
use Assinafy\SDK\Exceptions\NetworkException;
use Assinafy\SDK\Http\HttpClientInterface;
use Assinafy\SDK\Http\LogRedactor;
use Assinafy\SDK\Http\Response;
use Assinafy\WP\Vendor\Psr\Log\LoggerInterface;
use Assinafy\WP\Vendor\Psr\Log\NullLogger;

defined( 'ABSPATH' ) || exit;

/**
 * Talks to the Assinafy API through the WordPress HTTP API.
 *
 * Injected into `AssinafyClient` so the SDK's Guzzle transport is never constructed and
 * `GuzzleHttp\*` is never autoloaded. Requests therefore inherit the site's proxy settings,
 * `WP_HTTP_BLOCK_EXTERNAL` policy and TLS configuration, and are interceptable through the
 * `pre_http_request` / `http_request_args` filters.
 *
 * Three rules in here are security controls rather than tidiness:
 *
 * - a request URI must be relative, so a caller cannot point a credentialled request at
 *   another origin;
 * - redirects are refused, so `X-Api-Key` is never replayed to a redirect target;
 * - signer-facing and public routes are sent with no credential at all.
 */
// phpcs:disable WordPress.NamingConventions.ValidFunctionName -- Method names are fixed by HttpClientInterface.
// phpcs:disable WordPress.NamingConventions.ValidVariableName -- Parameter names are fixed by HttpClientInterface.
final class WpHttpClient implements HttpClientInterface {

	/**
	 * Base URL with a mandatory trailing slash.
	 *
	 * The slash is load-bearing. RFC 3986 resolution replaces the last path segment of a base
	 * without one, so `https://api.assinafy.com.br/v1` + `documents/statuses` would silently
	 * lose the `/v1`. Request URLs are built by plain concatenation onto this value.
	 */
	private string $base_url;

	/**
	 * Default header names, for `__debugInfo()` only. Values stay on the request factory.
	 *
	 * @var list<string>
	 */
	private array $default_header_names;

	/**
	 * Assembles the arguments for one outbound request, credential policy included.
	 */
	private RequestFactory $requests;

	/**
	 * Turns a transport result into a `Response` or the failure it reports.
	 */
	private ResponseReader $responses;

	/**
	 * Structure-only diagnostics. Request and response bodies are never logged.
	 */
	private LoggerInterface $logger;

	/**
	 * @param Configuration        $config Base URL, timeout and default headers.
	 * @param LoggerInterface|null $logger Optional PSR-3 logger for structural diagnostics.
	 */
	public function __construct( #[\SensitiveParameter] Configuration $config, ?LoggerInterface $logger = null ) {
		$headers = $config->getHeaders();

		$this->base_url             = rtrim( $config->getBaseUrl(), '/' ) . '/';
		$this->logger               = $logger ?? new NullLogger();
		$this->default_header_names = array_keys( $headers );
		$this->requests             = new RequestFactory(
			$config->getTimeout(),
			$headers,
			$headers['User-Agent'] ?? 'Assinafy-PHP-SDK/v' . Configuration::SDK_VERSION
		);
		$this->responses            = new ResponseReader( $this->logger );
	}

	/**
	 * Keep credentials out of diagnostic object dumps.
	 *
	 * @return array{transport: string, logger: string, base_url: string, default_headers: list<string>}
	 */
	public function __debugInfo(): array {
		return array(
			'transport'       => 'wp_remote_request',
			'logger'          => $this->logger::class,
			'base_url'        => $this->base_url,
			'default_headers' => $this->default_header_names,
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string                $uri     URI relative to the configured base URL.
	 * @param array<string, scalar> $params  Query-string parameters.
	 * @param array<string, string> $headers Per-request headers.
	 */
	public function get( string $uri, array $params = array(), array $headers = array() ): Response {
		return $this->request(
			'GET',
			$uri,
			array(
				'query'   => $params,
				'headers' => $headers,
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string                       $uri     URI relative to the configured base URL.
	 * @param array<array-key, mixed>|null $data    JSON body; null sends no body at all.
	 * @param array<string, string>        $headers Per-request headers.
	 * @param array<string, scalar>        $query   Query-string parameters.
	 */
	public function post( string $uri, ?array $data = null, array $headers = array(), array $query = array() ): Response {
		return $this->request( 'POST', $uri, $this->json_options( $data, $headers, $query ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string                       $uri     URI relative to the configured base URL.
	 * @param array<array-key, mixed>|null $data    JSON body; null sends no body at all.
	 * @param array<string, string>        $headers Per-request headers.
	 * @param array<string, scalar>        $query   Query-string parameters.
	 */
	public function put( string $uri, ?array $data = null, array $headers = array(), array $query = array() ): Response {
		return $this->request( 'PUT', $uri, $this->json_options( $data, $headers, $query ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string                       $uri     URI relative to the configured base URL.
	 * @param array<array-key, mixed>|null $data    JSON body; null sends no body at all.
	 * @param array<string, string>        $headers Per-request headers.
	 * @param array<string, scalar>        $query   Query-string parameters.
	 */
	public function patch( string $uri, ?array $data = null, array $headers = array(), array $query = array() ): Response {
		return $this->request( 'PATCH', $uri, $this->json_options( $data, $headers, $query ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string                  $uri     URI relative to the configured base URL.
	 * @param array<string, string>   $headers Per-request headers.
	 * @param array<string, scalar>   $query   Query-string parameters.
	 * @param array<array-key, mixed> $data    Optional JSON body; an empty array sends none.
	 */
	public function delete( string $uri, array $headers = array(), array $query = array(), array $data = array() ): Response {
		$options = array( 'headers' => array() === $data ? $headers : $this->with_json_headers( $headers ) );

		if ( array() !== $data ) {
			$options['json'] = $data;
		}

		return $this->request( 'DELETE', $uri, $this->with_optional_query( $options, $query ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * WordPress has no multipart helper, so the body is assembled by hand. The file part must be
	 * named exactly `file`; any other part name answers 400 "O parâmetro \"file\" não está
	 * presente." and an extra `name=` field is accepted and silently discarded, so the document
	 * is renamed afterwards with PATCH rather than at upload time.
	 *
	 * The whole file is read into memory: the WordPress HTTP API cannot stream a request body.
	 *
	 * @param string                $uri      URI relative to the configured base URL.
	 * @param string                $filePath Absolute path of the file to upload.
	 * @param array<string, mixed>  $data     Extra form fields; arrays are JSON-encoded.
	 * @param array<string, string> $headers  Per-request headers.
	 *
	 * @throws \InvalidArgumentException When the file is missing or unreadable.
	 */
	public function uploadFile( string $uri, string $filePath, array $data = array(), array $headers = array() ): Response {
		$file_path = $filePath;

		if ( ! is_file( $file_path ) || ! is_readable( $file_path ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- A filesystem diagnostic, escaped by the screen that displays it.
			throw new \InvalidArgumentException( "File not found: {$file_path}" );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local file for a multipart body; WP_Filesystem cannot return binary safely here.
		$contents = file_get_contents( $file_path );
		if ( false === $contents ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- A filesystem diagnostic, escaped by the screen that displays it.
			throw new \InvalidArgumentException( "File is not readable: {$file_path}" );
		}

		$file_type = wp_check_filetype( $file_path );
		$multipart = array(
			array(
				'name'         => 'file',
				'contents'     => $contents,
				'filename'     => basename( $file_path ),
				'content_type' => is_string( $file_type['type'] ?? null ) ? $file_type['type'] : 'application/octet-stream',
			),
		);

		foreach ( $data as $key => $value ) {
			$multipart[] = array(
				'name'     => (string) $key,
				'contents' => is_array( $value ) ? (string) wp_json_encode( $value ) : (string) $value,
			);
		}

		return $this->request(
			'POST',
			$uri,
			array(
				'multipart' => $multipart,
				'headers'   => $headers,
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string                $uri         URI relative to the configured base URL.
	 * @param string                $body        Raw request body, possibly binary.
	 * @param string                $contentType Content type of the raw body.
	 * @param array<string, scalar> $query       Query-string parameters.
	 * @param array<string, string> $headers     Per-request headers.
	 */
	public function postRaw(
		string $uri,
		string $body,
		string $contentType,
		array $query = array(),
		array $headers = array()
	): Response {
		return $this->request(
			'POST',
			$uri,
			array(
				'query'   => $query,
				'body'    => $body,
				'headers' => array_merge( array( 'Content-Type' => $contentType ), $headers ),
			)
		);
	}

	/**
	 * Add the JSON content type without discarding caller headers.
	 *
	 * @param array<string, string> $headers Per-request headers.
	 * @return array<string, string>
	 */
	private function with_json_headers( array $headers ): array {
		return array_merge( array( 'Content-Type' => 'application/json' ), $headers );
	}

	/**
	 * @param array<string, mixed>  $options Transport options.
	 * @param array<string, scalar> $query   Query-string parameters.
	 * @return array<string, mixed>
	 */
	private function with_optional_query( array $options, array $query ): array {
		if ( array() !== $query ) {
			$options['query'] = $query;
		}

		return $options;
	}

	/**
	 * Body semantics for the methods that carry JSON.
	 *
	 * `null` means no body and no `Content-Type`; an empty array means a literal JSON `[]`,
	 * which `POST /accounts/{id}/fields/validate-multiple` and the signer sign route require.
	 *
	 * @param array<array-key, mixed>|null $data    JSON body, or null for none.
	 * @param array<string, string>        $headers Per-request headers.
	 * @param array<string, scalar>        $query   Query-string parameters.
	 * @return array<string, mixed>
	 */
	private function json_options( ?array $data, array $headers, array $query ): array {
		$options = array( 'headers' => null === $data ? $headers : $this->with_json_headers( $headers ) );

		if ( null !== $data ) {
			$options['json'] = $data;
		}

		return $this->with_optional_query( $options, $query );
	}

	/**
	 * Send one request and turn the result into a `Response` or an exception.
	 *
	 * @param string               $method  HTTP method.
	 * @param string               $uri     URI relative to the configured base URL.
	 * @param array<string, mixed> $options Transport options: query, headers, json, body, multipart.
	 *
	 * @throws \InvalidArgumentException When the URI is not relative or the payload cannot be encoded.
	 * @throws NetworkException When the transport fails or the response shape is invalid.
	 * @throws ApiException When the HTTP status or the envelope status reports a failure.
	 */
	private function request( string $method, string $uri, #[\SensitiveParameter] array $options = array() ): Response {
		RequestFactory::assert_relative_uri( $uri );

		$options = $this->requests->with_default_headers( $method, $uri, $options );
		$args    = $this->requests->args( $method, $options );

		$safe_path    = explode( '?', explode( '#', $uri, 2 )[0], 2 )[0];
		$safe_request = LogRedactor::redactText( "{$method} {$safe_path}" );

		$this->logger->debug(
			"Assinafy API Request: {$safe_request}",
			array( 'request' => LogRedactor::summarizeRequestOptions( $options ) )
		);

		// Concatenation, not RFC 3986 reference resolution: the base URL keeps its trailing
		// slash, so a validated relative URI can only ever land on the configured origin.
		$url = $this->base_url . $uri;
		// Not `add_query_arg()`: it emits array arguments verbatim, so a `+` or `&` in a signer
		// email would reach the wire unencoded. RFC 3986 is what the SDK's Guzzle transport sends.
		if ( isset( $options['query'] ) && is_array( $options['query'] ) && array() !== $options['query'] ) {
			$url .= ( str_contains( $url, '?' ) ? '&' : '?' )
				. http_build_query( $options['query'], '', '&', PHP_QUERY_RFC3986 );
		}

		return $this->responses->read( $this->dispatch( $url, $args, $safe_request ), $safe_request );
	}

	/**
	 * Send the request and map a transport failure onto `NetworkException`.
	 *
	 * Guarantees the failing URL never reaches the exception chain: the `WP_Error` is replaced
	 * by a cause carrying only its sanitised error code, because a request URI can hold a signer
	 * access code.
	 *
	 * @param string               $url          Absolute request URL.
	 * @param array<string, mixed> $args         Transport arguments.
	 * @param string               $safe_request Redacted method and path, for diagnostics.
	 * @return array<string, mixed> Raw `wp_remote_request()` result.
	 *
	 * @throws NetworkException When the transport fails.
	 */
	private function dispatch( string $url, #[\SensitiveParameter] array $args, string $safe_request ): array {
		$result = wp_remote_request( $url, $args );

		if ( is_wp_error( $result ) ) {
			$this->logger->error(
				"Assinafy Network Error: {$safe_request}",
				array( 'error_code' => (string) $result->get_error_code() )
			);

			throw new NetworkException(
				self::network_message( $result ), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- This sanitized exception is not HTML; display consumers escape it.
				0,
				self::sanitized_previous( $result ), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Preserve the sanitized Throwable cause without converting it into HTML.
				self::request_was_not_sent( $result ) ? array( 'request_sent' => false ) : array()
			);
		}

		return $result;
	}

	/** Only transport failures before an HTTP request may retry a rotating token. */
	private static function request_was_not_sent( \WP_Error $error ): bool {
		if ( 'http_request_not_executed' === $error->get_error_code() ) {
			return true;
		}

		return 'http_request_failed' === $error->get_error_code()
			&& 1 === preg_match( '/^cURL error (?:6|7|35|60):/', $error->get_error_message() );
	}

	/**
	 * An actionable message for the failure modes a site owner can fix.
	 *
	 * @param \WP_Error $error Transport error.
	 */
	private static function network_message( \WP_Error $error ): string {
		if ( 'http_request_not_executed' === $error->get_error_code() ) {
			return 'Network error while calling the Assinafy API: this site blocks external HTTP requests. '
				. 'Add the Assinafy API host to WP_ACCESSIBLE_HOSTS, or unset WP_HTTP_BLOCK_EXTERNAL.';
		}

		return 'Network error while calling the Assinafy API';
	}

	/**
	 * Keep a safe diagnostic cause without retaining the request URI.
	 *
	 * A request URI can carry a signer access code, and `WP_Error` messages from the transport
	 * quote the URL they failed on, so the original is discarded rather than chained.
	 *
	 * @param \WP_Error $error Transport error.
	 */
	private static function sanitized_previous( \WP_Error $error ): \RuntimeException {
		$code = preg_replace( '/[^A-Za-z0-9_.-]/', '', (string) $error->get_error_code() );

		return new \RuntimeException(
			'Underlying HTTP transport error (WP_Error' . ( '' === (string) $code ? '' : ': ' . $code ) . ')'
		);
	}
}
