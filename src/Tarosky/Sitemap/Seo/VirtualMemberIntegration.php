<?php

namespace Tarosky\Sitemap\Seo;


use Tarosky\Sitemap\Pattern\Singleton;

/**
 * Integration layer for the virtual-member plugin.
 *
 * Abstracts the virtual-member plugin API so that callers
 * do not depend on its concrete classes directly.
 */
class VirtualMemberIntegration extends Singleton {

	/**
	 * Check if the virtual-member plugin is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return class_exists( 'Kunoichi\VirtualMember\PostType' );
	}

	/**
	 * Get virtual members assigned to a post.
	 *
	 * Returns an empty array if the plugin is not active.
	 *
	 * @param \WP_Post $post Post object.
	 * @return \WP_Post[] Array of virtual member post objects.
	 */
	public function get_members( \WP_Post $post ): array {
		if ( ! $this->is_active() ) {
			return [];
		}
		return \Kunoichi\VirtualMember\Ui\PublicScreen::get_instance()->get_members( $post );
	}

	/**
	 * Get the default virtual member.
	 *
	 * @return \WP_Post|null Default member post object, or null if not set.
	 */
	public function get_default_member(): ?\WP_Post {
		if ( ! $this->is_active() ) {
			return null;
		}
		$default_id = \Kunoichi\VirtualMember\PostType::default_user();
		return $default_id ? get_post( $default_id ) : null;
	}

	/**
	 * Get profile schema for a virtual member.
	 *
	 * Falls back to OgpProvider for older versions of the virtual-member plugin
	 * that do not have StructuredDataProvider.
	 *
	 * @param int|\WP_Post $member Virtual member post ID or post object.
	 * @return array Profile schema array, or empty array if no compatible provider is available.
	 */
	public function get_profile_schema( $member ): array {
		if ( class_exists( 'Kunoichi\VirtualMember\Services\StructuredDataProvider' ) ) {
			return \Kunoichi\VirtualMember\Services\StructuredDataProvider::get_instance()->get_profile_schema( $member ) ?? [];
		} elseif ( class_exists( 'Kunoichi\VirtualMember\Services\OgpProvider' ) ) {
			return \Kunoichi\VirtualMember\Services\OgpProvider::get_instance()->get_ogp( $member ) ?? [];
		}
		return [];
	}
}
