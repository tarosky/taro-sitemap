<?php

namespace Tarosky\Sitemap\Provider;


use Tarosky\Sitemap\Pattern\SitemapIndexProvider;
use Tarosky\Sitemap\Utility\QueryArgsHelper;

/**
 * News sitemap index.
 */
class NewsSitemapIndexProvider extends SitemapIndexProvider {


	/**
	 * {@inheritdoc}
	 */
	protected function target_name() {
		return 'news';
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_active() {
		$types = $this->option()->news_post_types;
		return ! empty( $types );
	}

	/**
	 * Get URLs.
	 *
	 * @return string[]
	 */
	protected function get_urls() {
		$query = new \WP_Query( $this->news_query_args( 'index', [
			'posts_per_page' => 1,
			'fields'         => 'ids',
		] ) );
		// Filter out external permalinks
		$valid_post_ids = array_filter( $query->posts, function ( $post_id ) {
			return ! $this->is_external_permalink( get_post( $post_id ) );
		} );
		$total          = count( $valid_post_ids );
		$per_page       = $this->default_news_per_page();
		$urls           = [];
		for ( $i = 1; $i <= ceil( $total / $per_page ); $i++ ) {
			$urls[] = home_url( sprintf( 'sitemap_news_%d.xml', $i ) );
		}
		return $urls;
	}
}
