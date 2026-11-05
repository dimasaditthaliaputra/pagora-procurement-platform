<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PenerimaanBarang;
use App\Models\Pengiriman;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PenerimaanBarang>
 */
class PenerimaanBarangFactory extends Factory
{
    protected $model = PenerimaanBarang::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => PurchaseOrder::query(),
            'shipment_id' => Pengiriman::factory()->arrived(),
            'inspected_by' => User::factory(),
            'matched_plate_number' => strtoupper(fake()->bothify('? #### ??')),
            'inspected_at' => now(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}

