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
		$query    = new \WP_Query( $this->news_query_args( 'index', [
			'posts_per_page' => 1,
			'fields'         => 'ids',
		] ) );
		$total    = $query->found_posts;
		$per_page = $this->default_news_per_page();
		$urls     = [];
		for ( $i = 1; $i <= ceil( $total / $per_page ); $i++ ) {
			$urls[] = $links[] = $this->sitemap_url([
				'sitemap_type'   => 'news',
				'sitemap_target' => 'news',
				'paged'          => $i,
			]);
		}
		return $urls;
	}
}
