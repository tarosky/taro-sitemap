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
				DATE_FORMAT(post_date, '%Y-%m') AS date,
				YEAR(post_date) AS year,
				MONTH(post_date) AS month,
				COUNT(ID) AS total
			FROM {$wpdb->posts}
			WHERE {$wheres}
			GROUP BY YEAR(post_date), MONTH(post_date)
		SQL;
		// Already escaped.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->get_results( $query );
		$links  = [];
		foreach ( $result as $row ) {
			$total_page = ceil( $row->total / $this->option()->posts_per_page );
			for ( $i = 1; $i <= $total_page; $i++ ) {
				// $links[] = home_url( sprintf( 'sitemap_post_%06d_%d.xml', $row->date, $i ) );
				$links[] = $this->sitemap_url([
					'sitemap_type'   => 'map',
					'sitemap_target' => 'post',
					'year'           => $row->year,
					'monthnum'       => $row->month,
					'paged'          => $i,
				]);
			}
		}
		return $links;
	}

	/**
	 * Get the sitemap URL.
	 *
	 * @param array $args {
	 *     @type string $sitemap_type   Required. The type of the sitemap.
	 *     @type string $sitemap_target Required. The target of the sitemap.
	 *     @type int    $paged          Required. The page number.
	 *     @type int    $year           Optional. The year of the sitemap archive.
	 *     @type int    $monthnum       Optional. The month number of the sitemap archive.
	 * }
	 * @return string
	 */
	public function sitemap_url( $args ) {
		if ( empty( $args['sitemap_type'] ) || empty( $args['sitemap_target'] ) || ! is_numeric( $args['paged'] ) ) {
			return '';
		}
		if ( get_option( 'permalink_structure' ) ) {
			$parts = [ 'sitemap', $args['sitemap_target'] ];
			if ( isset( $args['year'], $args['monthnum'] ) ) {
				$parts[] = sprintf( '%s%02d', $args['year'], $args['monthnum'] );
			}
			$parts[] = $args['paged'];
			return home_url( implode( '_', $parts ) . '.xml' );
		}
		return add_query_arg( $args, home_url( '/' ) );
	}
}
