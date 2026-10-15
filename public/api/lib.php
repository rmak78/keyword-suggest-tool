<?php
/**
 * Shared helpers for the suggest proxy.
 *
 * Every upstream autocomplete call goes through http_get() so there is one
 * place for timeouts, the User-Agent, gzip and caching.
 */

declare(strict_types=1);

const KS_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';
const KS_TIMEOUT_SECONDS = 8;
const KS_CACHE_TTL_SECONDS = 3600;
const KS_MAX_QUERY_LENGTH = 200;

/** Supported locales: code => [language, country]. Keep in sync with src/engines.ts. */
const KS_LOCALES = [
    'en-US' => ['en', 'US'],
    'en-GB' => ['en', 'GB'],
    'en-AU' => ['en', 'AU'],
    'en-CA' => ['en', 'CA'],
    'en-IN' => ['en', 'IN'],
    'de-DE' => ['de', 'DE'],
    'fr-FR' => ['fr', 'FR'],
    'es-ES' => ['es', 'ES'],
    'it-IT' => ['it', 'IT'],
    'nl-NL' => ['nl', 'NL'],
    'pt-BR' => ['pt', 'BR'],
    'ja-JP' => ['ja', 'JP'],
];

function json_out(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fail(string $message, int $status = 400, array $extra = []): void
{
    json_out(array_merge(['error' => $message, 'suggestions' => []], $extra), $status);
}

/**
 * Load the engine registry: id => adapter factory path.
 * @return array<string, string>
 */
function engine_registry(): array
{
    /** @var array<string, string> $registry */
    $registry = require __DIR__ . '/engines/registry.php';
    return $registry;
}

/**
 * Build an adapter. Each adapter file returns a callable
 * (string $query, string $lang, string $country): array{url: string, headers?: string[], parse: callable}
 */
function load_engine(string $id): ?callable
{
    static $loaded = [];
    if (array_key_exists($id, $loaded)) {
        return $loaded[$id];
    }
    $registry = engine_registry();
    if (!isset($registry[$id])) {
        return null;
    }
    // Each adapter file is required at most once per request (they may declare consts/functions).
    $adapter = require __DIR__ . '/engines/' . $registry[$id];
    return $loaded[$id] = is_callable($adapter) ? $adapter : null;
}

function resolve_locale(?string $locale): array
{
    $locale = $locale ?: 'en-US';
    if (!isset(KS_LOCALES[$locale])) {
        $locale = 'en-US';
    }
    [$lang, $country] = KS_LOCALES[$locale];
    return [$locale, $lang, $country];
}

function clean_query(?string $q): ?string
{
    if ($q === null) {
        return null;
    }
    $q = trim(preg_replace('/\s+/u', ' ', $q) ?? '');
    if ($q === '' || mb_strlen($q) > KS_MAX_QUERY_LENGTH) {
        return null;
    }
    return $q;
}

function cache_dir(): ?string
{
    $dir = sys_get_temp_dir() . '/keyword-suggest-cache';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
        return null;
    }
    return is_writable($dir) ? $dir : null;
}

/**
 * Perform a GET with curl. Returns [body, error, httpStatus].
 * @param string[] $headers
 * @return array{0: ?string, 1: ?string, 2: int}
 */
function http_get(string $url, array $headers = [], bool $useCache = true): array
{
    $cacheFile = null;
    if ($useCache && ($dir = cache_dir()) !== null) {
        $cacheFile = $dir . '/' . sha1($url) . '.json';
        if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < KS_CACHE_TTL_SECONDS) {
            $cached = file_get_contents($cacheFile);
            if ($cached !== false) {
                return [$cached, null, 200];
            }
        }
    }

    if (!function_exists('curl_init')) {
        return [null, 'PHP curl extension is not installed on this server', 0];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_CONNECTTIMEOUT => KS_TIMEOUT_SECONDS,
        CURLOPT_TIMEOUT => KS_TIMEOUT_SECONDS,
        CURLOPT_USERAGENT => KS_USER_AGENT,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => array_merge(['Accept: */*', 'Accept-Language: en-US,en;q=0.8'], $headers),
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($body === false || $body === null) {
        return [null, $err !== '' ? $err : 'Request failed', $status];
    }
    if ($status >= 400) {
        return [null, "Upstream returned HTTP $status", $status];
    }
    if ($cacheFile !== null) {
        @file_put_contents($cacheFile, (string) $body, LOCK_EX);
    }
    return [(string) $body, null, $status];
}

/**
 * Normalise a list of suggestions: strip HTML/markup, trim, dedupe, drop empties and URLs.
 * @param mixed[] $items
 * @return string[]
 */
function normalise_suggestions(array $items, int $limit = 50): array
{
    $out = [];
    $seen = [];
    foreach ($items as $item) {
        if (!is_string($item)) {
            continue;
        }
        $s = html_entity_decode(strip_tags($item), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = trim(preg_replace('/\s+/u', ' ', $s) ?? '');
        if ($s === '' || preg_match('#^https?://#i', $s)) {
            continue;
        }
        $key = mb_strtolower($s);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out[] = $s;
        if (count($out) >= $limit) {
            break;
        }
    }
    return $out;
}

/**
 * Parse the common OpenSearch-style response: ["query", ["s1", "s2", ...]].
 * @return string[]
 */
function parse_opensearch(string $body): array
{
    $data = json_decode($body, true);
    if (!is_array($data) || !isset($data[1]) || !is_array($data[1])) {
        throw new RuntimeException('Unexpected response format');
    }
    return $data[1];
}

/**
 * Run an adapter for a query and return the normalised result array.
 * @return array{engine: string, query: string, locale: string, suggestions: string[], extra: array<string, mixed>, error: ?string, ms: int}
 */
function run_engine(string $id, string $query, string $locale, bool $useCache = true): array
{
    [$locale, $lang, $country] = resolve_locale($locale);
    $started = microtime(true);
    $result = ['engine' => $id, 'query' => $query, 'locale' => $locale, 'suggestions' => [], 'extra' => [], 'error' => null, 'ms' => 0];

    $adapter = load_engine($id);
    if ($adapter === null) {
        $result['error'] = 'Unknown engine';
        return $result;
    }

    try {
        $spec = $adapter($query, $lang, $country);
        [$body, $err] = http_get($spec['url'], $spec['headers'] ?? [], $useCache);
        if ($body === null) {
            $result['error'] = $err;
        } else {
            $parsed = $spec['parse']($body);
            // Adapters may return a plain list or ['suggestions' => [...], 'extra' => [...]].
            if (isset($parsed['suggestions'])) {
                $result['suggestions'] = normalise_suggestions($parsed['suggestions']);
                $result['extra'] = $parsed['extra'] ?? [];
            } else {
                $result['suggestions'] = normalise_suggestions($parsed);
            }
        }
    } catch (Throwable $e) {
        $result['error'] = $e->getMessage();
    }

    $result['ms'] = (int) round((microtime(true) - $started) * 1000);
    return $result;
}
