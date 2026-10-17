<?php
/**
 * Plugin Name: Keyword Suggest
 * Plugin URI:  https://github.com/rmak78/keyword-suggest-tool
 * Description: Suggests keywords for your posts and products from Google, YouTube, Bing, Amazon, eBay and other autocomplete sources based on what you are writing. Click a suggestion to add it as a tag, set it as the SEO focus keyword, or insert it into the content.
 * Version:     2.1.0
 * Author:      Bixisoft
 * Author URI:  https://bixisoft.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: keyword-suggest
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('KSUG_VERSION', '2.1.0');
define('KSUG_FILE', __FILE__);
define('KSUG_DIR', plugin_dir_path(__FILE__));
define('KSUG_URL', plugin_dir_url(__FILE__));
define('KSUG_OPTION', 'keyword_suggest_settings');

require_once KSUG_DIR . 'includes/api/lib.php';
require_once KSUG_DIR . 'includes/api/keyphrases.php';

/**
 * Engine catalogue shown in the UI. Ids match includes/api/engines/registry.php.
 *
 * @return array<string, array{label: string, group: string}>
 */
function ksug_engines(): array
{
    return [
        'google' => ['label' => 'Google', 'group' => 'Search'],
        'bing' => ['label' => 'Bing', 'group' => 'Search'],
        'yahoo' => ['label' => 'Yahoo', 'group' => 'Search'],
        'duckduckgo' => ['label' => 'DuckDuckGo', 'group' => 'Search'],
        'qwant' => ['label' => 'Qwant', 'group' => 'Search'],
        'yandex' => ['label' => 'Yandex', 'group' => 'Search'],
        'youtube' => ['label' => 'YouTube', 'group' => 'Video'],
        'amazon' => ['label' => 'Amazon', 'group' => 'Shopping'],
        'ebay' => ['label' => 'eBay', 'group' => 'Shopping'],
        'wikipedia' => ['label' => 'Wikipedia', 'group' => 'Reference'],
        'appstore' => ['label' => 'App Store', 'group' => 'Apps'],
    ];
}

/** @return array{engines: string[], locale: string, limit: int, post_types: string[]} */
function ksug_settings(): array
{
    $defaults = [
        'engines' => ['google', 'bing', 'youtube', 'amazon'],
        'locale' => 'en-US',
        'limit' => 10,
        'post_types' => ['post', 'page', 'product'],
    ];
    $saved = get_option(KSUG_OPTION, []);
    $s = is_array($saved) ? array_merge($defaults, $saved) : $defaults;
    $s['engines'] = array_values(array_intersect((array) $s['engines'], array_keys(ksug_engines())));
    $s['locale'] = isset(\KeywordSuggest\KS_LOCALES[$s['locale']]) ? $s['locale'] : 'en-US';
    $s['limit'] = max(3, min(25, (int) $s['limit']));
    $s['post_types'] = array_values(array_filter((array) $s['post_types'], 'is_string'));
    return $s;
}

// --- Admin UI: meta box -----------------------------------------------------

add_action('add_meta_boxes', static function (string $post_type): void {
    if (!in_array($post_type, ksug_settings()['post_types'], true)) {
        return;
    }
    add_meta_box(
        'keyword-suggest',
        __('Keyword Suggestions', 'keyword-suggest'),
        'ksug_render_meta_box',
        $post_type,
        'normal',
        'high'
    );
});

function ksug_render_meta_box(WP_Post $post): void
{
    echo '<div id="ksug-app" class="ksug" data-post-id="' . (int) $post->ID . '">';
    echo '<noscript>' . esc_html__('Keyword Suggest needs JavaScript.', 'keyword-suggest') . '</noscript>';
    echo '</div>';
}

