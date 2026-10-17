<?php
/**
 * Default transport for the web tool and OpenCart: curl + file cache.
 *
 * The WordPress plugin ships transport-wp.php under this name instead
 * (wp_remote_get + transients), so lib.php never touches curl or the
 * filesystem directly.
 */

declare(strict_types=1);

namespace KeywordSuggest;

/**
 * @param string[] $headers "Name: value" strings
 * @return array{0: ?string, 1: ?string, 2: int} [body, error, httpStatus]
 */
function transport_get(string $url, array $headers, int $timeout, string $userAgent): array
{
    if (!function_exists('curl_init')) {
        return [null, 'PHP curl extension is not installed on this server', 0];
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_USERAGENT => $userAgent,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($body === false || $body === null) {
        return [null, $err !== '' ? $err : 'Request failed', $status];
    }
    return [(string) $body, null, $status];
}

function cache_dir(): ?string
{
    $dir = sys_get_temp_dir() . '/keyword-suggest-cache';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
        return null;
    }
    return is_writable($dir) ? $dir : null;
}

function cache_available(): bool
{
    return cache_dir() !== null;
}

function cache_get(string $key, int $ttl): ?string
{
    $dir = cache_dir();
    if ($dir === null) {
        return null;
    }
    $file = $dir . '/' . $key . '.json';
    if (is_file($file) && (time() - filemtime($file)) < $ttl) {
        $cached = file_get_contents($file);
        return $cached === false ? null : $cached;
    }
    return null;
}

function cache_set(string $key, string $value, int $ttl): void
{
    $dir = cache_dir();
    if ($dir !== null) {
        @file_put_contents($dir . '/' . $key . '.json', $value, LOCK_EX);
    }
}

function strip_html(string $html): string
{
    return strip_tags($html);
}
