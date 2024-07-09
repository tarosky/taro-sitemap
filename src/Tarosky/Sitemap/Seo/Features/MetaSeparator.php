<?php

namespace Tarosky\Sitemap\Seo\Features;


use Tarosky\Sitemap\Pattern\AbstractFeaturePattern;

/**
 * Change title separator.
 */
class MetaSeparator extends AbstractFeaturePattern {

	/**
	 * {@inheritDoc}
	 */
	protected function is_active(): bool {
		return (bool) $this->option( 'separator' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function register_hooks() {
		add_filter( 'document_title_separator', function ( $sep ) {
			return (string) $this->option( 'separator' );
		} );
	}
}