add_action('admin_enqueue_scripts', static function (string $hook): void {
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }
    $screen = get_current_screen();
    if (!$screen || !in_array($screen->post_type, ksug_settings()['post_types'], true)) {
        return;
    }

    wp_enqueue_style('keyword-suggest', KSUG_URL . 'admin/css/keyword-suggest.css', [], KSUG_VERSION);
    wp_enqueue_script('keyword-suggest', KSUG_URL . 'admin/js/keyword-suggest.js', ['jquery', 'wp-api-fetch'], KSUG_VERSION, true);

    // Non-hierarchical taxonomy used for "Add as tag" (post_tag, product_tag, ...).
    $tagTax = null;
    foreach (get_object_taxonomies($screen->post_type, 'objects') as $tax) {
        if (!$tax->hierarchical && $tax->show_ui && $tax->public) {
            $tagTax = $tax;
            if ($tax->name === 'post_tag' || $tax->name === 'product_tag') {
                break;
            }
        }
    }

    $settings = ksug_settings();
    wp_localize_script('keyword-suggest', 'KSUG', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ksug'),
        'engines' => ksug_engines(),
        'defaultEngines' => $settings['engines'],
        'locale' => $settings['locale'],
        'locales' => array_keys(\KeywordSuggest\KS_LOCALES),
        'tagTaxonomy' => $tagTax ? $tagTax->name : null,
        'tagRestBase' => $tagTax ? ($tagTax->rest_base ?: $tagTax->name) : null,
        'seo' => [
            'yoast' => defined('WPSEO_VERSION'),
            'rankmath' => class_exists('RankMath') || defined('RANK_MATH_VERSION'),
            'aioseo' => function_exists('aioseo') || defined('AIOSEO_VERSION'),
        ],
        'i18n' => [
            'seeds' => __('Seed keywords', 'keyword-suggest'),
            'seedsHelp' => __('Comma separated. Filled from your title and content; edit freely.', 'keyword-suggest'),
            'fromContent' => __('Suggest seeds from content', 'keyword-suggest'),
            'sources' => __('Sources', 'keyword-suggest'),
            'locale' => __('Market', 'keyword-suggest'),
            'onClick' => __('When I click a suggestion', 'keyword-suggest'),
            'actTag' => __('Add as tag', 'keyword-suggest'),
            'actFocus' => __('Set as SEO focus keyword', 'keyword-suggest'),
            'actInsert' => __('Insert into content at cursor', 'keyword-suggest'),
            'actCopy' => __('Copy to clipboard', 'keyword-suggest'),
            'get' => __('Get suggestions', 'keyword-suggest'),
            'loading' => __('Fetching suggestions…', 'keyword-suggest'),
            'filter' => __('Filter…', 'keyword-suggest'),
            'none' => __('No suggestions yet. Add a seed keyword and click "Get suggestions".', 'keyword-suggest'),
            'empty' => __('No results. Try different seeds or sources.', 'keyword-suggest'),
            'added' => __('Added', 'keyword-suggest'),
            'copied' => __('Copied', 'keyword-suggest'),
            'noSeeds' => __('Enter at least one seed keyword.', 'keyword-suggest'),
            'noEngines' => __('Select at least one source.', 'keyword-suggest'),
            'noTagTax' => __('This post type has no tags. Choose another action.', 'keyword-suggest'),
            'noSeo' => __('No supported SEO plugin (Yoast, Rank Math) detected; keyword copied instead.', 'keyword-suggest'),
            'error' => __('Request failed', 'keyword-suggest'),
            'categories' => __('eBay categories', 'keyword-suggest'),
            'addAll' => __('Add all shown as tags', 'keyword-suggest'),
        ],
    ]);
});

// --- AJAX -------------------------------------------------------------------

function ksug_check_request(): void
{
    check_ajax_referer('ksug', 'nonce');
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Forbidden'], 403);
    }
}

add_action('wp_ajax_ksug_seeds', static function (): void {
    ksug_check_request();
    $title = isset($_POST['title']) ? wp_unslash((string) $_POST['title']) : '';
    $content = isset($_POST['content']) ? wp_unslash((string) $_POST['content']) : '';
    $seeds = \KeywordSuggest\extract_keyphrases($title, $content, 8);
    wp_send_json_success(['seeds' => $seeds]);
});

