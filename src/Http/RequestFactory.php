<?php
/**
 * Everything that turns a call into arguments for `wp_remote_request()`.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Http;

defined( 'ABSPATH' ) || exit;

use Assinafy\SDK\Exceptions\NetworkException;
use WpOrg\Requests\Transport\Curl;

/**
 * Assembles one outbound request, and decides what credential it may carry.
 *
 * Three of the four rules here are security controls rather than tidiness:
 *
 * - a request URI must be relative, so a caller cannot point a credentialled request at
 *   another origin;
 * - a request a proxy would read in plain text is never sent;
 * - signer-facing and public routes are sent with no credential at all.
 *
 * The fourth is the body: the three shapes — JSON, raw string and multipart — stay mutually
 * exclusive, and a multipart body is framed by a freshly generated boundary that no
 * caller-supplied `Content-Type` can displace.
 */
final class RequestFactory {

	/**
	 * Fixed routes that must be sent with no credential, keyed as `METHOD path`.
	 *
	 * @var list<string>
	 */
	private const CREDENTIALLESS_ROUTES = array(
		'POST login',
		'POST authentication/social-login',
		'PUT authentication/request-password-reset',
		'PUT authentication/reset-password',
		'GET sign',
		'POST verify',
		'POST signature',
		'GET signers/self',
		'PUT signers/accept-terms',
		'PUT signers/documents/sign-multiple',
		'PUT signers/documents/decline-multiple',
	);

	/**
	 * Credentialless routes that carry an identifier, matched against `METHOD path`.
	 *
	 * @var list<non-empty-string>
	 */
	private const CREDENTIALLESS_ROUTE_PATTERNS = array(
		'~^GET documents/[^/]+/verify$~',
		'~^GET public/documents/[^/]+$~',
		'~^PUT public/documents/[^/]+/send-token$~',
		'~^GET signature/[^/]+$~',
		'~^PUT documents/[^/]+/signers/confirm-data$~',
		'~^POST documents/[^/]+/assignments/(?!estimate-cost$)[^/]+$~',
		'~^PUT documents/[^/]+/assignments/[^/]+/reject$~',
		'~^GET signers/[^/]+/document$~',
		'~^GET signers/[^/]+/documents(?:/search|/[^/]+/download/[^/]+)?$~',
	);

	/**
	 * @param int                   $timeout         Total per-request timeout in seconds.
	 * @param array<string, string> $default_headers Configured headers, credential included.
	 * @param string                $user_agent      User-Agent every request is forced to carry.
	 */
	public function __construct(
		private readonly int $timeout,
		#[\SensitiveParameter] private readonly array $default_headers,
		private readonly string $user_agent
	) {
	}

	/**
	 * Reject a URI that could carry the workspace credential off the configured origin.
	 *
	 * Guarantees that nothing downstream ever sees a URI with a leading slash, a scheme, a host
	 * or a `.`/`..` segment, in raw or percent-encoded form, and that the rejection happens
	 * before any request argument is assembled.
	 *
	 * @param string $uri URI relative to the configured base URL.
	 *
	 * @throws \InvalidArgumentException When the URI is not relative.
	 */
	public static function assert_relative_uri( string $uri ): void {
		$path = wp_parse_url( $uri, PHP_URL_PATH );

		// A leading slash, a scheme, a host or a `..` segment would move the request off the
		// configured origin while it still carries the workspace credential. `rawurldecode()`
		// catches the percent-encoded forms of the same traversal.
		if (
			'' === $uri
			|| str_starts_with( $uri, '/' )
			|| null !== wp_parse_url( $uri, PHP_URL_SCHEME )
			|| null !== wp_parse_url( $uri, PHP_URL_HOST )
			|| ! is_string( $path )
			|| 1 === preg_match( '~(?:^|/)\.{1,2}(?:/|$)~', rawurldecode( $path ) )
		) {
			throw new \InvalidArgumentException( 'Request URI must be relative to the configured API base URL' );
		}
	}

