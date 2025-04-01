<?php

namespace Tarosky\Sitemap\Seo\Features;


use Tarosky\Sitemap\Pattern\AbstractFeaturePattern;
use Tarosky\Sitemap\Pattern\PostMetaBoxTrait;

class PostDescription extends AbstractFeaturePattern {

	use PostMetaBoxTrait;

	/**
	 * {@inheritDoc}
	 */
	protected function is_active(): bool {
		$post_types = $this->option( 'post_desc' );
		return ! empty( $post_types );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function register_hooks() {
		$this->add_meta_box( 99 );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_active_post_type( $post_type ): bool {
		$post_types = (array) $this->option( 'post_desc' );
		return in_array( $post_type, $post_types, true );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function do_save( $post ): void {
		update_post_meta( $post->ID, $this->meta_key(), filter_input( INPUT_POST, $this->meta_key() ) );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function render_meta_box( $post ): void {
		/**
		 * Filters the maximum length for post descriptions.
		 *
		 * @param int $max_length Maximum character length for post descriptions (default: 140)
		 * @return int Filtered maximum length
		 *
		 * @hook tsmap_post_description_max_length
		 */
		$max_length = apply_filters( 'tsmap_post_description_max_length', 140 );
		printf(
			'<p><label>%s<br /><textarea rows="5" name="%s" style="width:100%%; box-sizing: border-box" maxlength="%d">%s</textarea></lable></p>',
			esc_html__( 'Meta Description', 'tsmap' ),
			esc_attr( $this->meta_key() ),
			$max_length,
			esc_textarea( get_post_meta( $post->ID, $this->meta_key(), true ) )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function meta_key() {
		/**
		 * Filters the meta key used to store post descriptions.
		 *
		 * @param string $meta_key Meta key for post descriptions (default: '_description')
		 * @return string Filtered meta key
		 *
		 * @hook tsmap_post_description_key
		 */
		return apply_filters( 'tsmap_post_description_key', '_description' );
	}

	/**
	 * Display description.
	 *
	 * @param int|null|\WP_Post $post Post object.
	 * @return string
	 */
	public function get_description( $post = null ) {
		$post = get_post( $post );
		if ( $post ) {
			return get_post_meta( $post->ID, $this->meta_key(), true );
		}
		return '';
	}
}
