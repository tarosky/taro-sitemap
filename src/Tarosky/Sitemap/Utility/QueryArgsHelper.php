<?php

namespace Tarosky\Sitemap\Utility;


/**
 * Get query args.
 */
trait QueryArgsHelper {

	use OptionAccessor;

	/**
	 * Get query arguments.
	 *
	 * @param string $type Hook type.
	 * @param array  $args Arguments to override.
	 * @return array
	 */
	protected function news_query_args( $type, $args = [] ) {
		/**
		 * Filters the query arguments for news sitemap.
		 *
		 * Use this filter to modify the WP_Query arguments that control which posts
		 * are included in the news sitemap.
		 *
		 * @param array  $args Query arguments array
		 * @param string $type Hook type
		 * @return array Filtered query arguments
		 *
		 * @hook tsmap_news_sitemap_query_args
		 */
		return apply_filters( 'tsmap_news_sitemap_query_args', array_merge( [
			'post_status'         => 'publish',
			'post_type'           => $this->option()->news_post_types,
			'orderby'             => [ 'date' => 'DESC' ],
			'posts_per_page'      => $this->default_news_per_page(),
			'ignore_sticky_posts' => true,
			'date_query'          => [
				[
					'after'     => '48 hours ago',
					'inclusive' => true,
				],
			],
		], $args ), $type );
	}

	/**
	 * Checks if permalink is external.
	 *
	 * @return bool
	 */
	public function is_external_permalink( \WP_Post|int $post ) {
		$url = get_permalink( $post );
		if ( is_string( $url ) ) {
			$site_host = parse_url( get_site_url(), PHP_URL_HOST );
			$url_host  = parse_url( $url, PHP_URL_HOST );
			if ( $url_host !== $site_host ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * News posts per page.
	 *
	 * @return int
	 */
	protected function default_news_per_page() {
		/**
		 * Filters the maximum number of posts per page in news sitemap.
		 *
		 * @param int $per_page Maximum number of posts per page (default: 1000)
		 * @return int Filtered maximum number of posts per page
		 *
		 * @hook tsmap_news_sitemap_per_page
		 */
		return min( 1000, (int) apply_filters( 'tsmap_news_sitemap_per_page', 1000 ) );
	}

	/**
	 * Get lastmod.
	 *
	 * @return string
	 */
	protected function get_last_mod( $post_date ) {
		return mysql2date( \DateTime::W3C, $post_date );
	}

	/**
	 * Get news name.
	 *
	 * @return string
	 */
	public function news_name() {
		static $news_name = '';
		if ( empty( $news_name ) ) {
			/**
			 * Filters the news publication name used in news sitemap.
			 *
			 * @param string $name Default publication name (site title)
			 * @return string Filtered publication name
			 *
			 * @hook tsmap_news_name
			 */
			$news_name = apply_filters( 'tsmap_news_name', get_bloginfo( 'title' ) );
		}
		return $news_name;
	}

	/**
	 * Get default locale.
	 *
	 * @return string
	 */
	public function get_site_lang() {
		static $default_locale = '';
		if ( empty( $default_locale ) ) {
			$locale = array_map( 'strtolower', explode( '_', get_locale() ) );
			if ( 'zh' === $locale[0] ) {
				$default_locale = implode( '-', $locale );
			} else {
				$default_locale = $locale[0];
			}
		}
		return $default_locale;
	}

	/**
	 * Get paginated post types.
	 *
	 * @return array
	 */
	public function post_type_indices() {
		global $wpdb;
		$post_types = $this->option()->post_types;
		if ( empty( $post_types ) ) {
			return [];
		}
		$in_clause = implode( ', ', array_map( function ( $post_type ) use ( $wpdb ) {
			return $wpdb->prepare( '%s', $post_type );
		}, $post_types ) );
		/**
		 * Filters the WHERE clauses for post index query in sitemap.
		 *
		 * @param array    $where_clauses Array of WHERE clauses
		 * @param string[] $post_types    Array of post types
		 * @return array Filtered WHERE clauses
		 *
		 * @hook tsmap_post_index_query_where
		 */
		$wheres = apply_filters( 'tsmap_post_index_query_where', [
			"post_type IN ( {$in_clause} )",
			"post_status = 'publish'",
		], $post_types);
		$wheres = implode( ' AND ', $wheres );
		$query  = <<<SQL
			SELECT
			    EXTRACT( YEAR_MONTH from post_date ) as date,
			    GROUP_CONCAT(ID) as ids
			FROM {$wpdb->posts}
			WHERE {$wheres}
			GROUP BY EXTRACT( YEAR_MONTH from post_date )
SQL;
		// Already escaped.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->get_results( $query );
		$urls   = [];
		foreach ( $result as $row ) {
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
				$urls[] = home_url( sprintf( 'sitemap_post_%06d_%d.xml', $row->date, $i ) );
			}
		}
		return $urls;
	}
}
