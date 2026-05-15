<?php

namespace App\Service\Forum;

class ContentModerationService
{
    private const BLOCKED_TERMS = [
        'worthless',
        'useless',
        'stupid',
        'idiot',
        'hate',
        'kill',
        'die',
        'merde',
        'putain',
        'connard',
        'salope',
        'bitch',
        'fuck',
        'shit',
    ];

    /**
     * Lightweight parity with the Java forum moderation fallback.
     *
     * @return string[] Human-readable flagged categories. Empty means allowed.
     */
    public function checkContent(?string $text): array
    {
        $text = trim((string) $text);
        if ($text === '') {
            return [];
        }

        $lower = mb_strtolower($text);
        foreach (self::BLOCKED_TERMS as $term) {
            if (preg_match('/\b' . preg_quote($term, '/') . '\b/u', $lower)) {
                return ['Offensive content'];
            }
        }

        return [];
    }
}
