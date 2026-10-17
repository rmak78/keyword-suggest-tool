<?php
// Yandex suggest, Firefox format: ["q", ["s1", ...]].
declare(strict_types=1);

namespace KeywordSuggest;

return static function (string $q, string $lang, string $country): array {
    return [
        'url' => 'https://suggest.yandex.net/suggest-ff.cgi?' . http_build_query([
            'part' => $q,
            'uil' => $lang,
            'v' => 3,
            'sn' => 20,
        ]),
        'parse' => __NAMESPACE__ . '\parse_opensearch',
    ];
};
