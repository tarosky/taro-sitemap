<?php

namespace Tarosky\Sitemap\Seo\Features;


use Tarosky\Sitemap\Pattern\AbstractFeaturePattern;
use Tarosky\Sitemap\Pattern\PostMetaBoxTrait;

/**
 * Add feature to exclude from news sitemap.
 */
class NewsSitemapExclusion extends AbstractFeaturePattern {

	use PostMetaBoxTrait;

	protected function register_hooks() {
		add_filter( 'tsmap_news_sitemap_query_args', [ $this, 'news_sitemap_query' ] );
		$this->add_meta_box( 110 );
	}

	protected function is_active(): bool {
		$news_post_types = $this->option( 'news_post_types' );
		return ! empty( $news_post_types );
	}

	public function is_active_post_type( $post_type ): bool {
		$news_post_types = (array) $this->option( 'news_post_types' );
		return in_array( $post_type, $news_post_types, true );
	}

	protected function do_save( $post ): void {
		$input = filter_input( INPUT_POST, '_news_sitemap_noindex' );
		if ( '1' === $input ) {
			// Save meta value.
			update_post_meta( $post->ID, '_news_sitemap_noindex', '1' );
		} else {
			// Delete post meta.
			delete_post_meta( $post->ID, '_news_sitemap_noindex' );
		}
	}

	protected function render_meta_box( $post ): void {
		printf(
			'<p><label><input type="checkbox" name="_news_sitemap_noindex" value="1" %s</label> %s</p>',
			checked( get_post_meta( $post->ID, '_news_sitemap_noindex', true ), '1', false ),
			esc_html__( 'Exclude this post from news sitemap', 'tsmap' )
		);
	}

	/**
	 * Filter news sitemap query arguments.
	 *
	 * @param array $args Query arguments for news sitemap.
	 * @return array
	 */
	public function news_sitemap_query( $args ) {
		if ( ! isset( $args['meta_query'] ) ) {
			$args['meta_query'] = [];
		}
		$args['meta_query'][] = [
			'key'     => '_news_sitemap_noindex',
			'compare' => 'NOT EXISTS',
		];
		return $args;
	}
}
