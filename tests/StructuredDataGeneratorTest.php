<?php

use Tarosky\Sitemap\Seo\Features\StructuredDataGenerator;
use Tarosky\Sitemap\Seo\VirtualMemberIntegration;

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

	public function tear_down() {
		// Reset any injected mock back to the real singleton.
		$this->generator->set_virtual_member_integration( VirtualMemberIntegration::get_instance() );
		parent::tear_down();
	}

	// -----------------------------------------------------------------------
	// Helper: build and inject a VirtualMemberIntegration mock.
	// -----------------------------------------------------------------------

	/**
	 * Create a VirtualMemberIntegration mock and inject it into the generator.
	 *
	 * @param array         $members     Value returned by get_members().
	 * @param \WP_Post|null $default     Value returned by get_default_member().
	 * @param array         $profile_map Map of [ member_post => schema_array ] for get_profile_schema().
	 * @return \PHPUnit\Framework\MockObject\MockObject
	 */
	private function inject_virtual_member_mock( array $members = [], $default = null, array $profile_map = [] ) {
		$mock = $this->getMockBuilder( VirtualMemberIntegration::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'is_active', 'get_members', 'get_default_member', 'get_profile_schema' ] )
			->getMock();

		$mock->method( 'is_active' )->willReturn( true );
		$mock->method( 'get_members' )->willReturn( $members );
		$mock->method( 'get_default_member' )->willReturn( $default );
		$mock->method( 'get_profile_schema' )->willReturnCallback(
			function ( $member ) use ( $profile_map ) {
				$id = is_object( $member ) ? $member->ID : (int) $member;
				return $profile_map[ $id ] ?? [];
			}
		);

		$this->generator->set_virtual_member_integration( $mock );
		return $mock;
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
	 * Test that front page with static page outputs WebSite only, NOT Article.
	 */
	public function test_front_page_static_page_has_website_but_not_article() {
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
		$this->assertNull( $article, 'Article schema should NOT exist on front page.' );

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

	// -----------------------------------------------------------------------
	// Tests for get_authors_structure()
	// -----------------------------------------------------------------------

	/**
	 * When the virtual-member plugin is inactive, returns the standard Person structure.
	 *
	 * Uses a partial mock: only is_active() is mocked (returns false).
	 * get_members() and get_default_member() run their real implementations,
	 * which check is_active() internally and early-return [] / null.
	 */
	public function test_authors_structure_when_virtual_member_plugin_inactive() {
		$user_id = self::factory()->user->create( [
			'display_name' => 'Inactive Plugin Author',
			'user_url'     => '',
		] );
		$post_id = self::factory()->post->create( [
			'post_status' => 'publish',
			'post_author' => $user_id,
		] );
		$post = get_post( $post_id );

		$mock = $this->getMockBuilder( VirtualMemberIntegration::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'is_active' ] )
			->getMock();
		$mock->method( 'is_active' )->willReturn( false );
		$this->generator->set_virtual_member_integration( $mock );

		$author = $this->generator->get_authors_structure( $post );

		$this->assertIsArray( $author );
		$this->assertEquals( 'Person', $author['@type'] );
		$this->assertEquals( 'Inactive Plugin Author', $author['name'] );
	}

	/**
	 * No virtual members and no default member: returns the standard Person structure.
	 */
	public function test_authors_structure_returns_person_when_no_virtual_members() {
		$user_id = self::factory()->user->create( [
			'display_name' => 'Test Author',
			'user_url'     => '',
		] );
		$post_id = self::factory()->post->create( [
			'post_status' => 'publish',
			'post_author' => $user_id,
		] );
		$post = get_post( $post_id );

		$this->inject_virtual_member_mock( [], null );

		$author = $this->generator->get_authors_structure( $post );

		$this->assertIsArray( $author );
		$this->assertEquals( 'Person', $author['@type'] );
		$this->assertEquals( 'Test Author', $author['name'] );
		$this->assertArrayNotHasKey( 'url', $author, 'Empty user_url should not produce a url key.' );
	}

	/**
	 * When user_url is a valid http(s) URL, the url key is included.
	 */
	public function test_authors_structure_includes_url_when_valid_user_url() {
		$user_id = self::factory()->user->create( [
			'user_url' => 'https://example.com',
		] );
		$post_id = self::factory()->post->create( [
			'post_status' => 'publish',
			'post_author' => $user_id,
		] );
		$post = get_post( $post_id );

		$this->inject_virtual_member_mock( [], null );

		$author = $this->generator->get_authors_structure( $post );

		$this->assertArrayHasKey( 'url', $author );
		$this->assertEquals( 'https://example.com', $author['url'] );
	}

	/**
	 * When user_url is not a valid URL, the url key is omitted.
	 */
	public function test_authors_structure_excludes_url_when_invalid_user_url() {
		$user_id = self::factory()->user->create( [
			'user_url' => 'not-a-url',
		] );
		$post_id = self::factory()->post->create( [
			'post_status' => 'publish',
			'post_author' => $user_id,
		] );
		$post = get_post( $post_id );

		$this->inject_virtual_member_mock( [], null );

		$author = $this->generator->get_authors_structure( $post );

		$this->assertArrayNotHasKey( 'url', $author, 'Invalid user_url should not produce a url key.' );
	}

	/**
	 * When get_members() returns members, the author is replaced by their profile schemas.
	 */
	public function test_authors_structure_replaced_by_virtual_members() {
		$user_id = self::factory()->user->create();
		$post_id = self::factory()->post->create( [
			'post_status' => 'publish',
			'post_author' => $user_id,
		] );
		$post = get_post( $post_id );

		$member_a = self::factory()->post->create_and_get( [ 'post_type' => 'post', 'post_status' => 'publish' ] );
		$member_b = self::factory()->post->create_and_get( [ 'post_type' => 'post', 'post_status' => 'publish' ] );

		$schema_a = [ '@type' => 'Person', 'name' => 'Member A' ];
		$schema_b = [ '@type' => 'Person', 'name' => 'Member B' ];

		$this->inject_virtual_member_mock(
			[ $member_a, $member_b ],
			null,
			[ $member_a->ID => $schema_a, $member_b->ID => $schema_b ]
		);

		$author = $this->generator->get_authors_structure( $post );

		$this->assertIsArray( $author );
		$this->assertCount( 2, $author );
		$this->assertEquals( $schema_a, $author[0] );
		$this->assertEquals( $schema_b, $author[1] );
	}

	/**
	 * When get_members() returns empty and get_default_member() returns a member,
	 * the author is replaced by the default member's profile schema.
	 */
	public function test_authors_structure_falls_back_to_default_member() {
		$user_id = self::factory()->user->create();
		$post_id = self::factory()->post->create( [
			'post_status' => 'publish',
			'post_author' => $user_id,
		] );
		$post = get_post( $post_id );

		$default_member = self::factory()->post->create_and_get( [ 'post_type' => 'post', 'post_status' => 'publish' ] );
		$default_schema = [ '@type' => 'Person', 'name' => 'Default Member' ];

		$this->inject_virtual_member_mock( [], $default_member, [ $default_member->ID => $default_schema ] );

		$author = $this->generator->get_authors_structure( $post );

		$this->assertIsArray( $author );
		$this->assertCount( 1, $author );
		$this->assertEquals( $default_schema, $author[0] );
	}

	/**
	 * Members whose get_profile_schema() returns an empty array are skipped.
	 */
	public function test_authors_structure_skips_members_with_empty_profile_schema() {
		$user_id = self::factory()->user->create();
		$post_id = self::factory()->post->create( [
			'post_status' => 'publish',
			'post_author' => $user_id,
		] );
		$post = get_post( $post_id );

		$member_ok    = self::factory()->post->create_and_get( [ 'post_type' => 'post', 'post_status' => 'publish' ] );
		$member_empty = self::factory()->post->create_and_get( [ 'post_type' => 'post', 'post_status' => 'publish' ] );
		$schema_ok    = [ '@type' => 'Person', 'name' => 'Valid Member' ];

		$this->inject_virtual_member_mock(
			[ $member_ok, $member_empty ],
			null,
			[ $member_ok->ID => $schema_ok, $member_empty->ID => [] ]
		);

		$author = $this->generator->get_authors_structure( $post );

		$this->assertCount( 1, $author, 'Members with empty profile schema should be skipped.' );
		$this->assertEquals( $schema_ok, $author[0] );
	}

	/**
	 * The tsmap_json_ld_author_type filter changes the @type (no-virtual-member case).
	 */
	public function test_authors_structure_author_type_filter() {
		$user_id = self::factory()->user->create();
		$post_id = self::factory()->post->create( [
			'post_status' => 'publish',
			'post_author' => $user_id,
		] );
		$post = get_post( $post_id );

		$this->inject_virtual_member_mock( [], null );

		$callback = function ( $type ) {
			return 'Organization';
		};
		add_filter( 'tsmap_json_ld_author_type', $callback );

		$author = $this->generator->get_authors_structure( $post );

		remove_filter( 'tsmap_json_ld_author_type', $callback );

		$this->assertEquals( 'Organization', $author['@type'] );
	}

	// -----------------------------------------------------------------------
	// Private helpers
	// -----------------------------------------------------------------------

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
