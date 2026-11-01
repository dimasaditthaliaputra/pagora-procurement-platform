<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Komoditas;
use App\Models\PerusahaanBuyer;
use App\Models\TenantCommodity;
use Illuminate\Database\Seeder;

/**
 * Demo seeder: mengaktifkan semua komoditas aktif untuk setiap Tenant demo.
 * Idempotent: firstOrCreate dengan kunci (tenant_id, commodity_id).
 */
class TenantCommoditySeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $commodityIds = Komoditas::where('is_active', true)->pluck('id');

        PerusahaanBuyer::pluck('id')->each(function (int $tenantId) use ($commodityIds): void {
            foreach ($commodityIds as $commodityId) {
                TenantCommodity::firstOrCreate(
                    ['tenant_id' => $tenantId, 'commodity_id' => $commodityId],
                    ['is_active' => true]
                );
            }
        });
    }
}
