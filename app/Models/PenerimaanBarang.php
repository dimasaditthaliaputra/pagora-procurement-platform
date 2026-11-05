<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'uuid',
    'purchase_order_id',
    'shipment_id',
    'inspected_by',
    'matched_plate_number',
    'inspected_at',
    'notes',
])]
#[Hidden([
    'id',
    'purchase_order_id',
    'shipment_id',
    'inspected_by',
])]
class PenerimaanBarang extends Model
{
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'goods_receipts';

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
            'inspected_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Pengiriman::class, 'shipment_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function pemeriksa(): BelongsTo
    {
        return $this->inspector();
    }

    public function items(): HasMany
    {
        return $this->hasMany(PenerimaanBarangItem::class, 'goods_receipt_id')->orderBy('id');
    }
}