	/**
	 * Refuse a request that a proxy would read in plain text.
	 *
	 * WordPress's Requests tries cURL first, and cURL tunnels HTTPS through a proxy with
	 * CONNECT. The streams transport it falls back to has no tunnel: it connects to the proxy
	 * in plain TCP and writes the absolute URL, headers and body there, or with TLS verification
	 * on, fails after connecting. Refusing first opens no socket, and a refresh token that never
	 * left stays usable.
	 *
	 * @param string $url Absolute request URL.
	 *
	 * @throws NetworkException When a proxy applies and only the streams transport is available.
	 */
	public static function assert_tunnelled( string $url ): void {
		$proxy = new \WP_HTTP_Proxy();
		if ( ! $proxy->is_enabled() || ! $proxy->send_through_proxy( $url ) || Curl::test( array( 'ssl' => true ) ) ) {
			return;
		}

		throw new NetworkException(
			sprintf(
				/* translators: %s: Assinafy API host, such as api.assinafy.com.br. */
				__( 'Without the PHP cURL extension, WordPress cannot send Assinafy requests securely through the HTTP proxy configured for this site. Enable cURL, or add %s to WP_PROXY_BYPASS_HOSTS.', 'assinafy' ),
				(string) wp_parse_url( $url, PHP_URL_HOST )
			),
			0,
			null,
			array( 'request_sent' => false )
		);
	}


	/**
	 * Assemble the argument array for `wp_remote_request()`, body included.
	 *
	 * Guarantees every request refuses redirects, verifies TLS and carries the configured
	 * User-Agent alongside the headers {@see self::with_default_headers()} settled; that the
	 * three body shapes stay mutually exclusive; that a multipart body is framed by a freshly
	 * generated boundary no caller-supplied `Content-Type` can displace; and that an unencodable
	 * JSON payload is refused rather than sent as an empty body.
	 *
	 * @param string               $method  HTTP method.
	 * @param array<string, mixed> $options Transport options, credentials already applied.
	 * @return array<string, mixed>
	 *
	 * @throws \InvalidArgumentException When the payload cannot be encoded as JSON.
	 */
	public function args( string $method, #[\SensitiveParameter] array $options ): array {
		$headers = is_array( $options['headers'] ) ? $options['headers'] : array();
		$body    = null;

		if ( isset( $options['multipart'] ) && is_array( $options['multipart'] ) ) {
			$boundary = '----AssinafyFormBoundary' . bin2hex( random_bytes( 16 ) );

			// The boundary lives in the Content-Type, so a caller-supplied one is replaced
			// rather than merged: keeping it would strip the boundary and the API would answer
			// 400 "The Content-Type header must be multipart/form-data.".
			$headers                 = Headers::without( $headers, 'Content-Type' );
			$headers['Content-Type'] = 'multipart/form-data; boundary=' . $boundary;

			$body = self::build_multipart_body( $options['multipart'], $boundary );
		} elseif ( array_key_exists( 'json', $options ) ) {
			$encoded = wp_json_encode( $options['json'] );
			if ( false === $encoded ) {
				throw new \InvalidArgumentException( 'Request payload could not be encoded as JSON' );
			}
			$body = $encoded;
		} elseif ( isset( $options['body'] ) && is_string( $options['body'] ) ) {
			$body = $options['body'];
		}

		$args = array(
			'method'      => $method,
			'timeout'     => $this->timeout,
			// WordPress follows five redirects by default. API calls are not browser navigation,
			// and a followed redirect would forward X-Api-Key to whatever origin it names.
			'redirection' => 0,
			// Passed explicitly so `https_ssl_verify` cannot quietly disable TLS verification.
			'sslverify'   => true,
			'user-agent'  => $this->user_agent,
			'headers'     => $headers,
		);

		if ( null !== $body ) {
			$args['body'] = $body;
		}

		return $args;
	}


