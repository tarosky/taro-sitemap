<?php

namespace Tarosky\Sitemap\Provider;


use Tarosky\Sitemap\Pattern\SitemapProvider;

/**
 * Post sitemap
 *
 * @package tsmap
 */
class PostSitemapProvider extends SitemapProvider {

	/**
	 * {@inheritdoc}
	 */
	protected function target_name() {
		return 'post';
	}

	/**
	 * Get sitemap url.
	 *
	 * @return string
	 */
	protected function get_xslt_url() {
		return home_url( 'sitemap_style_map.xsl' );
	}


	/**
	 * {@inheritdoc}
	 */
	protected function namespaces() {
		return array_merge( parent::namespaces(), [
			'xmlns:image' => 'http://www.google.com/schemas/sitemap-image/1.1',
		] );
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_active() {
		$post_types = $this->option()->post_types;
		return ! empty( $post_types );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function get_urls() {
		global $wpdb;
		$year      = get_query_var( 'year' );
		$month     = get_query_var( 'monthnum' );
		$from      = sprintf( '%04d-%02d-01 00:00:00', $year, $month );
		$to        = ( new \DateTimeImmutable() )->modify( sprintf( 'last day of %04d-%02d', $year, $month ) )->format( 'Y-m-d 23:59:59' );
		$in_clause = implode( ', ', array_map( function ( $post_type ) use ( $wpdb ) {
			return $wpdb->prepare( '%s', $post_type );
		}, $this->option()->post_types ) );
		$per_page  = $this->option()->posts_per_page;
		$offset    = ( max( 1, get_query_var( 'paged' ) ) - 1 ) * $per_page;
		$query     = <<<SQL
			SELECT * FROM {$wpdb->posts}
			WHERE post_type IN ( {$in_clause} )
			  AND post_status = 'publish'
			  AND post_date BETWEEN %s AND %s
			ORDER BY post_date DESC
			LIMIT %d, %d
SQL;
		// Already escaped.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$query = $wpdb->prepare( $query, $from, $to, $offset, $per_page );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $query );
		// Filter results to remove no index.
		$results = apply_filters( 'tsmap_sitemap_results', $results, $this->type, $this->target_name(), $year, $month );
		if ( empty( $results ) ) {
			return [];
		}
		$images = [];
		if ( 'post' === $this->option()->attachment_sitemap ) {
			// Includes attachment sitemaps.
			$post_ids         = array_map( function ( $post ) {
				return $post->ID;
			}, $results );
			$attachment_in    = implode( ', ', $post_ids );
			$attachment_query = new \WP_Query( [
				'post_type'       => 'attachment',
				'post_parent__in' => $post_ids,
				'fields'          => 'id=>parent',
				'post_status'     => 'inherit',
				'post_mime_type'  => 'image',
				'no_found_rows'   => true,
				'posts_per_page'  => -1,
			] );
			foreach ( $attachment_query->posts as $attachment ) {
				$parent = $attachment->post_parent;
				if ( ! isset( $images[ $parent ] ) ) {
					$images[ $parent ] = [];
				}
				$images[ $parent ][] = wp_get_attachment_image_url( $attachment->ID, 'full' );
			}
		}
		$urls = [];
		foreach ( $results as $post ) {
			$urls[] = [
				'link'    => get_permalink( $post ),
				'lastmod' => $this->get_last_mod( $post->post_modified ),
				'images'  => $images[ $post->ID ] ?? [],
			];
		}
		return $urls;
	}
}
