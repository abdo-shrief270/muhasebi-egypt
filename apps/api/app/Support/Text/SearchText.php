<?php

declare(strict_types=1);

namespace App\Support\Text;

/**
 * Normalises text for search so that spelling variants match: أ/إ/آ → ا, ة → ه, ى → ي,
 * no tashkeel or tatweel, lower case, single spaces.
 */
final class SearchText
{
    public static function normalize(string $text): string
    {
        $text = preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $text) ?? $text;
        $text = strtr($text, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي']);
        $text = mb_strtolower($text);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * @return list<string>
     */
    public static function tokens(string $query): array
    {
        $normalized = self::normalize($query);

        return $normalized === '' ? [] : array_values(array_unique(explode(' ', $normalized)));
    }

    /** Escapes LIKE wildcards in user input. */
    public static function like(string $token): string
    {
        return '%'.addcslashes($token, '%_\\').'%';
    }
}
