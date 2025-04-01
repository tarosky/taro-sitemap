<?php

namespace Tarosky\Sitemap\Seo\Features;


use Tarosky\Sitemap\Pattern\PostMetaBoxTrait;
use Tarosky\Sitemap\Pattern\RobotsFilterPattern;

class PostNoindex extends RobotsFilterPattern {

	use PostMetaBoxTrait;

	/**
	 * {@inheritDoc}
	 */
	protected function is_active(): bool {
		$post_types = $this->option( 'noindex_posts' );
		return ! empty( $post_types );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function register_hooks() {
		parent::register_hooks();
		$this->add_meta_box( 100 );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_active_post_type( $post_type ): bool {
		$post_types = (array) $this->option( 'noindex_posts' );
		return in_array( $post_type, $post_types, true );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function do_save( $post ): void {
		update_post_meta( $post->ID, $this->meta_key(), (int) filter_input( INPUT_POST, $this->meta_key() ) );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function render_meta_box( $post ): void {
		printf(
			'<p><label><input type="checkbox" name="%s" value="1" %s</label> %s</p>',
			esc_attr( $this->meta_key() ),
			checked( get_post_meta( $post->ID, $this->meta_key(), true ), '1', false ),
			esc_html__( 'Hide this post from search engines', 'tsmap' )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function wp_robots( $robots ) {
		if ( ! is_singular() || ! $this->is_active_post_type( get_queried_object()->post_type ) ) {
			return $robots;
		}
		if ( get_post_meta( get_queried_object_id(), $this->meta_key(), true ) ) {
			$robots['noindex'] = true;
		}
		return $robots;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function meta_key() {
		/**
		 * Filters the meta key used to store post noindex status.
		 *
		 * @param string $meta_key Meta key for post noindex status (default: '_noindex')
		 * @return string Filtered meta key
		 *
		 * @hook tsmap_noindex_key
		 */
		return apply_filters( 'tsmap_noindex_key', '_noindex' );
	}
}
