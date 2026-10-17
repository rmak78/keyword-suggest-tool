=== Keyword Suggest ===
Contributors: bixisoft
Tags: keywords, seo, tags, autocomplete, woocommerce
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 2.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Keyword ideas from Google, YouTube, Bing, Amazon, eBay & more, right in the editor. Click to add as tag, focus keyword, or into content.

== Description ==

**Real keyword ideas, where you write.**

Keyword Suggest adds a *Keyword Suggestions* box to the post, page and WooCommerce product editor. It reads your title and content, proposes seed keywords, and pulls live autocomplete suggestions from the sources you choose. Each suggestion shows which engines returned it, so you can see at a glance which phrases people actually type.

**Click to add.** One click adds a suggestion as a tag, sets it as the Yoast SEO or Rank Math focus keyword, inserts it into the content at the cursor, or copies it. *Add all shown as tags* handles bulk tagging.

**11 sources, no API keys.** Google, Bing, Yahoo, DuckDuckGo, Qwant, Yandex, YouTube, Amazon, eBay (with matching eBay categories), Wikipedia and the Apple App Store. Pick a market (12 locales) and the right Amazon or eBay marketplace is used automatically.

**Private by design.** Requests go from your server straight to the search engines. No third-party service, no account, nothing to pay for, nothing tracked.

**Built for both editors.** Works in the block editor and the classic editor, on any public post type you enable.

**Settings that explain themselves.** Choose default sources and market, set how many suggestions to fetch per source, pick the post types, and run a one-click *Test sources from this server* to see which engines respond on your hosting.

The same engine powers the free [Keyword Suggest Tool](https://github.com/rmak78/keyword-suggest-tool) web app and the OpenCart extension. Source code and issue tracker are on GitHub.

== Installation ==

1. Upload the `keyword-suggest` folder to `/wp-content/plugins/`, or install the zip from Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Optionally pick default sources and market under Settings > Keyword Suggest.
4. Open any post or product: the Keyword Suggestions box appears below the editor. In the block editor it sits in the **Meta Boxes** area at the bottom; click that bar to expand it.

== Frequently Asked Questions ==

= Does it need an API key or an account? =

No. It uses each engine's public autocomplete endpoint, called from your own server.

= Where do the suggestions come from? =

From the same autocomplete that appears when you type into Google, YouTube, Bing, Yahoo, DuckDuckGo, Qwant, Yandex, Amazon, eBay, Wikipedia or the App Store search box. Only the sources you tick are queried.

= Which SEO plugins are supported for the focus keyword? =

Yoast SEO and Rank Math. With other SEO plugins the keyword is copied to the clipboard instead, with a notice.

= A source shows an error in the test =

Autocomplete endpoints are unofficial and some hosts block outbound requests to certain domains. The settings page test shows the exact error per source. Each source is a small adapter file under `includes/api/engines/`; fixes ship with plugin updates.

= Does it send my content anywhere? =

Only the seed keywords you fetch suggestions for are sent, to the engines you selected. Nothing goes to the plugin author or any third-party service.

== Screenshots ==

1. The Keyword Suggestions box in the block editor: seeds from your content, live suggestions with source dots, one click to add as a tag.
2. Settings: pick default sources and market, test every source from your server, control how many suggestions to fetch.

== Changelog ==

= 2.1.0 =
* First WordPress release, sharing the engine adapters with Keyword Suggest Tool v2.
* Settings page with grouped source tiles and a live source test.

== Upgrade Notice ==

= 2.1.0 =
First release.
