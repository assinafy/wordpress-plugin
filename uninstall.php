<?php
/**
 * Removes everything the plugin stored, when the site asked for that.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

$assinafy_autoload = __DIR__ . '/vendor-prefixed/autoload.php';

if ( is_readable( $assinafy_autoload ) ) {
	require_once $assinafy_autoload;
}

unset( $assinafy_autoload );

if ( ! class_exists( \Assinafy\WP\Settings::class ) ) {
	return;
}

\Assinafy\WP\Plugin::for_each_site(
	static function (): void {
		// Outside the retention gate: it covers settings and document records, and once the
		// plugin is deleted no code is left that could revoke a role grant.
		\Assinafy\WP\Capabilities::uninstall();

		if ( ! (bool) get_option( \Assinafy\WP\Settings::OPTION_DELETE_DATA, false ) ) {
			return;
		}

		// Use the registry so newly added settings cannot survive an opted-in uninstall.
		foreach ( array_keys( \Assinafy\WP\Settings::OPTIONS ) as $assinafy_option ) {
			delete_option( $assinafy_option );
		}

		delete_option( \Assinafy\WP\Log::OPTION );

		$assinafy_retained = 0;
		do {
			$assinafy_documents = get_posts(
				array(
					'post_type'        => \Assinafy\WP\Documents\DocumentPostType::POST_TYPE,
					'post_status'      => array_values( get_post_stati() ),
					'numberposts'      => 100,
					'offset'           => $assinafy_retained,
					'orderby'          => 'ID',
					'order'            => 'ASC',
					'fields'           => 'ids',
					'no_found_rows'    => true,
					// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters -- Uninstall must include all plugin records regardless of theme or language filters.
					'suppress_filters' => true,
				)
			);

			foreach ( $assinafy_documents as $assinafy_document_id ) {
				if ( ! wp_delete_post( (int) $assinafy_document_id, true ) ) {
					++$assinafy_retained;
				}
			}
			$assinafy_has_more = 100 === count( $assinafy_documents );
		} while ( $assinafy_has_more );

		\Assinafy\WP\Plugin::deactivate( false );

		// Enumerate only plugin transients and atomic send locks, then invalidate WP caches.
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Dynamic transient names have no enumeration API; this is a one-off uninstall.
		$assinafy_transients = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_assinafy_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_assinafy_' ) . '%',
				$wpdb->esc_like( \Assinafy\WP\Documents\SendService::LOCK_PREFIX ) . '%' . $wpdb->esc_like( '.lock' )
			)
		);

		foreach ( $assinafy_transients as $assinafy_transient ) {
			delete_option( (string) $assinafy_transient );
		}
	}
);
