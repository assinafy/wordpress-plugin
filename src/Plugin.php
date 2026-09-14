<?php
/**
 * Plugin wiring.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

namespace Assinafy\WP;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Admin\DocumentMetaBox;
use Assinafy\WP\Admin\SendScreen;
use Assinafy\WP\Documents\DocumentPostType;
use Assinafy\WP\Documents\DocumentRecord;
use Assinafy\WP\Documents\DownloadProxy;
use Assinafy\WP\Documents\SendService;
use Assinafy\WP\Documents\StatusSync;
use Assinafy\WP\Integrations\Hook;
use Assinafy\WP\Webhook\Handler;
use Assinafy\WP\Webhook\Route;

/**
 * Builds the core object graph and hooks it up. Every other class
 * receives what it needs through its constructor, so nothing here is reachable as a
 * global service locator.
 *
 * `boot()` runs on `init`. Nothing translated, and nothing that registers a post type,
 * setting or route, may run before that.
 */
final class Plugin {

	/**
	 * The hourly reconcile cron hook.
	 */
	public const CRON_HOOK = 'assinafy_reconcile';

	/**
	 * The booted instance, or null before `boot()`.
	 */
	private static ?self $instance = null;

	/**
	 * Build and register everything. Safe to call twice.
	 */
	public static function boot(): void {
		if ( null !== self::$instance ) {
			return;
		}

		self::$instance = new self();
		self::$instance->register();
	}

	/**
	 * Prepare the site on activation.
	 *
	 * No `flush_rewrite_rules()`: the document post type is `public => false` and has no
	 * rewrite surface, so flushing would cost a full rule rebuild and change nothing.
	 *
	 * @param bool $network_wide Whether WordPress is activating the whole network.
	 */
	public static function activate( bool $network_wide ): void {
		if ( $network_wide && is_multisite() ) {
			self::for_each_site( static fn() => self::activate( false ), get_current_network_id() );

			return;
		}

		Capabilities::install();

		if ( '' === (string) get_option( Settings::OPTION_WEBHOOK_TOKEN, '' ) ) {
			// Through `Route`, so the token's length and alphabet stay the ones its route
			// pattern matches. A token generated to a different shape would leave the
			// endpoint answering 404.
			Route::rotate_token();
		}

		if ( false === wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CRON_HOOK );
		}
	}

	/**
	 * Stop reconciliation and cancel queued sends on deactivation. No document is deleted here — a deactivated plugin
	 * that has thrown away the link between local records and remote documents cannot be
	 * reactivated usefully.
	 *
	 * @param bool $network_wide Whether WordPress is deactivating the whole network.
	 */
	public static function deactivate( bool $network_wide ): void {
		if ( $network_wide && is_multisite() ) {
			self::for_each_site(
				static function (): void {
					// Network deactivation leaves independently activated site plugins running.
					if ( ! in_array( plugin_basename( ASSINAFY_FILE ), (array) get_option( 'active_plugins', array() ), true ) ) {
						self::deactivate( false );
					}
				},
				get_current_network_id()
			);

			return;
		}

		wp_clear_scheduled_hook( self::CRON_HOOK );
		wp_unschedule_hook( Hook::ACTION );
	}

	/**
	 * Provision a new site after WordPress has created its tables and roles.
	 *
	 * @param int $site_id    Newly initialized site id.
	 * @param int $network_id Network owning the site.
	 */
	public static function initialize_site( int $site_id, int $network_id ): void {
		$active = get_network_option( $network_id, 'active_sitewide_plugins', array() );

		if ( ! is_array( $active ) || ! isset( $active[ plugin_basename( ASSINAFY_FILE ) ] ) ) {
			return;
		}

		switch_to_blog( $site_id );
		try {
			self::activate( false );
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * Apply a lifecycle operation to sites in bounded pages, restoring the caller's site.
	 *
	 * @param callable(): void $callback   Operation on the current site.
	 * @param int              $network_id Network to visit, or zero for all on uninstall.
	 */
	public static function for_each_site( callable $callback, int $network_id = 0 ): void {
		if ( ! is_multisite() ) {
			$callback();

			return;
		}

		$offset = 0;
		do {
			$site_ids = get_sites(
				array(
					'fields'     => 'ids',
					'number'     => 100,
					'offset'     => $offset,
					'network_id' => $network_id,
					'orderby'    => 'id',
					'order'      => 'ASC',
				)
			);

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				try {
					$callback();
				} finally {
					restore_current_blog();
				}
			}

			$offset  += 100;
			$has_more = 100 === count( $site_ids );
		} while ( $has_more );
	}

	/**
	 * Build the graph and attach it to WordPress.
	 */
	private function register(): void {
		// Just-in-time loading never looks inside a plugin's own directory, so the bundled
		// catalogue in /languages is only found once this call registers the path.
		// Plugin Check reports this function as discouraged. That advice assumes translations
		// arrive as wordpress.org language packs, which take precedence here when they exist;
		// the call stays so pt_BR works from the first install, before any pack is published.
		load_plugin_textdomain( 'assinafy', false, dirname( plugin_basename( ASSINAFY_FILE ) ) . '/languages' );

		$credentials = new Credentials();
		$log         = new Log();
		$clients     = new ClientFactory( $credentials, $log );
		$records     = new DocumentRecord();
		$send        = new SendService( $clients, $records, $log );
		$sync        = new StatusSync( $clients, $records );

		( new Capabilities() )->register();
		( new DocumentPostType() )->register();
		( new Settings( $credentials, $clients, $log ) )->register();
		( new DownloadProxy( $clients, $records ) )->register();
		( new Hook( $send ) )->register();
		( new Route( new Handler( $sync, $log ) ) )->register();
		( new Privacy( $records, $log ) )->register();

		// reconcile() returns the number of documents it refreshed, which `wp assinafy sync`
		// reports. An action callback must return nothing, so the count is discarded here.
		add_action(
			self::CRON_HOOK,
			static function () use ( $sync ): void {
				$sync->reconcile();
			}
		);

		if ( is_admin() ) {
			( new SendScreen( $send ) )->register();
			( new DocumentMetaBox( $clients, $records, $sync ) )->register();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( \WP_CLI::class ) ) {
			( new Cli( $clients, $send, $sync ) )->register();
		}

		/**
		 * Register adapters after the core services and WordPress hooks are ready.
		 *
		 * Adapters attach their listener while plugins load, before `init` runs.
		 *
		 * @param SendService    $send    Shared signature-request service.
		 * @param DocumentRecord $records Typed access to local documents and their sources.
		 */
		do_action( 'assinafy_ready', $send, $records );
	}
}
