<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Komoditas;
use App\Models\PerusahaanBuyer;
use App\Models\TenantCommodity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantCommodity>
 */
class TenantCommodityFactory extends Factory
{
    protected $model = TenantCommodity::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => PerusahaanBuyer::factory(),
            'commodity_id' => Komoditas::factory(),
            'is_active' => true,
            'shrinkage_tolerance' => null,
        ];
    }
}
