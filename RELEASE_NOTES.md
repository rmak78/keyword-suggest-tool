Version 2.1 brings the keyword engine into WordPress and OpenCart. Both plugins bundle the same engine adapters as the web tool, run every request from your own server, and need no API keys.

## Downloads

| File | What it is |
|---|---|
| `keyword-suggest-tool-v2.1.0.zip` | Standalone web tool. Upload the contents to any PHP host. |
| `keyword-suggest-wordpress-v2.1.0.zip` | WordPress plugin. Plugins > Add New > Upload Plugin. |
| `keyword_suggest.ocmod.zip` | OpenCart 4.x extension. Extensions > Installer > Upload, then Extensions > Modules > Keyword Suggest > Install. |

## WordPress plugin

- Adds a **Keyword Suggestions** box to posts, pages and WooCommerce products (block editor and classic editor).
- Seeds are extracted automatically from the title and content; edit them freely.
- Click a suggestion to **add it as a tag**, **set it as the focus keyword** (Yoast SEO, Rank Math), **insert it into the content** at the cursor, or copy it. "Add all shown as tags" for bulk.
- Settings > Keyword Suggest: default sources, market, suggestions per source, which post types show the box.
- In the block editor the box lives in the collapsible **Meta Boxes** area at the bottom of the editor.

## OpenCart 4 extension

- Adds a **Keyword suggestions** panel to the General tab of the product form.
- Seeds come from the product name, meta title and description.
- Click a suggestion to add it to **Meta Tag Keywords**, **Product Tags**, or insert it into the **Description** at the cursor; or copy it. Works per language tab.
- Extensions > Modules > Keyword Suggest: status, default sources, market, suggestions per source.
- Tested on OpenCart 4.1.0.4.

## Web tool

- No functional change since 2.0.0. The engine adapters are now namespaced (`KeywordSuggest\…`) so they can be bundled inside other software.

## Requirements

PHP 8.0+ with the curl extension. WordPress 6.0+. OpenCart 4.0.2+.

Suggestions come from each engine's public autocomplete and are not affiliated with or endorsed by those companies.
