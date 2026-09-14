<?php
/**
 * Plugin Name: Assinafy for Contact Form 7
 * Description: Request signatures for an existing PDF after a successful Contact Form 7 submission.
 * Version: 0.1.0
 * Requires at least: 6.8
 * Requires PHP: 8.2
 * Requires Plugins: assinafy, contact-form-7
 * Author: Assinafy
 * License: GPL-2.0-or-later
 * Text Domain: assinafy-contact-form-7
 *
 * @package Assinafy\WP\Addons\ContactForm7
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/src/FormSettings.php';
require_once __DIR__ . '/src/Adapter.php';
require_once __DIR__ . '/src/Requests.php';

add_action(
	'assinafy_ready',
	static function ( \Assinafy\WP\Documents\SendService $send, \Assinafy\WP\Documents\DocumentRecord $records ): void {
		if ( class_exists( 'WPCF7_ContactForm' ) ) {
			( new \Assinafy\WP\Addons\ContactForm7\Adapter( $send, $records ) )->register();
		}
	},
	10,
	2
);
