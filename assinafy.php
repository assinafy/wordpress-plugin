<?php
/**
 * Plugin Name:       Assinafy
 * Plugin URI:        https://github.com/assinafy/wordpress-plugin
 * Description:       Send WordPress documents for electronic signature with Assinafy and track them to completion.
 * Version:           1.1.1
 * Requires at least: 6.8
 * Requires PHP:      8.2
 * WC requires at least: 10.2.2
 * WC tested up to:   11.1
 * Author:            Assinafy
 * Author URI:        https://www.assinafy.com.br/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       assinafy
 * Domain Path:       /languages
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

define( 'ASSINAFY_VERSION', '1.1.1' );
defined( 'ASSINAFY_OAUTH_CLIENT_ID' ) || define( 'ASSINAFY_OAUTH_CLIENT_ID', 'uf60VDVAg9DKG43nwql7b712SuGb1F7hOUN9Swp_ReglF55C' );
define( 'ASSINAFY_FILE', __FILE__ );
define( 'ASSINAFY_DIR', plugin_dir_path( __FILE__ ) );
define( 'ASSINAFY_URL', plugin_dir_url( __FILE__ ) );

/**
 * Minimum PHP version. The Assinafy PHP SDK requires 8.2, so an older interpreter
 * cannot run this plugin at all. Bail with a notice rather than a fatal error.
 */
if ( version_compare( PHP_VERSION, '8.2', '<' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: required PHP version, 2: current PHP version. */
						__( 'Assinafy requires PHP %1$s or newer. This site runs PHP %2$s.', 'assinafy' ),
						'8.2',
						PHP_VERSION
					)
				)
			);
		}
	);

	return;
}

$assinafy_autoload = ASSINAFY_DIR . 'vendor-prefixed/autoload.php';

if ( is_readable( $assinafy_autoload ) ) {
	require_once $assinafy_autoload;
}

unset( $assinafy_autoload );

/**
 * The autoloader is only proven by a class it is supposed to provide. Checking that the
 * file exists proves nothing: a truncated or unbuilt tree passes that test and the next
 * line fatals.
 */
if ( ! class_exists( \Assinafy\SDK\AssinafyClient::class ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'Assinafy could not load its dependencies. Reinstall the plugin from a complete package.', 'assinafy' )
			);
		}
	);

	return;
}

register_activation_hook( __FILE__, array( \Assinafy\WP\Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \Assinafy\WP\Plugin::class, 'deactivate' ) );

// Bundled adapters attach through the same public readiness hook as external add-ons.
require_once ASSINAFY_DIR . 'integrations/bootstrap.php';

add_action(
	'wp_initialize_site',
	static function ( WP_Site $site ): void {
		\Assinafy\WP\Plugin::initialize_site( (int) $site->blog_id, (int) $site->network_id );
	},
	200
);

add_action( 'init', array( \Assinafy\WP\Plugin::class, 'boot' ) );
