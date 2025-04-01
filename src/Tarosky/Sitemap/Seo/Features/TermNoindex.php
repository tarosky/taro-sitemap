<?php

namespace Tarosky\Sitemap\Seo\Features;


use Tarosky\Sitemap\Pattern\RobotsFilterPattern;
use Tarosky\Sitemap\Pattern\TermMetaTrait;

class TermNoindex extends RobotsFilterPattern {

	use TermMetaTrait;

	/**
	 * {@inheritDoc}
	 */
	protected function register_hooks() {
		parent::register_hooks();
		$this->add_term_meta_hooks( 100 );
	}


	/**
	 * {@inheritDoc}
	 */
	protected function get_field( \WP_Term $term ): string {
		return sprintf(
			'<label><input type="checkbox" value="1" name="%s" %s /> %s</label>',
			esc_attr( $this->meta_key() ),
			checked( get_term_meta( $term->term_id, $this->meta_key(), true ), '1', false ),
			esc_html__( 'Make this term noindex for search engines.', 'tsmap' )
		);
	}

	/**
	 * Meta key name is filterable.
	 *
	 * @return string
	 */
	protected function meta_key() {
		/**
		 * Filters the meta key used to store term noindex status.
		 *
		 * @param string $meta_key Meta key for term noindex status (default: 'noindex')
		 * @return string Filtered meta key
		 *
		 * @hook tsmap_noindex_term_meta_key
		 */
		return apply_filters( 'tsmap_noindex_term_meta_key', 'noindex' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function taxonomies(): array {
		return (array) $this->option( 'noindex_terms' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function do_save( $term_id ): void {
		update_term_meta( $term_id, $this->meta_key(), (int) filter_input( INPUT_POST, $this->meta_key() ) );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function label(): string {
		return __( 'Noindex', 'tsmap' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function wp_robots( $robots ) {
		if ( ! ( is_category() || is_tag() || is_tax() ) ) {
			return $robots;
		}
		$term = get_queried_object();
		if ( ! is_a( $term, 'WP_Term' ) ) {
			return $robots;
		}
		if ( ! $this->is_active_taxonomy( $term->taxonomy ) ) {
			return $robots;
		}
		$term_meta = get_term_meta( $term->term_id, $this->meta_key(), true );
		if ( $term_meta ) {
			$robots['noindex'] = true;
		}
		return $robots;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function is_active(): bool {
		return 0 < count( $this->taxonomies() );
	}
}
