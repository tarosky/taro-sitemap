<?php

namespace Tarosky\Sitemap\Styles;


use Tarosky\Sitemap\Pattern\SitemapStylePattern;

/**
 * Sitemap index style.
 *
 * @package tsmap
 */
class SitemapStyle extends SitemapStylePattern {

	/**
	 * {@inheritdoc}
	 */
	public function style_name() {
		return 'map';
	}

	/**
	 * {@inheritdoc}
	 */
	protected function do_style() {
		?><xsl:stylesheet
			version="1.0"
			xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
			xmlns:sitemap="http://www.sitemaps.org/schemas/sitemap/0.9"
			xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"
			exclude-result-prefixes="sitemap"
		>

		<xsl:output method="html" encoding="UTF-8" indent="yes" />
		<xsl:variable name="has-lastmod"    select="count( /sitemap:urlset/sitemap:url/sitemap:lastmod )"    />
		<xsl:variable name="has-changefreq" select="count( /sitemap:urlset/sitemap:url/sitemap:changefreq )" />
		<xsl:template match="/">
			<html <?php language_attributes(); ?>>
			<head>
				<meta charset="UTF-8" />
				<title><?php echo esc_xml( 'Sitemap', 'tsmap' ); ?></title>
				<meta name="viewport" content="width=device-width,initial-scale=1.0" />
				<link rel="stylesheet" type="text/css" href="<?php echo esc_url( $this->get_stylesheet_url() ); ?>" />
			</head>
			<body>
			<div class="sitemap">
				<div class="sitemap-header">
					<h1><?php echo esc_xml( 'Sitemap', 'tsmap' ); ?></h1>
				</div>
				<div class="sitemap-content">
					<ol class="sitemap-toc">
						<xsl:for-each select="sitemap:urlset/sitemap:url">
							<li class="sitemap-item">
								<a class="sitemap-link" href="{sitemap:loc}"><xsl:value-of select="sitemap:loc" /></a><br />
								<span class="sitemap-lastmod"><xsl:value-of select="sitemap:lastmod" /></span>
								<xsl:for-each select="image:image">
									<img src="{image:loc}" alt="" class="sitemap-image" />
								</xsl:for-each>
							</li>
						</xsl:for-each>
					</ol>
				</div>
			</div>
			</body>
			</html>
		</xsl:template>
		</xsl:stylesheet>
		<?php
	}
}
