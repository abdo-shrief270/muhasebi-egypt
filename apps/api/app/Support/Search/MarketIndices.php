<?php

namespace App\Support\Search;

/**
 * The marketplace's search indices: what each holds and how Arabic is folded, split and matched.
 * Bump VERSION when a definition changes: `search:setup` then builds {name}_v{VERSION} beside the
 * live one and moves the alias once it's filled, so the site never searches an empty index.
 */
final class MarketIndices
{
    public const VERSION = 1;

    /** Offers: one document per (product variant × branch) a shop shows in the marketplace. */
    public const OFFERS = 'market_offers';

    /** The reference catalog (phones first): autocomplete, «سعره في كل المحلات» pages. */
    public const ITEMS = 'market_items';

    /** @return array<string, array<string, mixed>> logical name => settings + mappings */
    public static function all(): array
    {
        return [self::OFFERS => self::offers(), self::ITEMS => self::items()];
    }

    /** @return array<string, mixed> */
    public static function analysis(): array
    {
        return [
            'char_filter' => [
                // The same folding as Support\Text\SearchText, so the marketplace matches like the app.
                'ar_fold' => ['type' => 'mapping', 'mappings' => ['أ=>ا', 'إ=>ا', 'آ=>ا', 'ٱ=>ا', 'ة=>ه', 'ى=>ي', 'ؤ=>و', 'ئ=>ي', '\\u0640=>']],
            ],
            'filter' => [
                // a54 → a54, a, 54 · iphone15pro → iphone15pro, iphone, 15, pro · 128gb → 128gb, 128, gb
                'parts' => ['type' => 'word_delimiter', 'split_on_numerics' => true, 'split_on_case_change' => false, 'preserve_original' => true, 'generate_number_parts' => true, 'generate_word_parts' => true, 'catenate_all' => true],
                'ar_synonyms' => ['type' => 'synonym_graph', 'synonyms' => self::synonyms(), 'lenient' => true],
            ],
            'analyzer' => [
                // Index time: fold, split model numbers, no synonyms (they're applied when searching).
                'ar_text' => ['type' => 'custom', 'char_filter' => ['ar_fold'], 'tokenizer' => 'standard', 'filter' => ['lowercase', 'decimal_digit', 'arabic_normalization', 'parts', 'unique']],
                'ar_search' => ['type' => 'custom', 'char_filter' => ['ar_fold'], 'tokenizer' => 'standard', 'filter' => ['lowercase', 'decimal_digit', 'arabic_normalization', 'ar_synonyms']],
                'ar_prefix' => ['type' => 'custom', 'char_filter' => ['ar_fold'], 'tokenizer' => 'standard', 'filter' => ['lowercase', 'decimal_digit', 'arabic_normalization']],
            ],
            'normalizer' => [
                'ar_keyword' => ['type' => 'custom', 'char_filter' => ['ar_fold'], 'filter' => ['lowercase', 'decimal_digit', 'arabic_normalization']],
            ],
        ];
    }

    /** @return list<string> the lines of resources/search/synonyms_ar.txt */
    public static function synonyms(): array
    {
        $lines = file(resource_path('search/synonyms_ar.txt'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        return array_values(array_filter(array_map('trim', $lines), fn (string $l) => $l !== '' && ! str_starts_with($l, '#')));
    }

    /** @return array<string, mixed> */
    private static function text(bool $suggest = false): array
    {
        $field = ['type' => 'text', 'analyzer' => 'ar_text', 'search_analyzer' => 'ar_search', 'fields' => ['raw' => ['type' => 'keyword', 'normalizer' => 'ar_keyword', 'ignore_above' => 256]]];
        if ($suggest) {
            $field['fields']['suggest'] = ['type' => 'search_as_you_type', 'analyzer' => 'ar_prefix'];
        }

        return $field;
    }

    /** @return array<string, mixed> */
    private static function settings(): array
    {
        // One node to start with: one shard, no replicas (raise replicas when a second node joins).
        return ['number_of_shards' => 1, 'number_of_replicas' => 0, 'refresh_interval' => '2s', 'max_ngram_diff' => 3, 'analysis' => self::analysis()];
    }

    /** @return array<string, mixed> */
    private static function offers(): array
    {
        return ['settings' => self::settings(), 'mappings' => ['dynamic' => 'strict', 'properties' => [
            'tenant_id' => ['type' => 'keyword'],
            'branch_id' => ['type' => 'keyword'],
            'product_id' => ['type' => 'keyword'],
            'variant_id' => ['type' => 'keyword'],
            'item_id' => ['type' => 'keyword'],              // the reference catalog item it's matched to, if any
            'title' => self::text(true),                     // «جراب سيليكون iPhone 15 — أسود»
            'description' => ['type' => 'text', 'analyzer' => 'ar_text', 'search_analyzer' => 'ar_search'],
            'brand' => self::text(),
            'models' => self::text(),                        // compatible phones («iPhone 15», «Galaxy A54»)
            'category' => ['type' => 'keyword'],            // reference category key
            'category_name' => self::text(),
            'condition' => ['type' => 'keyword'],           // new | used
            'grade' => ['type' => 'keyword'],               // A | B | C (used)
            'battery_health' => ['type' => 'byte'],
            'storage_gb' => ['type' => 'short'],
            'color' => ['type' => 'keyword', 'normalizer' => 'ar_keyword'],
            'price' => ['type' => 'long'],                  // piasters
            'old_price' => ['type' => 'long'],
            'availability' => ['type' => 'keyword'],        // in | low | out
            'image' => ['type' => 'keyword', 'index' => false],
            'images' => ['type' => 'integer'],
            'shop' => ['properties' => [
                'slug' => ['type' => 'keyword'],
                'name' => self::text(),
                'rating' => ['type' => 'half_float'],
                'reviews' => ['type' => 'integer'],
                'response' => ['type' => 'half_float'],      // 0..1: how reliably it answers orders on time
                'delivers' => ['type' => 'boolean'],
                'verified' => ['type' => 'boolean'],
            ]],
            'location' => ['type' => 'geo_point'],
            'governorate' => ['type' => 'keyword'],
            'area' => ['type' => 'keyword', 'normalizer' => 'ar_keyword'],
            'listed_at' => ['type' => 'date'],
            'updated_at' => ['type' => 'date'],
        ]]];
    }

    /** @return array<string, mixed> */
    private static function items(): array
    {
        return ['settings' => self::settings(), 'mappings' => ['dynamic' => 'strict', 'properties' => [
            'slug' => ['type' => 'keyword'],
            'kind' => ['type' => 'keyword'],                // phone | accessory | part
            'brand' => self::text(),
            'series' => ['type' => 'keyword'],
            'model' => self::text(true),                     // «iPhone 15 Pro Max»
            'aliases' => self::text(true),                   // «ايفون 15 برو ماكس»
            'year' => ['type' => 'short'],
            'storage_gb' => ['type' => 'short'],
            'network' => ['type' => 'keyword'],
            'image' => ['type' => 'keyword', 'index' => false],
            // filled from the offers, refreshed with them
            'offers' => ['type' => 'integer'],
            'shops' => ['type' => 'integer'],
            'min_price' => ['type' => 'long'],
            'max_price' => ['type' => 'long'],
            'used_offers' => ['type' => 'integer'],
            'used_min_price' => ['type' => 'long'],
            'updated_at' => ['type' => 'date'],
        ]]];
    }
}
