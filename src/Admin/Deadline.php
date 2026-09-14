<?php
/**
 * The signature deadline a date field produces.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Turns the `YYYY-MM-DD` an `<input type="date">` posts into the API's `expires_at`.
 *
 * Both admin screens that offer a deadline — the compose form and the document meta box —
 * read the same field and need the same instant out of it, so the conversion lives here
 * rather than once per screen.
 */
final class Deadline {

	/**
	 * The end of the named day in UTC.
	 *
	 * A date is usable only when it parses, names a real calendar day, and is still in the
	 * future; anything else is refused, including today, because a deadline that has already
	 * passed would be accepted by the API and expire the request immediately.
	 *
	 * The instant is the last second of the day rather than midnight: a signer given until
	 * the 31st has the whole of the 31st.
	 *
	 * @param string $expires_on Date as posted, expected as `YYYY-MM-DD`.
	 *
	 * @return string ISO 8601 instant in UTC, or '' when the value is not a usable future date.
	 */
	public static function parse( string $expires_on ): string {
		if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $expires_on, $parts ) ) {
			return '';
		}

		if ( ! wp_checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1], $expires_on ) ) {
			return '';
		}

		$end_of_day = strtotime( $expires_on . ' 23:59:59 UTC' );

		if ( false === $end_of_day || $end_of_day <= time() ) {
			return '';
		}

		return gmdate( 'Y-m-d\TH:i:s\Z', $end_of_day );
	}
}
