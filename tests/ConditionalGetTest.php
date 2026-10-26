<?php

use Tarosky\Sitemap\Seo\Features\ConditionalGet;

/**
 * Tests for ConditionalGet.
 */
class ConditionalGetTest extends WP_UnitTestCase {

	/**
	 * @var ConditionalGet
	 */
	private $feature;

	public function set_up() {
		parent::set_up();
		$this->feature = ConditionalGet::get_instance();
	}

	public function tear_down() {
		unset( $_SERVER['REQUEST_METHOD'] );
		parent::tear_down();
	}

	/**
	 * Create a published post.
	 *
	 * @param array $args Post args.
	 * @return WP_Post
	 */
	private function create_post( $args = [] ) {
		return self::factory()->post->create_and_get( array_merge( [
			'post_status'   => 'publish',
			'post_date'     => '2026-01-01 09:00:00',
			'post_date_gmt' => '2026-01-01 00:00:00',
		], $args ) );
	}

	/**
	 * Singular published post is a target.
	 */
	public function test_singular_post_is_target() {
		$post = $this->create_post();
		$this->go_to( get_permalink( $post ) );

		$this->assertEquals( $post->ID, $this->feature->get_target_post()->ID );
	}

	/**
	 * Non-singular pages, logged-in users, password protected posts and POST requests are excluded.
	 */
	public function test_excluded_requests() {
		$post = $this->create_post();

		$this->go_to( home_url( '/' ) );
		$this->assertNull( $this->feature->get_target_post(), 'Front page (latest posts) should be excluded.' );

		$this->go_to( get_permalink( $post ) );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$this->assertNull( $this->feature->get_target_post(), 'POST should be excluded.' );
		$_SERVER['REQUEST_METHOD'] = 'GET';

		wp_set_current_user( self::factory()->user->create() );
		$this->assertNull( $this->feature->get_target_post(), 'Logged-in user should be excluded.' );
		wp_set_current_user( 0 );

		$protected = $this->create_post( [ 'post_password' => 'secret' ] );
		$this->go_to( get_permalink( $protected ) );
		$this->assertNull( $this->feature->get_target_post(), 'Password protected post should be excluded.' );
	}

	/**
	 * Filter can disable the feature per post.
	 */
	public function test_filter_disables_target() {
		$post = $this->create_post();
		$this->go_to( get_permalink( $post ) );
		add_filter( 'tsmap_conditional_get_enabled', '__return_false' );

		$result = $this->feature->get_target_post();

		remove_filter( 'tsmap_conditional_get_enabled', '__return_false' );
		$this->assertNull( $result );
	}

	/**
	 * ETag is weak and changes when the post is updated.
	 */
	public function test_etag_changes_on_update() {
		$post = $this->create_post();
		$etag = $this->feature->get_etag( $post );

		$this->assertMatchesRegularExpression( '#^W/"[0-9a-f]{32}"$#', $etag );
		$this->assertEquals( $etag, $this->feature->get_etag( get_post( $post->ID ) ), 'Same post should have same ETag.' );

		$post->post_modified_gmt = '2026-02-01 00:00:00';
		$this->assertNotEquals( $etag, $this->feature->get_etag( $post ) );
	}

	/**
	 * Last-Modified uses GMT, not local time.
	 */
	public function test_last_modified_uses_gmt() {
		$post = $this->create_post();
		$post->post_modified     = '2026-01-01 09:00:00';
		$post->post_modified_gmt = '2026-01-01 00:00:00';

		$this->assertEquals( gmmktime( 0, 0, 0, 1, 1, 2026 ), $this->feature->get_last_modified( $post ) );
	}

	/**
	 * Newer approved comment updates Last-Modified.
	 */
	public function test_last_modified_includes_comments() {
		$post = $this->create_post();
		self::factory()->comment->create( [
			'comment_post_ID'  => $post->ID,
			'comment_approved' => 1,
			'comment_date'     => '2026-03-01 09:00:00',
			'comment_date_gmt' => '2026-03-01 00:00:00',
		] );
		$post = get_post( $post->ID );
		$post->post_modified_gmt = '2026-01-01 00:00:00';

		$this->assertEquals( gmmktime( 0, 0, 0, 3, 1, 2026 ), $this->feature->get_last_modified( $post ) );
	}

	/**
	 * Conditional request evaluation.
	 *
	 * @dataProvider not_modified_provider
	 */
	public function test_is_not_modified( $if_none_match, $if_modified_since, $expected ) {
		$etag          = 'W/"abc"';
		$last_modified = gmmktime( 0, 0, 0, 1, 1, 2026 );

		$this->assertSame( $expected, $this->feature->is_not_modified( $etag, $last_modified, $if_none_match, $if_modified_since ) );
	}

	/**
	 * @return array
	 */
	public function not_modified_provider() {
		return [
			'no condition'                    => [ '', '', false ],
			'matching weak etag'              => [ 'W/"abc"', '', true ],
			'matching strong etag'            => [ '"abc"', '', true ],
			'matching etag in list'           => [ '"xyz", W/"abc"', '', true ],
			'wildcard'                        => [ '*', '', true ],
			'different etag'                  => [ 'W/"xyz"', '', false ],
			'etag precedes modified since'    => [ 'W/"xyz"', 'Thu, 01 Jan 2026 00:00:00 GMT', false ],
			'modified since same time'        => [ '', 'Thu, 01 Jan 2026 00:00:00 GMT', true ],
			'modified since later'            => [ '', 'Fri, 02 Jan 2026 00:00:00 GMT', true ],
			'modified since earlier'          => [ '', 'Wed, 31 Dec 2025 00:00:00 GMT', false ],
			'invalid modified since'          => [ '', 'invalid date', false ],
		];
	}
}
