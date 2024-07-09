<?php

namespace Tarosky\Sitemap\Seo\Features;


use Tarosky\Sitemap\Pattern\RobotsFilterPattern;

/**
 * No index for other pages.
 *
 */
class OtherNoindex extends RobotsFilterPattern {

	/**
	 * {@inheritDoc}
	 */
	protected function is_active(): bool {
		$options = $this->option( 'noindex_other' );
		return ! empty( $options );
	}


	/**
	 * {@inheritDoc}
	 */
	public function wp_robots( $robots ) {
		$noindex  = false;
		$nofollow = false;
		$options  = $this->option( 'noindex_other' );
		if ( is_search() ) {
			$noindex  = in_array( 'search', $options, true );
			$nofollow = $noindex;
		} elseif ( is_404() ) {
			$noindex = in_array( '404', $options, true );
		} elseif ( is_attachment() ) {
			$noindex = in_array( 'attachment', $options, true );
		}
		if ( $noindex ) {
			$robots['noindex'] = true;
		}
		if ( $nofollow ) {
			$robots['nofollow'] = true;
			if ( isset( $robots['follow'] ) ) {
				unset( $robots['follow'] );
			}
		}
		return $robots;
	}
}
