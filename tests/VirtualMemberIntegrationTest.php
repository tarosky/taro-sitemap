<?php

use Tarosky\Sitemap\Seo\VirtualMemberIntegration;

/**
 * Tests for VirtualMemberIntegration.
 *
 * In the test environment the virtual-member plugin is loaded via Composer
 * autoload (kunoichi/virtual-member ^1.2), so the Kunoichi classes exist and
 * Services\StructuredDataProvider is available, while Services\OgpProvider is
 * not. The OgpProvider fallback branch in get_profile_schema() therefore only
 * applies to older plugin releases and is not exercised here.
 */
class VirtualMemberIntegrationTest extends WP_UnitTestCase {

	/**
	 * @var VirtualMemberIntegration
	 */
	private $integration;

	public function set_up() {
		parent::set_up();
		$this->integration = VirtualMemberIntegration::get_instance();
	}

	/**
	 * The plugin class is autoloadable as a dev dependency, so is_active() is true.
	 */
	public function test_is_active_returns_true_when_plugin_classes_available() {
		$this->assertTrue( $this->integration->is_active() );
	}

	/**
	 * A post with no assigned members yields an empty array.
	 */
	public function test_get_members_returns_empty_for_post_without_members() {
		$post = self::factory()->post->create_and_get();

		$this->assertSame( [], $this->integration->get_members( $post ) );
	}

	/**
	 * Returns null when no default member is configured.
	 */
	public function test_get_default_member_returns_null_when_not_configured() {
		$this->assertNull( $this->integration->get_default_member() );
	}

	/**
	 * Delegates to StructuredDataProvider and returns the member profile schema.
	 */
	public function test_get_profile_schema_delegates_to_structured_data_provider() {
		register_post_type( 'member', [ 'public' => true ] );
		$member_id = self::factory()->post->create( [
			'post_type'   => 'member',
			'post_title'  => 'Jane Doe',
			'post_status' => 'publish',
		] );

		$schema = $this->integration->get_profile_schema( get_post( $member_id ) );

		$this->assertIsArray( $schema );
		$this->assertSame( 'Person', $schema['@type'] );
		$this->assertSame( 'Jane Doe', $schema['name'] );
	}

	/**
	 * Returns an empty array for a post that is not a virtual member.
	 */
	public function test_get_profile_schema_returns_empty_for_non_member_post() {
		$post_id = self::factory()->post->create( [ 'post_type' => 'post' ] );

		$schema = $this->integration->get_profile_schema( get_post( $post_id ) );

		$this->assertSame( [], $schema );
	}
}
