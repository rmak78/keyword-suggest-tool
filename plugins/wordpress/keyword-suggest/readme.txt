=== Keyword Suggest ===
Contributors: bixisoft
Tags: keywords, seo, tags, autocomplete, woocommerce
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 2.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Keyword ideas from Google, YouTube, Bing, Amazon, eBay and more, right in the editor. Click a suggestion to add it as a tag, set the SEO focus keyword, or insert it.

== Description ==

Keyword Suggest adds a **Keyword Suggestions** box to the post, page and product editor.

* Seeds are proposed automatically from your title and content; edit them freely.
* Suggestions come from the public autocomplete of Google, Bing, Yahoo, DuckDuckGo, Qwant, Yandex, YouTube, Amazon, eBay, Wikipedia and the Apple App Store. No API keys, no accounts.
* Each suggestion shows which sources returned it.
* Click a suggestion to **add it as a tag** (posts and WooCommerce products), **set it as the focus keyword** (Yoast SEO or Rank Math), **insert it into the content** at the cursor, or copy it.
* Works with the block editor and the classic editor.
* eBay also returns matching categories.

Requests are made by your own server (PHP curl), so nothing is sent to a third-party service other than the search engines themselves.

The same engine powers the standalone web tool: https://github.com/rmak78/keyword-suggest-tool

== Installation ==

1. Upload the `keyword-suggest` folder to `/wp-content/plugins/`, or install the zip from Plugins > Add New > Upload.
2. Activate the plugin.
3. Optionally pick default sources and market under Settings > Keyword Suggest.
4. Open any post or product: the Keyword Suggestions box appears below the editor. In the block editor it sits in the **Meta Boxes** area at the bottom; click that bar to expand it.

== Frequently Asked Questions ==

= Does it need an API key? =

No. It uses each engine's public autocomplete endpoint.

= A source shows an error =

Autocomplete endpoints are unofficial and change occasionally. Each one is a small adapter file under `includes/api/engines/`. Updates ship with new plugin versions.

= Which SEO plugins are supported for the focus keyword? =

Yoast SEO and Rank Math. With other SEO plugins the keyword is copied to the clipboard instead.

== Changelog ==

= 2.0.0 =
* First WordPress release, sharing the engine adapters with Keyword Suggest Tool v2.
