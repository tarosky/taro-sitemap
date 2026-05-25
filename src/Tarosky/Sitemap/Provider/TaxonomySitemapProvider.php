<?php

namespace Tarosky\Sitemap\Provider;


use Tarosky\Sitemap\Pattern\SitemapProvider;

/**
 * Taxonomy sitemap
 *
 * @package tsmap
 */
class TaxonomySitemapProvider extends SitemapProvider {

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
		return $this->option()->taxonomies;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function get_urls() {
		global $wpdb;
		$in_clause = implode( ', ', array_map( function ( $taxonomy ) use ( $wpdb ) {
			return $wpdb->prepare( '%s', $taxonomy );
		}, $this->option()->taxonomies ) );
		$per_page  = $this->option()->posts_per_page;
		$offset    = ( max( 1, get_query_var( 'paged' ) ) - 1 ) * $per_page;
		$query     = <<<SQL
			SELECT *
			FROM {$wpdb->term_taxonomy} AS tt
			INNER JOIN {$wpdb->terms} AS t
			ON tt.term_id = t.term_id
			WHERE tt.taxonomy IN ( {$in_clause} )
			  AND tt.count > 0
			ORDER BY tt.term_id ASC
			LIMIT %d, %d
SQL;
		// Already escaped.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$query = $wpdb->prepare( $query, $offset, $per_page );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $query );
		/**
		 * Filters the taxonomy results for sitemap entries.
		 *
		 * @since 3.0.0
		 *
		 * @param object[] $results     Array of term objects from database query
		 * @param string   $type        Sitemap type (e.g., 'map')
		 * @param string   $target_name Target name (e.g., 'taxonomy')
		 * @return array Filtered results
		 *
		 * @hook tsmap_taxonomy_sitemap_results
		 */
		$results = apply_filters( 'tsmap_taxonomy_sitemap_results', $results, $this->type, $this->target_name() );
		if ( empty( $results ) ) {
			return [];
		}
		$urls = [];
		foreach ( $results as $term ) {
			$urls[] = [
				'link' => get_term_link( $term, $term->taxonomy ),
			];
		}
		return $urls;
	}
}
