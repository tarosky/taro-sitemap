<?php

namespace Tarosky\Sitemap\Pattern;

/**
 * Features patterns.
 *
 * Cub classes of this class represent some features.
 */
abstract class AbstractFeaturePattern extends Singleton {

	/**
	 * Initialize.
	 *
	 * @return void
	 */
	final protected function init() {
		if ( ! $this->is_active() ) {
			return;
		}
		$this->register_hooks();
	}

	/**
	 * Is this feature is active?
	 *
	 * @return bool
	 */
	abstract protected function is_active():bool;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	abstract protected function register_hooks();

	/**
	 * Get option.
	 *
	 * @param string $key
	 * @return mixed
	 */
	public function option( $key ) {
		return get_option( 'tsmap_' . $key );
	}
}
