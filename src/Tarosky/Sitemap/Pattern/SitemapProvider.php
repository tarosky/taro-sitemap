<?php

namespace Tarosky\Sitemap\Pattern;

/**
 * Sitemap pattern.
 */
abstract class SitemapProvider extends AbstractSitemapProvider {

	protected $type = 'map';

	/**
	 * Namespaces.
	 *
	 * @return string[]
	 */
	protected function namespaces() {
		return [
			'xmlns' => 'http://www.sitemaps.org/schemas/sitemap/0.9',
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function render() {
		$urls = $this->get_urls();
		$this->set_query_time();
		$this->header();

		/**
		 * Action fired before sitemap output begins.
		 *
		 * This action runs before any XML is output for the sitemap.
		 * Use it to perform additional processing before sitemap generation.
		 *
		 * @param string $type        Sitemap type (e.g., 'map')
		 * @param string $target_name Target name (post type, taxonomy name, etc.)
		 *
		 * @hook tsmap_pre_sitemap
		 */
		do_action( 'tsmap_pre_sitemap', $this->type, $this->target_name() );
		echo '<?xml version="1.0" encoding="UTF-8" ?>' . "\n";
		$url = $this->get_xslt_url();
		if ( ! empty( $url ) ) {
			printf(
				'<?xml-stylesheet type="text/xsl" href="%s" ?>' . "\n",
				esc_url( $url )
			);
		}
		$namespaces = [];
		foreach ( $this->namespaces() as $key => $value ) {
			$namespaces[] = sprintf( '%s="%s"', esc_html( $key ), esc_url( $value ) );
		}
		printf( '<urlset %s>%s', implode( ' ', $namespaces ), "\n" );
		/**
		 * Action fired after opening the urlset tag but before items.
		 *
		 * This action runs after the urlset opening tag but before any URL items are output.
		 *
		 * @param string $type        Sitemap type (e.g., 'map')
		 * @param string $target_name Target name (post type, taxonomy name, etc.)
		 *
		 * @hook tsmap_before_sitemap
		 */
		do_action( 'tsmap_before_sitemap', $this->type, $this->target_name() );
		foreach ( $urls as $url ) {
			?>
			<url>
				<?php
				$this->do_item( $url );
				/**
				 * Action fired for each sitemap item.
				 *
				 * This action runs inside each URL item in the sitemap.
				 * Use it to add custom elements to each URL entry.
				 *
				 * @param string $type        Sitemap type (e.g., 'map')
				 * @param string $target_name Target name (post type, taxonomy name, etc.)
				 *
				 * @hook tsmap_sitemap_item
				 */
				do_action( 'tsmap_sitemap_item', $this->type, $this->target_name() );
				?>
			</url>
			<?php
		}
		/**
		 * Action fired after all sitemap items but before closing the urlset tag.
		 *
		 * This action runs after all URL items have been output but before the urlset closing tag.
		 *
		 * @param string $type        Sitemap type (e.g., 'map')
		 * @param string $target_name Target name (post type, taxonomy name, etc.)
		 *
		 * @hook tsmap_after_sitemap
		 */
		do_action( 'tsmap_after_sitemap', $this->type, $this->target_name() );
		echo '</urlset>';
	}

	/**
	 * Render each url item.
	 *
	 * @param array $url
	 * @return void
	 */
	public function do_item( $url ) {
		if ( ! empty( $url['link'] ) ) {
			printf( '<loc>%s</loc>%s', esc_url( $url['link'] ), "\n" );
		}
		if ( ! empty( $url['lastmod'] ) ) {
			printf( '<lastmod>%s</lastmod>%s', esc_xml( $url['lastmod'] ), "\n" );
		}
		if ( ! empty( $url['images'] ) ) {
			foreach ( $url['images'] as $src ) {
				printf( '<image:image><image:loc>%s</image:loc></image:image>%s', $src, "\n" );
			}
		}
	}
}
