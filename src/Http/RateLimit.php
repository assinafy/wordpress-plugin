<?php
/**
 * The account's remaining request budget.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Http;

defined( 'ABSPATH' ) || exit;

/**
 * What the API last said about the rate-limit window.
 *
 * The budget is 120 requests a minute per key on a rolling window and a single upload spends
 * three to four of it, so a batch job needs to know where it stands before it starts. No SDK
 * resource method hands back a `Response`, which makes the transport the only place these
 * headers can be seen, and this transient the only place the answer survives the request that
 * learned it.
 */
final class RateLimit {

	/**
	 * Transient holding the budget the API last reported.
	 *
	 * Shape: `array{remaining:int, reset:int, recorded:int}`. Read it through
	 * {@see self::snapshot()} rather than by name.
	 */
	public const TRANSIENT = 'assinafy_rate_limit';

	/**
	 * How long the snapshot stays useful. The API window is one minute.
	 */
	private const TTL = 120;

	/**
	 * The rate-limit budget the API last reported, when still fresh.
	 *
	 * `remaining` is `X-Rate-Limit-Remaining`, `reset` is `X-Rate-Limit-Reset` in seconds, and
	 * `recorded` is the Unix time the values were captured. The reconcile cron reads this to
	 * back off; no SDK resource method returns a `Response`, so the transport is the only place
	 * these headers can be seen.
	 *
	 * @return array{remaining: int, reset: int, recorded: int}|null Null when nothing is recorded.
	 */
	public static function snapshot(): ?array {
		$snapshot = get_transient( self::TRANSIENT );

		if ( ! is_array( $snapshot ) || ! isset( $snapshot['remaining'], $snapshot['reset'], $snapshot['recorded'] ) ) {
			return null;
		}

		return array(
			'remaining' => (int) $snapshot['remaining'],
			'reset'     => (int) $snapshot['reset'],
			'recorded'  => (int) $snapshot['recorded'],
		);
	}


	/**
	 * Record the rate-limit budget a response reports.
	 *
	 * A 429 counts. It is the one status that reports a spent budget, and the reconcile cron
	 * cannot back off from a throttled window if the headers that say so are thrown away. Any
	 * other failure is ignored, as is a response that carries no usable header.
	 *
	 * @param int                                      $status_code HTTP status.
	 * @param array<string, array<int, string>|string> $headers     Response headers.
	 */
	public static function record( int $status_code, array $headers ): void {
		if ( 429 !== $status_code && ( $status_code < 200 || $status_code >= 300 ) ) {
			return;
		}

		$remaining = Headers::line( $headers, 'X-Rate-Limit-Remaining' );
		if ( ! is_numeric( $remaining ) ) {
			return;
		}

		$reset = Headers::line( $headers, 'X-Rate-Limit-Reset' );

		set_transient(
			self::TRANSIENT,
			array(
				'remaining' => (int) $remaining,
				'reset'     => is_numeric( $reset ) ? (int) $reset : 0,
				'recorded'  => time(),
			),
			self::TTL
		);
	}
}
