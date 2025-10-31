<?php

namespace Tarosky\Sitemap\Provider;

use Tarosky\Sitemap\Pattern\SitemapIndexProvider;

/**
 * Sitemap index for posts.
 */
class AttachmentSitemapIndexProvider extends SitemapIndexProvider {

	/**
	 * {@inheritdoc}
	 */
	protected function target_name() {
		return 'attachment';
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_active() {
		return 'attachment' === $this->option()->attachment_sitemap;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function get_urls() {
		global $wpdb;
		/**
		 * Filters the JOIN clause for attachment sitemap index query.
		 *
		 * @param string $join_clause JOIN clause for the SQL query
		 * @return string Filtered JOIN clause
		 *
		 * @hook tsmap_attachment_query_join
		 */
		$join_clause = apply_filters( 'tsmap_attachment_query_join', '' );
		/**
		 * Filters the WHERE clause for attachment sitemap index query.
		 *
		 * @param string $where_clause WHERE clause for the SQL query
		 * @return string Filtered WHERE clause
		 *
		 * @hook tsmap_attachment_query_where
		 */
		$where_clause = apply_filters( 'tsmap_attachment_query_where', '' );
		$query        = <<<SQL
			SELECT
			    EXTRACT( YEAR_MONTH from p1.post_date ) as date,
			    GROUP_CONCAT(p1.ID) as ids
			FROM {$wpdb->posts} AS p1
			LEFT JOIN {$wpdb->posts} AS p2
			ON p1.post_parent = p2.ID
			{$join_clause}
			WHERE p1.post_type = 'attachment'
			  AND p1.post_mime_type LIKE 'image%'
			  AND p2.post_status = 'publish'
			  {$where_clause}
			GROUP BY EXTRACT( YEAR_MONTH from p1.post_date )
SQL;
		$urls         = [];
		// Already escaped.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( $wpdb->get_results( $query ) as $row ) {
			$ids = array_map( 'intval', explode( ',', $row->ids ) );
			// Filter out external attachments
			$valid_ids = array_filter( $ids, function ( $id ) {
				return ! $this->is_external_permalink( get_post( $id ) );
			} );
			$total     = count( $valid_ids );
			// Skip month if no internal attachments
			if ( 0 === $total ) {
				continue;
			}
			$pages = ceil( $total / $this->option()->posts_per_page );
			for ( $i = 1; $i <= $pages; $i++ ) {
				$urls[] = home_url( sprintf( 'sitemap_attachment_%06d_%d.xml', $row->date, $i ) );
			}
		}
		return $urls;
	}
}
