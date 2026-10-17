<?php
/**
 * GET /api/health.php[?q=test][&locale=en-US]
 *
 * Probes every engine adapter once and reports which ones respond from this
 * server. The front end uses it to grey out engines that are blocked or whose
 * upstream endpoint has changed.
 */

declare(strict_types=1);

namespace KeywordSuggest;

require __DIR__ . '/lib.php';

$query = clean_query(isset($_GET['q']) ? (string) $_GET['q'] : 'shoes') ?? 'shoes';
$locale = isset($_GET['locale']) ? (string) $_GET['locale'] : 'en-US';

$report = [
    'php' => PHP_VERSION,
    'curl' => function_exists('curl_init'),
    'cache' => cache_dir() !== null,
    'query' => $query,
    'engines' => [],
];

foreach (array_keys(engine_registry()) as $id) {
    $r = run_engine($id, $query, $locale, false);
    $report['engines'][$id] = [
        'ok' => $r['error'] === null && count($r['suggestions']) > 0,
        'count' => count($r['suggestions']),
        'sample' => array_slice($r['suggestions'], 0, 3),
        'error' => $r['error'] ?? (count($r['suggestions']) === 0 ? 'No suggestions returned' : null),
        'ms' => $r['ms'],
    ];
}

json_out($report);
