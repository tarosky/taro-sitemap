<?php
/**
 * PHPUnit bootstrap file for Taro Sitemap.
 */

// Load Composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

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
