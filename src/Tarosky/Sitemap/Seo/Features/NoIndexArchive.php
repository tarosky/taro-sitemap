<?php

namespace Tarosky\Sitemap\Seo\Features;


use Tarosky\Sitemap\Pattern\RobotsFilterPattern;

/**
 * NoIndexArchive.
 *
 */
class NoIndexArchive extends RobotsFilterPattern {

	/**
	 * {@inheritDoc}
	 */
	protected function is_active(): bool {
		$paged = $this->option( 'noindex_archive_limit' );
		return $paged && is_numeric( $paged );
	}


	/**
	 * Add noindex to archive if page is greater than option.
	 *
	 * @see wp_robots()
	 * @param string[] $robots An array for the value of `<meta name="robots" />`
	 * @return string[]
	 */
	public function wp_robots( $robots ) {
		/**
		 * Filters the current page number used for archive noindex determination.
		 *
		 * @param int $paged Current page number from query var 'paged'
		 * @return int Filtered page number
		 *
		 * @hook tsmap_archive_count
		 */
		$cur_page = (int) apply_filters( 'tsmap_archive_count', get_query_var( 'paged' ) );
		if ( ! $cur_page ) {
			return $robots;
		}
		$max = (int) $this->option( 'noindex_archive_limit' );
		if ( $max < $cur_page ) {
			$robots['noindex'] = true;
		}
		return $robots;
	}
}
