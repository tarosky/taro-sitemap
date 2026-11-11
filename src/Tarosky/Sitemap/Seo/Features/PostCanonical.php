<?php

namespace Tarosky\Sitemap\Seo\Features;


use Tarosky\Sitemap\Pattern\AbstractFeaturePattern;
use Tarosky\Sitemap\Pattern\PostMetaBoxTrait;

/**
 * Custom canonical URL for each post.
 */
class PostCanonical extends AbstractFeaturePattern {

	use PostMetaBoxTrait;

	/**
	 * {@inheritDoc}
	 */
	protected function is_active(): bool {
		return ! empty( $this->option( 'canonical_posts' ) );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function register_hooks() {
		$this->add_meta_box( 98 );
		add_filter( 'get_canonical_url', [ $this, 'filter_canonical' ], 10, 2 );
		add_filter( 'tsmap_ogp', [ $this, 'filter_ogp' ] );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_active_post_type( $post_type ): bool {
		return in_array( $post_type, (array) $this->option( 'canonical_posts' ), true );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function do_save( $post ): void {
		$url = $this->sanitize( (string) filter_input( INPUT_POST, $this->meta_key() ) );
		if ( $url ) {
			update_post_meta( $post->ID, $this->meta_key(), $url );
		} else {
			delete_post_meta( $post->ID, $this->meta_key() );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	protected function render_meta_box( $post ): void {
		printf(
			'<p><label>%s<br /><input type="url" name="%s" value="%s" placeholder="https://" style="width:100%%; box-sizing: border-box" /></label></p><p class="description">%s</p>',
			esc_html__( 'Canonical URL', 'tsmap' ),
			esc_attr( $this->meta_key() ),
			esc_attr( get_post_meta( $post->ID, $this->meta_key(), true ) ),
			esc_html__( 'Leave empty to use the permalink.', 'tsmap' )
		);
	}

	/**
	 * Meta key.
	 *
	 * @return string
	 */
	protected function meta_key() {
		/**
		 * Filters the meta key used to store custom canonical URL.
		 *
		 * @param string $meta_key Meta key for canonical URL (default: '_tsmap_canonical')
		 * @return string Filtered meta key
		 *
		 * @hook tsmap_post_canonical_key
		 */
		return apply_filters( 'tsmap_post_canonical_key', '_tsmap_canonical' );
	}

	/**
	 * Accept only absolute http(s) URL.
	 *
	 * @param string $url Input.
	 * @return string
	 */
	public function sanitize( $url ) {
		$url = esc_url_raw( trim( $url ), [ 'http', 'https' ] );
		return wp_http_validate_url( $url ) ? $url : '';
	}

	/**
	 * Get custom canonical URL.
	 *
	 * @param int|null|\WP_Post $post Post object.
	 * @return string Empty if not set.
	 */
	public function get_canonical( $post = null ) {
		$post = get_post( $post );
		if ( ! $post || ! $this->is_active_post_type( $post->post_type ) ) {
			return '';
		}
		return (string) get_post_meta( $post->ID, $this->meta_key(), true );
	}

	/**
	 * Override core canonical.
	 *
	 * @param string   $url  Canonical URL.
	 * @param \WP_Post $post Post object.
	 * @return string
	 */
	public function filter_canonical( $url, $post ) {
		return $this->get_canonical( $post ) ?: $url;
	}

	/**
	 * Sync og:url with canonical.
	 *
	 * @param array $ogp OGP values.
	 * @return array
	 */
	public function filter_ogp( $ogp ) {
		if ( is_singular() ) {
			$canonical = $this->get_canonical( get_queried_object() );
			if ( $canonical ) {
				$ogp['og:url'] = $canonical;
			}
		}
		return $ogp;
	}
}
