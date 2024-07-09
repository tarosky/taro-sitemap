<?php

namespace Tarosky\Sitemap\Pattern;


/**
 * Utility for Term meta page.
 */
trait TermMetaTrait {

	/**
	 * Array of taxonomies.
	 *
	 * @return string[]
	 */
	abstract protected function taxonomies(): array;

	/**
	 * Initialize.
	 *
	 * @param int $priority Hook priority.
	 * @return void
	 */
	protected function add_term_meta_hooks( $priority = 11 ) {
		add_filter( 'tsmap_has_term_meta', [ $this, 'has_term_meta' ], $priority );
		add_filter( 'tsmap_term_meta_fields', [ $this, 'get_meta_fields' ], $priority, 2 );
		add_action( 'tsmap_save_term_meta', [ $this, 'save' ], $priority, 2 );
	}

	/**
	 * Is active taxonomy?
	 *
	 * @param string[] $taxonomies Taxonomies.
	 * @return string[]
	 */
	public function has_term_meta( $taxonomies ) {
		foreach ( $this->taxonomies() as $taxonomy ) {
			if ( ! in_array( $taxonomy, $taxonomies, true ) ) {
				$taxonomies[] = $taxonomy;
			}
		}
		return $taxonomies;
	}

	/**
	 * Render post meta box.
	 *
	 * @param array    $fields Fields of term meta. label => field.
	 * @param \WP_Term $term   Term object.
	 * @return array
	 */
	public function get_meta_fields( array $fields, \WP_Term $term ) {
		if ( $this->is_active_taxonomy( $term->taxonomy ) ) {
			$fields[ $this->label() ] = $this->get_field( $term );
		}
		return $fields;
	}

	/**
	 * Render meta box label.
	 *
	 * @return string
	 */
	abstract protected function label(): string;

	/**
	 * Render field area.
	 *
	 * @param \WP_Term $term Term object.
	 * @return string
	 */
	abstract protected function get_field( \WP_Term $term ): string;

	/**
	 * Save if meta box is available.
	 *
	 * @param \WP_Post $post
	 * @return void
	 */
	public function save( $term_id, $taxonomy ) {
		if ( $this->is_active_taxonomy( $taxonomy ) ) {
			$this->do_save( $term_id );
		}
	}

	/**
	 * Save meta box value.
	 *
	 * @param int $term_id Save term meta. Taxonomy is already validated.
	 * @return void
	 */
	abstract protected function do_save( $term_id ): void;

	/**
	 * Is active taxonomy?
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return bool
	 */
	protected function is_active_taxonomy( $taxonomy ) {
		return in_array( $taxonomy, $this->taxonomies(), true );
	}
}
