<?php
// Wikipedia OpenSearch (officially documented): ["q", ["Title 1", ...], [...], [...]]
declare(strict_types=1);

namespace KeywordSuggest;

return static function (string $q, string $lang, string $country): array {
    $lang = preg_match('/^[a-z]{2,3}$/', $lang) ? $lang : 'en';
    return [
        'url' => "https://$lang.wikipedia.org/w/api.php?" . http_build_query([
            'action' => 'opensearch',
            'search' => $q,
            'limit' => 20,
            'namespace' => 0,
            'format' => 'json',
        ]),
        'headers' => ['User-Agent: keyword-suggest-tool/2.0 (https://github.com/rmak78/keyword-suggest-tool)'],
        'parse' => __NAMESPACE__ . '\parse_opensearch',
    ];
};
