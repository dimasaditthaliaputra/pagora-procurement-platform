<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PurchaseReturnStatus;
use Database\Factories\PurchaseReturnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Return of QC-rejected goods to the Vendor (FR-42, BR-27). Once `confirmed` only `completed` may follow (BR-17).
 */
#[Fillable([
    'uuid',
    'return_number',
    'purchase_order_id',
    'dispute_id',
    'return_date',
    'reason',
    'status',
    'vendor_confirmed_by',
    'vendor_confirmed_at',
    'created_by',
])]
#[Hidden(['id', 'purchase_order_id', 'dispute_id', 'vendor_confirmed_by', 'created_by'])]
class PurchaseReturn extends Model
{
    /** @use HasFactory<PurchaseReturnFactory> */
    use HasFactory, HasUuids;

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => PurchaseReturnStatus::class,
            'return_date' => 'date',
            'vendor_confirmed_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function sengketa(): BelongsTo
    {
        return $this->belongsTo(Sengketa::class, 'dispute_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class, 'purchase_return_id');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function vendorConfirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_confirmed_by');
    }
}
