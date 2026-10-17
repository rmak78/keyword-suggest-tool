<?php
// Yahoo search suggest. Primary: gossip endpoint ({"gossip":{"results":[{"key":...}]}}).
// Fallback: the Firefox-format endpoint (["q", ["s1", ...]]).
declare(strict_types=1);

namespace KeywordSuggest;

return static function (string $q, string $lang, string $country): array {
    return [
        'url' => 'https://search.yahoo.com/sugg/gossip/gossip-us-ura/?' . http_build_query([
            'output' => 'sd1',
            'command' => $q,
            'nresults' => 20,
        ]),
        'parse' => static function (string $body) use ($q): array {
            $data = json_decode($body, true);
            if (is_array($data) && isset($data['gossip']['results']) && is_array($data['gossip']['results'])) {
                return array_map(static fn($r) => $r['key'] ?? '', $data['gossip']['results']);
            }
            [$alt, $err] = http_get('https://search.yahoo.com/sugg/ff?' . http_build_query([
                'output' => 'fxjson',
                'command' => $q,
            ]), [], false);
            if ($alt === null) {
                throw new \RuntimeException('Yahoo fallback request failed');
            }
            return parse_opensearch($alt);
        },
    ];
};
