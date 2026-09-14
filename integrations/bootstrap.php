<?php
/**
 * Bundled adapters, registered independently of the Assinafy core.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

use Assinafy\WP\Documents\DocumentRecord;
use Assinafy\WP\Documents\SendService;
use Assinafy\WP\Integrations\WooCommerce;
use Assinafy\WP\Integrations\Elementor;

( new Elementor() )->register();

// Feature declarations must be attached while plugins load, before the core boots on init.
add_action( 'before_woocommerce_init', array( WooCommerce::class, 'declare_compatibility' ) );

add_action(
	'assinafy_ready',
	static function ( SendService $send, DocumentRecord $records ): void {
		if ( class_exists( 'WooCommerce' ) ) {
			( new WooCommerce( $send, $records ) )->register();
		}
	},
	10,
	2
);
