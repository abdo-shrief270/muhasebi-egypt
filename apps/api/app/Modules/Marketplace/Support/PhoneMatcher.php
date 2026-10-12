<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Support;

use App\Modules\Marketplace\Contracts\PhoneCatalog;
use App\Support\Text\SearchText;
use Illuminate\Support\Facades\Cache;

/**
 * Finds which phone of the reference catalog a shop's item is, from its name (and brand), the way
 * a person would: every word of the model's name (or one of its Arabic names) is in it, and it
 * doesn't name a bigger model («Pro», «Max»…) the phone isn't. The most specific name wins; a tie
 * between two phones is no match (the shop picks).
 */
final class PhoneMatcher
{
    public const CACHE = 'market:phones:match:v2';

    /** Words that say which model: a name carrying one the phone lacks is a different phone. */
    private const MODIFIERS = ['pro', 'max', 'plus', 'ultra', 'lite', 'mini', 'fe', 'prime', 'neo', 'edge', 'fold', 'flip', 'air', 'turbo', 'power', 'play', 'note', 'go', 'e', 'c', 'x', 's', 'r'];

    /** Words that may be left out of a shop's name: «Samsung A55» is the «Galaxy A55 5G». */
    private const OPTIONAL = ['galaxy', '5g', '4g'];

    /** Arabic spellings shops type → the catalog's words. */
    private const WORDS = [
        'ايفون' => 'iphone', 'ايفوون' => 'iphone', 'سامسونج' => 'samsung', 'سامسونح' => 'samsung', 'جالاكسي' => 'galaxy', 'جلاكسي' => 'galaxy',
        'شاومي' => 'xiaomi', 'شياومي' => 'xiaomi', 'ريدمي' => 'redmi', 'رديمي' => 'redmi', 'بوكو' => 'poco', 'نوت' => 'note',
        'اوبو' => 'oppo', 'ريلمي' => 'realme', 'ريلم' => 'realme', 'فيفو' => 'vivo', 'انفينكس' => 'infinix', 'انفنكس' => 'infinix',
        'تكنو' => 'tecno', 'تيكنو' => 'tecno', 'هونر' => 'honor', 'هونور' => 'honor', 'هواوي' => 'huawei', 'نوكيا' => 'nokia',
        'موتورولا' => 'motorola', 'موتو' => 'moto', 'بيكسل' => 'pixel', 'جوجل' => 'google', 'ايتل' => 'itel', 'ونبلس' => 'oneplus',
        'برو' => 'pro', 'ماكس' => 'max', 'بلس' => 'plus', 'الترا' => 'ultra', 'لايت' => 'lite', 'ميني' => 'mini', 'اير' => 'air',
        'سي' => 'c', 'اس' => 's', 'اكس' => 'x', 'ايه' => 'a', 'هوت' => 'hot', 'سمارت' => 'smart', 'سبارك' => 'spark', 'كامون' => 'camon', 'بوفا' => 'pova', 'نوفا' => 'nova',
    ];

    /** @var list<array{phone: array<string, mixed>, names: list<array{required: list<string>, optional: list<string>, tokens: list<string>}>, brand: string}>|null */
    private ?array $entries = null;

    public function __construct(private readonly PhoneCatalog $catalog) {}

    /**
     * The phone an item is, when sure. Storage comes from «128GB», «256 جيجا», «8/256».
     *
     * @return array{phone: array<string, mixed>, storage_gb: int|null}|null
     */
    public function match(string $name, ?string $brand = null): ?array
    {
        $ranked = $this->rank($name, $brand);
        if ($ranked === [] || (isset($ranked[1]) && $ranked[1]['score'] === $ranked[0]['score'])) {
            return null;
        }

        return ['phone' => $ranked[0]['phone'], 'storage_gb' => self::storage($name, $ranked[0]['phone']['storage_gb'])];
    }

    /**
     * The likeliest phones, best first (what the shop's screen suggests).
     *
     * @return list<array<string, mixed>>
     */
    public function suggest(string $name, ?string $brand = null, int $limit = 3): array
    {
        return array_slice(array_column($this->rank($name, $brand), 'phone'), 0, $limit);
    }

