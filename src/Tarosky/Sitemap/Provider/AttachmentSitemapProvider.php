<?php

namespace Tarosky\Sitemap\Provider;


use Tarosky\Sitemap\Pattern\SitemapProvider;

/**
 * Attachment sitemap.
 *
 * @package tsmap
 */
class AttachmentSitemapProvider extends PostSitemapProvider {

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
		$year     = get_query_var( 'year' );
		$month    = get_query_var( 'monthnum' );
		$from     = sprintf( '%04d-%02d-01 00:00:00', $year, $month );
		$to       = ( new \DateTimeImmutable() )->modify( sprintf( 'last day of %04d-%02d', $year, $month ) )->format( 'Y-m-d 23:59:59' );
		$per_page = $this->option()->posts_per_page;
		$offset   = ( max( 1, get_query_var( 'paged' ) ) - 1 ) * $per_page;
		/**
		 * Filters the JOIN clause for attachment sitemap query.
		 *
		 * @param string $join_clause JOIN clause for the SQL query
		 * @return string Filtered JOIN clause
		 *
		 * @hook tsmap_attachment_query_join
		 */
		$join_clause = apply_filters( 'tsmap_attachment_query_join', '' );
		/**
		 * Filters the WHERE clause for attachment sitemap query.
		 *
		 * @param string $where_clause WHERE clause for the SQL query
		 * @return string Filtered WHERE clause
		 *
		 * @hook tsmap_attachment_query_where
		 */
		$where_clause = apply_filters( 'tsmap_attachment_query_where', '' );
		$query        = <<<SQL
			SELECT p1.* FROM {$wpdb->posts} AS p1
			LEFT JOIN {$wpdb->posts} AS p2
			ON p1.post_parent = p2.ID
			{$join_clause}
			WHERE p1.post_type = 'attachment'
			  AND p1.post_status = 'inherit'
			  AND p1.post_date BETWEEN %s AND %s
			  AND p1.post_mime_type LIKE ':::image:::'
			  AND p2.post_status = 'publish'
			  {$where_clause}
			ORDER BY p1.post_date DESC
			LIMIT %d, %d
SQL;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$query = $wpdb->prepare( $query, $from, $to, $offset, $per_page );
		// Replace LIKE query..
		$query = str_replace( ':::image:::', 'image%', $query );
		// Already escaped.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $query );
		if ( empty( $results ) ) {
			return [];
		}
		$urls = [];
		foreach ( $results as $row ) {
			$urls[] = [
				'link'    => get_permalink( $row ),
				'lastmod' => $this->get_last_mod( $row->post_modified ),
				'images'  => [
					wp_get_attachment_image_url( $row->ID, 'full' ),
				],
			];
		}
		return $urls;
	}
}
