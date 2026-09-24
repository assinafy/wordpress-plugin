<?php
/**
 * Queue-driven double for `wp_remote_request()`.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Tests\Unit\Support;

use WP_Error;

/**
 * Records what the transport asked for and replays what a test told it to answer.
 *
 * Nothing here reaches the network: `tests/wp-stubs.php` points `wp_remote_request()` at
 * {@see self::handle()}, so a test can assert on the exact `$args` the transport built —
 * headers, `redirection`, `sslverify`, the multipart body — and on how it reacts to a
 * given status, header set and body.
 */
final class FakeHttp {

	/**
	 * Requests the transport made, oldest first.
	 *
	 * @var array<int, array{url: string, args: array<string, mixed>}>
	 */
	public static array $requests = array();

	/**
	 * Responses still queued, oldest first.
	 *
	 * @var array<int, array<string, mixed>|WP_Error>
	 */
	private static array $queue = array();

	/** @var (\Closure(): void)|null Simulates another request during an HTTP call. */
	public static ?\Closure $before_response = null;

	/**
	 * Forget every recorded request and queued response.
	 */
	public static function reset(): void {
		self::$requests        = array();
		self::$queue           = array();
		self::$before_response = null;
	}

	/**
	 * Queue one HTTP response.
	 *
	 * @param int                                     $status  HTTP status line code.
	 * @param string                                  $body    Raw response body.
	 * @param array<string, array<int, string>|string> $headers Response headers.
	 */
	public static function queue( int $status, string $body = '', array $headers = array() ): void {
		self::$queue[] = array(
			'response' => array(
				'code'    => $status,
				'message' => '',
			),
			'headers'  => new FakeHeaders( $headers ),
			'body'     => $body,
		);
	}

	/**
	 * Queue one transport failure.
	 *
	 * @param string $code    Error code, e.g. `http_request_failed`.
	 * @param string $message Error message; the real one quotes the failed URL.
	 */
	public static function queue_error( string $code, string $message = '' ): void {
		self::$queue[] = new WP_Error( $code, $message );
	}

	/**
	 * The most recent request, or null when none was made.
	 *
	 * @return array{url: string, args: array<string, mixed>}|null
	 */
	public static function last(): ?array {
		return self::$requests[ count( self::$requests ) - 1 ] ?? null;
	}

	/**
	 * Request headers of the most recent request, lower-cased keys.
	 *
	 * @return array<string, string>
	 */
	public static function last_headers(): array {
		$last    = self::last();
		$headers = is_array( $last['args']['headers'] ?? null ) ? $last['args']['headers'] : array();
		$lowered = array();

		foreach ( $headers as $name => $value ) {
			$lowered[ strtolower( (string) $name ) ] = (string) $value;
		}

		return $lowered;
	}

	/**
	 * Serve one request from the queue.
	 *
	 * @param string               $url  Request URL.
	 * @param array<string, mixed> $args Request arguments.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function handle( string $url, array $args ) {
		self::$requests[] = array(
			'url'  => $url,
			'args' => $args,
		);
		if ( null !== self::$before_response ) {
			( self::$before_response )();
		}

		if ( array() === self::$queue ) {
			return new WP_Error( 'assinafy_test_no_response', 'No response was queued for this request.' );
		}

		return array_shift( self::$queue );
	}
}
