<?php

namespace Tarosky\Sitemap\Seo\Features;


use Tarosky\Sitemap\Pattern\AbstractFeaturePattern;

/**
 * No index for other pages.
 *
 */
class CanonicalPriority extends AbstractFeaturePattern {

	/**
	 * {@inheritDoc}
	 */
	protected function register_hooks() {
		$priority = $this->canonical_priority();
		if ( 10 !== $priority ) {
			remove_action( 'wp_head', 'rel_canonical' );
			add_action( 'wp_head', 'rel_canonical', $priority );
		}
	}

	/**
	 * Get canonical priority.
	 *
	 * @return int
	 */
	public function canonical_priority() {
		if ( ! $this->is_active() ) {
			return 10;
		}
		return (int) $this->option( 'canonical_priority' );
	}


	/**
	 * {@inheritDoc}
	 */
	protected function is_active(): bool {
		$options = $this->option( 'canonical_priority' );
		return $options && is_numeric( $options );
	}
}
