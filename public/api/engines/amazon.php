<?php
// Amazon search autocomplete (2017 API): {"suggestions":[{"value":"..."},...]}
// Each marketplace has its own completion host and marketplace id (mid).
declare(strict_types=1);

namespace KeywordSuggest;

const KS_AMAZON_MARKETS = [
    'US' => ['completion.amazon.com', 'ATVPDKIKX0DER'],
    'GB' => ['completion.amazon.co.uk', 'A1F83G8C2ARO7P'],
    'CA' => ['completion.amazon.ca', 'A2EUQ1WTGCTBG2'],
    'AU' => ['completion.amazon.com.au', 'A39IBJ37TRP1C6'],
    'IN' => ['completion.amazon.in', 'A21TJRUUN4KGV'],
    'DE' => ['completion.amazon.de', 'A1PA6795UKMFR9'],
    'FR' => ['completion.amazon.fr', 'A13V1IB3VIYZZH'],
    'ES' => ['completion.amazon.es', 'A1RKKUPIHCS9HS'],
    'IT' => ['completion.amazon.it', 'APJ6JRA9NG5V4'],
    'NL' => ['completion.amazon.nl', 'A1805IZSGTT6HS'],
    'BR' => ['completion.amazon.com.br', 'A2Q3Y263D00KWC'],
    'JP' => ['completion.amazon.co.jp', 'A1VC38T7YXB528'],
];

return static function (string $q, string $lang, string $country): array {
    [$host, $mid] = KS_AMAZON_MARKETS[$country] ?? KS_AMAZON_MARKETS['US'];
    return [
        'url' => "https://$host/api/2017/suggestions?" . http_build_query([
            'limit' => 11,
            'prefix' => $q,
            'suggestion-type' => 'KEYWORD',
            'alias' => 'aps',
            'site-variant' => 'desktop',
            'lop' => strtolower($lang) . '_' . strtoupper($country),
            'mid' => $mid,
        ]),
        'headers' => ['Referer: https://www.' . preg_replace('/^completion\./', '', $host) . '/'],
        'parse' => static function (string $body): array {
            $data = json_decode($body, true);
            if (!is_array($data) || !isset($data['suggestions']) || !is_array($data['suggestions'])) {
                throw new \RuntimeException('Unexpected response format');
            }
            return array_map(static fn($r) => is_array($r) ? ($r['value'] ?? '') : (string) $r, $data['suggestions']);
        },
    ];
};
