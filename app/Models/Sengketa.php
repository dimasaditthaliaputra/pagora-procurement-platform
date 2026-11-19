<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DisputeResolutionType;
use App\Enums\PemicuSengketa;
use App\Enums\StatusSengketa;
use Database\Factories\SengketaFactory;
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
    'purchase_order_item_id',
    'invoice_id',
    'trigger_type',
    'reason',
    'opened_by',
    'status',
    'resolution_type',
    'resolution_notes',
    'vendor_confirmed_by',
    'vendor_confirmed_at',
    'resolved_by',
    'opened_at',
    'resolved_at',
])]
#[Hidden([
    'id',
    'purchase_order_id',
    'purchase_order_item_id',
    'invoice_id',
    'opened_by',
    'vendor_confirmed_by',
    'resolved_by',
])]
class Sengketa extends Model
{
    /** @use HasFactory<SengketaFactory> */
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'disputes';

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
            'status' => StatusSengketa::class,
            'resolution_type' => DisputeResolutionType::class,
            'vendor_confirmed_at' => 'datetime',
            'opened_at' => 'datetime',
            'resolved_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function poItem(): BelongsTo
    {
        return $this->belongsTo(PoItem::class, 'purchase_order_item_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function pembuka(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function vendorConfirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_confirmed_by');
    }

    public function penyelesai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class, 'dispute_id')->orderBy('id');
    }

    public function purchaseReturns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class, 'dispute_id')->orderBy('id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(LogSengketa::class, 'dispute_id')->orderBy('created_at')->orderBy('id');
    }

    public function triggerEnum(): ?PemicuSengketa
    {
        if (! is_string($this->trigger_type) || $this->trigger_type === '') {
            return null;
        }

        return PemicuSengketa::tryFrom($this->trigger_type);
    }

    public function triggerLabel(): string
    {
        return $this->triggerEnum()?->label() ?? 'Hasil QC Ditolak';
    }
}
