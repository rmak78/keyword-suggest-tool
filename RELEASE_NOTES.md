Complete rebuild of the Keyword Suggest Tool. Version 1 stopped working because its asset files were never shipped and the search engines changed their autocomplete endpoints. Version 2 is a new TypeScript + Tailwind CSS front end with a small PHP proxy, so every engine is called server-side and no API keys are needed.

## Install (shared hosting: cPanel, Plesk, FastPanel…)

1. Download `keyword-suggest-tool-v2.0.0.zip` below.
2. Unzip and upload its contents to the folder you want, for example `public_html/keyword-suggest/`.
3. Open the page. The sources panel shows a green dot for every engine that responds from your server.

Requirements: PHP 8.0+ with the curl extension. Nothing to configure.

## What's new

**11 sources**: Google, YouTube, Bing, Yahoo, DuckDuckGo, Qwant, Yandex, Amazon (12 marketplaces), eBay (10 sites, with matching categories), Wikipedia, Apple App Store.

**Search**
- Query any number of engines at once; every keyword is tagged with the sources it came from
- Seed expansion: append a–z, 0–9, or prepend question and modifier words (how, best, vs, near me…)
- Follow suggestions of suggestions up to two levels deep, with a result cap and per-engine delay
- Language / market selector that also picks the right Amazon and eBay marketplace
- Pre-fill seeds from the URL: `?keyword=running+shoes`

**Results**
- Live filter, filter by source, sort by order found / A–Z / most sources / depth
- Copy to clipboard, download as CSV (opens cleanly in Excel) or TXT

**UI**
- Modern responsive design, light / dark / auto theme
- Keyboard shortcuts: Ctrl+Enter to start, Esc to stop
- Built-in engine status check so you can see which sources work from your host

**Under the hood**
- TypeScript, Vite 7, Tailwind CSS 4, no runtime JavaScript dependencies
- One small PHP adapter per engine under `api/engines/`, so a changed endpoint is a one-file fix
- Unit tests and a GitHub Actions workflow that builds the deployable `dist/` on every push

Suggestions come from each engine's public autocomplete and are not affiliated with or endorsed by those companies.