    /** @return list<array{phone: array<string, mixed>, score: int}> */
    private function rank(string $name, ?string $brand): array
    {
        $tokens = self::tokens($name);
        if ($tokens === []) {
            return [];
        }
        $have = array_flip($tokens);
        $brandKey = $brand !== null ? self::brandKey(self::tokens($brand)[0] ?? '') : null;
        $brandKey = $brandKey !== null && self::knownBrand($brandKey) ? $brandKey : null;
        // A brand named in the item's name («Oppo A55») rules out the others' «A55».
        $named = array_values(array_unique(array_filter(array_map(self::brandKey(...), $tokens), self::knownBrand(...))));

        $found = [];
        foreach ($this->entries() as $entry) {
            if (($brandKey !== null && $brandKey !== $entry['brand']) || ($named !== [] && ! in_array($entry['brand'], $named, true))) {
                continue;
            }
            $best = null;
            foreach ($entry['names'] as $n) {
                if (array_diff($n['required'], $tokens) !== []) {
                    continue;
                }
                // A word that names a bigger / different model than this one.
                $known = array_flip($n['tokens']);
                foreach (self::MODIFIERS as $m) {
                    if (isset($have[$m]) && ! isset($known[$m])) {
                        continue 2;
                    }
                }
                $score = count($n['required']) * 10 + count(array_intersect($n['optional'], $tokens)) * 2 - count(array_diff($n['optional'], $tokens));
                $best = max($best ?? PHP_INT_MIN, $score);
            }
            if ($best !== null) {
                $found[] = ['phone' => $entry['phone'], 'score' => $best];
            }
        }
        usort($found, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return $found;
    }

    /** @return list<array{phone: array<string, mixed>, names: list<array{required: list<string>, optional: list<string>, tokens: list<string>}>, brand: string}> */
    private function entries(): array
    {
        return $this->entries ??= Cache::remember(self::CACHE, 600, function (): array {
            $entries = [];
            foreach ($this->catalog->published() as $phone) {
                $brand = self::brandKey(self::tokens($phone['brand'])[0] ?? '');
                $names = [];
                foreach ([$phone['model'], ...$phone['aliases']] as $label) {
                    $tokens = array_values(array_filter(self::tokens($label), fn (string $t) => $t !== $brand));
                    if ($tokens === []) {
                        continue;
                    }
                    $required = array_values(array_diff($tokens, self::OPTIONAL));
                    if ($required === [] || $required === ['iphone']) {
                        continue;
                    }
                    $names[] = ['required' => $required, 'optional' => array_values(array_intersect($tokens, self::OPTIONAL)), 'tokens' => $tokens];
                }
                $entries[] = [
                    'phone' => array_intersect_key($phone, array_flip(['id', 'slug', 'brand', 'model', 'name', 'storage_gb'])),
                    'names' => $names,
                    'brand' => $brand,
                ];
            }

            return $entries;
        });
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE);
    }

    /**
     * Folded words: Arabic spellings to the catalog's, «iPhone15» → iphone 15, «A55» stays one
     * word (that's how models are written), storage («128GB») left out.
     *
     * @return list<string>
     */
    public static function tokens(string $text): array
    {
        $text = SearchText::normalize($text);
        $text = str_replace(['+', 'ـ'], [' plus ', ''], $text);
        // «iphone15» → «iphone 15», but «a55», «s24», «13c» keep their shape.
        $text = preg_replace('/([a-z]{3,})(\d)/u', '$1 $2', $text) ?? $text;
        $words = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($words as $w) {
            if ((preg_match('/^\d+(gb|g|tb|t)$/u', $w) && ! preg_match('/^[2-5]g$/', $w)) || in_array($w, ['gb', 'tb', 'جيجا', 'جيجابايت', 'تيرا', 'رام', 'ram'], true)) {
                continue;
            }
            $w = self::WORDS[$w] ?? $w;
            // «ال» before a known word: «الايفون».
            if (str_starts_with($w, 'ال') && isset(self::WORDS[mb_substr($w, 2)])) {
                $w = self::WORDS[mb_substr($w, 2)];
            }
            // «سي 67», «A 55» → «c67», «a55»: how the catalog writes them.
            $last = array_key_last($out);
            if ($last !== null && preg_match('/^[a-z]$/', $out[$last]) && preg_match('/^\d{1,3}[a-z]{0,2}$/', $w)) {
                $out[$last] .= $w;

                continue;
            }
            $out[] = $w;
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  list<int>  $options  the phone's usual storage sizes
     */
    public static function storage(string $text, array $options = []): ?int
    {
        $text = SearchText::normalize($text);
        $found = [];
        if (preg_match_all('/(\d{1,4})\s*(?:gb|g|جيجابايت|جيجا بايت|جيجا)(?!\p{L})/u', $text, $m)) {
            $found = array_map('intval', $m[1]);
        }
        if (preg_match_all('/(\d)\s*(?:tb|t|تيرا)(?!\p{L})/u', $text, $m)) {
            $found = [...$found, ...array_map(fn ($n) => (int) $n * 1024, $m[1])];
        }
        // «8/256»: RAM / storage.
        if (preg_match_all('/\b\d{1,2}\s*\/\s*(\d{2,4})\b/u', $text, $m)) {
            $found = [...$found, ...array_map('intval', $m[1])];
        }
        $sizes = [16, 32, 64, 128, 256, 512, 1024, 2048];
        // A bare size the phone comes in: «S24 Ultra 512».
        if ($found === [] && $options !== [] && preg_match_all('/(?<![\p{L}\d])(\d{2,4})(?![\p{L}\d])/u', $text, $m)) {
            $found = array_values(array_filter(array_map('intval', $m[1]), fn (int $n) => $n >= 32));
        }
        foreach (array_reverse($found) as $gb) {
            if (in_array($gb, $options, true) || ($options === [] && in_array($gb, $sizes, true))) {
                return $gb;
            }
        }
        foreach (array_reverse($found) as $gb) {
            if ($gb >= 32 && in_array($gb, $sizes, true)) {
                return $gb;
            }
        }

        return null;
    }

    private static function knownBrand(string $key): bool
    {
        return in_array($key, ['apple', 'samsung', 'xiaomi', 'oppo', 'realme', 'vivo', 'infinix', 'tecno', 'honor', 'huawei', 'nokia', 'motorola', 'itel', 'google', 'oneplus'], true);
    }

    /** Sub-brands sold under their maker in the catalog. */
    private static function brandKey(string $key): string
    {
        return match ($key) {
            'redmi', 'poco', 'mi' => 'xiaomi',
            'iphone' => 'apple',
            'pixel' => 'google',
            'moto' => 'motorola',
            default => $key,
        };
    }
}
