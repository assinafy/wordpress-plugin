<?php
/**
 * PHPUnit bootstrap for the unit suite.
 *
 * The unit suite deliberately runs with no WordPress installation at all: Composer's autoloader
 * plus `tests/wp-stubs.php`, which reimplements the handful of `wp_*` functions the classes
 * under test actually call. That is what lets the suite gate every CI job without a database,
 * a WordPress checkout, or Docker.
 *
 * The integration suite uses `tests/bootstrap.php` and `phpunit-integration.xml.dist` instead.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once __DIR__ . '/wp-stubs.php';
require_once __DIR__ . '/stubs/wp-http.php';
