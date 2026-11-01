<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CommodityCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Reference seeder: harus jalan di semua environment termasuk production.
 * Idempotent: firstOrCreate dengan kunci 'name'. Harus jalan sebelum KomoditasSeeder.
 */
class CommodityCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Pangan Pokok',
            'Bumbu & Rempah',
            'Daging & Unggas',
            'Sayuran & Buah',
            'Minyak & Lemak',
            'Gula & Pemanis',
            'Tepung & Olahan',
            'Lainnya',
        ];

        foreach ($categories as $name) {
            CommodityCategory::firstOrCreate(
                ['name' => $name],
                ['uuid' => (string) Str::uuid(), 'is_active' => true]
            );
        }
    }
}
