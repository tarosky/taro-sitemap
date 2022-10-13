<?php
/*
Plugin Name: Tarosky Sitemap
Plugin URI: https://wordpress.org/extend/plugins/taro-sitemap
Description: Yet another sitemap plugin with more than 200,000 posts.
Version: nightly
Author: Tarosky INC
Author URI: https://tarosky.co.jp
Text Domain: tsmap
Domain Path: /languages
License: GPL3 or Later
*/

/**
 * @package tsmap
 */

// Do not load directory.
defined( 'ABSPATH' ) || die();

/**
 * Initialize plugin.
 *
 * @return void
 */
function tsmap_init() {
	load_plugin_textdomain( 'tsmap', false, basename( __DIR__ ) . '/languages' );
	require __DIR__ . '/vendor/autoload.php';
	// Root Controllers.
	\Tarosky\Sitemap\Setting::get_instance();
	\Tarosky\Sitemap\Registry::get_instance();
	// Posts sitemap.
	\Tarosky\Sitemap\Provider\PostSitemapIndexProvider::get_instance();
	\Tarosky\Sitemap\Provider\PostSitemapProvider::get_instance();
	// News sitemaps.
	\Tarosky\Sitemap\Provider\NewsSitemapIndexProvider::get_instance();
	\Tarosky\Sitemap\Provider\NewsSitemapProvider::get_instance();
	// Attachment sitemap.
	\Tarosky\Sitemap\Provider\AttachmentSitemapIndexProvider::get_instance();
	\Tarosky\Sitemap\Provider\AttachmentSitemapProvider::get_instance();
	// Taxonomy sitemap.
	\Tarosky\Sitemap\Provider\TaxonomySitemapIndexProvider::get_instance();
	\Tarosky\Sitemap\Provider\TaxonomySitemapProvider::get_instance();
	// Sitemap style
	\Tarosky\Sitemap\Styles\SitemapIndexStyle::get_instance();
	\Tarosky\Sitemap\Styles\SitemapStyle::get_instance();
	\Tarosky\Sitemap\Styles\NewsStyle::get_instance();

	add_action( 'init', 'tsmap_register_assets' );
}

// Register hook.
add_action( 'plugins_loaded', 'tsmap_init' );

/**
 * Register assets.
 *
 * @return void
 */
function tsmap_register_assets() {
	$json = __DIR__ . '/wp-dependencies.json';
	if ( ! file_exists( $json ) ) {
		return;
	}
	$settings = json_decode( file_get_contents( $json ), true );
	if ( empty( $settings ) ) {
		return;
	}
	foreach ( $settings as $setting ) {
		if ( empty( $setting['path'] ) ) {
			continue;
		}
		$url = plugins_url( $setting['path'], __FILE__ );
		switch ( $setting['ext'] ) {
			case 'js':
				wp_register_script( $setting['handle'], $url, $setting['deps'], $setting['hash'], $setting['footer'] );
				break;
			case 'css':
				wp_register_style( $setting['handle'], $url, $setting['deps'], $setting['hash'], $setting['media'] );
				break;
		}
	}
}
