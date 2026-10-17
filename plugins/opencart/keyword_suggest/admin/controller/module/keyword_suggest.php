<?php
namespace Opencart\Admin\Controller\Extension\KeywordSuggest\Module;

/**
 * Keyword Suggest for OpenCart 4.x
 *
 * Adds a "Keyword suggestions" panel to the product form (via the
 * admin/view/catalog/product_form/after event) and two AJAX endpoints that
 * run the bundled engine adapters server-side.
 */
class KeywordSuggest extends \Opencart\System\Engine\Controller
{
    private const ROUTE = 'extension/keyword_suggest/module/keyword_suggest';
    private const EVENT_CODE = 'keyword_suggest';

    /** @var array<string, array{label: string, group: string}> */
    private const ENGINES = [
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

    private const DEFAULT_ENGINES = ['google', 'bing', 'amazon', 'ebay'];

    private function loadApi(): void
    {
        require_once DIR_EXTENSION . 'keyword_suggest/system/library/keyword_suggest/api/lib.php';
        require_once DIR_EXTENSION . 'keyword_suggest/system/library/keyword_suggest/api/keyphrases.php';
    }

    /** @return array{status: bool, engines: string[], locale: string, limit: int} */
    private function settings(): array
    {
        $this->loadApi();
        $engines = (array) ($this->config->get('module_keyword_suggest_engines') ?: self::DEFAULT_ENGINES);
        $engines = array_values(array_intersect($engines, array_keys(self::ENGINES)));
        $locale = (string) ($this->config->get('module_keyword_suggest_locale') ?: 'en-US');
        if (!isset(\KeywordSuggest\KS_LOCALES[$locale])) {
            $locale = 'en-US';
        }
        return [
            'status' => (bool) $this->config->get('module_keyword_suggest_status'),
            'engines' => $engines ?: self::DEFAULT_ENGINES,
            'locale' => $locale,
            'limit' => max(3, min(25, (int) ($this->config->get('module_keyword_suggest_limit') ?: 10))),
        ];
    }

    // --- Settings page ----------------------------------------------------

    public function index(): void
    {
        $this->load->language(self::ROUTE);
        $this->document->setTitle($this->language->get('heading_title'));
        $this->loadApi();

        $token = 'user_token=' . $this->session->data['user_token'];

        $data['breadcrumbs'] = [
            ['text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', $token)],
            ['text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', $token . '&type=module')],
            ['text' => $this->language->get('heading_title'), 'href' => $this->url->link(self::ROUTE, $token)],
        ];
        $data['save'] = $this->url->link(self::ROUTE . '.save', $token);
        $data['back'] = $this->url->link('marketplace/extension', $token . '&type=module');

