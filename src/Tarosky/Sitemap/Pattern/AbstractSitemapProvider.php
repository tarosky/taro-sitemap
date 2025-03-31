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

	protected $start_time = 0;

	protected $queried_time = 0;

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
		$this->start_time = microtime( true );
		// Okay, time to render sitemap.
		$this->render();
		// Record Rendering time.
		$recording_time = microtime( true ) - $this->start_time;
		printf( "\n" . '<!-- Rendering Time: %fms -->', $recording_time * 1000 );
		// Render Query time.
		if ( $this->queried_time ) {
			printf( "\n" . '<!-- Query Time: %fms -->', ( $this->queried_time - $this->start_time ) * 1000 );
		}
		exit;
	}

	/**
	 * Save query time.
	 *
	 * @return void
	 */
	protected function set_query_time() {
		$this->queried_time = microtime( true );
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
