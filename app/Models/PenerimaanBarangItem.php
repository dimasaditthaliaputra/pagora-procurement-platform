<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatusItemPenerimaan;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'uuid',
    'goods_receipt_id',
    'purchase_order_item_id',
    'received_quantity',
    'status',
    'notes',
    'rejection_photos',
])]
#[Hidden([
    'id',
    'goods_receipt_id',
    'purchase_order_item_id',
])]
class PenerimaanBarangItem extends Model
{
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'goods_receipt_items';

    /**
     * Columns that store UUIDs.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Get route key name for model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'received_quantity' => 'decimal:3',
            'status' => StatusItemPenerimaan::class,
            'rejection_photos' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Inspection evidence and item records are immutable once stored (BR-17, RULES §9.4).
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new \LogicException('Hasil inspeksi penerimaan barang bersifat immutable dan tidak dapat diubah.');
    }

    /**
     * Inspection evidence and item records are immutable once stored (BR-17, RULES §9.4).
     */
    public function delete(): ?bool
    {
        throw new \LogicException('Hasil inspeksi penerimaan barang bersifat immutable dan tidak dapat dihapus.');
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(PenerimaanBarang::class, 'goods_receipt_id');
    }

    public function penerimaanBarang(): BelongsTo
    {
        return $this->goodsReceipt();
    }

    public function poItem(): BelongsTo
    {
        return $this->belongsTo(PoItem::class, 'purchase_order_item_id');
    }
}
