<?php

namespace Tarosky\Sitemap\Pattern;



/**
 * Site map index pattern.
 *
 * @package tsmap
 */
abstract class SitemapIndexProvider extends AbstractSitemapProvider {

	/**
	 * @var string Sitemap type.
	 */
	protected $type = 'index';

	/**
	 * {@inheritdoc}
	 */
	protected function init() {
		parent::init();
		if ( $this->is_active() ) {
			add_filter( 'robots_txt', [ $this, 'robots_txt' ], 10, 2 );
			add_filter( 'tsmap_sitemap_urls', [ $this, 'sitemap_urls' ] );
		}
	}

	/**
	 * Get sitemap URLs.
	 *
	 * @param string[] $urls URL list of sitemap index.
	 * @return string[]
	 */
	public function sitemap_urls( $urls ) {
		$urls[] = $this->build_url();
		return $urls;
	}

	/**
	 * Add robots.txt.
	 *
	 * @param string $txt       Robots.txt content.
	 * @param bool   $is_public If this site is public.
	 * @return string
	 */
	public function robots_txt( $txt, $is_public ) {
		if ( $is_public ) {
			$txt .= sprintf( 'Sitemap: %s%s', esc_url( $this->build_url() ), "\n" );
		}
		return $txt;
	}

	/**
	 * Get sitemap URL.
	 *
	 * @return string
	 */
	public function build_url() {
		return home_url( sprintf( '/sitemap_index_%s.xml', $this->target_name() ) );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function get_xslt_url() {
		return home_url( 'sitemap_style_index.xsl' );
	}

	/**
	 * Render XML sitemap.
	 *
	 * @return void
	 */
	public function render() {
		$urls = $this->get_urls();
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
		echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		do_action( 'tsmap_before_sitemap', $this->type, $this->target_name() );
		foreach ( $urls as $url ) {
			?>
			<sitemap>
				<loc><?php echo esc_url( $url ); ?></loc>
				<?php do_action( 'tsmap_sitemap_item', $this->type, $this->target_name() ); ?>
			</sitemap>
			<?php
		}
		do_action( 'tsmap_after_sitemap', $this->type, $this->target_name() );
		echo '</sitemapindex>';
	}
}
