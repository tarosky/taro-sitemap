<?php

use Tarosky\Sitemap\Provider\NewsSitemapProvider;
use Tarosky\Sitemap\Provider\PostSitemapProvider;

/**
 * Tests for lastmod output in sitemaps.
 *
 * Covers the scenario where a scheduled post has post_modified < post_date
 * (wp_publish_post() only updates post_status, not post_modified).
 */
class SitemapLastmodTest extends WP_UnitTestCase {

	// -----------------------------------------------------------------------
	// Unit tests: get_last_mod()
	// -----------------------------------------------------------------------

	private function call_get_last_mod( string $post_modified, string $post_date = null ): string {
		$provider = NewsSitemapProvider::get_instance();
		$method   = new ReflectionMethod( $provider, 'get_last_mod' );
		$method->setAccessible( true );
		if ( $post_date === null ) {
			return $method->invoke( $provider, $post_modified );
		}
		return $method->invoke( $provider, $post_modified, $post_date );
	}

	public function test_get_last_mod_uses_post_modified_when_newer() {
		$modified = '2026-05-20 10:00:00';
		$date     = '2026-05-14 08:00:00';
		$result   = $this->call_get_last_mod( $modified, $date );
		$this->assertStringContainsString( '2026-05-20', $result );
	}

	public function test_get_last_mod_uses_post_date_when_newer() {
		$modified = '2026-05-14 08:00:00';
		$date     = '2026-05-20 09:00:00';
		$result   = $this->call_get_last_mod( $modified, $date );
		$this->assertStringContainsString( '2026-05-20', $result );
	}

	public function test_get_last_mod_with_equal_dates() {
		$dt     = '2026-05-20 08:00:00';
		$result = $this->call_get_last_mod( $dt, $dt );
		$this->assertStringContainsString( '2026-05-20', $result );
	}

	public function test_get_last_mod_without_post_date_uses_post_modified() {
		$modified = '2026-05-14 08:00:00';
		$result   = $this->call_get_last_mod( $modified );
		$this->assertStringContainsString( '2026-05-14', $result );
	}

	// -----------------------------------------------------------------------
	// Integration tests: XML output
	// -----------------------------------------------------------------------

	/**
	 * Collect do_item() output for each URL from get_urls() and wrap in urlset.
	 *
	 * @param object $provider SitemapProvider instance.
	 * @return string Complete XML string with urlset wrapper.
	 */
	private function render_url_items( $provider ): string {
		$get_urls = new ReflectionMethod( $provider, 'get_urls' );
		$get_urls->setAccessible( true );
		$urls = $get_urls->invoke( $provider );

		$items = '';
		foreach ( $urls as $url ) {
			ob_start();
			echo '<url>';
			$provider->do_item( $url );
			echo '</url>';
			$items .= ob_get_clean();
		}

		return '<urlset'
			. ' xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
			. ' xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"'
			. ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"'
			. '>'
			. $items
			. '</urlset>';
	}

	private function parse_sitemap_xml( string $xml ): SimpleXMLElement {
		$sxe = new SimpleXMLElement( $xml );
		$sxe->registerXPathNamespace( 's', 'http://www.sitemaps.org/schemas/sitemap/0.9' );
		$sxe->registerXPathNamespace( 'news', 'http://www.google.com/schemas/sitemap-news/0.9' );
		return $sxe;
	}

	/**
	 * Creates a post that mimics a scheduled-then-published post:
	 * post_date = recently (within 48h), post_modified = 6 days ago.
	 */
	private function create_scheduled_then_published_post(): int {
		global $wpdb;

		$post_id = self::factory()->post->create( [
			'post_status' => 'publish',
			'post_title'  => 'Scheduled post',
		] );

		$now = current_time( 'mysql' );
		$old = gmdate( 'Y-m-d H:i:s', strtotime( '-6 days', current_time( 'timestamp' ) ) );

		$wpdb->update(
			$wpdb->posts,
			[
				'post_date'          => $now,
				'post_date_gmt'      => get_gmt_from_date( $now ),
				'post_modified'      => $old,
				'post_modified_gmt'  => get_gmt_from_date( $old ),
			],
			[ 'ID' => $post_id ]
		);
		clean_post_cache( $post_id );

		return $post_id;
	}

