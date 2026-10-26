<?php

namespace Tarosky\Sitemap\Seo\Features;


use Tarosky\Sitemap\Pattern\AbstractFeaturePattern;

/**
 * Send ETag and optionally respond 304 to conditional requests.
 *
 * Lets crawlers like Googlebot reuse cached pages.
 *
 * @see https://developers.google.com/search/blog/2024/12/crawling-december-caching
 */
class ConditionalGet extends AbstractFeaturePattern {

	/**
	 * {@inheritDoc}
	 */
	protected function is_active(): bool {
		return (bool) $this->option( 'etag' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function register_hooks() {
		// After redirect_canonical (priority 10), before the template is rendered.
		add_action( 'template_redirect', [ $this, 'handle_request' ], 11 );
	}

	/**
	 * Send ETag and exit with 304 if enabled and not modified.
	 *
	 * @return void
	 */
	public function handle_request() {
		$post = $this->get_target_post();
		if ( ! $post || headers_sent() ) {
			return;
		}
		$etag = $this->get_etag( $post );
		header( 'ETag: ' . $etag );
		if ( ! $this->option( 'etag_304' ) ) {
			return;
		}
		$if_none_match = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) : '';
		if ( $this->is_not_modified( $etag, $if_none_match ) ) {
			status_header( 304 );
			exit;
		}
	}

	/**
	 * Get the post if the current request is a target.
	 *
	 * @return \WP_Post|null
	 */
	public function get_target_post() {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( $_SERVER['REQUEST_METHOD'] ) : 'GET';
		if ( ! in_array( $method, [ 'GET', 'HEAD' ], true ) ) {
			return null;
		}
		// Responses may differ per user.
		if ( is_user_logged_in() || is_preview() || ! is_singular() ) {
			return null;
		}
		$post = get_queried_object();
		if ( ! is_a( $post, 'WP_Post' ) || 'publish' !== $post->post_status || post_password_required( $post ) ) {
			return null;
		}
		/**
		 * Filters whether to send ETag for the post.
		 *
		 * @param bool     $should_handle Default true.
		 * @param \WP_Post $post          Post object.
		 *
		 * @hook tsmap_etag_enabled
		 */
		if ( ! apply_filters( 'tsmap_etag_enabled', true, $post ) ) {
			return null;
		}
		return $post;
	}

	/**
	 * Get weak ETag of the post.
	 *
	 * Weak because the HTML is not byte-identical (e.g. nonce, ads).
	 *
	 * @param \WP_Post $post Post object.
	 * @return string
	 */
	public function get_etag( $post ) {
		/**
		 * Filters the components which make up the ETag.
		 *
		 * Add values which change the page HTML, e.g. theme version.
		 *
		 * @param array    $components Components.
		 * @param \WP_Post $post       Post object.
		 *
		 * @hook tsmap_etag_components
		 */
		$components = apply_filters( 'tsmap_etag_components', [
			$post->ID,
			$post->post_modified_gmt,
			$post->comment_count,
		], $post );
		return sprintf( 'W/"%s"', md5( implode( '|', $components ) ) );
	}

	/**
	 * Does If-None-Match match the current ETag? (RFC 9110 13.1.2)
	 *
	 * @param string $etag          Current ETag.
	 * @param string $if_none_match If-None-Match header.
	 * @return bool
	 */
	public function is_not_modified( $etag, $if_none_match ) {
		if ( '*' === trim( $if_none_match ) ) {
			return true;
		}
		// Weak comparison: ignore W/ prefix.
		$current = preg_replace( '#^W/#', '', $etag );
		foreach ( explode( ',', $if_none_match ) as $tag ) {
			if ( preg_replace( '#^W/#', '', trim( $tag ) ) === $current ) {
				return true;
			}
		}
		return false;
	}
}
