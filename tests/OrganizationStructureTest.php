<?php

use Tarosky\Sitemap\Seo\Features\StructuredDataGenerator;

/**
 * Tests for Organization structured data.
 */
class OrganizationStructureTest extends WP_UnitTestCase {

	/**
	 * @var StructuredDataGenerator
	 */
	private $generator;

	public function set_up() {
		parent::set_up();
		$this->generator = StructuredDataGenerator::get_instance();
		update_option( 'show_on_front', 'posts' );
	}

	/**
	 * Organization is not output unless enabled.
	 */
	public function test_organization_is_not_output_by_default() {
		$this->go_to( home_url( '/' ) );

		$this->assertNull( $this->find_by_type( $this->generator->get_json_ld(), 'Organization' ) );
	}

	/**
	 * Enabled Organization has name, url and @id with defaults.
	 */
	public function test_organization_is_output_on_front_page() {
		update_option( 'tsmap_jsonld_organization', '1' );
		$this->go_to( home_url( '/' ) );

		$organization = $this->find_by_type( $this->generator->get_json_ld(), 'Organization' );

		$this->assertNotNull( $organization );
		$this->assertEquals( 'https://schema.org', $organization['@context'] );
		$this->assertEquals( home_url( '/#organization' ), $organization['@id'] );
		$this->assertEquals( get_bloginfo( 'name' ), $organization['name'] );
		$this->assertEquals( home_url( '/' ), $organization['url'] );
		$this->assertArrayNotHasKey( 'description', $organization );
		$this->assertArrayNotHasKey( 'sameAs', $organization );
		$this->assertArrayNotHasKey( 'address', $organization );
		// WebSite is still output.
		$this->assertNotNull( $this->find_by_type( $this->generator->get_json_ld(), 'WebSite' ) );
	}

	/**
	 * Organization is not output on singular pages.
	 */
	public function test_organization_is_not_output_on_singular() {
		update_option( 'tsmap_jsonld_organization', '1' );
		update_option( 'tsmap_jsonld_article_post_types', [ 'post' ] );
		$post_id = self::factory()->post->create( [
			'post_status' => 'publish',
			'post_author' => self::factory()->user->create(),
		] );
		$this->go_to( get_permalink( $post_id ) );

		$this->assertNull( $this->find_by_type( $this->generator->get_json_ld(), 'Organization' ) );
	}

	/**
	 * Publisher settings, description, sameAs and address are reflected.
	 */
	public function test_organization_has_all_fields() {
		update_option( 'tsmap_jsonld_organization', '1' );
		update_option( 'tsmap_jsonld_publisher_name', 'Example Inc.' );
		update_option( 'tsmap_jsonld_publisher_url', 'https://corp.example.com/' );
		update_option( 'tsmap_jsonld_organization_description', ' Example description. ' );
		update_option( 'tsmap_jsonld_organization_same_as', "https://en.wikipedia.org/wiki/Example\r\n\r\nnot a url\nhttps://x.com/example\nhttps://x.com/example" );
		update_option( 'tsmap_jsonld_organization_postal_code', '100-0001' );
		update_option( 'tsmap_jsonld_organization_region', '東京都' );
		update_option( 'tsmap_jsonld_organization_locality', '千代田区' );
		update_option( 'tsmap_jsonld_organization_street', '千代田1-1' );
		update_option( 'tsmap_jsonld_organization_country', 'JP' );

		$organization = $this->generator->get_organization_structure();

		$this->assertEquals( 'Example Inc.', $organization['name'] );
		$this->assertEquals( 'https://corp.example.com/', $organization['url'] );
		$this->assertEquals( 'Example description.', $organization['description'] );
		$this->assertEquals( [ 'https://en.wikipedia.org/wiki/Example', 'https://x.com/example' ], $organization['sameAs'] );
		$this->assertEquals( [
			'@type'           => 'PostalAddress',
			'postalCode'      => '100-0001',
			'addressRegion'   => '東京都',
			'addressLocality' => '千代田区',
			'streetAddress'   => '千代田1-1',
			'addressCountry'  => 'JP',
		], $organization['address'] );
	}

	/**
	 * Partial address only contains specified fields.
	 */
	public function test_organization_partial_address() {
		update_option( 'tsmap_jsonld_organization', '1' );
		update_option( 'tsmap_jsonld_organization_country', 'JP' );

		$organization = $this->generator->get_organization_structure();

		$this->assertEquals( [
			'@type'          => 'PostalAddress',
			'addressCountry' => 'JP',
		], $organization['address'] );
	}

	/**
	 * Organization is filterable.
	 */
	public function test_organization_is_filterable() {
		update_option( 'tsmap_jsonld_organization', '1' );
		$callback = function ( $json ) {
			$json['@type'] = 'NewsMediaOrganization';
			return $json;
		};
		add_filter( 'tsmap_json_ld_organization_structure', $callback );

		$organization = $this->generator->get_organization_structure();

		remove_filter( 'tsmap_json_ld_organization_structure', $callback );
		$this->assertEquals( 'NewsMediaOrganization', $organization['@type'] );
	}

	/**
	 * Publisher always has @type Organization, without @id when disabled.
	 */
	public function test_publisher_has_organization_type() {
		$post = self::factory()->post->create_and_get();

		$publisher = $this->generator->get_publisher_structure( $post );

		$this->assertEquals( 'Organization', $publisher['@type'] );
		$this->assertEquals( get_bloginfo( 'name' ), $publisher['name'] );
		$this->assertArrayNotHasKey( '@id', $publisher );
	}

	/**
	 * Publisher refers to the Organization node when enabled.
	 */
	public function test_publisher_refers_organization_when_enabled() {
		update_option( 'tsmap_jsonld_organization', '1' );
		$post = self::factory()->post->create_and_get();

		$publisher = $this->generator->get_publisher_structure( $post );

		$this->assertEquals( home_url( '/#organization' ), $publisher['@id'] );
	}

	/**
	 * Find JSON-LD by @type.
	 *
	 * @param array  $json_lds JSON-LD list.
	 * @param string $type     Type.
	 * @return array|null
	 */
	private function find_by_type( array $json_lds, string $type ) {
		foreach ( $json_lds as $json ) {
			if ( isset( $json['@type'] ) && $json['@type'] === $type ) {
				return $json;
			}
		}
		return null;
	}
}
