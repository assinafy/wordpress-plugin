<?php
/**
 * PHPUnit bootstrap for the integration suite.
 *
 * The integration suite needs the WordPress core test library, whose location arrives in
 * `WP_TESTS_DIR` — wp-env exports it as `/wordpress-phpunit` inside the `tests-cli` container,
 * and the CI job exports the path it unpacked wordpress-develop into.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

/**
 * WordPress's own bootstrap loads the Yoast polyfills from wherever it was installed, which
 * is not this checkout. Pointing it at the copy Composer installed here keeps the two from
 * disagreeing about which version is loaded.
 */
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) && is_dir( dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills' );
}

$assinafy_tests_dir = getenv( 'WP_TESTS_DIR' );
$assinafy_tests_dir = is_string( $assinafy_tests_dir ) && '' !== $assinafy_tests_dir
	? rtrim( $assinafy_tests_dir, '/\\' )
	: rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';

if ( ! is_readable( $assinafy_tests_dir . '/includes/functions.php' ) ) {
	fwrite(
		STDERR,
		'The WordPress test library was not found at ' . $assinafy_tests_dir . '.' . PHP_EOL
		. 'Set WP_TESTS_DIR, or run the integration suite through wp-env:' . PHP_EOL
		. '    npm run test:php:integration' . PHP_EOL
		. 'The unit suite needs neither:' . PHP_EOL
		. '    composer test' . PHP_EOL
	);

	exit( 1 );
}

require_once $assinafy_tests_dir . '/includes/functions.php';

/**
 * Load the plugin the way WordPress would, before the core bootstrap reaches `init` — the
 * post type, the settings, the capability bridge and the REST route all register there, and
 * a plugin required after that point registers none of them.
 */
tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		/*
		 * WooCommerce, when the environment has it. The core test suite boots its own
		 * WordPress and loads only what this bootstrap requires, so a plugin being active on
		 * the site says nothing about whether the suite can see it. Without this the
		 * WooCommerce integration tests skip rather than run.
		 */
		$assinafy_woocommerce_file = getenv( 'ASSINAFY_WOOCOMMERCE_FILE' );
		$assinafy_woocommerce      = is_string( $assinafy_woocommerce_file ) && '' !== $assinafy_woocommerce_file
			? array( $assinafy_woocommerce_file )
			: glob( dirname( __DIR__, 2 ) . '/woocommerce*/woocommerce.php' );

		if ( is_array( $assinafy_woocommerce ) && array() !== $assinafy_woocommerce ) {
			require_once $assinafy_woocommerce[0];
		} elseif ( '1' === getenv( 'ASSINAFY_REQUIRE_WOOCOMMERCE' ) ) {
			fwrite( STDERR, 'WooCommerce is required for this integration run but was not found.' . PHP_EOL );
			exit( 1 );
		}

		// Explicit host paths keep licensed plugins optional and CI reproducible.
		foreach ( array( 'CF7', 'WPFORMS', 'GRAVITY_FORMS' ) as $assinafy_host ) {
			$assinafy_host_file = getenv( 'ASSINAFY_' . $assinafy_host . '_FILE' );
			if ( is_string( $assinafy_host_file ) && '' !== $assinafy_host_file ) {
				require_once $assinafy_host_file;
			}
		}
		if ( '1' === getenv( 'ASSINAFY_GRAVITY_FORMS_CONTRACT' ) && ! class_exists( 'GFForms' ) ) {
			require __DIR__ . '/stubs/gravity-forms.php';
		}
		require dirname( __DIR__ ) . '/assinafy.php';
		$assinafy_addons = glob( dirname( __DIR__ ) . '/addons/*/assinafy-*.php' );
		foreach ( is_array( $assinafy_addons ) ? $assinafy_addons : array() as $assinafy_addon ) {
			require_once $assinafy_addon;
		}
	}
);

/*
 * WooCommerce needs its own tables before `wc_create_order()` works, and the suite installs a
 * fresh database. `WC_Install::install()` is idempotent, so running it on every boot is safe.
 */
tests_add_filter(
	'setup_theme',
	static function (): void {
		if ( class_exists( 'WC_Install' ) ) {
			WC_Install::install();

			/*
			 * install() leaves `woocommerce_newly_installed` set, and WooCommerce consumes it
			 * on the next `admin_init`. The suite boots as frontend, so the first admin_init it
			 * ever runs is the one inside WP_Ajax_UnitTestCase::_handleAjax() — which means
			 * `maybe_enable_hpos()` runs in the middle of an admin-ajax request and prints its
			 * database errors into the buffer the test then decodes as JSON. A real site
			 * consumes the flag on an ordinary admin page load long before any ajax request.
			 */
			update_option( WC_Install::NEWLY_INSTALLED_OPTION, 'no' );

			/*
			 * HPOS creates its tables the first time the feature is switched on. When that
			 * happens inside a test it runs under WP_UnitTestCase's `query` filter, which
			 * rewrites CREATE TABLE into CREATE TEMPORARY TABLE — and MySQL refuses to open a
			 * temporary table twice in one statement, which every HPOS order read does: it
			 * joins wc_order_addresses once as billing and once as shipping. wc_get_order()
			 * then returns false and WooCommerce's own wc_paying_customer() fatals on it.
			 * Creating the tables here, before the first transaction, keeps them real ones,
			 * which is also what an installed site has.
			 */
			$assinafy_hpos_sync = '\\Automattic\\WooCommerce\\Internal\\DataStores\\Orders\\DataSynchronizer';

			if ( function_exists( 'wc_get_container' ) && class_exists( $assinafy_hpos_sync ) ) {
				wc_get_container()->get( $assinafy_hpos_sync )->create_database_tables();
			}
		}
		if ( class_exists( '\\WPForms\\Helpers\\DB' ) ) {
			\WPForms\Helpers\DB::create_custom_tables();
			// The suite boots as frontend, then WP_Ajax_UnitTestCase runs admin_init.
			// Real wp-admin requests load these native helpers before that action.
			require_once WPFORMS_PLUGIN_DIR . 'includes/admin/admin.php';
		}
	}
);

require $assinafy_tests_dir . '/includes/bootstrap.php';
