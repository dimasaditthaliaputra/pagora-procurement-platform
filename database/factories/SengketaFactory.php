<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PemicuSengketa;
use App\Enums\StatusSengketa;
use App\Models\Sengketa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sengketa>
 */
class SengketaFactory extends Factory
{
    protected $model = Sengketa::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_order_id' => null,
            'purchase_order_item_id' => null,
            'invoice_id' => null,
            'trigger_type' => PemicuSengketa::GoodsMismatch->value,
            'reason' => 'Kondisi fisik bahan baku yang diterima di gudang mengalami kerusakan kemasan.',
            'dibuka_oleh' => User::factory(),
            'status' => StatusSengketa::Terbuka,
            'tanggal_dibuka' => now(),
            'tanggal_selesai' => null,
        ];
    }

    /**
     * Indicate that the dispute is currently in mediation.
     */
    public function inMediation(): static
    {
        return $this->state(fn (): array => [
            'status' => StatusSengketa::DalamMediasi,
        ]);
    }

    /**
     * Indicate that the dispute has been resolved.
     */
    public function resolved(): static
    {
        return $this->state(fn (): array => [
            'status' => StatusSengketa::Selesai,
            'tanggal_selesai' => now(),
        ]);
    }
}
