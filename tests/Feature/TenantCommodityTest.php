<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Komoditas;
use App\Models\PerusahaanBuyer;
use App\Models\TenantCommodity;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantCommodityTest extends TestCase
{
    use RefreshDatabase;

    public function test_shrinkage_tolerance_falls_back_to_commodity_default(): void
    {
        $commodity = Komoditas::factory()->create(['default_shrinkage_tolerance' => 2.5]);

        $withoutOverride = TenantCommodity::factory()->create(['commodity_id' => $commodity->id]);
        $withOverride = TenantCommodity::factory()->create([
            'commodity_id' => $commodity->id,
            'shrinkage_tolerance' => 4,
        ]);

        $this->assertSame('2.50', $withoutOverride->effectiveShrinkageTolerance());
        $this->assertSame('4.00', $withOverride->effectiveShrinkageTolerance());
    }

    public function test_commodity_can_only_be_activated_once_per_tenant(): void
    {
        $tenant = PerusahaanBuyer::factory()->create();
        $commodity = Komoditas::factory()->create();

        TenantCommodity::factory()->create(['tenant_id' => $tenant->id, 'commodity_id' => $commodity->id]);

        $this->expectException(QueryException::class);

        TenantCommodity::factory()->create(['tenant_id' => $tenant->id, 'commodity_id' => $commodity->id]);
    }
}
