<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatusItemPenerimaan;
use App\Models\PenerimaanBarang;
use App\Models\PenerimaanBarangItem;
use App\Models\PoItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PenerimaanBarangItem>
 */
class PenerimaanBarangItemFactory extends Factory
{
    protected $model = PenerimaanBarangItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'goods_receipt_id' => PenerimaanBarang::factory(),
            'purchase_order_item_id' => PoItem::query(),
            'received_quantity' => 100.000,
            'status' => StatusItemPenerimaan::Accepted,
            'notes' => null,
            'rejection_photos' => null,
        ];
    }

    /**
     * Mark item as rejected with mandatory note and immutable photo evidence.
     */
    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'received_quantity' => 0,
            'status' => StatusItemPenerimaan::Rejected,
            'notes' => 'Kondisi fisik barang rusak dan tidak sesuai spesifikasi pesanan.',
            'rejection_photos' => ['goods-receipts/rejections/demo-bukti-1.jpg'],
        ]);
    }
}

