<?php

namespace Tarosky\Sitemap\Utility;

/**
 * Ping utility.
 *
 * @package tsmap
 */
trait Ping {

	/**
	 * Ping to Google.
	 *
	 * @param string $url
	 * @return bool|\WP_Error
	 */
	public function ping_url_to_google( $url ) {
		$url    = add_query_arg( [
			'sitemap' => rawurlencode( $url ),
		], 'https://www.google.com/ping' );
		$result = wp_remote_get( $url );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return true;
	}
}
