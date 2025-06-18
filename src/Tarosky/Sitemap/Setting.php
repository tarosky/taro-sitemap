<?php

namespace Tarosky\Sitemap;


use Tarosky\Sitemap\Pattern\Singleton;

/**
 * Singleton pattern.
 *
 * @package tsmap
 * @property-read bool     $disable_core
 * @property-read int      $posts_per_page
 * @property-read string[] $post_types
 * @property-read bool     $exclusion_per_post
 * @property-read string[] $news_post_types
 * @property-read string[] $taxonomies
 * @property-read string   $attachment_sitemap
 */
class Setting extends Singleton {

	/**
	 * {@inheritdoc}
	 */
	protected function init() {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_init', [ $this, 'add_settings' ] );
	}

	/**
	 * Register submenu.
	 *
	 * @return void
	 */
	public function add_menu_page() {
		add_submenu_page( 'tools.php', __( 'Sitemap & SEO', 'tsmap' ), __( 'Sitemap & SEO', 'tsmap' ), 'manage_options', 'tsmap', [ $this, 'render_page' ] );
	}

	/**
	 * Render setting page.
	 *
	 * @return void
	 */
	public function render_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Sitemap & SEO Setting', 'tsmap' ); ?></h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
				<?php
				settings_fields( 'tsmap' );
				do_settings_sections( 'tsmap' );
				submit_button();
				?>
				<p>
					<?php
					// Tell user to flush Permalinks after saving.
					echo wp_kses_post(sprintf(
						'&#9888; ' . __('After clicking Save Changes, you also need to click Save Changes in %s, before your changes will take effect.', 'tsmap'),
						sprintf(
							'<a href="%s">%s</a>',
							home_url( 'wp-admin/options-permalink.php' ),
							__('Permalinks', 'tsmap')
						)
					));
					?>
				</p>
			</form>
			<hr style="margin: 40px 0;" />
			<h2><?php esc_html_e( 'Sitemap URL', 'tsmap' ); ?></h2>
			<p>
				<?php
				// Description.
				echo wp_kses_post( sprintf(
					// translators: %1$s is URL, %2$s is robotx.txt url.
					__( 'The sitemap URL are listed below. You should register these URLs at <a href="%1$s" target="_blank" rel="noopener noreferrer">Google Search Console</a>. They also appear in <a href="%2$s">robots.txt</a>.', 'tsmap' ),
					'https://search.google.com/search-console',
					home_url( 'robots.txt' )
				) );
				?>
			</p>

