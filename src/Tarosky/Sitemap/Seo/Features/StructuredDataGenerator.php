<?php

namespace Tarosky\Sitemap\Seo\Features;


use Tarosky\Sitemap\Pattern\AbstractFeaturePattern;
use Tarosky\Sitemap\Seo\VirtualMemberIntegration;

/**
 * Structured data generator.
 *
 */
class StructuredDataGenerator extends AbstractFeaturePattern {

	/**
	 * VirtualMemberIntegration instance (nullable for lazy-load / DI).
	 *
	 * @var VirtualMemberIntegration|null
	 */
	private $virtual_member_integration = null;

	/**
	 * Get the VirtualMemberIntegration instance.
	 *
	 * Falls back to the shared singleton when none has been injected.
	 *
	 * @return VirtualMemberIntegration
	 */
	public function get_virtual_member_integration(): VirtualMemberIntegration {
		if ( null === $this->virtual_member_integration ) {
			$this->virtual_member_integration = VirtualMemberIntegration::get_instance();
		}
		return $this->virtual_member_integration;
	}

	/**
	 * Set the VirtualMemberIntegration instance.
	 *
	 * Intended for use in tests to inject a mock.
	 *
	 * @param VirtualMemberIntegration $integration Integration instance to inject.
	 * @return void
	 */
	public function set_virtual_member_integration( VirtualMemberIntegration $integration ): void {
		$this->virtual_member_integration = $integration;
	}

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
		// Front page: output WebSite and Organization schema.
		if ( is_front_page() ) {
			$website_json_ld = $this->get_website_structure();
			if ( $website_json_ld ) {
				$json_lds[] = $website_json_ld;
			}
			$organization_json_ld = $this->get_organization_structure();
			if ( $organization_json_ld ) {
				$json_lds[] = $organization_json_ld;
			}
		}
		// If this is singular (exclude front page to avoid Article on homepage).
		$article_post_types = $this->option( 'jsonld_article_post_types' );
		if ( ! is_front_page() && ! empty( $article_post_types ) && is_singular( $article_post_types ) && is_a( get_queried_object(), 'WP_Post' ) ) {
			$article_json_ld = $this->get_article_structure( get_queried_object() );
			if ( $article_json_ld ) {
				$json_lds[] = $article_json_ld;
			}
		}
		/**
		 * Filters the JSON-LD structured data array.
		 *
		 * @param array $json_lds Array of JSON-LD structured data
		 * @return array Filtered JSON-LD array
		 *
		 * @hook tsmap_json_ld
		 */
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
	 * Get WebSite structured data for front page.
	 *
	 * @return array
	 */
	public function get_website_structure() {
		$json        = [
			'@context' => 'https://schema.org',
			'@type'    => 'WebSite',
			'name'     => get_bloginfo( 'name' ),
			'url'      => home_url( '/' ),
		];
		$description = get_bloginfo( 'description' );
		if ( $description ) {
			$json['description'] = $description;
		}
		/**
		 * Filters the WebSite structured data for the front page.
		 *
		 * @param array $json WebSite structured data.
		 * @return array Filtered WebSite structured data.
		 *
		 * @hook tsmap_json_ld_website_structure
		 */
		return apply_filters( 'tsmap_json_ld_website_structure', $json );
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
			'@type'            => /**
			 * Filters the article type for JSON-LD structured data.
			 *
			 * @param string   $type Article type (default: 'Article')
			 * @param \WP_Post $post Post object
			 * @return string Filtered article type
			 *
			 * @hook tsmap_json_ld_article_type
			 */
			apply_filters( 'tsmap_json_ld_article_type', 'Article', $post ),
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
			/**
			 * Filters the author type for JSON-LD structured data.
			 *
			 * @param string   $type   Author type. Default 'Person'. Can also be 'Organization', etc.
			 * @param \WP_User $author Author user object.
			 * @param \WP_Post $post   Post object.
			 */
			'@type' => apply_filters( 'tsmap_json_ld_author_type', 'Person', $author, $post ),
			'name'  => get_the_author_meta( 'display_name', $author->ID ),
		];
		if ( preg_match( '#^https?://.+#', $author->user_url ) ) {
			$json['url'] = $author->user_url;
		}
		// If virtual member exists, set author.
		$integration = $this->get_virtual_member_integration();
		$members     = [];
		foreach ( $integration->get_members( $post ) as $member ) {
			$j = $integration->get_profile_schema( $member );
			if ( ! empty( $j ) ) {
				$members[] = $j;
			}
		}
		if ( empty( $members ) ) {
			// Try to get default member.
			$default = $integration->get_default_member();
			if ( $default ) {
				$j = $integration->get_profile_schema( $default );
				if ( ! empty( $j ) ) {
					$members[] = $j;
				}
			}
		}
		if ( ! empty( $members ) ) {
			$json = $members;
		}
		return $json;
	}

	/**
	 * Get Organization structured data for front page.
	 *
	 * Returns an empty array unless the Organization output is enabled.
	 *
	 * @see https://developers.google.com/search/docs/appearance/structured-data/organization
	 * @return array
	 */
	public function get_organization_structure() {
		if ( ! $this->is_organization_enabled() ) {
			return [];
		}
		$base        = $this->get_organization_base();
		$json        = array_merge( [
			'@context' => 'https://schema.org',
			'@type'    => $this->get_organization_type(),
			'@id'      => $this->build_organization_id( $base['url'] ),
		], $base );
		$description = trim( (string) $this->option( 'jsonld_organization_description' ) );
		if ( $description ) {
			$json['description'] = $description;
		}
		$same_as = $this->get_organization_same_as();
		if ( ! empty( $same_as ) ) {
			$json['sameAs'] = $same_as;
		}
		$address = $this->get_organization_address();
		if ( ! empty( $address ) ) {
			$json['address'] = $address;
		}
		$parent = $this->get_parent_organization();
		if ( ! empty( $parent ) ) {
			$json['parentOrganization'] = $parent;
		}
		/**
		 * Filters the Organization structured data for the front page.
		 *
		 * @param array $json Organization structured data.
		 * @return array Filtered Organization structured data.
		 *
		 * @hook tsmap_json_ld_organization_structure
		 */
		return apply_filters( 'tsmap_json_ld_organization_structure', $json );
	}

	/**
	 * Is Organization output enabled?
	 *
	 * @return bool
	 */
	public function is_organization_enabled() {
		return (bool) $this->option( 'jsonld_organization' );
	}

	/**
	 * Get the schema type of the organization.
	 *
	 * @return string
	 */
	public function get_organization_type() {
		$type = $this->option( 'jsonld_organization_type' );
		return in_array( $type, [ 'NewsMediaOrganization', 'Corporation' ], true ) ? $type : 'Organization';
	}

	/**
	 * Build the @id of an Organization node from its URL.
	 *
	 * The same organization gets the same @id across sites,
	 * e.g. media sites can refer to their publishing company.
	 *
	 * @param string $url Organization URL.
	 * @return string
	 */
	public function build_organization_id( $url ) {
		return trailingslashit( $url ) . '#organization';
	}

	/**
	 * Get parent organization reference.
	 *
	 * @return array Empty array if parent URL is not set.
	 */
	protected function get_parent_organization() {
		$url = trim( (string) $this->option( 'jsonld_organization_parent_url' ) );
		if ( ! preg_match( '#^https?://.+#', $url ) ) {
			return [];
		}
		$parent = [
			'@type' => 'Organization',
			'@id'   => $this->build_organization_id( $url ),
			'url'   => $url,
		];
		$name   = trim( (string) $this->option( 'jsonld_organization_parent_name' ) );
		if ( $name ) {
			$parent['name'] = $name;
		}
		return $parent;
	}

	/**
	 * Get name, url and logo of the organization.
	 *
	 * Shared by Organization and Article publisher.
	 *
	 * @return array
	 */
	protected function get_organization_base() {
		$base = [
			'name' => get_bloginfo( 'name' ),
			'url'  => home_url( '/' ),
		];
		// Fallback logo: site logo, then site icon.
		$site_logo = get_theme_mod( 'custom_logo' );
		$logo      = $site_logo ? wp_get_attachment_image_url( $site_logo, 'full' ) : '';
		if ( ! $logo ) {
			$logo = get_site_icon_url( 'full' );
		}
		if ( $logo ) {
			$base['logo'] = $logo;
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
				$base[ $key ] = $option;
			}
		}
		return $base;
	}

	/**
	 * Get sameAs URLs from the setting (one URL per line).
	 *
	 * @return string[]
	 */
	protected function get_organization_same_as() {
		$lines = preg_split( '/\R/u', (string) $this->option( 'jsonld_organization_same_as' ) );
		$urls  = array_filter( array_map( 'trim', $lines ), function ( $line ) {
			return (bool) preg_match( '#^https?://.+#', $line );
		} );
		return array_values( array_unique( $urls ) );
	}

	/**
	 * Get PostalAddress of the organization.
	 *
	 * @return array Empty array if no address field is set.
	 */
	protected function get_organization_address() {
		$address = [];
		foreach ( [
			'postalCode'      => 'postal_code',
			'addressRegion'   => 'region',
			'addressLocality' => 'locality',
			'streetAddress'   => 'street',
			'addressCountry'  => 'country',
		] as $property => $key ) {
			$value = trim( (string) $this->option( 'jsonld_organization_' . $key ) );
			if ( $value ) {
				$address[ $property ] = $value;
			}
		}
		if ( empty( $address ) ) {
			return [];
		}
		return array_merge( [ '@type' => 'PostalAddress' ], $address );
	}

	/**
	 * @param \WP_Post $post
	 *
	 * @return mixed|null
	 */
	public function get_publisher_structure( $post ) {
		$base      = $this->get_organization_base();
		$publisher = [ '@type' => $this->get_organization_type() ];
		if ( $this->is_organization_enabled() ) {
			// Refer to the Organization node on the front page.
			$publisher['@id'] = $this->build_organization_id( $base['url'] );
		}
		$publisher = array_merge( $publisher, $base );
		/**
		 * Filters the publisher structure for JSON-LD structured data.
		 *
		 * @param array    $publisher Publisher structure
		 * @param \WP_Post $post      Post object
		 * @return array Filtered publisher structure
		 *
		 * @hook tsmap_json_ld_publisher_structure
		 */
		return apply_filters( 'tsmap_json_ld_publisher_structure', $publisher, $post );
	}
}
