# Tarosky Sitemap

Contributors: tarosky, Takahashi_Fumiki, tswallie  
Tags: sitemap,google,news  
Tested up to: 7.1  
Requires PHP: 8.1  
Stable Tag: nightly

Sitemap plugin.

## Description

This plugin provides sitemap xml. Sitemaps below are available.

1. Post type sitemap.
2. Taxonomy sitemap.
3. Google News sitemap([detail](https://developers.google.com/search/docs/advanced/sitemaps/news-sitemap))
4. Image sitemap(optional for sites with a large size of contents)

The remarkable features of this plugin are:

- Every type of sitemaps is a independent sitemap index.
- Sitemap are separated by **YEAR-MONTH**. This leverages MySQL index and reduce database cpu utilization under bot access. So effective for the sites with huge number of contents.
- Atatchment site map is also effective for the sites with a lot of attachment files.

### Setup

1. Go to <code>Admin screen -> Tools -> Sitemap</code> and register site map which you want.
2. Get sitemap URLs and register them at Google Search Console.

### Customize

Besides setting on admin screen, some hooks are also available.

#### News Sitemap

By default, news sitemap consists of the posts under specified post type(e.g. post). If you need some condition(e.g. only posts under a category, excluding some tags), use filter hook below:

```
/**
 * Filter hook for WP_Query arguments for news sitemap.
 *
 * @param array $query_args Which passed to WP_Query
 * @return array
 */
add_filter( 'hms_news_sitemap_query_args', function( $query_args ) {
	// 1. Includes only posts under category "news"
	// 2. Exclude posts with tag "sponsored"
	$query_args['tax_query'] = [
		[
			[
				'taxonomy' => 'category',
				'field'    => 'slug',
				'terms'    => 'news',
			],
			[
				'taxonomy' => 'post_tag',
				'field'    => 'slug',
				'terms'    => 'sponsored',
				'operator' => 'NOT IN',
			],
		],
	];
} );
```

#### Organization (Structured Data)

Go to <code>Admin screen -> Tools -> Sitemap & SEO -> Structured Data</code> and enable "Organization" to output Organization JSON-LD on the front page.

- Name, URL and logo are shared with the publisher settings. The logo falls back to the site logo, then the site icon.
- `@id` is generated from the publisher URL (e.g. `https://example.com/#organization`). Sites pointing to the same URL refer to the same organization.
- For a publishing company with multiple media sites, set "NewsMediaOrganization" on each media site and enter the company site's publisher URL as "Parent Organization URL". The media sites and the company are linked via `parentOrganization`.

```
/**
 * Filters the Organization structured data for the front page.
 *
 * @param array $json Organization structured data.
 * @return array
 */
add_filter( 'tsmap_json_ld_organization_structure', function( $json ) {
	$json['alternateName'] = 'Example';
	return $json;
} );
```

#### HTTP Cache (ETag)

Googlebot caches pages with `ETag` and sends `If-None-Match` when recrawling. If the ETag matches, the page body need not be transferred again ([detail](https://developers.google.com/search/blog/2024/12/crawling-december-caching)).

Settings are in <code>Admin screen -> Tools -> Sitemap & SEO -> HTTP Cache</code>.

- **ETag**: Send `ETag` header on singular pages. The body is always returned. If the site is behind a CDN like CloudFront, the CDN responds `304` to crawlers.
- **304 Not Modified**: Respond `304` without body if `If-None-Match` matches. For sites without CDN only. Behind CloudFront, the CDN revalidates expired cache with `If-None-Match` and keeps the stale page as long as the origin returns `304`.

Logged-in users, previews and password protected posts are excluded. The ETag is weak and consists of post ID, modified date and comment count. If the page HTML depends on other things, add them to the ETag or disable it per post.

```
// Add theme version to ETag.
add_filter( 'tsmap_etag_components', function( $components, $post ) {
	$components[] = wp_get_theme()->get( 'Version' );
	return $components;
}, 10, 2 );

// Disable for specific posts.
add_filter( 'tsmap_etag_enabled', function( $enabled, $post ) {
	return 'page' !== $post->post_type;
}, 10, 2 );
```

## Installation

- Download zip and unpack it.
- Upload under wp-content/plugins
- Go to admin screen and activate it.

## Frequently Asked Questions

Feel free to contact us in [GitHub](https://github.com/tarosky/tarosky-sitemap). 

## Changelog

### 3.0.0

* **Breaking:** Requires PHP 8.1 or later.
* Add Organization structured data (JSON-LD) on the front page with description, sameAs, address and parent organization.
* Add organization type setting (Organization / NewsMediaOrganization / Corporation).
* Add `@type` to the publisher of Article and refer to the Organization by `@id`.
* Publisher logo now falls back to the site logo before the site icon.
* Add filter `tsmap_json_ld_organization_structure`.
* Add option to enable attachment pages.
* Add options to send ETag header and respond 304 to conditional requests.
* Fix typo in the setting section name.

### 2.4.0

* Bump PHP minimum version.
* Add filter for 

### 2.0.1

* Bugfix of removing language attributes.

### 2.0.0

* Add noindex setting.
* Add OGP setting.
* Add meta setting.

### 1.0.0

* Initial release.