add_action('wp_ajax_ksug_fetch', static function (): void {
    ksug_check_request();
    $settings = ksug_settings();

    $engines = isset($_POST['engines']) ? array_map('sanitize_key', (array) wp_unslash($_POST['engines'])) : [];
    $engines = array_values(array_intersect($engines, array_keys(ksug_engines())));
    $seedsRaw = isset($_POST['seeds']) ? (array) wp_unslash($_POST['seeds']) : [];
    $seeds = [];
    foreach ($seedsRaw as $s) {
        $q = \KeywordSuggest\clean_query(sanitize_text_field((string) $s));
        if ($q !== null) {
            $seeds[] = $q;
        }
    }
    $seeds = array_slice(array_values(array_unique($seeds)), 0, 6);
    $locale = isset($_POST['locale']) ? sanitize_text_field((string) $_POST['locale']) : $settings['locale'];

    if (!$engines) {
        wp_send_json_error(['message' => 'No engines selected'], 400);
    }
    if (!$seeds) {
        wp_send_json_error(['message' => 'No seeds'], 400);
    }

    $results = [];
    $categories = [];
    $errors = [];
    foreach ($seeds as $seed) {
        foreach ($engines as $engine) {
            $r = \KeywordSuggest\run_engine($engine, $seed, $locale);
            if ($r['error'] !== null) {
                $errors[$engine] = $r['error'];
                continue;
            }
            foreach (array_slice($r['suggestions'], 0, $settings['limit']) as $kw) {
                $key = mb_strtolower($kw);
                if (!isset($results[$key])) {
                    $results[$key] = ['keyword' => $kw, 'sources' => [], 'seed' => $seed];
                }
                if (!in_array($engine, $results[$key]['sources'], true)) {
                    $results[$key]['sources'][] = $engine;
                }
            }
            foreach ($r['extra']['categories'] ?? [] as $c) {
                $categories[$c] = true;
            }
        }
    }

    wp_send_json_success([
        'results' => array_values($results),
        'categories' => array_keys($categories),
        'errors' => $errors,
    ]);
});

// --- Settings page ----------------------------------------------------------

add_action('admin_menu', static function (): void {
    add_options_page(
        __('Keyword Suggest', 'keyword-suggest'),
        __('Keyword Suggest', 'keyword-suggest'),
        'manage_options',
        'keyword-suggest',
        'ksug_render_settings_page'
    );
});

add_action('admin_init', static function (): void {
    register_setting('keyword_suggest', KSUG_OPTION, [
        'type' => 'array',
        'sanitize_callback' => static function ($input): array {
            $input = is_array($input) ? $input : [];
            return [
                'engines' => array_values(array_intersect(array_map('sanitize_key', (array) ($input['engines'] ?? [])), array_keys(ksug_engines()))),
                'locale' => isset($input['locale'], \KeywordSuggest\KS_LOCALES[$input['locale']]) ? (string) $input['locale'] : 'en-US',
                'limit' => max(3, min(25, (int) ($input['limit'] ?? 10))),
                'post_types' => array_values(array_map('sanitize_key', (array) ($input['post_types'] ?? []))),
            ];
        },
        'default' => [],
    ]);
});

