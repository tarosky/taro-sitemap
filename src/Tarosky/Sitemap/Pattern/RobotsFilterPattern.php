<?php

namespace Tarosky\Sitemap\Pattern;

/**
 * Add filter for wp_robots hook.
 */
abstract class RobotsFilterPattern extends AbstractFeaturePattern {


	/**
	 * {@inheritDoc}
	 */
	protected function register_hooks() {
		add_filter( 'wp_robots', [ $this, 'wp_robots' ]);
	}

	/**
	 * Add filter for wp_robots hook.
	 *
	 * @see wp_robots()
	 * @param string[] $robots An array for the value of `<meta name="robots" />`
	 * @return string[]
	 */
	abstract public function wp_robots( $robots );
}
