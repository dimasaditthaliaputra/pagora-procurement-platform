<?php

declare(strict_types=1);

namespace App\Models;

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
    'dibuka_oleh',
    'status',
    'tanggal_dibuka',
    'tanggal_selesai',
])]
#[Hidden([
    'id',
    'purchase_order_id',
    'purchase_order_item_id',
    'invoice_id',
    'dibuka_oleh',
])]
class Sengketa extends Model
{
    /** @use HasFactory<SengketaFactory> */
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'sengketa';

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
            'tanggal_dibuka' => 'datetime',
            'tanggal_selesai' => 'datetime',
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
        return $this->belongsTo(User::class, 'dibuka_oleh');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(LogSengketa::class, 'sengketa_id')->orderBy('created_at')->orderBy('id');
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
