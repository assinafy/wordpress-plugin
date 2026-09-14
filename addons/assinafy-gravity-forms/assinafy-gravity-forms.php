<?php
/**
 * Plugin Name: Assinafy for Gravity Forms
 * Description: Send an existing PDF for signature from a Gravity Forms feed.
 * Version: 0.1.0
 * Requires at least: 6.8
 * Requires PHP: 8.2
 * Requires Plugins: assinafy
 * Author: Assinafy
 * License: GPL-2.0-or-later
 * Text Domain: assinafy-gravity-forms
 *
 * @package Assinafy\WP\Addons\GravityForms
 */

declare(strict_types=1);

namespace Assinafy\WP\Addons\GravityForms;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Documents\SendService;

/** Register through the host's feed framework, including late plugin load order. */
function assinafy_gravity_forms_load(): void {
	static $registered = false;
	if ( $registered ) {
		return;
	}
	if ( ! class_exists( 'GFForms' ) || ! defined( 'ASSINAFY_VERSION' ) ) {
		return;
	}
	\GFForms::include_feed_addon_framework();
	require_once __DIR__ . '/Addon.php';
	require_once __DIR__ . '/Requests.php';
	$registered = true;
	\GFAddOn::register( Addon::class );
}
add_action( 'gform_loaded', __NAMESPACE__ . '\\assinafy_gravity_forms_load', 20 );
add_action( 'plugins_loaded', __NAMESPACE__ . '\\assinafy_gravity_forms_load', 20 );
add_action(
	'assinafy_ready',
	static function ( SendService $sender ): void {
		if ( class_exists( Addon::class, false ) ) {
			Addon::get_instance()->connect( $sender );
		}
	}
);
add_action(
	'admin_notices',
	static function (): void {
		if ( current_user_can( 'activate_plugins' ) && ( ! defined( 'ASSINAFY_VERSION' ) || ! class_exists( 'GFForms' ) ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Assinafy for Gravity Forms requires Assinafy Core 1.0.0 and Gravity Forms 2.9.4 or later.', 'assinafy-gravity-forms' ) . '</p></div>';
		}
	}
);
