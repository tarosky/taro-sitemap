<?php

namespace Tarosky\Sitemap\Provider;


use Tarosky\Sitemap\Pattern\SitemapProvider;
use Tarosky\Sitemap\Utility\QueryArgsHelper;

/**
 * News sitemap.
 *
 * @package tsmap
 */
class NewsSitemapProvider extends SitemapProvider {

	protected $type = 'news';

	/**
	 * {@inheritdoc}
	 */
	protected function target_name() {
		return 'news';
	}

	/**
	 * {@inheritdoc}
	 */
	protected function namespaces() {
		return array_merge( parent::namespaces(), [
			'xmlns:news' => 'http://www.google.com/schemas/sitemap-news/0.9',
		] );
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_active() {
		$post_types = $this->option()->news_post_types;
		return ! empty( $post_types );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function get_urls() {
		$paged = max( 1, get_query_var( 'paged' ) );
		$query = new \WP_Query( $this->news_query_args( 'map', [
			'no_found_rows' => true,
		] ) );
		$urls  = [];
		foreach ( $query->posts as $post ) {
			$urls[] = [
				'link'    => get_permalink( $post ),
				'lastmod' => $this->get_last_mod( $post->post_modified ),
				'title'   => get_the_title( $post ),
				'date'    => get_the_time( 'Y-m-d', $post ),
			];
		}
		return $urls;
	}

	/**
	 * Render item.
	 *
	 * @param string[] $url URL.
	 * @return void
	 */
	public function do_item( $url ) {
		parent::do_item( $url );
		/**
		 * Filters the language code used in news sitemap.
		 *
		 * @param string $language Language code (e.g., 'en', 'ja')
		 * @return string Filtered language code
		 *
		 * @hook tsmap_news_lang
		 */
		$language = apply_filters( 'tsmap_news_lang', $this->get_site_lang() );
		?>
		<news:news>
			<news:publication>
				<news:name><![CDATA[ <?php echo esc_xml( $this->news_name() ); ?> ]]></news:name>
				<news:language><?php echo esc_xml( $language ); ?></news:language>
			</news:publication>
			<news:publication_date><?php echo esc_xml( $url['date'] ); ?></news:publication_date>
			<news:title><![CDATA[ <?php echo esc_xml( $url['title'] ); ?> ]]></news:title>
		</news:news>
		<?php
	}
}
