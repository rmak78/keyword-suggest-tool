<?php
// Apple App Store search hints. Returns an XML plist with <string> entries,
// or JSON {"hints":[{"term":"..."}]} depending on the Accept header.
declare(strict_types=1);

return static function (string $q, string $lang, string $country): array {
    return [
        'url' => 'https://search.itunes.apple.com/WebObjects/MZSearchHints.woa/wa/hints?' . http_build_query([
            'clientApplication' => 'Software',
            'term' => $q,
            'cc' => strtolower($country),
        ]),
        'headers' => ['X-Apple-Store-Front: 143441-1,29', 'Accept: application/json'],
        'parse' => static function (string $body): array {
            $data = json_decode($body, true);
            if (is_array($data) && isset($data['hints']) && is_array($data['hints'])) {
                return array_map(static fn($h) => is_array($h) ? ($h['term'] ?? '') : (string) $h, $data['hints']);
            }
            if (preg_match_all('/<key>term<\/key>\s*<string>(.*?)<\/string>/s', $body, $m) && count($m[1]) > 0) {
                return $m[1];
            }
            throw new RuntimeException('Unexpected response format');
        },
    ];
};
