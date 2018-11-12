=== RediSearch ===
Contributors: foadyousefi
Author URI: https://7km.co
Plugin URI: https://github.com/7kmCo/wp-redisearch
Tags: search, redisearch, redis, fuzzy, aggregation, searching, autosuggest, suggest, advanced search, woocommerce
Requires at least: 4.6
Tested up to: 5
Stable tag: 0.2.1
Requires PHP: 5.2.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Flexible search engine for WordPress with very high performance.

== Description ==

Redisearch implements a search engine on top of Redis. It has lots of advanced features, like exact phrase matching and numeric filtering for text queries, that are nearly not possible or inefficient with mysql search queries.

Here you find a list of RediSearch features included in the plugin:

__Search__: Instantly find the content you’re looking for. The first time.

__Scoring fields differently__: Give different score to different fields. For example higher score to product name and number than its description.

__Fuzzy Search__: Don't worry about visitors misspelling.

__Autosuggest__: Adds a suggestion string to an auto-complete suggestion dictionary.

__Synonyms__: RediSearch supports synonyms, that is searching for synonyms words defined by the synonym data structure.

And even more features will be added in upcoming versions soon.

Some planned features are:

*   Binary documents indexing: Searching through binary files like pdf, word, powerpoint and ...
*   Advanced search: Adding advanced search functionality.

== Installation ==
1. First, you will need to properly [install and configure](https://redis.io/topics/quickstart) Redis and [RediSearch](https://oss.redislabs.com/redisearch/Quick_Start/).
2. Activate the plugin in WordPress.
3. In the RediSearch settings page, input your Redis host and port and do the configuration.
4. In RediSearch dashboard page, click on Index button.
5. Let you visitors enjoy.


== Frequently Asked Questions ==

= What is wrong with WordPress native search? =

Although mySql is a great database to storing relational data, It acts very poor on search queries and you must forget about some features like fuzzy matching and synonyms.

= How Redisearch is compared to ElasticSearch? =

Yes, ElasticSearch is a great search engine and it has very good performance compared to mySql. But RediSearch has almost 5 to 10 times better performance and also its way easier to create index, sync your data and send query requests.

== Changelog ==

= 0.2.1 =
* Added: WooCommerce support added as Feature
* Fixed: Return option values if empty string stores in database
* Fixed: Fix incorrect link to settings page
* Fixed: Fix harcoded index name in WP-CLI INFO command
* Added: filter hook 'wp_redisearch_indexable_temrs' to manipulate indexable terms list
* Added: filter hook 'wp_redisearch_indexable_post_types' to manipulate indexable post types

= 0.2.0 =
* Added: WP-CLI support
* Added: Register and activating of Features
* Added: filter hook 'wp_redisearch_indexable_meta_keys' to add extra meta keys to the index
* Added: filter hook 'wp_redisearch_indexable_meta_schema' to manipulate type of post meta fields (default is text)
* Added: action hook 'wp_redisearch_after_post_indexed' fires after posts indexed from the main index command
* Added: action hook 'wp_redisearch_after_post_published' fires after a post have been published
* Added: action hook 'wp_redisearch_after_post_deleted' fires after a post have been deleted
* Added: action hook 'wp_redisearch_after_index_created' fires after main index created
* Added: action hook 'wp_redisearch_settings_indexing_fields' fires after settings fields inside indexing options page
* Fixed: Fix indexing posts on publish/update

= 0.1.1 =
* Use default value for settings if not set in settings

= 0.1.0 =
* Initial plugin
