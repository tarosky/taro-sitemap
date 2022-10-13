<?php

namespace Tarosky\Sitemap\Pattern;


use Tarosky\Sitemap\Utility\OptionAccessor;
use Tarosky\Sitemap\Utility\QueryArgsHelper;

/**
 * Get sitemap index.
 *
 * @package tsmap
 */
abstract class AbstractSitemapProvider extends Singleton {

	use QueryArgsHelper;

	/**
	 * Render header
	 *
	 * @return void
	 */
	protected function header() {
		header( 'Content-type: application/xml; charset=UTF-8' );
	}

	/**
	 * Get XSLT URL.
	 *
	 * @return string
	 */
	protected function get_xslt_url() {
		return '';
	}

	/**
	 * Return name of sitemap index name.
	 *
	 * @return string
	 */
	abstract protected function target_name();

	/**
	 * @return void
	 */
	protected function init() {
		if ( $this->is_active() ) {
			// Register sitemap
			add_action( 'tsmap_do_sitemap_' . $this->type, [ $this, 'do_sitemap' ] );
		}
	}

	/**
	 * Is sitemap index active?
	 *
	 * @return bool
	 */
	abstract public function is_active();

	/**
	 * Render sitemap.
	 *
	 * @param string $target
	 * @return void
	 */
	public function do_sitemap( $target ) {
		if ( $target !== $this->target_name() ) {
			// This is not target.
			return;
		}
		// Okay, time to render sitemap.
		$this->render();
		exit;
	}

	/**
	 * Get URL list.
	 *
	 * @return string[]|array[]
	 */
	abstract protected function get_urls();

	/**
	 * Render sitemap.
	 *
	 * @return void
	 */
	abstract public function render();
}
