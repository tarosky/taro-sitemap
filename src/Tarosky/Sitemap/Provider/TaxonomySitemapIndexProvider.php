<?php

namespace Tarosky\Sitemap\Provider;

use Tarosky\Sitemap\Pattern\SitemapIndexProvider;

/**
 * Sitemap index for posts.
 */
class TaxonomySitemapIndexProvider extends SitemapIndexProvider {

	/**
	 * {@inheritdoc}
	 */
	protected function target_name() {
		return 'taxonomy';
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_active() {
		return (bool) $this->option()->taxonomies;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function get_urls() {
		$per_page = $this->option()->posts_per_page;
		global $wpdb;
		$in_clause = implode( ', ', array_map( function ( $taxonomy ) use ( $wpdb ) {
			return $wpdb->prepare( '%s', $taxonomy );
		}, $this->option()->taxonomies ) );
		$query     = <<<SQL
			SELECT COUNT(term_id)
			FROM {$wpdb->term_taxonomy}
			WHERE taxonomy IN ( {$in_clause} )
			  AND count > 0
SQL;
		// Already escaped.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$paged = ceil( $wpdb->get_var( $query ) / $per_page );
		$urls  = [];
		for ( $i = 1; $i <= $paged; $i++ ) {
			$urls[] = $links[] = $this->sitemap_url([
				'sitemap_type'   => 'map',
				'sitemap_target' => 'taxonomy',
				'paged'          => $i,
			]);
		}
		return $urls;
	}
}