			<?php
			// Display URL.
			$urls = Registry::get_registered_sitemap_urls();
			if ( empty( $urls ) ) {
				printf(
					'<div style="color: red;">%s</div>',
					esc_html__( 'No sitemaps are available.', 'tsmap' )
				);
			} else {
				foreach ( $urls as $url ) {
					?>
					<div style="margin: 10px 0;">
						<input type="url" style="width: 100%; box-sizing: border-box" readonly
							value="<?php echo esc_url( $url ); ?>"  onFocus="this.select()" />
					</div>
					<?php
				}
			}
			?>
		</div>
		<?php
	}

	/**
	 * Get post types for selection.
	 *
	 * @param string $context       Filter context.
	 * @param bool   $include_media Is attachment included.
	 * @return array{label:string,name:string}[]
	 */
	protected function selectable_post_types( $context, $include_media = false ) {
		$post_types = get_post_types( [ 'public' => true ], OBJECT );
		if ( ! $include_media ) {
			$post_types = array_filter( $post_types, function ( $post_type ) {
				return 'attachment' !== $post_type->name;
			} );
		}
		/**
		 * Filters the list of post types available for selection in settings.
		 *
		 * @param array  $post_types Array of post type objects with 'value' and 'label' keys
		 * @param string $context    Context where the filter is being applied
		 * @return array Filtered post types array
		 *
		 * @hook tsmap_seo_post_types_selection
		 */
		return apply_filters( 'tsmap_seo_post_types_selection', array_map( function ( $post_type ) {
			return [
				'value' => $post_type->name,
				'label' => $post_type->label,
			];
		}, $post_types ), $context );
	}

	/**
	 * Register setting fields.
	 *
	 * @return void
	 */
	public function add_settings() {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}
		foreach ( [
			[ 'default', __( 'Sitemap', 'tsmap' ), __( 'Setting for Sitemap.', 'tsmap' ) ],
			[ 'noindex', __( 'Noindex', 'tsmap' ), __( 'Add options of noindex for posts and taxonomies. This affects the appearance on search engines.', 'tsmap' ) ],
			[ 'canonical', __( 'Canonical', 'tsmap' ), __( 'Canonical URL related features.', 'tsmap' ) ],
			[ 'meta', __( 'Meta', 'tsmap' ), __( 'Additional setting for <code>&lt;head&gt;</code> tag.', 'tsmap' ) ],
			[ 'ogp', __( 'OGP', 'tsmap' ), __( 'OGP setting. Displayed on social media.', 'tsmap' ) ],
			[ 'json-ld', __( 'Structured Date', 'tsmap' ), __( 'Display structured data as JSON-LD in <code>&lt;head&gt;</code> tag. Filter hooks <code>tsmap_json_ld</code> is also available.', 'tsmap' ) ],
		] as list( $key, $title, $description ) ) {
			add_settings_section( 'tsmap_setting_' . $key, $title, function () use ( $description ) {
				printf( '<p class="description">%s</p>', wp_kses_post( $description ) );
			}, 'tsmap' );
		}
		/**
		 * Filters the list of taxonomies available for selection in settings.
		 *
		 * @param array $taxonomies Array of taxonomy objects with 'value' and 'label' keys
		 * @return array Filtered taxonomies array
		 *
		 * @hook tsmap_seo_taxonomies_selection
		 */
		$taxonomies = apply_filters( 'tsmap_seo_taxonomies_selection', array_map( function ( \WP_Taxonomy $taxonomy ) {
			return [
				'value' => $taxonomy->name,
				'label' => $taxonomy->label,
			];
		}, get_taxonomies( [ 'public' => true ], OBJECT ) ) );
		// Register setting.
		foreach ( [
			// Sitemap features.
			[
				'id'    => 'disable_core',
				'title' => __( 'Core Sitemap', 'tsmap' ),
				'type'  => 'bool',
				'label' => __( 'Disable core sitemap', 'tsmap' ),
			],
			[
				'id'      => 'post_types',
				'title'   => __( 'Post types in Sitemap', 'tsmap' ),
				'type'    => 'checkbox',
				'label'   => __( 'Please check post type to be included in site map.', 'tsmap' ),
				'options' => $this->selectable_post_types( 'sitemap' ),
			],
			[
				'id'      => 'exclusion_per_post',
				'title'   => __( 'Exclude each posts from Sitemap', 'tsmap' ),
				'type'    => 'radio',
				'label'   => __( 'Allow exclusion option for each posts. This feature may affect sitemap performance.', 'tsmap' ),
				'options' => [
					[
						'value' => '',
						'label' => __( 'No', 'tsmap' ),
					],
					[
						'value' => '1',
						'label' => __( 'Yes', 'tsmap' ),
					],
				],
			],
			[
				'id'          => 'posts_per_page',
				'title'       => __( 'Posts per page', 'tsmap' ),
				'type'        => 'number',
				'label'       => __( 'Number of posts per each sitemap. Should be under 5,000.', 'tsmap' ),
				'placeholder' => '1000',
			],
			[
				'id'      => 'attachment_sitemap',
				'title'   => __( 'Attachment', 'tsmap' ),
				'type'    => 'radio',
				'label'   => __( 'How attachments appear in sitemap.', 'tsmap' ),
				'options' => [
					[
						'value' => '',
						'label' => __( 'Not displayed.', 'tsmap' ),
					],
					[
						'value' => 'post',
						'label' => __( 'In post sitemap', 'tsmap' ),
					],
					[
						'value' => 'attachment',
						'label' => __( 'Create attachment page sitemap', 'tsmap' ),
					],
				],
			],
			[
				'id'      => 'news_post_types',
				'title'   => __( 'Post types in news sitemap', 'tsmap' ),
				'type'    => 'checkbox',
				'label'   => __( 'Please check post type to be included in news site map.', 'tsmap' ),
				'options' => $this->selectable_post_types( 'news_sitemap' ),
			],
			[
				'id'      => 'taxonomies',
				'title'   => __( 'Taxonomies in Sitemap', 'tsmap' ),
				'type'    => 'checkbox',
				'label'   => __( 'Please check taxonomy archive in site map.', 'tsmap' ),
				'options' => $taxonomies,
			],
			// No Index.
			[
				'id'      => 'noindex_posts',
				'section' => 'noindex',
				'title'   => __( 'No Indexable Post Types', 'tsmap' ),
				'type'    => 'checkbox',
				'label'   => __( 'Each post in checked post types will have noindex meta box.', 'tsmap' ),
				'options' => $this->selectable_post_types( 'noindex', true ),
			],
			[
				'id'      => 'noindex_terms',
				'section' => 'noindex',
				'title'   => __( 'No Indexable Taxonomies', 'tsmap' ),
				'type'    => 'checkbox',
				'label'   => __( 'Each term in checked taxonomies will have noindex setting field.', 'tsmap' ),
				'options' => $taxonomies,
			],
			[
				'id'          => 'noindex_archive_limit',
				'section'     => 'noindex',
				'title'       => __( 'Noindex Archive', 'tsmap' ),
				'type'        => 'number',
				'label'       => __( 'Archive page greater than this number will be noindex.', 'tsmap' ),
				'placeholder' => __( 'e.g. 6', 'tsmap' ),
			],
			[
				'id'      => 'noindex_other',
				'section' => 'noindex',
				'title'   => __( 'Other Page', 'tsmap' ),
				'type'    => 'checkbox',
				'label'   => __( 'Miscellaneous pages to be no-indexed.', 'tsmap' ),
				'options' => [
					[
						'value' => 'search',
						'label' => __( 'Search Results', 'tsmap' ),
					],
					[
						'value' => '404',
						'label' => __( '404 Page', 'tsmap' ),
					],
					[
						'value' => 'attachment',
						'label' => __( 'Attachment page', 'tsmap' ),
					],
				],
			],
			[
				'id'          => 'canonical_priority',
				'section'     => 'canonical',
				'title'       => __( 'Canonical Priority', 'tsmap' ),
				'type'        => 'number',
				'label'       => __( 'To leverage search engines crawling, change priority to lower number. 1 is the best.', 'tsmap' ),
				'placeholder' => __( 'Default. 10', 'tsmap' ),
			],
			[
				'id'      => 'canonical_archive',
				'section' => 'canonical',
				'title'   => __( 'Canonical For Archive', 'tsmap' ),
				'type'    => 'checkbox',
				'label'   => __( 'WordPress has canonical link only for singular pages. This option provides canonical features to archive pages.', 'tsmap' ),
				'options' => [
					[
						'value' => 'taxonomies',
						'label' => __( 'Taxonomy Page', 'tsmap' ),
					],
					[
						'value' => 'author',
						'label' => __( 'Author Archive', 'tsmap' ),
					],
					[
						'value' => 'home',
						'label' => __( 'Blog Archive', 'tsmap' ),
					],
					[
						'value' => 'post_type',
						'label' => __( 'Post Type Archive', 'tsmap' ),
					],
				],
			],
			[
				'id'          => 'separator',
				'section'     => 'meta',
				'title'       => __( 'Separator', 'tsmap' ),
				'type'        => 'text',
				'label'       => __( 'Separator for document title.', 'tsmap' ),
				'placeholder' => __( 'Default. -', 'tsmap' ),
			],
			[
				'id'      => 'post_desc',
				'section' => 'meta',
				'title'   => __( 'Post Description', 'tsmap' ),
				'type'    => 'checkbox',
				'label'   => __( 'Each post in checked post type will have post description field.', 'tsmap' ),
				'options' => $this->selectable_post_types( 'meta_desc' ),
			],
			[
				'id'      => 'auto_desc',
				'section' => 'meta',
				'title'   => __( 'Description', 'tsmap' ),
				'type'    => 'radio',
				'label'   => __( 'The strategy for display description.', 'tsmap' ),
				'options' => [
					[
						'label' => __( 'Do nothing', 'tsmap' ),
						'value' => '',
					],
					[
						'label' => __( 'Automatic Generate', 'tsmap' ),
						'value' => 'auto',
					],
					[
						'label' => __( 'Display if specified(excerpt, description, etc.)', 'tsmap' ),
						'value' => 'manual',
					],
				],
			],
			[
				'id'      => 'front_desc',
				'section' => 'meta',
				'title'   => __( 'Front page', 'tsmap' ),
				'type'    => 'textarea',
				'label'   => __( 'Description for front page.', 'tsmap' ),
			],
			[
				'id'      => 'ogp',
				'section' => 'ogp',
				'title'   => __( 'Render OGP', 'tsmap' ),
				'type'    => 'bool',
				'label'   => __( 'Display OGP in head tag.', 'tsmap' ),
			],
			[
				'id'      => 'default_image',
				'section' => 'ogp',
				'title'   => __( 'Default Image', 'tsmap' ),
				'type'    => 'image',
				'label'   => __( 'Attachment ID of default image. This image is used for page without featured image..', 'tsmap' ),
			],
			[
				'id'      => 'fb_app_id',
				'section' => 'ogp',
				'title'   => __( 'Facebook App ID', 'tsmap' ),
				'type'    => 'text',
				'label'   => __( 'Facebook App ID. Required for Facebook page and retargeting ad', 'tsmap' ),
			],
			[
				'id'      => 'fb_page_url',
				'section' => 'ogp',
				'title'   => __( 'Facebook Page URL', 'tsmap' ),
				'type'    => 'text',
				'label'   => __( 'Displayed as Author of this site in Facebook.', 'tsmap' ),
			],
			[
				'id'      => 'twitter_account',
				'section' => 'ogp',
				'title'   => __( 'X(ex-Twitter) screen name', 'tsmap' ),
				'type'    => 'text',
				'label'   => __( 'e.g. @elonmask', 'tsmap' ),
			],
			[
				'id'      => 'twitter_size',
				'section' => 'ogp',
				'title'   => __( 'X card size', 'tsmap' ),
				'type'    => 'radio',
				'label'   => __( 'Card size shared on X.', 'tsmap' ),
				'options' => [
					[
						'label' => 'summary_large_image',
						'value' => 'summary_large_image',
					],
					[
						'label' => 'summary',
						'value' => '',
					],
				],
			],
			[
				'id'      => 'jsonld_article_post_types',
				'section' => 'json-ld',
				'title'   => __( 'Article Post Type', 'tsmap' ),
				'type'    => 'checkbox',
				'options' => $this->selectable_post_types( 'json_ld' ),
				'label'   => __( 'Checked post type will display JSON-LD in head tag.', 'tsmap' ),
			],
			[
				'id'          => 'jsonld_publisher_name',
				'section'     => 'json-ld',
				'title'       => __( 'Publisher Name', 'tsmap' ),
				'type'        => 'text',
				'label'       => __( 'Publisher name of article. Default is site name.', 'tsmap' ),
				'placeholder' => get_bloginfo( 'name' ),
			],
			[
				'id'          => 'jsonld_publisher_url',
				'section'     => 'json-ld',
				'title'       => __( 'Publisher URL', 'tsmap' ),
				'type'        => 'text',
				'label'       => __( 'Publisher URL of article. Default is site URL.', 'tsmap' ),
				'placeholder' => get_bloginfo( 'url' ),
			],
			[
				'id'      => 'jsonld_publisher_logo',
				'section' => 'json-ld',
				'title'   => __( 'Publisher Logo', 'tsmap' ),
				'type'    => 'image',
				'label'   => __( 'Attachment ID of the publisher. Default is site icon.', 'tsmap' ),
			],
		] as $setting ) {
			$id      = 'tsmap_' . $setting['id'];
			$section = $setting['section'] ?? 'default';
			add_settings_field( $id, $setting['title'], function () use ( $id, $setting ) {
				$value = get_option( $id );
				switch ( $setting['type'] ) {
					case 'number':
					case 'text':
					case 'image':
						$type = 'image' === $setting['type'] ? 'number' : $setting['type'];
						printf(
							'<input type="%1$s" value="%2$s" name="%3$s" placeholder="%4$s" />',
							esc_attr( $type ),
							esc_attr( $value ),
							esc_attr( $id ),
							esc_attr( $setting['placeholder'] ?? '' )
						);
						break;
					case 'textarea':
						printf(
							'<textarea name="%1$s" placeholder="%2$s" rows="3" style="width:100%%; box-sizing: border-box;">%3$s</textarea>',
							esc_attr( $id ),
							esc_attr( $setting['placeholder'] ?? '' ),
							esc_textarea( $value )
						);
						break;
					case 'bool':
						printf(
							'<label><input type="checkbox" name="%s" value="1" %s/>%s</label>',
							esc_attr( $id ),
							checked( $value, true, false ),
							esc_html( $setting['label'] )
						);
						break;
					case 'checkbox':
						$value = (array) $value;
						foreach ( $setting['options'] as $option ) {
							printf(
								'<label style="display: inline-block; margin-right: 1em;"><input type="checkbox" name="%1$s[]" value="%2$s" %3$s/> %4$s</label>',
								esc_attr( $id ),
								esc_attr( $option['value'] ),
								checked( in_array( $option['value'], $value, true ), true, false ),
								esc_html( $option['label'] )
							);
						}
						break;
					case 'radio':
						foreach ( $setting['options'] as $option ) {
							printf(
								'<label style="display: block; margin-bottom: 0.5em;"><input type="radio" name="%1$s" value="%2$s" %3$s/> %4$s</label>',
								esc_attr( $id ),
								esc_attr( $option['value'] ),
								checked( $option['value'], $value, false ),
								esc_html( $option['label'] )
							);
						}
						break;
				}
				if ( ! empty( $setting['label'] ) && 'bool' !== $setting['type'] ) {
					printf( '<p class="description">%s</p>', esc_html( $setting['label'] ) );
				}
				if ( 'image' === $setting['type'] ) {
					$attachment = $value ? get_post( $value ) : null;
					if ( $attachment ) {
						printf(
							'<figure>%s<figcaption>%s%s</figcaption></figure>',
							wp_get_attachment_image( $attachment->ID, 'thumbnail' ),
							esc_html__( 'Preview: ', 'tsmap' ),
							esc_html( $attachment->post_title )
						);
					}
				}
			}, 'tsmap', 'tsmap_setting_' . $section );

			register_setting( 'tsmap', $id );

		}
	}

	/**
	 * Get property
	 *
	 * @param string $name Property name.
	 * @return mixed
	 */
	public function __get( $name ) {
		switch ( $name ) {
			case 'post_types':
			case 'news_post_types':
			case 'taxonomies':
				return array_values( array_filter( (array) get_option( 'tsmap_' . $name, [] ) ) );
			case 'disable_core':
			case 'exclusion_per_post':
				return (bool) get_option( 'tsmap_' . $name );
			case 'posts_per_page':
				return min( 5000, (int) get_option( 'tsmap_' . $name, 1000 ) ) ?: 1000;
			case 'attachment_sitemap':
				return get_option( 'tsmap_' . $name );
			default:
				return null;
		}
	}
}
