<?php

namespace Tarosky\Sitemap\Seo\Features;


use Tarosky\Sitemap\Pattern\AbstractFeaturePattern;

/**
 * Change title separator.
 */
class OgpGenerator extends AbstractFeaturePattern {

	/**
	 * {@inheritDoc}
	 */
	protected function is_active(): bool {
		return (bool) $this->option( 'ogp' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function register_hooks() {
		add_filter( 'language_attributes', [ $this, 'language_attributes' ] );
		add_action( 'wp_head', [ $this, 'render_ogp' ] );
	}

	/**
	 * Render ns.
	 *
	 * @param string $attributes
	 * @return string
	 */
	public function language_attributes( $attributes ) {
		$ns          = [ ( is_front_page() ) ? 'website: http://ogp.me/ns/website#' : 'article: http://ogp.me/ns/article#' ];
		$ns []       = 'og: http://ogp.me/ns#';
		$ns []       = 'fb: http://ogp.me/ns/fb#';
		$attributes .= sprintf( ' prefix="%s"', esc_attr( implode( ' ', $ns ) ) );
		return $attributes;
	}

	/**
	 * Render OGP tag.
	 *
	 * @return void
	 */
	public function render_ogp() {
		$ogp                 = [];
		$ogp['og:title']     = wp_get_document_title();
		$ogp['og:type']      = is_front_page() ? 'website' : 'article';
		$ogp['og:locale']    = get_locale();
		$ogp['og:site_name'] = get_bloginfo( 'name' );
		// Set URL.
		if ( is_front_page() ) {
			$url = home_url();
		} elseif ( is_singular() ) {
			$url = get_permalink( get_queried_object() );
		} elseif ( is_home() && get_option( 'page_for_posts' ) ) {
			$url = get_permalink( get_option( 'page_for_posts' ) );
		} elseif ( is_tag() || is_category() || is_tax() ) {
			$term = get_queried_object();
			if ( is_a( $term, 'WP_Term' ) ) {
				$url = get_term_link( $term );
			}
		} elseif ( is_author() ) {
			$author = get_queried_object();
			if ( is_a( $author, 'WP_User' ) ) {
				$url = get_author_posts_url( $author->ID );
			}
		} else {
			global $wp;
			$url = home_url( $wp->request );
		}
		$ogp['og:url'] = $url;
		// Set image.
		$image = '';
		if ( $this->option( 'default_image' ) ) {
			$attachment = wp_get_attachment_image_url( $this->option( 'default_image' ), 'full' );
			if ( $attachment ) {
				$image = $attachment;
			}
		}
		// Set date on singular
		if ( is_singular() && is_a( get_queried_object(), 'WP_Post' ) ) {
			$post                          = get_queried_object();
			$ogp['article:published_time'] = mysql2date( \DateTime::ATOM, $post->post_date );
			if ( $post->post_date < $post->post_modified ) {
				$ogp['article:modified_time'] = mysql2date( \DateTime::ATOM, $post->post_modified );
			}
		}
		if ( is_singular() && has_post_thumbnail( get_queried_object() ) ) {
			$image = wp_get_attachment_image_url( get_post_thumbnail_id( get_queried_object() ), 'full' );
		}
		/**
		 * Filters the image URL used for OGP tags.
		 *
		 * @param string $image Image URL
		 * @return string Filtered image URL
		 *
		 * @hook tsmap_ogp_image
		 */
		$image = apply_filters( 'tsmap_ogp_image', $image );
		if ( $image ) {
			$ogp['og:image'] = $image;
		}
		// Description.
		$ogp['og:description'] = AutoDescription::get_instance()->get_description();
		if ( $this->option( 'fb_page_url' ) ) {
			$ogp['article:publisher'] = $this->option( 'fb_page_url' );
		}
		// twitter
		$ogp['twitter:card'] = 'summary_large_image' === $this->option( 'twitter_size' ) ? 'summary_large_image' : 'summary';
		if ( $this->option( 'twitter_account' ) ) {
			$ogp['twitter:site'] = $this->option( 'twitter_account' );
		}
		// Render.
		/**
		 * Filters the OGP tags before output.
		 *
		 * @param array $ogp Array of OGP tags (key => value)
		 * @return array Filtered OGP tags
		 *
		 * @hook tsmap_ogp
		 */
		$ogp = apply_filters( 'tsmap_ogp', $ogp );
		foreach ( $ogp as $key => $value ) {
			if ( preg_match( '#^(og|fb|article):#', $key ) ) {
				$property = 'property';
			} else {
				$property = 'name';
			}
			printf( '<meta %s="%s" content="%s" />' . PHP_EOL, $property, $key, esc_attr( $value ) );
		}
	}
}
