<?php

namespace Tarosky\Sitemap\Seo\Features;


use Tarosky\Sitemap\Pattern\AbstractFeaturePattern;

/**
 * No index for other pages.
 *
 */
class CanonicalArchive extends AbstractFeaturePattern {

	/**
	 * {@inheritDoc}
	 */
	protected function register_hooks() {
		add_action( 'wp_head', [ $this, 'rel_canonical' ], CanonicalPriority::get_instance()->canonical_priority() );
	}

	/**
	 * Render canonical link for archive.
	 *
	 * @return void
	 */
	public function rel_canonical() {
		if ( is_singular() || is_front_page() ) {
			return;
		}
		$canonical = '';
		$options   = $this->option( 'canonical_archive' );
		$paged     = get_query_var( 'paged' );
		$suffix    = 1 < $paged ? sprintf( 'page/%d', $paged ) : '';
		if ( is_author() ) {
			if ( in_array( 'author', $options, true ) ) {
				// Try to render author url.
				$author = get_queried_object();
				if ( $author && is_a( $author, 'WP_User' ) ) {
					$canonical = get_author_posts_url( $author->ID );
				}
			}
		} elseif ( ( is_category() || is_tag() || is_tax() ) ) {
			if ( in_array( 'taxonomies', $options, true ) ) {
				// Try to render taxonomy url.
				$term = get_queried_object();
				if ( $term && is_a( $term, 'WP_Term' ) ) {
					$canonical = get_term_link( $term );
				}
			}
		} elseif ( is_post_type_archive() ) {
			if ( in_array( 'post_type', $options, true ) ) {
				// Try to render post type url.
				$canonical = get_post_type_archive_link( get_query_var( 'post_type' ) );
			}
		} elseif ( is_home() ) {
			if ( in_array( 'home', $options, true ) ) {
				// Try to get home url.
				$home_url_id = get_option( 'page_for_posts' );
				if ( $home_url_id && get_post( $home_url_id ) ) {
					$canonical = get_permalink( $home_url_id );
				}
			}
		}
		if ( $canonical && $suffix ) {
			if ( preg_match( '#/$#', $canonical ) ) {
				$suffix .= '/';
			}
			$canonical = trailingslashit( $canonical ) . $suffix;
		}
		$canonical = apply_filters( 'tsmap_canonical_archive_url', $canonical );
		if ( $canonical ) {
			printf( '<link rel="canonical" href="%s" />' . PHP_EOL, esc_url( $canonical ) );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	protected function is_active(): bool {
		$options = $this->option( 'canonical_archive' );
		return ! empty( $options );
	}
}
