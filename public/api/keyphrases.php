<?php
/**
 * Seed keyword extraction from free text (title + body).
 *
 * Pure PHP, no dependencies. Scores 1-3 word phrases by frequency, boosts
 * phrases that appear in the title, and drops stopwords and noise.
 * Used by the WordPress and OpenCart plugins to propose seeds from the
 * content being edited.
 */

declare(strict_types=1);

namespace KeywordSuggest;

/** Stopwords allowed in the middle of a phrase ("shoes for men", "cost of living"). */
const KS_CONNECTORS = ['for', 'of', 'and', 'in', 'to', 'with', 'on', 'vs'];

const KS_STOPWORDS = [
    'a', 'an', 'the', 'and', 'or', 'but', 'if', 'then', 'else', 'when', 'while', 'for', 'to', 'of', 'in', 'on', 'at', 'by',
    'with', 'from', 'as', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'it', 'its', 'this', 'that', 'these', 'those',
    'you', 'your', 'yours', 'we', 'our', 'ours', 'they', 'their', 'them', 'he', 'she', 'his', 'her', 'i', 'me', 'my', 'us',
    'can', 'could', 'will', 'would', 'should', 'may', 'might', 'must', 'shall', 'do', 'does', 'did', 'done', 'have', 'has',
    'had', 'not', 'no', 'yes', 'so', 'than', 'too', 'very', 'just', 'also', 'into', 'over', 'under', 'about', 'after',
    'before', 'up', 'down', 'out', 'off', 'all', 'any', 'some', 'each', 'more', 'most', 'other', 'such', 'only', 'own',
    'same', 'here', 'there', 'where', 'how', 'what', 'which', 'who', 'whom', 'why', 'one', 'two', 'new', 'get', 'got',
    'make', 'made', 'use', 'used', 'using', 'like', 'via', 'per', 'etc', 'nbsp', 'amp', 'quot', 'www', 'http', 'https',
    'com', 'html', 'click', 'read', 'see', 'page', 'post', 'free', 'best', 'top',
];

/**
 * @return string[] seed phrases, best first
 */
function extract_keyphrases(string $title, string $body, int $limit = 8): array
{
    $stop = array_flip(KS_STOPWORDS);
    $connector = array_flip(KS_CONNECTORS);

    // Split into clauses on punctuation and block tags so phrases never span a sentence boundary.
    $clauses = static function (string $html): array {
        $html = preg_replace('/<(script|style)[^>]*>.*?<\/\1>/is', ' ', $html) ?? '';
        $html = preg_replace('/<\/?(p|div|br|li|ul|ol|h[1-6]|tr|td|th|blockquote|section|article)[^>]*>/i', ' . ', $html) ?? '';
        $text = html_entity_decode(strip_html($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = mb_strtolower($text);
        $parts = preg_split('/[.!?;:,()\[\]{}"|\/\n\r]+|\s[-\x{2013}\x{2014}]\s/u', $text) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $part = preg_replace('/[^\p{L}\p{N}\s\'-]+/u', ' ', $part) ?? '';
            $part = trim(preg_replace('/\s+/u', ' ', $part) ?? '');
            if ($part !== '') {
                $out[] = $part;
            }
        }
        return $out;
    };

    $titleClauses = $clauses($title);
    $bodyClauses = $clauses($body);
    $titleText = implode(' ', $titleClauses);

    $tokenise = static function (string $text): array {
        $tokens = $text === '' ? [] : explode(' ', $text);
        return array_values(array_filter($tokens, static fn($t) => $t !== '' && mb_strlen($t) >= 2 && !is_numeric($t)));
    };

    /** @var array<string, float> $scores */
    $scores = [];
    /** @var array<string, int> $counts */
    $counts = [];

    $addGrams = static function (array $tokens, float $weight) use (&$scores, &$counts, $stop, $connector): void {
        $n = count($tokens);
        for ($len = 1; $len <= 3; $len++) {
            for ($i = 0; $i + $len <= $n; $i++) {
                $gram = array_slice($tokens, $i, $len);
                // Phrases may not start or end with a stopword, and may only contain a connector in the middle.
                if (isset($stop[$gram[0]]) || isset($stop[$gram[$len - 1]])) {
                    continue;
                }
                if ($len === 3 && isset($stop[$gram[1]]) && !isset($connector[$gram[1]])) {
                    continue;
                }
                $phrase = implode(' ', $gram);
                if (mb_strlen($phrase) < 3) {
                    continue;
                }
                // Longer phrases are rarer, so give them a boost per extra word.
                $scores[$phrase] = ($scores[$phrase] ?? 0) + $weight * (1 + 0.6 * ($len - 1));
                $counts[$phrase] = ($counts[$phrase] ?? 0) + 1;
            }
        }
    };

    foreach ($bodyClauses as $clause) {
        $addGrams($tokenise($clause), 1.0);
    }
    foreach ($titleClauses as $clause) {
        $addGrams($tokenise($clause), 4.0);
    }

    if (!$scores) {
        return [];
    }

    // A phrase that occurs only once is noise; keep it only if it is in the title.
    $titleSet = [];
    foreach (array_keys($scores) as $phrase) {
        if ($titleText !== '' && str_contains(" $titleText ", " $phrase ")) {
            $titleSet[$phrase] = true;
        }
    }
    foreach ($scores as $phrase => $score) {
        if ($counts[$phrase] < 2 && !isset($titleSet[$phrase])) {
            unset($scores[$phrase]);
        }
    }

    arsort($scores);

    // Prefer longer phrases over the unigrams they contain when scores are close.
    $out = [];
    foreach (array_keys($scores) as $phrase) {
        $redundant = false;
        foreach ($out as $kept) {
            if ($kept !== $phrase && (str_contains(" $kept ", " $phrase ") || str_contains(" $phrase ", " $kept "))) {
                // Keep both only if the shorter one is clearly stronger on its own.
                $shorter = mb_strlen($kept) < mb_strlen($phrase) ? $kept : $phrase;
                $longer = $shorter === $kept ? $phrase : $kept;
                if ($scores[$shorter] < $scores[$longer] * 1.5) {
                    $redundant = true;
                    break;
                }
            }
        }
        if (!$redundant) {
            $out[] = $phrase;
        }
        if (count($out) >= $limit) {
            break;
        }
    }

    return $out;
}
