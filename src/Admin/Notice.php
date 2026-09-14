<?php
/**
 * One-shot admin notices carried across a redirect.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * A notice stored for one user until the next screen renders it.
 *
 * Every `admin_post_` handler in the plugin ends in a redirect, so the outcome it wants to
 * report cannot be echoed where it happens. It goes into a short-lived per-user transient
 * instead, and the screen the redirect lands on prints it once and deletes it.
 *
 * The key prefix is a parameter because the screens do not share a channel: a notice the
 * compose form set must not surface on a document's meta box, and vice versa.
 */
final class Notice {

	/**
	 * How long a notice survives unread. Long enough for the redirect, short enough that an
	 * abandoned one never appears on a screen opened later.
	 */
	private const TTL = MINUTE_IN_SECONDS;

	/**
	 * Store a notice for the current user.
	 *
	 * @param string $prefix  Transient key prefix identifying the channel.
	 * @param string $type    `success`, or anything else for an error.
	 * @param string $message Already-translated text.
	 */
	public static function set( string $prefix, string $type, string $message ): void {
		set_transient(
			$prefix . get_current_user_id(),
			array(
				'type'    => 'success' === $type ? 'success' : 'error',
				'message' => $message,
			),
			self::TTL
		);
	}

	/**
	 * Print the current user's notice, if there is one, and consume it.
	 *
	 * @param string $prefix    Transient key prefix identifying the channel.
	 * @param string $placement Extra class on the notice: `is-dismissible` for a full screen,
	 *                          `inline` inside a meta box.
	 */
	public static function render( string $prefix, string $placement ): void {
		$key    = $prefix . get_current_user_id();
		$notice = get_transient( $key );

		if ( ! is_array( $notice ) ) {
			return;
		}

		delete_transient( $key );

		printf(
			'<div class="notice notice-%1$s %2$s"><p>%3$s</p></div>',
			esc_attr( 'success' === ( $notice['type'] ?? '' ) ? 'success' : 'error' ),
			esc_attr( $placement ),
			esc_html( (string) ( $notice['message'] ?? '' ) )
		);
	}
}
