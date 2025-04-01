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
		add_action( 'edit_attachment', [ $this, 'save_attachment' ] );
	}

	/**
	 * Is this post type has meta box?
	 *
	 * @param string $post_type
	 * @return bool
	 */
	protected function is_active_post_type( $post_type ) {
		/**
		 * Filters whether a post type should have SEO meta boxes.
		 *
		 * @param bool   $has_meta_box Whether the post type has meta boxes (default: false)
		 * @param string $post_type    Post type name
		 * @return bool Whether the post type should have SEO meta boxes
		 *
		 * @hook tsmap_has_meta_box
		 */
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
		/**
		 * Action fired when post meta is being saved.
		 *
		 * Use this action to save custom post meta fields.
		 *
		 * @param \WP_Post $post Post object
		 *
		 * @hook tsmap_save_post_meta
		 */
		do_action( 'tsmap_save_post_meta', $post );
	}

	/**
	 * Save metabox for attachment
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_attachment( $post_id ) {
		$attachment = get_post( $post_id );
		$this->save_post( $post_id, $attachment );
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
		/**
		 * Action fired when rendering the SEO meta box for a post.
		 *
		 * Use this action to output custom fields in the SEO meta box.
		 *
		 * @param \WP_Post $post Post object
		 *
		 * @hook tsmap_do_meta_box
		 */
		do_action( 'tsmap_do_meta_box', $post );
	}
}
