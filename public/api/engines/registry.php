<?php
/**
 * Engine id => adapter file. Keep ids in sync with src/engines.ts.
 * To add a source: drop a file in this folder and register it here.
 */

declare(strict_types=1);

return [
    'google' => 'google.php',
    'youtube' => 'youtube.php',
    'bing' => 'bing.php',
    'yahoo' => 'yahoo.php',
    'duckduckgo' => 'duckduckgo.php',
    'qwant' => 'qwant.php',
    'yandex' => 'yandex.php',
    'amazon' => 'amazon.php',
    'ebay' => 'ebay.php',
    'wikipedia' => 'wikipedia.php',
    'appstore' => 'appstore.php',
];
