<?php
// Qwant suggest: {"status":"success","data":{"items":[{"value":"..."}]}}.
declare(strict_types=1);

namespace KeywordSuggest;

return static function (string $q, string $lang, string $country): array {
    return [
        'url' => 'https://api.qwant.com/v3/suggest?' . http_build_query([
            'q' => $q,
            'locale' => strtolower($lang) . '_' . strtoupper($country),
            'version' => 2,
        ]),
        'headers' => ['Referer: https://www.qwant.com/'],
        'parse' => static function (string $body): array {
            $data = json_decode($body, true);
            if (!is_array($data) || !isset($data['data']['items']) || !is_array($data['data']['items'])) {
                throw new \RuntimeException('Unexpected response format');
            }
            return array_map(static fn($r) => is_array($r) ? ($r['value'] ?? '') : (string) $r, $data['data']['items']);
        },
    ];
};
