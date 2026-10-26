<?php

namespace Tarosky\Sitemap\Seo\Features;


use Tarosky\Sitemap\Pattern\AbstractFeaturePattern;

/**
 * Send ETag / Last-Modified and respond 304 to conditional requests.
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
		return (bool) $this->option( 'conditional_get' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function register_hooks() {
		// After redirect_canonical (priority 10), before the template is rendered.
		add_action( 'template_redirect', [ $this, 'handle_request' ], 11 );
	}

	/**
	 * Send headers and exit with 304 if not modified.
	 *
	 * @return void
	 */
	public function handle_request() {
		$post = $this->get_target_post();
		if ( ! $post || headers_sent() ) {
			return;
		}
		$etag          = $this->get_etag( $post );
		$last_modified = $this->get_last_modified( $post );
		header( 'ETag: ' . $etag );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $last_modified ) . ' GMT' );
		$if_none_match     = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) : '';
		$if_modified_since = isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ? wp_unslash( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) : '';
		if ( $this->is_not_modified( $etag, $last_modified, $if_none_match, $if_modified_since ) ) {
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
		 * Filters whether to handle conditional requests for the post.
		 *
		 * Return false for posts whose HTML changes without updating the post itself.
		 *
		 * @param bool     $should_handle Default true.
		 * @param \WP_Post $post          Post object.
		 *
		 * @hook tsmap_conditional_get_enabled
		 */
		if ( ! apply_filters( 'tsmap_conditional_get_enabled', true, $post ) ) {
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
	 * Get last modified timestamp of the post, including approved comments.
	 *
	 * @param \WP_Post $post Post object.
	 * @return int UNIX timestamp.
	 */
	public function get_last_modified( $post ) {
		$last_modified = (int) strtotime( $post->post_modified_gmt . ' UTC' );
		if ( 0 < $post->comment_count ) {
			$comments = get_comments( [
				'post_id' => $post->ID,
				'status'  => 'approve',
				'number'  => 1,
				'orderby' => 'comment_date_gmt',
				'order'   => 'DESC',
			] );
			if ( $comments ) {
				$last_modified = max( $last_modified, (int) strtotime( $comments[0]->comment_date_gmt . ' UTC' ) );
			}
		}
		/**
		 * Filters the last modified timestamp of the post.
		 *
		 * @param int      $last_modified UNIX timestamp.
		 * @param \WP_Post $post          Post object.
		 *
		 * @hook tsmap_last_modified
		 */
		return (int) apply_filters( 'tsmap_last_modified', $last_modified, $post );
	}

	/**
	 * Is the resource not modified? (RFC 9110 13.2.2)
	 *
	 * If-None-Match takes precedence over If-Modified-Since.
	 *
	 * @param string $etag              Current ETag.
	 * @param int    $last_modified     Current last modified timestamp.
	 * @param string $if_none_match     If-None-Match header.
	 * @param string $if_modified_since If-Modified-Since header.
	 * @return bool
	 */
	public function is_not_modified( $etag, $last_modified, $if_none_match, $if_modified_since ) {
		if ( '' !== trim( $if_none_match ) ) {
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
		if ( '' !== trim( $if_modified_since ) ) {
			$since = strtotime( $if_modified_since );
			return false !== $since && $last_modified <= $since;
		}
		return false;
	}
}
