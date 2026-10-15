<?php
// DuckDuckGo autocomplete: type=list returns ["q", ["s1", ...]].
declare(strict_types=1);

return static function (string $q, string $lang, string $country): array {
    return [
        'url' => 'https://duckduckgo.com/ac/?' . http_build_query([
            'q' => $q,
            'type' => 'list',
            'kl' => strtolower($country) . '-' . strtolower($lang),
        ]),
        'parse' => static function (string $body): array {
            $data = json_decode($body, true);
            // Default format is [{"phrase": "..."}], type=list gives the OpenSearch form.
            if (is_array($data) && isset($data[0]['phrase'])) {
                return array_map(static fn($r) => $r['phrase'] ?? '', $data);
            }
            return parse_opensearch($body);
        },
    ];
};
