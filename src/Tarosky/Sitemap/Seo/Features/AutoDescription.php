<?php

namespace Tarosky\Sitemap\Seo\Features;


use Tarosky\Sitemap\Pattern\AbstractFeaturePattern;
use Tarosky\Sitemap\Seo\PostMetaBoxes;

/**
 * Change title separator.
 */
class AutoDescription extends AbstractFeaturePattern {

	/**
	 * {@inheritDoc}
	 */
	protected function is_active(): bool {
		return (bool) $this->option( 'auto_desc' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function register_hooks() {
		add_action( 'wp_head', [ $this, 'render_description' ] );
	}

	/**
	 * Render description.
	 *
	 * @return void
	 */
	public function render_description() {
		$description = $this->get_description();
		$description_length = apply_filters( 'tsmap_auto_description_length', 140 );
		// see: {wp_trim_words()}
		$text = trim( preg_replace( "/[\n\r\t ]+/", ' ', $description ), ' ' );
		preg_match_all( '/./u', $text, $words_array );
		$words_array = array_slice( $words_array[0], 0, $description_length + 1 );
		if ( count( $words_array ) >= $description_length + 1 ) {
			// Too long. trim.
			array_pop( $words_array );
			array_pop( $words_array );
			$words_array[] = '…';
		}
		printf( '<meta name="description" content="%s" />', esc_attr( implode(  '', $words_array ) ) );
	}

	/**
	 * Post object.
	 *
	 * @param null|int|\WP_Post $post
	 *
	 * @return string
	 */
	public function get_post_desc( $post = null ) {
		$fixed_desc = '';
		$post = get_post( $post );
		if ( ! $post ) {
			return $fixed_desc;
		}
		$meta = PostDescription::get_instance()->get_description( $post );
		if ( $meta ) {
			$fixed_desc = $meta;
		}
		if ( ! $fixed_desc && has_excerpt( $post ) ) {
			$fixed_desc = $post->post_excerpt;
		}
		if ( ! $fixed_desc && $this->is_auto() ) {
			$fixed_desc = get_the_excerpt( $post );
		}
		return $fixed_desc;
	}

	/**
	 * Get description.
	 *
	 * @return string
	 */
	public function get_description() {
		$fixed_desc = '';
		if ( is_front_page() ) {
			// Is meta for front page?
			$meta = $this->option( 'front_desc' );
			if ( $meta ) {
				$fixed_desc = $meta;
			}
			// Front page object exists?
			if ( ! $fixed_desc ) {
				$front_page_id = get_option( 'page_on_front' );
				if ( $front_page_id && get_post( $front_page_id ) ) {
					$fixed_desc = $this->get_post_desc( $front_page_id );
				}
			}
		} elseif ( is_singular() ) {
			$fixed_desc = $this->get_post_desc( get_queried_object() );
		} elseif ( is_home() ) {
			$home_page_id = get_option( 'page_for_posts' );
			if ( $home_page_id && get_post( $home_page_id ) ) {
				$fixed_desc = $this->get_post_desc( $home_page_id );
			}
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( is_a( $term, 'WP_Term' ) ) {
				$meta = $term->description;
				if ( $meta ) {
					$fixed_desc = $meta;
				}
			}
		} elseif ( is_author() ) {
			$user = get_queried_object();
			if ( $user && is_a( $user, 'WP_User' ) ) {
				$fixed_desc = $user->user_description;
			}
		} elseif ( is_search() ) {
			$fixed_desc = sprintf( __( 'Search results for: %s', 'tsmap' ), get_search_query() );
		} elseif ( is_404() ) {
			$fixed_desc = __( 'Page not found.', 'tsmap' );
		}
		$fixed_desc = apply_filters( 'tsmap_meta_desc', $fixed_desc );
		return strip_tags( nl2br( strip_shortcodes( $fixed_desc ) ) );
	}

	/**
	 * Get automatic description.
	 *
	 * @return bool
	 */
	public function is_auto() {
		return 'auto' === $this->option( 'auto_desc' );
	}
}
