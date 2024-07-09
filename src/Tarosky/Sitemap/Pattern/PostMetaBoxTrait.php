<?php

namespace Tarosky\Sitemap\Pattern;

/**
 * Utility for post meta box.
 */
trait PostMetaBoxTrait {

	/**
	 * Is active post types?
	 *
	 * @param string $post_type
	 * @return bool
	 */
	abstract public function is_active_post_type( $post_type ): bool;

	protected function add_meta_box( $priority = 11 ) {
		add_filter( 'tsmap_has_meta_box', [ $this, 'has_meta_box' ], $priority, 2 );
		add_action( 'tsmap_do_meta_box', [ $this, 'do_meta_box' ], $priority );
		add_action( 'tsmap_save_post_meta', [ $this, 'save' ], $priority );
	}

	/**
	 * Is active post type?
	 *
	 * @param bool   $has
	 * @param string $post_type
	 * @return bool
	 */
	public function has_meta_box( $has, $post_type ) {
		if ( $this->is_active_post_type( $post_type ) ) {
			$has = true;
		}
		return $has;
	}

	/**
	 * Render post meta box.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function do_meta_box( $post ) {
		if ( $this->is_active_post_type( $post->post_type ) ) {
			$this->render_meta_box( $post );
		}
	}

	/**
	 * Render meta box content.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	abstract protected function render_meta_box( $post ): void;

	/**
	 * Save if meta box is available.
	 *
	 * @param \WP_Post $post
	 * @return void
	 */
	public function save( $post ) {
		if ( $this->is_active_post_type( $post->post_type ) ) {
			$this->do_save( $post );
		}
	}

	/**
	 * Save meta box value.
	 *
	 * @param \WP_Post $post Save post field.
	 * @return void
	 */
	abstract protected function do_save( $post ): void;
}
