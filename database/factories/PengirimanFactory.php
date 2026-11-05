<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatusPengiriman;
use App\Models\Pengiriman;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Pengiriman>
 */
class PengirimanFactory extends Factory
{
    protected $model = Pengiriman::class;

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
            'dispute_id' => null,
            'route' => fake()->randomElement(StatusPengiriman::routes()),
            'carrier_name' => fake()->randomElement(['Wahana Express', 'JNE Cargo', 'Indah Logistik', 'Armada Internal Vendor']),
            'vehicle_plate_number' => strtoupper(fake()->bothify('? #### ??')),
            'estimated_arrival_date' => now()->addDays(2)->toDateString(),
            'status' => StatusPengiriman::InTransit,
            'reported_by' => User::factory(),
        ];
    }

    /**
     * Mark the shipment as arrived at the destination warehouse.
     */
    public function arrived(): static
    {
        return $this->state(fn (): array => [
            'status' => StatusPengiriman::Arrived,
        ]);
    }
}
