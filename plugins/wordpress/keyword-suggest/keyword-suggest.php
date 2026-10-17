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
    $types = get_post_types(['show_ui' => true], 'objects');
    unset($types['attachment']);
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Keyword Suggest', 'keyword-suggest'); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields('keyword_suggest'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Default sources', 'keyword-suggest'); ?></th>
                    <td>
                        <?php foreach (ksug_engines() as $id => $e) : ?>
                            <label style="display:inline-block;min-width:11em;margin:0 0 6px">
                                <input type="checkbox" name="<?php echo esc_attr(KSUG_OPTION); ?>[engines][]" value="<?php echo esc_attr($id); ?>" <?php checked(in_array($id, $s['engines'], true)); ?>>
                                <?php echo esc_html($e['label']); ?>
                            </label>
                        <?php endforeach; ?>
                        <p class="description"><?php esc_html_e('Pre-selected in the editor; you can change them per post.', 'keyword-suggest'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ksug-locale"><?php esc_html_e('Default market', 'keyword-suggest'); ?></label></th>
                    <td>
                        <select id="ksug-locale" name="<?php echo esc_attr(KSUG_OPTION); ?>[locale]">
                            <?php foreach (array_keys(\KeywordSuggest\KS_LOCALES) as $code) : ?>
                                <option value="<?php echo esc_attr($code); ?>" <?php selected($code, $s['locale']); ?>><?php echo esc_html($code); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ksug-limit"><?php esc_html_e('Suggestions per source and seed', 'keyword-suggest'); ?></label></th>
                    <td><input id="ksug-limit" type="number" min="3" max="25" name="<?php echo esc_attr(KSUG_OPTION); ?>[limit]" value="<?php echo esc_attr((string) $s['limit']); ?>" class="small-text"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Show on', 'keyword-suggest'); ?></th>
                    <td>
                        <?php foreach ($types as $t) : ?>
                            <label style="display:inline-block;min-width:11em;margin:0 0 6px">
                                <input type="checkbox" name="<?php echo esc_attr(KSUG_OPTION); ?>[post_types][]" value="<?php echo esc_attr($t->name); ?>" <?php checked(in_array($t->name, $s['post_types'], true)); ?>>
                                <?php echo esc_html($t->labels->singular_name); ?>
                            </label>
                        <?php endforeach; ?>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <p class="description">
            <?php
            printf(
                /* translators: %s: link to GitHub */
                esc_html__('Suggestions come from each engine\'s public autocomplete and are fetched by your server. Source and web version: %s', 'keyword-suggest'),
                '<a href="https://github.com/rmak78/keyword-suggest-tool" target="_blank" rel="noopener">github.com/rmak78/keyword-suggest-tool</a>'
            );
            ?>
        </p>
    </div>
    <?php
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), static function (array $links): array {
    array_unshift($links, '<a href="' . esc_url(admin_url('options-general.php?page=keyword-suggest')) . '">' . esc_html__('Settings', 'keyword-suggest') . '</a>');
    return $links;
});
