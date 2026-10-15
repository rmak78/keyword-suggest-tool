<?php
// eBay autosuggest: {"res":{"sug":["..."],"categories":["..."]}} (JSONP-wrapped unless callback=0).
declare(strict_types=1);

const KS_EBAY_HOSTS = [
    'US' => 'autosug.ebay.com',
    'GB' => 'autosug.ebay.co.uk',
    'CA' => 'autosug.ebay.ca',
    'AU' => 'autosug.ebay.com.au',
    'IN' => 'autosug.ebay.in',
    'DE' => 'autosug.ebay.de',
    'FR' => 'autosug.ebay.fr',
    'ES' => 'autosug.ebay.es',
    'IT' => 'autosug.ebay.it',
    'NL' => 'autosug.ebay.nl',
];

/** Categories arrive as ["Name"] or as [[id, "Name"], ...]. */
function ebay_category_names(array $cats): array
{
    $out = [];
    foreach ($cats as $c) {
        if (is_string($c)) {
            $out[] = $c;
        } elseif (is_array($c)) {
            $name = $c[1] ?? $c['name'] ?? null;
            if (is_string($name)) {
                $out[] = $name;
            }
        }
    }
    return array_values(array_unique($out));
}

return static function (string $q, string $lang, string $country): array {
    $host = KS_EBAY_HOSTS[$country] ?? KS_EBAY_HOSTS['US'];
    return [
        'url' => "https://$host/autosug?" . http_build_query([
            'kwd' => $q,
            'sId' => 0,
            '_jgr' => 1,
            '_ch' => 0,
            'callback' => 0,
        ]),
        'parse' => static function (string $body): array {
            // Strip a JSONP wrapper if present: foo.bar(...) or /**/foo(...)
            $json = trim($body);
            if (!str_starts_with($json, '{')) {
                $start = strpos($json, '(');
                $end = strrpos($json, ')');
                if ($start !== false && $end !== false && $end > $start) {
                    $json = substr($json, $start + 1, $end - $start - 1);
                }
            }
            $data = json_decode($json, true);
            if (!is_array($data) || !isset($data['res']) || !is_array($data['res'])) {
                throw new RuntimeException('Unexpected response format');
            }
            $sug = $data['res']['sug'] ?? [];
            $cats = $data['res']['categories'] ?? [];
            return [
                'suggestions' => is_array($sug) ? $sug : [],
                'extra' => ['categories' => is_array($cats) ? ebay_category_names($cats) : []],
            ];
        },
    ];
};
