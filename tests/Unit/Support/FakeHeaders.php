<?php
/**
 * Stand-in for the case-insensitive header dictionary WordPress returns.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Unit\Support;

/**
 * `wp_remote_retrieve_headers()` hands back a `Requests_Utility_CaseInsensitiveDictionary`,
 * not an array, and only its `getAll()` keeps a repeated header as a list. Pagination in this
 * API exists solely in the four `X-Pagination-*` response headers, so a transport that reads
 * anything but `getAll()` would flatten them; this double exists to make that observable.
 */
final class FakeHeaders {

	/**
	 * @param array<string, array<int, string>|string> $headers Response headers.
	 */
	public function __construct( private array $headers ) {}

	/**
	 * @return array<string, array<int, string>|string>
	 */
	public function getAll(): array { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName -- Mirrors the WordPress class this doubles.
		return $this->headers;
	}
}
