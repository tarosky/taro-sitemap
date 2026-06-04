<?php
/**
 * PHPUnit bootstrap file for Taro Sitemap.
 */

// Load Composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Polyfill for enum_exists() — available since PHP 8.1.
// CI installs Composer dependencies on PHP 8.1 (pulling doctrine/instantiator 2.x,
// which calls enum_exists() unconditionally) but runs the tests in the PHP 7.4
// wp-env container, where the function does not exist. The mock builder would
// otherwise fatal with "Call to undefined function enum_exists()".
if ( ! function_exists( 'enum_exists' ) ) {
	function enum_exists( string $enum, bool $autoload = true ): bool {
		return false;
	}
}

// Determine the tests directory (defaults to the WP PHPUnit path in wp-env).
$_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: '/wordpress-phpunit/';

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
tests_add_filter( 'muplugins_loaded', function () {
	require dirname( __DIR__ ) . '/taro-sitemap.php';
} );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
