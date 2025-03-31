# Tarosky Sitemap

Contributors: tarosky, Takahashi_Fumiki
Tags: sitemap,google,news  
Tested up to: 6.7  
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

#### Newws Sitemap

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

## Installation

- Download zip and unpack it.
- Upload under wp-content/plugins
- Go to admin screen and activate it.

## Frequently Asked Questions

Feel free to contact us in [GitHub](https://github.com/tarosky/tarosky-sitemap). 

## Changelog

### 2.0.1

* Bugfix of removing language attributes.

### 2.0.0

* Add noindex setting.
* Add OGP setting.
* Add meta setting.

### 1.0.0

* Initial release.