function ksug_render_settings_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }
    $s = ksug_settings();
    $engines = ksug_engines();
    $groups = [];
    foreach ($engines as $id => $e) {
        $groups[$e['group']][$id] = $e;
    }
    $types = get_post_types(['show_ui' => true], 'objects');
    unset($types['attachment']);
    $opt = esc_attr(KSUG_OPTION);
    ?>
    <div class="wrap ksug-settings">
        <div class="ksug-hero">
            <div class="ksug-hero-icon" aria-hidden="true">
                <svg viewBox="0 0 64 64" width="44" height="44"><rect width="64" height="64" rx="14" fill="#7c3aed"/><circle cx="28" cy="28" r="12" fill="none" stroke="#fff" stroke-width="5"/><path d="M37 37l11 11" stroke="#fff" stroke-width="5" stroke-linecap="round"/><circle cx="46" cy="16" r="3.5" fill="#fbbf24"/><circle cx="14" cy="47" r="2.5" fill="#34d399"/></svg>
            </div>
            <div>
                <h1><?php esc_html_e('Keyword Suggest', 'keyword-suggest'); ?> <span class="ksug-version">v<?php echo esc_html(KSUG_VERSION); ?></span></h1>
                <p><?php esc_html_e('Keyword ideas from real search autocomplete, inside your editor. Pick the sources and market to pre-select; everything can still be changed per post.', 'keyword-suggest'); ?></p>
            </div>
        </div>

        <div class="ksug-grid">
            <form method="post" action="options.php" class="ksug-main">
                <?php settings_fields('keyword_suggest'); ?>

                <section class="ksug-card">
                    <header>
                        <h2><?php esc_html_e('Default sources', 'keyword-suggest'); ?></h2>
                        <div class="ksug-card-actions">
                            <button type="button" class="button-link" data-ksug-select="all"><?php esc_html_e('Select all', 'keyword-suggest'); ?></button>
                            <span aria-hidden="true">·</span>
                            <button type="button" class="button-link" data-ksug-select="none"><?php esc_html_e('None', 'keyword-suggest'); ?></button>
                        </div>
                    </header>
                    <p class="description"><?php esc_html_e('Each source is a public autocomplete endpoint. Nothing is sent anywhere else, and no API keys are needed.', 'keyword-suggest'); ?></p>
                    <?php foreach ($groups as $group => $items) : ?>
                        <div class="ksug-group">
                            <div class="ksug-group-title"><?php echo esc_html($group); ?></div>
                            <div class="ksug-tiles">
                                <?php foreach ($items as $id => $e) : ?>
                                    <label class="ksug-tile">
                                        <input type="checkbox" name="<?php echo $opt; ?>[engines][]" value="<?php echo esc_attr($id); ?>" <?php checked(in_array($id, $s['engines'], true)); ?>>
                                        <span class="ksug-tile-body">
                                            <i class="ksug-dot ksug-dot-<?php echo esc_attr($id); ?>"></i>
                                            <span><?php echo esc_html($e['label']); ?></span>
                                            <em class="ksug-tile-status" data-ksug-status="<?php echo esc_attr($id); ?>"></em>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </section>

                <section class="ksug-card">
                    <header><h2><?php esc_html_e('Suggestions', 'keyword-suggest'); ?></h2></header>
                    <div class="ksug-fields">
                        <div class="ksug-field">
                            <label for="ksug-locale"><?php esc_html_e('Default market', 'keyword-suggest'); ?></label>
                            <select id="ksug-locale" name="<?php echo $opt; ?>[locale]">
                                <?php foreach (\KeywordSuggest\KS_LOCALES as $code => $_) : ?>
                                    <option value="<?php echo esc_attr($code); ?>" <?php selected($code, $s['locale']); ?>><?php echo esc_html(\KeywordSuggest\KS_LOCALE_LABELS[$code] ?? $code); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Sets the language and country for every source, and the Amazon / eBay marketplace.', 'keyword-suggest'); ?></p>
                        </div>
                        <div class="ksug-field">
                            <label for="ksug-limit"><?php esc_html_e('Suggestions per source and seed', 'keyword-suggest'); ?> <output for="ksug-limit" id="ksug-limit-out"><?php echo (int) $s['limit']; ?></output></label>
                            <input id="ksug-limit" type="range" min="3" max="25" step="1" name="<?php echo $opt; ?>[limit]" value="<?php echo esc_attr((string) $s['limit']); ?>">
                            <p class="description"><?php esc_html_e('Lower keeps the list focused; higher finds more long-tail phrases.', 'keyword-suggest'); ?></p>
                        </div>
                    </div>
                </section>

                <section class="ksug-card">
                    <header><h2><?php esc_html_e('Show the suggestions box on', 'keyword-suggest'); ?></h2></header>
                    <div class="ksug-tiles">
                        <?php foreach ($types as $t) : ?>
                            <label class="ksug-tile">
                                <input type="checkbox" name="<?php echo $opt; ?>[post_types][]" value="<?php echo esc_attr($t->name); ?>" <?php checked(in_array($t->name, $s['post_types'], true)); ?>>
                                <span class="ksug-tile-body"><span class="dashicons <?php echo esc_attr($t->menu_icon && str_starts_with((string) $t->menu_icon, 'dashicons-') ? $t->menu_icon : 'dashicons-admin-post'); ?>"></span><span><?php echo esc_html($t->labels->singular_name); ?></span></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </section>

                <?php submit_button(__('Save settings', 'keyword-suggest'), 'primary ksug-save'); ?>
            </form>

            <aside class="ksug-side">
                <section class="ksug-card">
                    <header><h2><?php esc_html_e('Test sources from this server', 'keyword-suggest'); ?></h2></header>
                    <p class="description"><?php esc_html_e('Runs one live query per source so you can see what works on your hosting.', 'keyword-suggest'); ?></p>
                    <button type="button" class="button" id="ksug-test"><?php esc_html_e('Run test', 'keyword-suggest'); ?></button>
                    <span class="spinner" id="ksug-test-spinner"></span>
                    <table class="ksug-health" id="ksug-health" hidden>
                        <tbody></tbody>
                    </table>
                </section>

                <section class="ksug-card ksug-howto">
                    <header><h2><?php esc_html_e('How it works', 'keyword-suggest'); ?></h2></header>
                    <ol>
                        <li><?php esc_html_e('Open a post or product. The Keyword Suggestions box sits below the editor (in the block editor, expand the Meta Boxes bar).', 'keyword-suggest'); ?></li>
                        <li><?php esc_html_e('Seeds are filled from your title and content. Edit them, pick sources, click Get suggestions.', 'keyword-suggest'); ?></li>
                        <li><?php esc_html_e('Click any suggestion to add it as a tag, set it as the focus keyword, insert it at the cursor, or copy it.', 'keyword-suggest'); ?></li>
                    </ol>
                    <p class="ksug-links">
                        <a href="https://github.com/rmak78/keyword-suggest-tool" target="_blank" rel="noopener"><?php esc_html_e('Documentation & source', 'keyword-suggest'); ?></a> ·
                        <a href="https://github.com/rmak78/keyword-suggest-tool/issues" target="_blank" rel="noopener"><?php esc_html_e('Report a problem', 'keyword-suggest'); ?></a> ·
                        <a href="https://bixisoft.com" target="_blank" rel="noopener">Bixisoft</a>
                    </p>
                </section>
            </aside>
        </div>
        <p class="description ksug-footnote"><?php esc_html_e('Suggestions come from each engine\'s public autocomplete and are not affiliated with or endorsed by those companies.', 'keyword-suggest'); ?></p>
    </div>
    <?php
}