        $s = $this->settings();
        $data['module_keyword_suggest_status'] = $s['status'];
        $data['module_keyword_suggest_engines'] = $s['engines'];
        $data['module_keyword_suggest_locale'] = $s['locale'];
        $data['module_keyword_suggest_limit'] = $s['limit'];
        $data['engines'] = self::ENGINES;
        $data['locales'] = array_keys(\KeywordSuggest\KS_LOCALES);

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view(self::ROUTE, $data));
    }

    public function save(): void
    {
        $this->load->language(self::ROUTE);
        $json = [];

        if (!$this->user->hasPermission('modify', self::ROUTE)) {
            $json['error']['warning'] = $this->language->get('error_permission');
        }

        if (!$json) {
            $post = $this->request->post;
            $engines = array_values(array_intersect((array) ($post['module_keyword_suggest_engines'] ?? []), array_keys(self::ENGINES)));
            $this->load->model('setting/setting');
            $this->model_setting_setting->editSetting('module_keyword_suggest', [
                'module_keyword_suggest_status' => (int) !empty($post['module_keyword_suggest_status']),
                'module_keyword_suggest_engines' => $engines ?: self::DEFAULT_ENGINES,
                'module_keyword_suggest_locale' => (string) ($post['module_keyword_suggest_locale'] ?? 'en-US'),
                'module_keyword_suggest_limit' => max(3, min(25, (int) ($post['module_keyword_suggest_limit'] ?? 10))),
            ]);
            $json['success'] = $this->language->get('text_success');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function install(): void
    {
        if (!$this->user->hasPermission('modify', 'extension/module')) {
            return;
        }
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode(self::EVENT_CODE);
        $this->model_setting_event->addEvent([
            'code' => self::EVENT_CODE,
            'description' => 'Keyword Suggest: product form panel',
            'trigger' => 'admin/view/catalog/product_form/after',
            'action' => self::ROUTE . '.productForm',
            'status' => 1,
            'sort_order' => 0,
        ]);
        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting('module_keyword_suggest', [
            'module_keyword_suggest_status' => 1,
            'module_keyword_suggest_engines' => self::DEFAULT_ENGINES,
            'module_keyword_suggest_locale' => 'en-US',
            'module_keyword_suggest_limit' => 10,
        ]);
    }

    public function uninstall(): void
    {
        if (!$this->user->hasPermission('modify', 'extension/module')) {
            return;
        }
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode(self::EVENT_CODE);
        $this->load->model('setting/setting');
        $this->model_setting_setting->deleteSetting('module_keyword_suggest');
    }

    // --- Event: inject the panel into the product form -------------------

    public function productForm(string &$route, array &$args, &$output): void
    {
        if (!is_string($output) || !$this->config->get('module_keyword_suggest_status')) {
            return;
        }
        $this->load->language(self::ROUTE);
        $s = $this->settings();
        $token = 'user_token=' . $this->session->data['user_token'];
        $base = HTTP_CATALOG . 'extension/keyword_suggest/admin/view/';

        $config = [
            'suggestUrl' => html_entity_decode($this->url->link(self::ROUTE . '.suggest', $token), ENT_QUOTES, 'UTF-8'),
            'seedsUrl' => html_entity_decode($this->url->link(self::ROUTE . '.seeds', $token), ENT_QUOTES, 'UTF-8'),
            'engines' => self::ENGINES,
            'defaultEngines' => $s['engines'],
            'locale' => $s['locale'],
            'locales' => array_keys(\KeywordSuggest\KS_LOCALES),
            'i18n' => [
                'heading' => $this->language->get('panel_heading'),
                'seeds' => $this->language->get('panel_seeds'),
                'seedsHelp' => $this->language->get('panel_seeds_help'),
                'fromContent' => $this->language->get('panel_from_content'),
                'sources' => $this->language->get('panel_sources'),
                'locale' => $this->language->get('panel_locale'),
                'onClick' => $this->language->get('panel_on_click'),
                'actMeta' => $this->language->get('panel_act_meta'),
                'actTag' => $this->language->get('panel_act_tag'),
                'actDescription' => $this->language->get('panel_act_description'),
                'actCopy' => $this->language->get('panel_act_copy'),
                'get' => $this->language->get('panel_get'),
                'loading' => $this->language->get('panel_loading'),
                'filter' => $this->language->get('panel_filter'),
                'none' => $this->language->get('panel_none'),
                'empty' => $this->language->get('panel_empty'),
                'added' => $this->language->get('panel_added'),
                'copied' => $this->language->get('panel_copied'),
                'noSeeds' => $this->language->get('panel_no_seeds'),
                'noEngines' => $this->language->get('panel_no_engines'),
                'error' => $this->language->get('panel_error'),
                'categories' => $this->language->get('panel_categories'),
                'addAll' => $this->language->get('panel_add_all'),
            ],
        ];

        $inject = "\n<link rel=\"stylesheet\" href=\"{$base}stylesheet/keyword_suggest.css?v=2.1.0\">"
            . "\n<script>window.KSUG_OC = " . json_encode($config, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . ";</script>"
            . "\n<script src=\"{$base}javascript/keyword_suggest.js?v=2.1.0\"></script>\n";

        $pos = strripos($output, '</body>');
        $output = $pos === false ? $output . $inject : substr_replace($output, $inject, $pos, 0);
    }

    // --- AJAX ---------------------------------------------------------------

    private function denied(): bool
    {
        return !$this->user->hasPermission('modify', 'catalog/product');
    }

    public function seeds(): void
    {
        $json = [];
        if ($this->denied()) {
            $json['error'] = 'Permission denied';
        } else {
            $this->loadApi();
            // OpenCart HTML-escapes all request data; decode so tags can be stripped properly.
            $title = html_entity_decode((string) ($this->request->post['title'] ?? ''), ENT_QUOTES, 'UTF-8');
            $content = html_entity_decode((string) ($this->request->post['content'] ?? ''), ENT_QUOTES, 'UTF-8');
            $json['seeds'] = \KeywordSuggest\extract_keyphrases($title, $content, 8);
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function suggest(): void
    {
        $json = [];
        if ($this->denied()) {
            $json['error'] = 'Permission denied';
        } else {
            $this->loadApi();
            $s = $this->settings();

            $engines = array_values(array_intersect((array) ($this->request->post['engines'] ?? []), array_keys(self::ENGINES)));
            $seeds = [];
            foreach ((array) ($this->request->post['seeds'] ?? []) as $raw) {
                $q = \KeywordSuggest\clean_query(strip_tags(html_entity_decode((string) $raw, ENT_QUOTES, 'UTF-8')));
                if ($q !== null) {
                    $seeds[] = $q;
                }
            }
            $seeds = array_slice(array_values(array_unique($seeds)), 0, 6);
            $locale = (string) ($this->request->post['locale'] ?? $s['locale']);

            if (!$engines) {
                $json['error'] = 'No engines selected';
            } elseif (!$seeds) {
                $json['error'] = 'No seeds';
            } else {
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
                        foreach (array_slice($r['suggestions'], 0, $s['limit']) as $kw) {
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
                $json['results'] = array_values($results);
                $json['categories'] = array_keys($categories);
                $json['errors'] = $errors;
            }
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
