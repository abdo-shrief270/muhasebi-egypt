<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Enums\CategoryType;
use App\Support\Text\SearchText;
use Illuminate\Support\Facades\DB;

/**
 * The starter catalog every shop gets: categories plus the brands and models that sell in Egypt,
 * so the first product can be linked to "iPhone 13" without typing the model list first.
 * Shops edit all of it freely afterwards.
 */
final class DefaultCatalog
{
    /**
     * @return array<string, CategoryType>
     */
    public static function categories(): array
    {
        return [
            'جرابات' => CategoryType::Accessory,
            'سكرينات حماية' => CategoryType::Accessory,
            'شواحن' => CategoryType::Accessory,
            'كابلات' => CategoryType::Accessory,
            'سماعات' => CategoryType::Accessory,
            'باور بانك' => CategoryType::Accessory,
            'ساعات وأساور ذكية' => CategoryType::Accessory,
            'كروت ميموري وفلاشات' => CategoryType::Accessory,
            'حوامل وإكسسوارات عربية' => CategoryType::Accessory,
            'شاشات' => CategoryType::Part,
            'بطاريات' => CategoryType::Part,
            'بوردات وسوكت شحن' => CategoryType::Part,
            'فلاتات' => CategoryType::Part,
            'كاميرات' => CategoryType::Part,
            'ضهر وفريمات' => CategoryType::Part,
            'سماعات داخلية ومايكات' => CategoryType::Part,
            'موبايلات جديدة' => CategoryType::Device,
            'تابلت' => CategoryType::Device,
            'متنوع' => CategoryType::Other,
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function brands(): array
    {
        return [
            'Apple' => [
                'iPhone 11', 'iPhone 11 Pro', 'iPhone 11 Pro Max', 'iPhone 12', 'iPhone 12 Pro', 'iPhone 12 Pro Max',
                'iPhone 13', 'iPhone 13 Pro', 'iPhone 13 Pro Max', 'iPhone 14', 'iPhone 14 Plus', 'iPhone 14 Pro',
                'iPhone 14 Pro Max', 'iPhone 15', 'iPhone 15 Plus', 'iPhone 15 Pro', 'iPhone 15 Pro Max',
                'iPhone 16', 'iPhone 16 Pro', 'iPhone 16 Pro Max', 'iPhone X', 'iPhone XR', 'iPhone XS Max',
            ],
            'Samsung' => [
                'Galaxy A05', 'Galaxy A05s', 'Galaxy A06', 'Galaxy A14', 'Galaxy A15', 'Galaxy A16', 'Galaxy A24',
                'Galaxy A25', 'Galaxy A34', 'Galaxy A35', 'Galaxy A54', 'Galaxy A55', 'Galaxy S23', 'Galaxy S23 Ultra',
                'Galaxy S24', 'Galaxy S24 Ultra', 'Galaxy S25 Ultra',
            ],
            'Oppo' => ['A17', 'A18', 'A38', 'A58', 'A78', 'A79', 'A3', 'A3x', 'Reno 10', 'Reno 11', 'Reno 12'],
            'Xiaomi' => [
                'Redmi 12', 'Redmi 13', 'Redmi 13C', 'Redmi A3', 'Redmi Note 12', 'Redmi Note 13',
                'Redmi Note 13 Pro', 'Redmi Note 14', 'Poco X6 Pro', 'Poco F6',
            ],
            'Realme' => ['C53', 'C55', 'C61', 'C63', 'C67', 'Note 50', 'Realme 11', 'Realme 12', 'Realme 12 Pro'],
            'Vivo' => ['Y03', 'Y17s', 'Y18', 'Y27', 'Y28', 'Y36', 'V29', 'V30'],
            'Infinix' => ['Hot 30', 'Hot 40', 'Hot 40i', 'Hot 50', 'Smart 8', 'Smart 9', 'Note 30', 'Note 40', 'Zero 30'],
            'Tecno' => ['Spark 10', 'Spark 20', 'Spark 20 Pro', 'Spark Go 2024', 'Camon 20', 'Camon 30', 'Pova 6'],
            'Honor' => ['X6b', 'X7b', 'X8b', 'X9b', 'Honor 90', 'Honor 200'],
            'Huawei' => ['Nova 11', 'Nova 12', 'Y9a', 'Nova Y70', 'Nova Y90'],
            'Nokia' => ['C12', 'C22', 'C32', 'G22'],
        ];
    }

    /**
     * Inserts the starter catalog for a shop that has none yet. Plain queries, so the
     * migration can call it for shops that existed before the catalog did.
     */
    public static function seed(string $tenantId): void
    {
        if (DB::table('categories')->where('tenant_id', $tenantId)->exists()) {
            return;
        }

        $now = now();
        $sort = 0;

        DB::table('categories')->insert(array_map(function (string $name, CategoryType $type) use ($tenantId, $now, &$sort): array {
            return ['tenant_id' => $tenantId, 'name' => $name, 'type' => $type->value, 'sort' => $sort++, 'created_at' => $now, 'updated_at' => $now];
        }, array_keys(self::categories()), self::categories()));

        foreach (array_keys(self::brands()) as $brandSort => $brand) {
            $brandId = DB::table('brands')->insertGetId([
                'tenant_id' => $tenantId, 'name' => $brand, 'sort' => $brandSort, 'created_at' => $now, 'updated_at' => $now,
            ]);

            DB::table('device_models')->insert(array_map(fn (string $model): array => [
                'tenant_id' => $tenantId,
                'brand_id' => $brandId,
                'name' => $model,
                'search_name' => SearchText::normalize("{$brand} {$model}"),
                'created_at' => $now,
                'updated_at' => $now,
            ], self::brands()[$brand]));
        }
    }
}
