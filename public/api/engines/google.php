<?php
// Google web search autocomplete (undocumented, widely used).
// client=firefox returns plain JSON: ["query", ["s1", "s2", ...]]
declare(strict_types=1);

return static function (string $q, string $lang, string $country): array {
    return [
        'url' => 'https://suggestqueries.google.com/complete/search?' . http_build_query([
            'client' => 'firefox',
            'q' => $q,
            'hl' => $lang,
            'gl' => $country,
        ]),
        'parse' => 'parse_opensearch',
    ];
};
