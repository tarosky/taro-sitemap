<?php

namespace Tarosky\Sitemap\Seo;


use Tarosky\Sitemap\Pattern\Singleton;

/**
 * Post meta boxes for SEO.
 */
class PostMetaBoxes extends Singleton {

	/**
	 *
	 *
	 * @return void
	 */
	protected function init() {
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ], 100 );
		add_action( 'save_post', [ $this, 'save_post' ], 10, 2 );
	}

	/**
	 * Is this post type has meta box?
	 *
	 * @param string $post_type
	 * @return bool
	 */
	protected function is_active_post_type( $post_type ) {
		return apply_filters( 'tsmap_has_meta_box', false, $post_type );
	}

	/**
	 * Save meta box field.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 *
	 * @return void
	 */
	public function save_post( $post_id, $post ) {
		if ( ! wp_verify_nonce( filter_input( INPUT_POST, '_tsmapmetanonce' ), 'tsmap_postmeta' ) ) {
			return;
		}
		if ( ! $this->is_active_post_type( $post->post_type ) ) {
			return;
		}
		// Should save action.
		do_action( 'tsmap_save_post_meta', $post );
	}

	/**
	 * Add meta boxes if available.
	 *
	 * @param string $post_type
	 * @return void
	 */
	public function add_meta_boxes( $post_type ) {
		if ( $this->is_active_post_type( $post_type ) ) {
			add_meta_box( 'tsmap-post-meta', __( 'SEO Setting', 'tsmap' ), [ $this, 'do_meta_box' ], $post_type, 'side', 'low' );
		}
	}

	/**
	 * Render meta box.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function do_meta_box( $post ) {
		wp_nonce_field( 'tsmap_postmeta', '_tsmapmetanonce', false );
		do_action( 'tsmap_do_meta_box', $post );
	}
}
