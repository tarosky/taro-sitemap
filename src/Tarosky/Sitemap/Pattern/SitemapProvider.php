<?php

namespace Tarosky\Sitemap\Pattern;

/**
 * Sitemap pattern.
 */
abstract class SitemapProvider extends AbstractSitemapProvider {

	protected $type = 'map';

	/**
	 * {@inheritdoc}
	 */
	protected function get_xslt_url() {
		return home_url( 'sitemap_style_map.xsl' );
	}

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
		$start = microtime( true );
		$urls = $this->get_urls();
		$this->set_query_time();
		$this->header();

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
		do_action( 'tsmap_before_sitemap', $this->type, $this->target_name() );
		foreach ( $urls as $url ) {
			?>
			<url>
				<?php
				$this->do_item( $url );
				do_action( 'tsmap_sitemap_item', $this->type, $this->target_name() );
				?>
			</url>
			<?php
		}
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
