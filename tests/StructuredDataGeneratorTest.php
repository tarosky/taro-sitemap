<?php

use Tarosky\Sitemap\Seo\Features\StructuredDataGenerator;

/**
 * Tests for StructuredDataGenerator.
 */
class StructuredDataGeneratorTest extends WP_UnitTestCase {

	/**
	 * @var StructuredDataGenerator
	 */
	private $generator;

	public function set_up() {
		parent::set_up();
		$this->generator = StructuredDataGenerator::get_instance();
	}

	/**
	 * Test that front page with "Your latest posts" outputs WebSite schema.
	 */
	public function test_front_page_latest_posts_has_website_schema() {
		// Ensure "Your latest posts" (no static front page).
		update_option( 'show_on_front', 'posts' );
		delete_option( 'page_on_front' );

		// Go to the front page.
		$this->go_to( home_url( '/' ) );

		$this->assertTrue( is_front_page(), 'Should be front page.' );
		$this->assertFalse( is_singular(), 'Should NOT be singular.' );

		$json_lds = $this->generator->get_json_ld();

		$this->assertNotEmpty( $json_lds, 'JSON-LD should not be empty on front page.' );

		// Find WebSite type.
		$website = $this->find_by_type( $json_lds, 'WebSite' );
		$this->assertNotNull( $website, 'WebSite schema should exist on front page.' );
		$this->assertEquals( 'https://schema.org', $website['@context'] );
		$this->assertEquals( get_bloginfo( 'name' ), $website['name'] );
		$this->assertEquals( home_url( '/' ), $website['url'] );
	}

	/**
	 * Test that front page with static page outputs WebSite schema.
	 */
	public function test_front_page_static_page_has_website_schema() {
		$page_id = self::factory()->post->create( [
			'post_type'   => 'page',
			'post_title'  => 'Home',
			'post_status' => 'publish',
		] );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $page_id );

		$this->go_to( get_permalink( $page_id ) );

		$this->assertTrue( is_front_page(), 'Should be front page.' );

		$json_lds = $this->generator->get_json_ld();

		$website = $this->find_by_type( $json_lds, 'WebSite' );
		$this->assertNotNull( $website, 'WebSite schema should exist on static front page.' );

		// Clean up.
		update_option( 'show_on_front', 'posts' );
		delete_option( 'page_on_front' );
	}

	/**
	 * Test that front page with static page also outputs Article schema when page is in jsonld_article_post_types.
	 */
	public function test_front_page_static_page_has_both_website_and_article() {
		$user_id = self::factory()->user->create( [ 'role' => 'editor' ] );
		$page_id = self::factory()->post->create( [
			'post_type'   => 'page',
			'post_title'  => 'Home',
			'post_status' => 'publish',
			'post_author' => $user_id,
		] );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $page_id );
		update_option( 'tsmap_jsonld_article_post_types', [ 'page' ] );

		$this->go_to( get_permalink( $page_id ) );

		$json_lds = $this->generator->get_json_ld();

		$website = $this->find_by_type( $json_lds, 'WebSite' );
		$this->assertNotNull( $website, 'WebSite schema should exist.' );

		$article = $this->find_by_type( $json_lds, 'Article' );
		$this->assertNotNull( $article, 'Article schema should also exist for static front page.' );

		// Clean up.
		update_option( 'show_on_front', 'posts' );
		delete_option( 'page_on_front' );
		delete_option( 'tsmap_jsonld_article_post_types' );
	}

	/**
	 * Test that singular post outputs Article but NOT WebSite schema.
	 */
	public function test_singular_post_has_article_but_not_website() {
		$user_id = self::factory()->user->create( [ 'role' => 'editor' ] );
		update_option( 'tsmap_jsonld_article_post_types', [ 'post' ] );

		$post_id = self::factory()->post->create( [
			'post_title'  => 'Test Post',
			'post_status' => 'publish',
			'post_author' => $user_id,
		] );

		$this->go_to( get_permalink( $post_id ) );

		$this->assertTrue( is_singular(), 'Should be singular.' );
		$this->assertFalse( is_front_page(), 'Should NOT be front page.' );

		$json_lds = $this->generator->get_json_ld();

		$website = $this->find_by_type( $json_lds, 'WebSite' );
		$this->assertNull( $website, 'WebSite schema should NOT exist on singular post.' );

		$article = $this->find_by_type( $json_lds, 'Article' );
		$this->assertNotNull( $article, 'Article schema should exist on singular post.' );

		delete_option( 'tsmap_jsonld_article_post_types' );
	}

	/**
	 * Test that WebSite schema includes description when bloginfo description is set.
	 */
	public function test_website_schema_includes_description() {
		update_option( 'blogdescription', 'Test Site Description' );
		update_option( 'show_on_front', 'posts' );

		$this->go_to( home_url( '/' ) );

		$json_lds = $this->generator->get_json_ld();
		$website  = $this->find_by_type( $json_lds, 'WebSite' );

		$this->assertNotNull( $website );
		$this->assertEquals( 'Test Site Description', $website['description'] );
	}

	/**
	 * Test that WebSite schema excludes description when empty.
	 */
	public function test_website_schema_excludes_empty_description() {
		update_option( 'blogdescription', '' );
		update_option( 'show_on_front', 'posts' );

		$this->go_to( home_url( '/' ) );

		$json_lds = $this->generator->get_json_ld();
		$website  = $this->find_by_type( $json_lds, 'WebSite' );

		$this->assertNotNull( $website );
		$this->assertArrayNotHasKey( 'description', $website );
	}

	/**
	 * Test that WebSite schema is filterable via tsmap_json_ld_website_structure.
	 */
	public function test_website_schema_is_filterable() {
		update_option( 'show_on_front', 'posts' );

		$callback = function ( $json ) {
			$json['inLanguage'] = 'ja';
			return $json;
		};
		add_filter( 'tsmap_json_ld_website_structure', $callback );

		$this->go_to( home_url( '/' ) );

		$json_lds = $this->generator->get_json_ld();
		$website  = $this->find_by_type( $json_lds, 'WebSite' );

		$this->assertNotNull( $website );
		$this->assertEquals( 'ja', $website['inLanguage'] );

		remove_filter( 'tsmap_json_ld_website_structure', $callback );
	}

	/**
	 * Test that archive page (not front page) has no JSON-LD by default.
	 */
	public function test_archive_page_has_no_jsonld() {
		$cat_id = self::factory()->category->create( [ 'name' => 'Test Category' ] );
		self::factory()->post->create( [
			'post_status'   => 'publish',
			'post_category' => [ $cat_id ],
		] );

		$this->go_to( get_category_link( $cat_id ) );

		$this->assertFalse( is_front_page(), 'Should NOT be front page.' );
		$this->assertTrue( is_category(), 'Should be category archive.' );

		$json_lds = $this->generator->get_json_ld();

		$this->assertEmpty( $json_lds, 'Category archive should have no JSON-LD by default.' );
	}

	/**
	 * Find a JSON-LD item by @type.
	 *
	 * @param array  $json_lds Array of JSON-LD items.
	 * @param string $type     The @type to search for.
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
