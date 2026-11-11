<?php

use Tarosky\Sitemap\Seo\Features\PostCanonical;

/**
 * Tests for PostCanonical.
 */
class PostCanonicalTest extends WP_UnitTestCase {

	/**
	 * @var PostCanonical
	 */
	private $feature;

	public function set_up() {
		parent::set_up();
		update_option( 'tsmap_canonical_posts', [ 'post' ] );
		$this->feature = PostCanonical::get_instance();
		// Hooks are registered at plugin load, before the option is set.
		add_filter( 'get_canonical_url', [ $this->feature, 'filter_canonical' ], 10, 2 );
		add_filter( 'tsmap_ogp', [ $this->feature, 'filter_ogp' ] );
	}

	/**
	 * Only absolute http(s) URLs are accepted.
	 */
	public function test_sanitize() {
		$this->assertEquals( 'https://example.com/a?b=1', $this->feature->sanitize( ' https://example.com/a?b=1 ' ) );
		$this->assertEquals( '', $this->feature->sanitize( 'javascript:alert(1)' ) );
		$this->assertEquals( '', $this->feature->sanitize( '/relative/path' ) );
		$this->assertEquals( '', $this->feature->sanitize( '' ) );
	}

	/**
	 * Invalid input keeps the current value, empty input deletes it.
	 */
	public function test_save_canonical() {
		$post_id = self::factory()->post->create();
		$this->feature->save_canonical( $post_id, 'https://example.com/original' );
		$this->assertEquals( 'https://example.com/original', get_post_meta( $post_id, '_tsmap_canonical', true ) );

		$this->feature->save_canonical( $post_id, '/relative/path' );
		$this->assertEquals( 'https://example.com/original', get_post_meta( $post_id, '_tsmap_canonical', true ), 'Invalid input keeps current value.' );

		$this->feature->save_canonical( $post_id, '  ' );
		$this->assertEquals( '', get_post_meta( $post_id, '_tsmap_canonical', true ), 'Empty input deletes value.' );
	}

	/**
	 * Custom canonical overrides the permalink only for active post types.
	 */
	public function test_canonical_url() {
		$post = self::factory()->post->create_and_get();
		$page = self::factory()->post->create_and_get( [ 'post_type' => 'page' ] );
		$this->assertEquals( get_permalink( $post ), wp_get_canonical_url( $post ), 'Empty meta keeps permalink.' );

		update_post_meta( $post->ID, '_tsmap_canonical', 'https://example.com/original' );
		update_post_meta( $page->ID, '_tsmap_canonical', 'https://example.com/original' );
		$this->assertEquals( 'https://example.com/original', wp_get_canonical_url( $post ) );
		$this->assertEquals( get_permalink( $page ), wp_get_canonical_url( $page ), 'Inactive post type is untouched.' );
	}

	/**
	 * og:url follows custom canonical on singular page.
	 */
	public function test_ogp_url() {
		$post = self::factory()->post->create_and_get();
		update_post_meta( $post->ID, '_tsmap_canonical', 'https://example.com/original' );
		$this->go_to( get_permalink( $post ) );
		$ogp = apply_filters( 'tsmap_ogp', [ 'og:url' => get_permalink( $post ) ] );
		$this->assertEquals( 'https://example.com/original', $ogp['og:url'] );
	}
}
