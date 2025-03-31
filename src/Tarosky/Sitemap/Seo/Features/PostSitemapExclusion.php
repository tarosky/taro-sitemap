<?php

namespace Tarosky\Sitemap\Seo\Features;


use Tarosky\Sitemap\Pattern\AbstractFeaturePattern;
use Tarosky\Sitemap\Pattern\PostMetaBoxTrait;

/**
 * Add feature to exclude from news sitemap.
 */
class PostSitemapExclusion extends AbstractFeaturePattern {

	use PostMetaBoxTrait;

	protected function register_hooks() {
		add_filter( 'tsmap_sitemap_results', [ $this, 'filter_post_sitemap' ] );
		$this->add_meta_box( 101 );
	}

	protected function is_active(): bool {
		return (bool) $this->option( 'exclusion_per_post' );
	}

	public function is_active_post_type( $post_type ): bool {
		return in_array( $post_type, $this->option( 'post_types' ), true );
	}

	protected function do_save( $post ): void {
		$input = filter_input( INPUT_POST, '_exclude_from_sitemap' );
		if ( '1' === $input ) {
			// Save meta value.
			update_post_meta( $post->ID, '_exclude_from_sitemap', '1' );
		} else {
			// Delete post meta.
			delete_post_meta( $post->ID, '_exclude_from_sitemap' );
		}
	}

	protected function render_meta_box( $post ): void {
		printf(
			'<p><label><input type="checkbox" name="_exclude_from_sitemap" value="1" %s</label> %s</p>',
			checked( get_post_meta( $post->ID, '_exclude_from_sitemap', true ), '1', false ),
			esc_html__( 'Exclude this post from post sitemap', 'tsmap' )
		);
	}

	/**
	 * Filter post sitemap results.
	 *
	 * @param \WP_Post[] $result
	 * @return array
	 */
	public function filter_post_sitemap( $result ) {
		$post_ids = array_map( function ( $post ) {
			return $post->ID;
		}, $result );
		if ( empty( $post_ids ) ) {
			return $result;
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
					'key'     => '_exclude_from_sitemap',
					'value'   => '1',
				],
			],
		] );
		if ( empty( $exclude_ids ) ) {
			return $result;
		}
		return array_values( array_filter( $result, function( $post ) use ( $exclude_ids ) {
			return ! in_array( (int) $post->ID, $exclude_ids, true );
		} ) );
	}
}