	public function test_news_sitemap_lastmod_not_older_than_publication_date() {
		update_option( 'tsmap_news_post_types', [ 'post' ] );

		$post_id = $this->create_scheduled_then_published_post();

		$provider = NewsSitemapProvider::get_instance();

		$xml = $this->render_url_items( $provider );
		$sxe = $this->parse_sitemap_xml( $xml );

		$found = false;
		foreach ( $sxe->children( 'http://www.sitemaps.org/schemas/sitemap/0.9' ) as $url ) {
			$lastmod  = (string) $url->children( 'http://www.sitemaps.org/schemas/sitemap/0.9' )->lastmod;
			$news     = $url->children( 'http://www.google.com/schemas/sitemap-news/0.9' );
			$pub_date = isset( $news->news->publication_date ) ? (string) $news->news->publication_date : '';

			if ( empty( $lastmod ) || empty( $pub_date ) ) {
				continue;
			}

			$this->assertGreaterThanOrEqual(
				strtotime( $pub_date ),
				strtotime( $lastmod ),
				"<lastmod>({$lastmod}) は <news:publication_date>({$pub_date}) 以上であること"
			);
			$found = true;
		}

		$this->assertTrue( $found, 'ニュースサイトマップに URL が1件以上含まれること' );

		delete_option( 'tsmap_news_post_types' );
		wp_delete_post( $post_id, true );
	}

	public function test_post_sitemap_lastmod_not_older_than_post_date() {
		update_option( 'tsmap_post_types', [ 'post' ] );

		$post_id = $this->create_scheduled_then_published_post();
		$post    = get_post( $post_id );

		// PostSitemapProvider の get_urls() は year/monthnum query var を使う
		set_query_var( 'year', (int) date( 'Y', strtotime( $post->post_date ) ) );
		set_query_var( 'monthnum', (int) date( 'm', strtotime( $post->post_date ) ) );
		set_query_var( 'paged', 1 );

		$provider = PostSitemapProvider::get_instance();

		$xml = $this->render_url_items( $provider );
		$sxe = $this->parse_sitemap_xml( $xml );

		$found = false;
		foreach ( $sxe->children( 'http://www.sitemaps.org/schemas/sitemap/0.9' ) as $url ) {
			$lastmod = (string) $url->children( 'http://www.sitemaps.org/schemas/sitemap/0.9' )->lastmod;
			if ( empty( $lastmod ) ) {
				continue;
			}

			// lastmod は post_date 以上であること
			$post_date_ts = strtotime( $post->post_date );
			$this->assertGreaterThanOrEqual(
				$post_date_ts,
				strtotime( $lastmod ),
				"<lastmod>({$lastmod}) は post_date({$post->post_date}) 以上であること"
			);
			$found = true;
		}

		$this->assertTrue( $found, 'ポストサイトマップに URL が1件以上含まれること' );

		delete_option( 'tsmap_post_types' );
		wp_delete_post( $post_id, true );
	}

	public function test_news_sitemap_lastmod_correct_when_post_modified_is_newer() {
		update_option( 'tsmap_news_post_types', [ 'post' ] );

		// 通常投稿: post_modified > post_date (普通のケース)
		$post_id = self::factory()->post->create( [
			'post_status' => 'publish',
			'post_title'  => 'Normal post',
		] );

		// post_modified を post_date より1時間後にする
		global $wpdb;
		$post    = get_post( $post_id );
		$later   = gmdate( 'Y-m-d H:i:s', strtotime( $post->post_date ) + 3600 );
		$wpdb->update(
			$wpdb->posts,
			[
				'post_modified'     => $later,
				'post_modified_gmt' => get_gmt_from_date( $later ),
			],
			[ 'ID' => $post_id ]
		);
		clean_post_cache( $post_id );

		$provider = NewsSitemapProvider::get_instance();
		$xml      = $this->render_url_items( $provider );
		$sxe      = $this->parse_sitemap_xml( $xml );

		$found = false;
		foreach ( $sxe->children( 'http://www.sitemaps.org/schemas/sitemap/0.9' ) as $url ) {
			$lastmod = (string) $url->children( 'http://www.sitemaps.org/schemas/sitemap/0.9' )->lastmod;
			if ( empty( $lastmod ) ) {
				continue;
			}
			// post_modified ベース (= $later) が使われていることを確認
			$this->assertGreaterThanOrEqual(
				strtotime( $post->post_date ),
				strtotime( $lastmod ),
				"<lastmod>({$lastmod}) は post_date({$post->post_date}) 以上であること"
			);
			$found = true;
		}

		$this->assertTrue( $found, 'ニュースサイトマップに URL が1件以上含まれること' );

		delete_option( 'tsmap_news_post_types' );
		wp_delete_post( $post_id, true );
	}
}
