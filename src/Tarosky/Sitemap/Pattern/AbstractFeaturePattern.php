<?php

namespace Tarosky\Sitemap\Pattern;

/**
 * Features patterns.
 *
 * Cub classes of this class represent some features.
 */
abstract class AbstractFeaturePattern extends Singleton {

	/**
	 * Initialize.
	 *
	 * @return void
	 */
	final protected function init() {
		if ( ! $this->is_active() ) {
			return;
		}
		$this->register_hooks();
	}

	/**
	 * Is this feature is active?
	 *
	 * @return bool
	 */
	abstract protected function is_active(): bool;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	abstract protected function register_hooks();

	/**
	 * Get option.
	 *
	 * @param string $key
	 * @return mixed
	 */
	public function option( $key ) {
		return get_option( 'tsmap_' . $key );
	}

	/**
	 * Exclude posts with meta key.
	 *
	 * @param \WP_Post[] $posts
	 * @param string     $meta_key
	 * @param mixed      $meta_value
	 *
	 * @return \WP_Post[]
	 */
	protected function exclude_list( $posts, $meta_key, $meta_value = '1' ) {
		$post_ids = array_map( function ( $post ) {
			return $post->ID;
		}, $posts );
		if ( empty( $post_ids ) ) {
			return $posts;
		}
		// Get post ids which has no meta.
		$exclude_ids = get_posts( [
			'fields'         => 'ids',
			'post_type'      => $this->option( 'post_types' ),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'post__in'       => $post_ids,
			'meta_query'     => [
				[
					'key'   => $meta_key,
					'value' => $meta_value,
				],
			],
		] );
		if ( empty( $exclude_ids ) ) {
			return $posts;
		}
		return array_values( array_filter( $posts, function ( $post ) use ( $exclude_ids ) {
			return ! in_array( (int) $post->ID, $exclude_ids, true );
		} ) );
	}
}
