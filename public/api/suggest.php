<?php
/**
 * GET /api/suggest.php?engine=google&q=keyword&locale=en-US
 *
 * Returns {"engine","query","locale","suggestions":[...],"extra":{},"error":null,"ms"}.
 * All upstream calls are made server-side so the browser never depends on
 * JSONP or CORS support from the search engines.
 */

declare(strict_types=1);

namespace KeywordSuggest;

require __DIR__ . '/lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    fail('Method not allowed', 405);
}

$engine = isset($_GET['engine']) ? strtolower(trim((string) $_GET['engine'])) : '';
if ($engine === '' || !preg_match('/^[a-z0-9_-]{1,32}$/', $engine) || load_engine($engine) === null) {
    fail('Unknown or missing engine', 400, ['engines' => array_keys(engine_registry())]);
}

$query = clean_query(isset($_GET['q']) ? (string) $_GET['q'] : null);
if ($query === null) {
    fail('Missing or invalid q (1-' . KS_MAX_QUERY_LENGTH . ' characters)', 400);
}

$locale = isset($_GET['locale']) ? (string) $_GET['locale'] : 'en-US';
$noCache = isset($_GET['nocache']);

$result = run_engine($engine, $query, $locale, !$noCache);
json_out($result, $result['error'] === null ? 200 : 502);