add_action('admin_enqueue_scripts', static function (string $hook): void {
    if ($hook !== 'settings_page_keyword-suggest') {
        return;
    }
    wp_enqueue_style('dashicons');
    wp_enqueue_style('keyword-suggest-settings', KSUG_URL . 'admin/css/keyword-suggest-settings.css', [], KSUG_VERSION);
    wp_enqueue_script('keyword-suggest-settings', KSUG_URL . 'admin/js/keyword-suggest-settings.js', ['jquery'], KSUG_VERSION, true);
    wp_localize_script('keyword-suggest-settings', 'KSUG_SETTINGS', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ksug'),
        'engines' => array_keys(ksug_engines()),
        'i18n' => [
            'ok' => __('OK', 'keyword-suggest'),
            'fail' => __('Failed', 'keyword-suggest'),
            'testing' => __('Testing…', 'keyword-suggest'),
            'results' => __('results', 'keyword-suggest'),
        ],
    ]);
});

add_action('wp_ajax_ksug_health', static function (): void {
    check_ajax_referer('ksug', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Forbidden'], 403);
    }
    $engine = isset($_POST['engine']) ? sanitize_key((string) $_POST['engine']) : '';
    if (!isset(ksug_engines()[$engine])) {
        wp_send_json_error(['message' => 'Unknown engine'], 400);
    }
    $r = \KeywordSuggest\run_engine($engine, 'running shoes', ksug_settings()['locale'], false);
    wp_send_json_success([
        'engine' => $engine,
        'ok' => $r['error'] === null && count($r['suggestions']) > 0,
        'count' => count($r['suggestions']),
        'sample' => array_slice($r['suggestions'], 0, 3),
        'error' => $r['error'] ?? (count($r['suggestions']) === 0 ? 'No suggestions returned' : null),
        'ms' => $r['ms'],
    ]);
});

add_filter('plugin_action_links_' . plugin_basename(__FILE__), static function (array $links): array {
    array_unshift($links, '<a href="' . esc_url(admin_url('options-general.php?page=keyword-suggest')) . '">' . esc_html__('Settings', 'keyword-suggest') . '</a>');
    return $links;
});
