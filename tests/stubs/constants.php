<?php
/**
 * The constants `assinafy.php` defines at load time, for static analysis only.
 *
 * PHPStan does not follow `define()` calls across file boundaries, so the constants the plugin
 * sets up in its bootstrap are invisible to it when analysing `src/`. Declaring them here keeps
 * the analysis honest without adding a runtime lookup or a wrapper function.
 *
 * This file is never loaded at runtime.
 *
 * @package Assinafy\WP
 */

declare(strict_types=1);

define( 'ASSINAFY_VERSION', '1.0.0' );
define( 'ASSINAFY_FILE', '' );
define( 'ASSINAFY_DIR', '' );
define( 'ASSINAFY_URL', '' );
