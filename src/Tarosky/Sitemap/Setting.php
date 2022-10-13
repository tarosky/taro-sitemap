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
		add_submenu_page( 'tools.php', __( 'Sitemap', 'tsmap' ), __( 'Sitemap', 'tsmap' ), 'manage_options', 'tsmap', [ $this, 'render_page' ] );
	}

	/**
	 * Render setting page.
	 *
	 * @return void
	 */
	public function render_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Sitemap Setting', 'tsmap' ); ?></h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
				<?php
				settings_fields( 'tsmap' );
				do_settings_sections( 'tsmap' );
				submit_button();
				?>
			</form>
			<hr style="margin: 40px 0;" />
			<h2><?php esc_html_e( 'Sitemap URL', 'tsmap' ); ?></h2>
			<?php echo wp_kses_post( sprintf(
				// translators: %1$s is URL, %2$s is robotx.txt url.
				__( '<p>The sitemap URL are listed below. You should register these URLs at <a href="%1$s" target="_blank" rel="noopener noreferrer">Google Search Console</a>. They also appear in <a href="%2$s">robots.txt</a>.</p>', 'tsmap' ),
				'https://search.google.com/search-console',
				home_url( 'robots.txt' )
			) ); ?>
			<?php
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
	 * Register setting fields.
	 *
	 * @return void
	 */
	public function add_settings() {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}
		// Register sections.
		add_settings_section( 'tsmap_setting_default', __( 'Setting', 'tsmap' ), function() {

		}, 'tsmap' );
		// Register setting.
		foreach ( [
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
				'options' => array_map( function( $post_type ) {
					return [
						'value' => $post_type->name,
						'label' => $post_type->label,
					];
				}, get_post_types( [ 'public' => true ], OBJECT ) ),
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
				'options' => array_map( function( $post_type ) {
					return [
						'value' => $post_type->name,
						'label' => $post_type->label,
					];
				}, get_post_types( [ 'public' => true ], OBJECT ) ),
			],
			[
				'id'      => 'taxonomies',
				'title'   => __( 'Taxonomies in Sitemap', 'tsmap' ),
				'type'    => 'checkbox',
				'label'   => __( 'Please check taxonomy archive in site map.', 'tsmap' ),
				'options' => array_map( function( \WP_Taxonomy $taxonomy ) {
					return [
						'value' => $taxonomy->name,
						'label' => $taxonomy->label,
					];
				}, get_taxonomies( [ 'public' => true ], OBJECT ) ),
			],
		] as $setting ) {
			$id = 'tsmap_' . $setting['id'];
			add_settings_field( $id, $setting['title'], function() use ( $id, $setting ) {
				$value = get_option( $id );
				switch ( $setting['type'] ) {
					case 'number':
					case 'text':
						printf(
							'<input type="%1$s" value="%2$s" name="%3$s" placeholder="%4$s" />',
							esc_attr( $setting['type'] ),
							esc_attr( $value ),
							esc_attr( $id ),
							esc_attr( $setting['placeholder'] ?? '' )
						);
						if ( ! empty( $setting['label'] ) ) {
							printf( '<p class="description">%s</p>', esc_html( $setting['label'] ) );
						}
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
			}, 'tsmap', 'tsmap_setting_default' );

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
