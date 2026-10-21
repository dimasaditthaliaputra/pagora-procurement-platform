<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'uuid',
    'purchase_order_id',
    'komoditas_id',
    'jumlah_pesanan',
    'harga_satuan_po',
    'subtotal',
    'harga_tawar_vendor',
    'status_item',
])]
#[Hidden(['id'])]
class PoItem extends Model
{
    use HasUuids;

    protected $table = 'po_item';

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
            'jumlah_pesanan' => 'decimal:3',
            'harga_satuan_po' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'harga_tawar_vendor' => 'decimal:2',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function komoditas(): BelongsTo
    {
        return $this->belongsTo(Komoditas::class, 'komoditas_id');
    }

    public function penerimaanBarangItems(): HasMany
    {
        return $this->hasMany(PenerimaanBarangItem::class, 'po_item_id');
    }
}
