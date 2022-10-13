<?php

namespace Tarosky\Sitemap\Provider;

use Tarosky\Sitemap\Pattern\SitemapIndexProvider;

/**
 * Sitemap index for posts.
 */
class PostSitemapIndexProvider extends SitemapIndexProvider {

	/**
	 * {@inheritdoc}
	 */
	protected function target_name() {
		return 'posts';
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
		return $this->post_type_indices();
	}
}
