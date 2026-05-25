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

	private function call_get_last_mod( string $post_modified, ?string $post_date = null ): string {
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

		$tz  = wp_timezone();
		$now = ( new DateTimeImmutable( 'now', $tz ) )->format( 'Y-m-d H:i:s' );
		$old = ( new DateTimeImmutable( '-6 days', $tz ) )->format( 'Y-m-d H:i:s' );

		$updated = $wpdb->update(
			$wpdb->posts,
			[
				'post_date'          => $now,
				'post_date_gmt'      => get_gmt_from_date( $now ),
				'post_modified'      => $old,
				'post_modified_gmt'  => get_gmt_from_date( $old ),
			],
			[ 'ID' => $post_id ]
		);
		$this->assertSame( 1, $updated, 'failed to seed scheduled-then-published post' );
		clean_post_cache( $post_id );

		return $post_id;
	}

	public function test_news_sitemap_lastmod_not_older_than_publication_date() {
		update_option( 'tsmap_news_post_types', [ 'post' ] );

		$post_id   = $this->create_scheduled_then_published_post();
		$permalink = get_permalink( $post_id );

		$provider = NewsSitemapProvider::get_instance();

		$xml = $this->render_url_items( $provider );
		$sxe = $this->parse_sitemap_xml( $xml );

		$found = false;
		foreach ( $sxe->children( 'http://www.sitemaps.org/schemas/sitemap/0.9' ) as $url ) {
			$loc = (string) $url->children( 'http://www.sitemaps.org/schemas/sitemap/0.9' )->loc;
			if ( $loc !== $permalink ) {
				continue;
			}

			$lastmod  = (string) $url->children( 'http://www.sitemaps.org/schemas/sitemap/0.9' )->lastmod;
			$news     = $url->children( 'http://www.google.com/schemas/sitemap-news/0.9' );
			$pub_date = isset( $news->news->publication_date ) ? (string) $news->news->publication_date : '';

			$this->assertNotEmpty( $lastmod, '<lastmod> が出力されていること' );
			$this->assertNotEmpty( $pub_date, '<news:publication_date> が出力されていること' );

			$pub_ts     = ( new DateTimeImmutable( $pub_date, wp_timezone() ) )->getTimestamp();
			$lastmod_ts = ( new DateTimeImmutable( $lastmod ) )->getTimestamp();
			$this->assertGreaterThanOrEqual(
				$pub_ts,
				$lastmod_ts,
				"<lastmod>({$lastmod}) は <news:publication_date>({$pub_date}) 以上であること"
			);
			$found = true;
		}

		$this->assertTrue( $found, "ニュースサイトマップに対象投稿({$permalink})が含まれること" );

		delete_option( 'tsmap_news_post_types' );
		wp_delete_post( $post_id, true );
	}

	public function test_post_sitemap_lastmod_not_older_than_post_date() {
		update_option( 'tsmap_post_types', [ 'post' ] );

		$post_id   = $this->create_scheduled_then_published_post();
		$post      = get_post( $post_id );
		$permalink = get_permalink( $post_id );

		// PostSitemapProvider の get_urls() は year/monthnum query var を使う
		$post_date_local = new DateTimeImmutable( $post->post_date, wp_timezone() );
		set_query_var( 'year', (int) $post_date_local->format( 'Y' ) );
		set_query_var( 'monthnum', (int) $post_date_local->format( 'm' ) );
		set_query_var( 'paged', 1 );

		$provider = PostSitemapProvider::get_instance();

		$xml = $this->render_url_items( $provider );
		$sxe = $this->parse_sitemap_xml( $xml );

		$found = false;
		foreach ( $sxe->children( 'http://www.sitemaps.org/schemas/sitemap/0.9' ) as $url ) {
			$loc = (string) $url->children( 'http://www.sitemaps.org/schemas/sitemap/0.9' )->loc;
			if ( $loc !== $permalink ) {
				continue;
			}

			$lastmod = (string) $url->children( 'http://www.sitemaps.org/schemas/sitemap/0.9' )->lastmod;
			$this->assertNotEmpty( $lastmod, '<lastmod> が出力されていること' );

			// lastmod は post_date 以上であること
			$post_date_ts = $post_date_local->getTimestamp();
			$lastmod_ts   = ( new DateTimeImmutable( $lastmod ) )->getTimestamp();
			$this->assertGreaterThanOrEqual(
				$post_date_ts,
				$lastmod_ts,
				"<lastmod>({$lastmod}) は post_date({$post->post_date}) 以上であること"
			);
			$found = true;
		}

		$this->assertTrue( $found, "ポストサイトマップに対象投稿({$permalink})が含まれること" );

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
		$later   = ( new DateTimeImmutable( $post->post_date, wp_timezone() ) )
			->modify( '+1 hour' )->format( 'Y-m-d H:i:s' );
		$updated = $wpdb->update(
			$wpdb->posts,
			[
				'post_modified'     => $later,
				'post_modified_gmt' => get_gmt_from_date( $later ),
			],
			[ 'ID' => $post_id ]
		);
		$this->assertSame( 1, $updated, 'failed to update post_modified' );
		clean_post_cache( $post_id );

		$permalink = get_permalink( $post_id );

		$provider = NewsSitemapProvider::get_instance();
		$xml      = $this->render_url_items( $provider );
		$sxe      = $this->parse_sitemap_xml( $xml );

		$found = false;
		foreach ( $sxe->children( 'http://www.sitemaps.org/schemas/sitemap/0.9' ) as $url ) {
			$loc = (string) $url->children( 'http://www.sitemaps.org/schemas/sitemap/0.9' )->loc;
			if ( $loc !== $permalink ) {
				continue;
			}

			$lastmod = (string) $url->children( 'http://www.sitemaps.org/schemas/sitemap/0.9' )->lastmod;
			$this->assertNotEmpty( $lastmod, '<lastmod> が出力されていること' );

			// post_modified ベース (= $later) が使われていることを確認
			$later_ts   = ( new DateTimeImmutable( $later, wp_timezone() ) )->getTimestamp();
			$lastmod_ts = ( new DateTimeImmutable( $lastmod ) )->getTimestamp();
			$this->assertSame(
				$later_ts,
				$lastmod_ts,
				"<lastmod>({$lastmod}) は post_modified ({$later}) に一致すること"
			);
			$found = true;
		}

		$this->assertTrue( $found, "ニュースサイトマップに対象投稿({$permalink})が含まれること" );

		delete_option( 'tsmap_news_post_types' );
		wp_delete_post( $post_id, true );
	}
}
