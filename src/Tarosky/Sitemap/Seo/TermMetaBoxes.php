<?php

namespace Tarosky\Sitemap\Seo;


use Tarosky\Sitemap\Pattern\Singleton;

/**
 * Post meta boxes for SEO.
 */
class TermMetaBoxes extends Singleton {

	/**
	 * {@inheritDoc}
	 */
	protected function init() {
		add_action( 'admin_init', [ $this, 'admin_init' ] );
		add_action( 'edit_term', [ $this, 'update_term' ], 10, 3 );
	}

	/**
	 * Register taoxnomies.
	 *
	 * @return void
	 */
	public function admin_init() {
		foreach ( $this->get_taxonomies() as $taxonomy ) {
			add_action( "{$taxonomy}_term_edit_form_top", [ $this, 'nonce_field' ] );
			add_action( "{$taxonomy}_edit_form_fields", [ $this, 'render_field' ] );
		}
	}

	/**
	 * Render nonce field.
	 *
	 * @return void
	 */
	public function nonce_field() {
		wp_nonce_field( 'tsmap_term_meta', '_tsmaptermmetanonce', false );
	}

	/**
	 * Is this post type has meta box?
	 *
	 * @return string[]
	 */
	protected function get_taxonomies() {
		/**
		 * Filters the list of taxonomies that should have term meta fields.
		 *
		 * @param string[] $taxonomies Array of taxonomy names
		 * @return string[] Filtered array of taxonomy names
		 *
		 * @hook tsmap_has_term_meta
		 */
		return apply_filters( 'tsmap_has_term_meta', [] );
	}

	/**
	 * Add meta boxes if available.
	 *
	 * @param \WP_Term $term
	 * @return void
	 */
	public function render_field( $term ) {
		/**
		 * Filters the fields to display in the term meta box.
		 *
		 * @param array    $fields Array of fields (label => HTML)
		 * @param \WP_Term $term   Current term object
		 * @return array Filtered array of fields
		 *
		 * @hook tsmap_term_meta_fields
		 */
		$fields = apply_filters( 'tsmap_term_meta_fields', [], $term );
		foreach ( $fields as $label => $field ) {
			?>
			<tr>
				<th><?php echo esc_html( $label ); ?></th>
				<td><?php echo $field; ?></td>
			</tr>
			<?php
		}
	}

	/**
	 * Save meta box field.
	 *
	 * @param int    $term_id   Term ID.
	 * @param int    $tt_id     Term taxonomy ID.
	 * @param string $stasonomy taxonomy of this term.
	 *
	 * @return void
	 */
	public function update_term( $term_id, $tt_id, $taxonomy ) {
		if ( ! wp_verify_nonce( filter_input( INPUT_POST, '_tsmaptermmetanonce' ), 'tsmap_term_meta' ) ) {
			return;
		}
		if ( ! in_array( $taxonomy, $this->get_taxonomies(), true ) ) {
			return;
		}
		// Should save action.
		/**
		 * Action fired when term meta is being saved.
		 *
		 * Use this action to save custom term meta fields.
		 *
		 * @param int    $term_id  Term ID
		 * @param string $taxonomy Taxonomy name
		 *
		 * @hook tsmap_save_term_meta
		 */
		do_action( 'tsmap_save_term_meta', $term_id, $taxonomy );
	}
}
