<?php
// Bing autosuggest. Primary: the long-standing OpenSearch JSON endpoint.
// Fallback (if primary stops responding): scrape www.bing.com/AS/Suggestions.
declare(strict_types=1);

return static function (string $q, string $lang, string $country): array {
    $market = strtolower($lang) . '-' . strtoupper($country);
    return [
        'url' => 'https://api.bing.com/osjson.aspx?' . http_build_query([
            'query' => $q,
            'market' => $market,
        ]),
        'parse' => static function (string $body) use ($q, $market): array {
            try {
                return parse_opensearch($body);
            } catch (RuntimeException $e) {
                // Fallback to the HTML suggestions endpoint used by bing.com itself.
                [$html, $err] = http_get('https://www.bing.com/AS/Suggestions?' . http_build_query([
                    'qry' => $q,
                    'cvid' => bin2hex(random_bytes(16)),
                    'mkt' => $market,
                    'cp' => mb_strlen($q),
                ]), ['Referer: https://www.bing.com/'], false);
                if ($html === null) {
                    throw new RuntimeException($err ?? 'Bing fallback failed');
                }
                if (!preg_match_all('/<span class="sa_tm_text">(.*?)<\/span>/s', $html, $m)) {
                    throw new RuntimeException('Unexpected response format');
                }
                return $m[1];
            }
        },
    ];
};
