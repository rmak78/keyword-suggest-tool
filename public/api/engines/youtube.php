<?php
// YouTube autocomplete: same endpoint as Google with ds=yt.
declare(strict_types=1);

return static function (string $q, string $lang, string $country): array {
    return [
        'url' => 'https://suggestqueries.google.com/complete/search?' . http_build_query([
            'client' => 'firefox',
            'ds' => 'yt',
            'q' => $q,
            'hl' => $lang,
            'gl' => $country,
        ]),
        'parse' => 'parse_opensearch',
    ];
};