	/**
	 * Apply configured headers per request.
	 *
	 * Done per request rather than once on a client so an explicit Bearer token can replace —
	 * rather than accompany — the configured API key, and vice versa.
	 *
	 * @param string               $method  HTTP method.
	 * @param string               $uri     URI relative to the configured base URL.
	 * @param array<string, mixed> $options Transport options.
	 * @return array<string, mixed>
	 *
	 * @throws \InvalidArgumentException When the caller supplies two competing credentials.
	 */
	public function with_default_headers( string $method, string $uri, #[\SensitiveParameter] array $options ): array {
		$headers = isset( $options['headers'] ) && is_array( $options['headers'] ) ? $options['headers'] : array();

		$headers               = Headers::without( $headers, 'User-Agent' );
		$headers['User-Agent'] = $this->user_agent;

		$has_authorization = Headers::has( $headers, 'Authorization' );
		$has_api_key       = Headers::has( $headers, 'X-Api-Key' );
		if ( $has_authorization && $has_api_key ) {
			throw new \InvalidArgumentException( 'A request cannot contain both Authorization and X-Api-Key headers' );
		}

		$credentialless = self::is_credentialless_request( $method, $uri );
		foreach ( $this->default_headers as $name => $value ) {
			if ( self::skips_default_header( $name, $credentialless, $has_authorization, $has_api_key ) ) {
				continue;
			}
			if ( ! Headers::has( $headers, $name ) ) {
				$headers[ $name ] = $value;
			}
		}

		$options['headers'] = $headers;

		return $options;
	}


	/**
	 * Whether one configured default header is withheld from this request.
	 *
	 * Guarantees a credentialless route receives neither `Authorization` nor `X-Api-Key`, and
	 * that a caller-supplied credential of one kind suppresses the configured credential of the
	 * other kind instead of accompanying it.
	 *
	 * @param string $name              Configured header name.
	 * @param bool   $credentialless    Whether the route is credentialless.
	 * @param bool   $has_authorization Whether the request already carries `Authorization`.
	 * @param bool   $has_api_key       Whether the request already carries `X-Api-Key`.
	 */
	private static function skips_default_header( string $name, bool $credentialless, bool $has_authorization, bool $has_api_key ): bool {
		if ( $credentialless && in_array( strtolower( $name ), array( 'authorization', 'x-api-key' ), true ) ) {
			return true;
		}

		return ( 'X-Api-Key' === $name && $has_authorization ) || ( 'Authorization' === $name && $has_api_key );
	}


	/**
	 * Public bootstrap, verification and signer-facing routes.
	 *
	 * These must not inherit the workspace credential from a client that also serves private
	 * resources. Explicit per-request headers are left untouched.
	 *
	 * @param string $method HTTP method.
	 * @param string $uri    URI relative to the configured base URL.
	 */
	private static function is_credentialless_request( string $method, string $uri ): bool {
		$path  = wp_parse_url( $uri, PHP_URL_PATH );
		$path  = is_string( $path ) ? ltrim( $path, '/' ) : '';
		$route = strtoupper( $method ) . ' ' . $path;

		if ( in_array( $route, self::CREDENTIALLESS_ROUTES, true ) ) {
			return true;
		}

		foreach ( self::CREDENTIALLESS_ROUTE_PATTERNS as $pattern ) {
			if ( 1 === preg_match( $pattern, $route ) ) {
				return true;
			}
		}

		return false;
	}


	/**
	 * Assemble a `multipart/form-data` body.
	 *
	 * @param array<int, array<string, mixed>> $parts    Parts, each with name, contents and optional filename.
	 * @param string                           $boundary Boundary token.
	 */
	private static function build_multipart_body( array $parts, string $boundary ): string {
		$body = '';

		foreach ( $parts as $part ) {
			$name        = isset( $part['name'] ) ? self::escape_part_value( (string) $part['name'] ) : '';
			$disposition = 'Content-Disposition: form-data; name="' . $name . '"';

			if ( isset( $part['filename'] ) ) {
				$disposition .= '; filename="' . self::escape_part_value( (string) $part['filename'] ) . '"';
			}

			$body .= '--' . $boundary . "\r\n" . $disposition . "\r\n";

			if ( isset( $part['content_type'] ) ) {
				$body .= 'Content-Type: ' . self::escape_part_value( (string) $part['content_type'] ) . "\r\n";
			}

			$body .= "\r\n" . ( isset( $part['contents'] ) ? (string) $part['contents'] : '' ) . "\r\n";
		}

		return $body . '--' . $boundary . "--\r\n";
	}


	/**
	 * Strip the characters that would let a part name or filename forge a MIME header.
	 *
	 * @param string $value Raw part name, filename or content type.
	 */
	private static function escape_part_value( string $value ): string {
		return str_replace( array( "\r", "\n", '"' ), '', $value );
	}
}
