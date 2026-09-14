<?php
/**
 * Header-bag reads that WordPress does not provide.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Http;

defined( 'ABSPATH' ) || exit;

/**
 * Case-insensitive reads over the header arrays on both sides of a request.
 *
 * HTTP header names are case-insensitive and WordPress preserves whatever case the server
 * sent, so every lookup here compares with `strcasecmp` rather than by array key. Multi-value
 * headers stay arrays: the API reports pagination only in `X-Pagination-*`, and flattening
 * would lose it.
 */
final class Headers {

	/**
	 * Capture response headers without flattening them.
	 *
	 * `wp_remote_retrieve_headers()` returns a case-insensitive dictionary, not an array, and
	 * `getAll()` keeps multi-value headers as arrays. Pagination exists only in the four
	 * `X-Pagination-*` response headers — there is no `meta` key anywhere in this API — so a
	 * lossy capture here breaks every paginated list.
	 *
	 * @param array<string, mixed> $result Result of `wp_remote_request()`.
	 * @return array<string, array<int, string>|string>
	 */
	public static function from_result( array $result ): array {
		$headers = wp_remote_retrieve_headers( $result );

		if ( is_object( $headers ) && method_exists( $headers, 'getAll' ) ) {
			return $headers->getAll();
		}

		return is_array( $headers ) ? $headers : array();
	}


	/**
	 * One response header, matched case-insensitively; `''` when absent.
	 *
	 * @param array<string, array<int, string>|string> $headers Response headers.
	 * @param string                                   $name    Header name.
	 */
	public static function line( array $headers, string $name ): string {
		foreach ( $headers as $header_name => $value ) {
			if ( ! is_string( $header_name ) || 0 !== strcasecmp( $header_name, $name ) ) {
				continue;
			}

			return is_array( $value ) ? (string) ( $value[0] ?? '' ) : (string) $value;
		}

		return '';
	}


	/**
	 * Remove one header, matched case-insensitively.
	 *
	 * @param array<string, mixed> $headers Headers.
	 * @param string               $name    Header name.
	 * @return array<string, mixed>
	 */
	public static function without( array $headers, string $name ): array {
		foreach ( array_keys( $headers ) as $header_name ) {
			if ( is_string( $header_name ) && 0 === strcasecmp( $header_name, $name ) ) {
				unset( $headers[ $header_name ] );
			}
		}

		return $headers;
	}


	/**
	 * @param array<array-key, mixed> $headers Headers.
	 * @param string                  $name    Header name.
	 */
	public static function has( array $headers, string $name ): bool {
		foreach ( array_keys( $headers ) as $header_name ) {
			if ( is_string( $header_name ) && 0 === strcasecmp( $header_name, $name ) ) {
				return true;
			}
		}

		return false;
	}
}
