<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'uuid',
    'purchase_return_id',
    'purchase_order_item_id',
    'goods_receipt_item_id',
    'quantity',
    'notes',
])]
#[Hidden(['id', 'purchase_return_id', 'purchase_order_item_id', 'goods_receipt_item_id'])]
class PurchaseReturnItem extends Model
{
    use HasUuids;

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
        ];
    }

    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class, 'purchase_return_id');
    }

    public function poItem(): BelongsTo
    {
        return $this->belongsTo(PoItem::class, 'purchase_order_item_id');
    }

    public function receiptItem(): BelongsTo
    {
        return $this->belongsTo(PenerimaanBarangItem::class, 'goods_receipt_item_id');
    }
}
