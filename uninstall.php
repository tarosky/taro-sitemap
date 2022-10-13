<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die( 'Do not access directly.' );
}

foreach ( [
	'disable_core',
	'post_types',
	'posts_per_page',
	'attachment_sitemap',
	'news_post_types',
	'taxonomies',
] as $key ) {
	delete_option( 'tsmap_' . $key );
}
