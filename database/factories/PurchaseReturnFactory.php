<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PurchaseReturnStatus;
use App\Models\PurchaseReturn;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pass `purchase_order_id` and `dispute_id` explicitly; there are no PO factories yet.
 *
 * @extends Factory<PurchaseReturn>
 */
class PurchaseReturnFactory extends Factory
{
    protected $model = PurchaseReturn::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'return_number' => 'RTR-'.now()->format('Ymd').'-'.fake()->unique()->numerify('####'),
            'return_date' => now()->toDateString(),
            'reason' => 'Barang ditolak QC dikembalikan ke Vendor.',
            'status' => PurchaseReturnStatus::AwaitingVendorConfirmation,
            'created_by' => User::factory(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (): array => [
            'status' => PurchaseReturnStatus::Confirmed,
            'vendor_confirmed_at' => now(),
        ]);
    }
}
