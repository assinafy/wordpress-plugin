<?php
/**
 * Plugin Name: Assinafy for WPForms
 * Description: Request an Assinafy signature after an accepted WPForms submission.
 * Version: 0.1.0
 * Requires at least: 6.8
 * Requires PHP: 8.2
 * Requires Plugins: assinafy
 * Text Domain: assinafy-wpforms
 * License: GPL-2.0-or-later
 *
 * @package Assinafy\WP\Addons\WPForms
 */

declare(strict_types=1);

namespace Assinafy\WP\Addons\WPForms;

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Documents\DocumentRecord;
use Assinafy\WP\Documents\SendService;

require_once __DIR__ . '/src/FormSettings.php';
require_once __DIR__ . '/src/RequestStore.php';
require_once __DIR__ . '/src/Adapter.php';

add_action(
	'assinafy_ready',
	static function ( SendService $send, DocumentRecord $records ): void {
		if ( function_exists( 'wpforms' ) && defined( 'WPFORMS_VERSION' ) && version_compare( WPFORMS_VERSION, '1.9.8.1', '>=' ) ) {
			( new Adapter( $send, $records ) )->register();
		}
	},
	10,
	2
);

add_action(
	'admin_notices',
	static function (): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		if ( ! did_action( 'assinafy_ready' ) || ! function_exists( 'wpforms' ) || ! defined( 'WPFORMS_VERSION' ) || version_compare( WPFORMS_VERSION, '1.9.8.1', '<' ) ) {
			echo '<div class="notice notice-warning"><p>';
			esc_html_e( 'Assinafy for WPForms requires Assinafy 1.0.0 and WPForms Lite or Pro 1.9.8.1 or newer.', 'assinafy-wpforms' );
			echo '</p></div>';
		}
	}
);
