<?php
/**
 * Minimal test runner for the shared PHP library (no PHPUnit dependency).
 * Run: php tests/php/keyphrases_test.php
 */

declare(strict_types=1);

require __DIR__ . '/../../public/api/lib.php';
require __DIR__ . '/../../public/api/keyphrases.php';

use function KeywordSuggest\extract_keyphrases;
use function KeywordSuggest\normalise_suggestions;
use function KeywordSuggest\parse_opensearch;

$failures = 0;
function check(string $name, bool $ok, string $detail = ''): void
{
    global $failures;
    echo ($ok ? 'PASS' : 'FAIL') . "  $name" . ($ok || $detail === '' ? '' : "  ($detail)") . "\n";
    if (!$ok) {
        $failures++;
    }
}

// --- extract_keyphrases ------------------------------------------------------

$seeds = extract_keyphrases(
    'Best Running Shoes for Flat Feet in 2026',
    '<p>Looking for running shoes that support flat feet? We tested 20 pairs of running shoes from Brooks, Asics and Nike. '
    . 'Stability running shoes with arch support help flat feet runners avoid overpronation.</p>'
    . '<p>Our top pick for flat feet is the Brooks Adrenaline. Arch support matters most.</p>'
);
check('title phrases rank first', array_slice($seeds, 0, 2) === ['running shoes', 'flat feet'], implode(' | ', $seeds));
check('repeated body phrase included', in_array('arch support', $seeds, true), implode(' | ', $seeds));
check('no phrase spans a sentence boundary', !in_array('feet we tested', $seeds, true) && !in_array('overpronation our', $seeds, true));
check('no leading/trailing stopwords', !array_filter($seeds, static fn($s) => preg_match('/^(the|for|of|and|a|in)\s|\s(the|for|of|and|a|in)$/', $s)));
check('limit respected', count(extract_keyphrases('a b c', str_repeat('alpha beta gamma delta. ', 20), 3)) <= 3);
check('empty input gives empty list', extract_keyphrases('', '') === []);
check('title only', extract_keyphrases('Hello World', '') === ['hello world']);
check('inline style attributes are not words', !array_intersect(extract_keyphrases('Apple Cinema 30"', '<p style="font-size: 12px">Apple Cinema display</p><p style="font-size: 12px">Apple Cinema</p>'), ['font', 'size', 'font-size']));
check('script/style content ignored', !in_array('alert', extract_keyphrases('Test', '<script>alert(1); alert(2); alert(3)</script><p>Test content. Test content.</p>'), true));

// --- normalise_suggestions ---------------------------------------------------

$n = normalise_suggestions(['Shoes', ' shoes ', '<b>shoes</b> for men', 'http://x', '', 'Shoes &amp; boots', 42]);
check('normalise dedupes case/space-insensitively', $n === ['Shoes', 'shoes for men', 'Shoes & boots'], json_encode($n));
check('normalise respects limit', count(normalise_suggestions(range(1, 100) ? array_map('strval', range(1, 100)) : [], 10)) === 10);

// --- parse_opensearch --------------------------------------------------------

check('parse_opensearch returns second element', parse_opensearch('["q",["a","b"]]') === ['a', 'b']);
try {
    parse_opensearch('{"nope":1}');
    check('parse_opensearch rejects bad shape', false);
} catch (RuntimeException $e) {
    check('parse_opensearch rejects bad shape', true);
}

echo $failures === 0 ? "\nAll PHP tests passed.\n" : "\n$failures PHP test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
