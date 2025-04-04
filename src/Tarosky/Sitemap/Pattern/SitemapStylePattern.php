<?php

namespace Tarosky\Sitemap\Pattern;


use Tarosky\Sitemap\Utility\OptionAccessor;

/**
 * Render stylesheet.
 *
 * @package tsmap
 */
abstract class SitemapStylePattern extends Singleton {

	use OptionAccessor;

	/**
	 * Get style name.
	 *
	 * @return string
	 */
	abstract public function style_name();

	/**
	 * {@inheritdoc}
	 */
	protected function init() {
		add_action( 'tsmap_do_sitemap_sitemap_style', [ $this, 'render_style' ] );
	}

	/**
	 * Get stylesheet URL.
	 *
	 * @return string
	 */
	protected function get_stylesheet_url() {
		$url   = '';
		$style = wp_styles()->query( 'tsmap-sitemap', 'registered' );
		if ( $style ) {
			$url = add_query_arg( [
				'version' => $style->ver,
			], $style->src );
		}
		/**
		 * Filters the stylesheet URL for sitemap styles.
		 *
		 * @param string $url        Stylesheet URL
		 * @param string $style_name Style name
		 * @return string Filtered stylesheet URL
		 *
		 * @hook tsmap_sitmap_stylesheet_url
		 */
		return apply_filters( 'tsmap_sitmap_stylesheet_url', $url, $this->style_name() );
	}

	/**
	 * Do style tag.
	 *
	 * @return void
	 */
	abstract protected function do_style();

	/**
	 * Render stylesheet.
	 *
	 * @param string $target Target name.
	 * @return void
	 */
	public function render_style( $target ) {
		if ( $this->style_name() !== $target ) {
			// Do nothing.
			return;
		}
		header( 'Content-type: application/xml; charset=UTF-8' );
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$this->do_style();
		exit;
	}
}
