<?php

namespace Tarosky\Sitemap\Seo\Features;


use Kunoichi\VirtualMember\Services\OgpProvider;
use Tarosky\Sitemap\Pattern\AbstractFeaturePattern;

/**
 * Structured data generator.
 *
 */
class StructuredDataGenerator extends AbstractFeaturePattern {

	/**
	 * {@inheritDoc}
	 */
	protected function is_active(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function register_hooks() {
		add_action( 'wp_head', [ $this, 'render_head' ] );
	}

	/**
	 * Get JSON LD.
	 *
	 * @return array[]
	 */
	public function get_json_ld() {
		$json_lds = [];
		// If this is singular.
		$article_post_types = $this->option( 'jsonld_article_post_types' );
		if ( ! empty( $article_post_types ) && is_singular( $article_post_types ) && is_a( get_queried_object(), 'WP_Post' ) ) {
			$article_json_ld = $this->get_article_structure( get_queried_object() );
			if ( $article_json_ld ) {
				$json_lds[] = $article_json_ld;
			}
		}
		return apply_filters( 'tsmap_json_ld', $json_lds );
	}

	/**
	 * Render JSON-LD in head.
	 *
	 * @return void
	 */
	public function render_head() {
		$json_ld = $this->get_json_ld();
		if ( empty( $json_ld ) ) {
			return;
		}
		foreach ( $json_ld as $json ) {
			printf(
				"<script type=\"application/ld+json\">\n%s\n</script>",
				json_encode( $json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT )
			);
		}
	}

	/**
	 * Get article structure.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array
	 */
	public function get_article_structure( $post ) {
		$json   = [
			'@context'         => 'http://schema.org',
			'@type'            => apply_filters( 'tsmap_json_ld_article_type', 'Article', $post ),
			'mainEntityOfPage' => [
				'@type' => 'WebPage',
				'@id'   => get_permalink( $post ),
			],
			'headline'         => get_the_title( $post ),
			'datePublished'    => mysql2date( \DateTime::W3C, $post->post_date ),
			'dateModified'     => mysql2date( \DateTime::W3C, $post->post_modified ),
			'author'           => $this->get_authors_structure( $post ),
			'publisher'        => $this->get_publisher_structure( $post ),
		];
		$images = [];
		if ( has_post_thumbnail( $post ) ) {
			$images[] = wp_get_attachment_image_url( get_post_thumbnail_id( $post ), 'full' );
		}
		if ( ! empty( $images ) ) {
			$json['image'] = $images;
		}
		return $json;
	}

	/**
	 * @param \WP_Post $post
	 *
	 * @return array
	 */
	public function get_authors_structure( $post ) {
		$author = get_userdata( $post->post_author );
		$json   = [
			'@type' => 'Person',
			'name'  => get_the_author_meta( 'display_name', $author->ID ),
		];
		if ( preg_match( '#^https?://.+#', $author->user_url ) ) {
			$json['url'] = $author->user_url;
		}
		// If virtual member exists, set author.
		if ( class_exists( 'Kunoichi\VirtualMember\Services\OgpProvider' ) ) {
			$members = [];
			foreach ( \Kunoichi\VirtualMember\Ui\PublicScreen::get_instance()->get_members( $post ) as $member ) {
				$j = OgpProvider::get_instance()->get_ogp( $member );
				if ( ! empty( $j ) ) {
					$members[] = $j;
				}
			}
			if ( empty( $members ) ) {
				// Try to get default member.
				$default_user = \Kunoichi\VirtualMember\PostType::default_user();
				if ( $default_user ) {
					$members[] = OgpProvider::get_instance()->get_ogp( $default_user );
				}
			}
			if ( ! empty( $members ) ) {
				$json = $members;
			}
		}
		return $json;
	}

	/**
	 * @param $post
	 *
	 * @return mixed|null
	 */
	public function get_publisher_structure( $post ) {
		$publisher = [
			'name' => get_bloginfo( 'name' ),
			'url'  => home_url( '/' ),
		];
		$site_icon = get_site_icon_url( 'full' );
		if ( $site_icon ) {
			$publisher['logo'] = $site_icon;
		}
		foreach ( [ 'name', 'url', 'logo' ] as $key ) {
			$option_key = 'jsonld_publisher_' . $key;
			if ( 'logo' === $key ) {
				$attachment_id = $this->option( $option_key );
				$option        = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'full' ) : '';
			} else {
				$option = $this->option( $option_key );
			}
			if ( $option ) {
				$publisher[ $key ] = $option;
			}
		}
		return apply_filters( 'tsmap_json_ld_publisher_structure', $publisher, $post );
	}
}
