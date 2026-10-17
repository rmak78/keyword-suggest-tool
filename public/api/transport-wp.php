<?php
/**
 * WordPress transport: wp_remote_get + transients.
 *
 * The plugin build copies this file to includes/api/transport.php so the
 * shared library uses the WordPress HTTP API and object cache, as the
 * wordpress.org guidelines require.
 */

declare(strict_types=1);

namespace KeywordSuggest;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param string[] $headers "Name: value" strings
 * @return array{0: ?string, 1: ?string, 2: int} [body, error, httpStatus]
 */
function transport_get(string $url, array $headers, int $timeout, string $userAgent): array
{
    $assoc = [];
    foreach ($headers as $line) {
        $parts = explode(':', $line, 2);
        if (count($parts) === 2) {
            $assoc[trim($parts[0])] = trim($parts[1]);
        }
    }
    if (isset($assoc['User-Agent'])) {
        $userAgent = $assoc['User-Agent'];
        unset($assoc['User-Agent']);
    }

    $response = wp_remote_get($url, [
        'timeout' => $timeout,
        'redirection' => 3,
        'user-agent' => $userAgent,
        'headers' => $assoc,
        'decompress' => true,
    ]);

    if (is_wp_error($response)) {
        return [null, $response->get_error_message(), 0];
    }
    $status = (int) wp_remote_retrieve_response_code($response);
    $body = (string) wp_remote_retrieve_body($response);
    return [$body, null, $status];
}

function cache_available(): bool
{
    return true;
}

function cache_get(string $key, int $ttl): ?string
{
    $value = get_transient('ksug_' . $key);
    return is_string($value) ? $value : null;
}

function cache_set(string $key, string $value, int $ttl): void
{
    set_transient('ksug_' . $key, $value, $ttl);
}

function strip_html(string $html): string
{
    return wp_strip_all_tags($html);
}
