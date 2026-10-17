# Directory submission kit

Everything needed to list Keyword Suggest on wordpress.org and the OpenCart Marketplace. Artwork lives in `plugins/wordpress/assets/` and `plugins/opencart/assets/`; regenerate screenshots from a local install if the UI changes.

---

## 1. WordPress.org plugin directory

Submit at https://wordpress.org/plugins/developers/add/ (one-time review, then SVN access).

### Listing fields

| Field | Value |
|---|---|
| Plugin name | Keyword Suggest |
| Slug (requested) | `keyword-suggest` |
| Short description (≤150 chars) | Keyword ideas from Google, YouTube, Bing, Amazon, eBay & more, right in the editor. Click to add as tag, focus keyword, or into content. |
| Tags (max 5) | keywords, seo, tags, autocomplete, woocommerce |
| Category hints | SEO, Editor, WooCommerce |
| Requires WP / PHP | 6.0 / 8.0 |
| Tested up to | 7.1 |
| License | GPLv2 or later |
| Contributors | bixisoft |
| Donate link | https://github.com/sponsors/rmak78 (optional) |

### Assets to upload to SVN `assets/`

| File | Spec | Status |
|---|---|---|
| `icon-128x128.png`, `icon-256x256.png` | PNG, square | ✅ `plugins/wordpress/assets/` |
| `banner-772x250.png`, `banner-1544x500.png` | PNG | ✅ |
| `screenshot-1.png` | Editor with suggestions | ✅ (1280×900) |
| `screenshot-2.png` | Settings page | ✅ (1280×800) |

Screenshot captions (must match the `== Screenshots ==` section in `readme.txt`):

1. The Keyword Suggestions box in the block editor: seeds from your content, live suggestions with source dots, one click to add as a tag.
2. Settings: pick default sources and market, test every source from your server, control how many suggestions to fetch.

### Full description (used in `readme.txt`, Markdown subset)

> **Real keyword ideas, where you write.**
>
> Keyword Suggest adds a *Keyword Suggestions* box to the post, page and WooCommerce product editor. It reads your title and content, proposes seed keywords, and pulls live autocomplete suggestions from the sources you choose. Each suggestion shows which engines returned it, so you can see at a glance which phrases people actually type.
>
> **Click to add.** One click adds a suggestion as a tag, sets it as the Yoast SEO or Rank Math focus keyword, inserts it into the content at the cursor, or copies it. "Add all shown as tags" handles bulk tagging.
>
> **11 sources, no API keys.** Google, Bing, Yahoo, DuckDuckGo, Qwant, Yandex, YouTube, Amazon, eBay, Wikipedia and the Apple App Store. Pick a market (12 locales) and the right Amazon or eBay marketplace is used automatically.
>
> **Private by design.** Requests go from your server straight to the search engines. No third-party service, no account, nothing to pay for.
>
> **Built for both editors.** Works in the block editor and the classic editor, on any public post type you enable.
>
> The same engine powers the free [Keyword Suggest Tool](https://github.com/rmak78/keyword-suggest-tool) web app and the OpenCart extension.

### Review checklist (wordpress.org guidelines)

- [x] All output escaped (`esc_html`, `esc_attr`, `esc_url`), all input sanitized, nonces + capability checks on AJAX.
- [x] No external JS/CSS loaded; no tracking; no phone-home.
- [x] Prefix `ksug_` on functions, `keyword_suggest_` on options, `KSUG_` on constants; shared library namespaced `KeywordSuggest\`.
- [x] `uninstall.php` removes the option.
- [x] GPL-compatible licence, readme.txt with Stable tag matching the plugin header.
- [x] Human-readable (unminified) JS and CSS.
- [x] Plugin Check (wordpress.org's reviewer tool) passes with 0 errors / 0 warnings. Re-run after changes: install the *Plugin Check* plugin locally and run `wp plugin check keyword-suggest`.
- [ ] After approval: `svn co https://plugins.svn.wordpress.org/keyword-suggest`, copy the plugin folder to `trunk/`, assets to `assets/`, tag `2.1.0`, commit.

---

## 2. OpenCart Marketplace

Submit at https://www.opencart.com/index.php?route=marketplace/extension/add (requires a marketplace seller account; free extensions are allowed).

### Listing fields

| Field | Value |
|---|---|
| Extension name | Keyword Suggest – keyword ideas on the product form |
| Category | Marketing / SEO |
| Price | Free |
| License | GPLv3 (or the licence in `LICENSE.md`) |
| Compatibility | 4.0.2.x, 4.1.0.x (tested 4.1.0.4) |
| Support | https://github.com/rmak78/keyword-suggest-tool/issues |
| Documentation | https://github.com/rmak78/keyword-suggest-tool#readme |
| Download file | `keyword_suggest.ocmod.zip` (from `npm run build:plugins` or the GitHub release) |

### Images

| File | Use |
|---|---|
| `plugins/opencart/assets/icon-100x100.png` | Extension icon |
| `plugins/opencart/assets/feature-1200x800.png` | Main listing image |
| `plugins/opencart/assets/screenshot-1.png` | Product form with suggestions |
| `plugins/opencart/assets/screenshot-2.png` | Module settings with source test |

### Short description (≤255 chars)

Real keyword ideas on the product form. Seeds from your product name and description, live autocomplete from Google, Bing, Amazon, eBay and more. Click to add to meta keywords, tags or the description. No API keys.

### Full description

**Stop guessing what shoppers type.**

Keyword Suggest adds a *Keyword suggestions* panel to the General tab of every product form. It reads the product name and description, proposes seed keywords, and fetches live autocomplete suggestions from the sources you pick. Every suggestion shows which engines returned it.

**One click to use it**

- Add to **Meta Tag Keywords**
- Add to **Product Tags**
- Insert into the **Description** at the cursor (CKEditor aware)
- Copy to clipboard
- "Add all shown" for bulk

Works per language tab, so multilingual stores get keywords for each language.

**11 sources, no API keys**

Google, Bing, Yahoo, DuckDuckGo, Qwant, Yandex, YouTube, Amazon, eBay (with matching eBay categories), Wikipedia, Apple App Store. Choose a market and the matching Amazon / eBay marketplace is used.

**Private**

Requests go from your server straight to the engines. No third-party service, no account, nothing to pay.

**Settings** (Extensions > Modules > Keyword Suggest): enable/disable, default sources, default market, suggestions per source, and a one-click *Test sources from this server* that shows which engines respond on your hosting.

**Installation**

1. Extensions > Installer > Upload `keyword_suggest.ocmod.zip`, then click the green Install button.
2. Extensions > Extensions > choose *Modules* > Keyword Suggest > Install (green +), then Edit to review settings.
3. Open any product: the panel is at the bottom of the General tab.

Requires PHP 8.0+ with curl. Suggestions come from each engine's public autocomplete and are not affiliated with or endorsed by those companies.

### Changelog

- 2.1.0: first release.

---

## 3. Regenerating the artwork

```bash
# icon, banners, feature image: docs/artwork.html rendered with headless Chromium
npx -y -p playwright@1.49.1 -c 'npx playwright install chromium && node scripts/render-artwork.mjs'
```

The design source is `docs/artwork.html` (plain HTML/CSS). Python 3 with Pillow is used for the downscaled icon sizes. Screenshots are taken by hand from a local WordPress / OpenCart install at 1280 px wide (settings page at 1280×800, editor at 1280×900).
