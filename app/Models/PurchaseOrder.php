<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatusPurchaseOrder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'uuid',
    'nomor_po',
    'vendor_id',
    'created_by',
    'prediksi_harga_id',
    'perusahaan_buyer_id',
    'harga_pagu_acuan',
    'status',
    'tanggal_po',
    'catatan',
])]
#[Hidden(['id'])]
class PurchaseOrder extends Model
{
    use HasUuids;

    protected $table = 'purchase_order';

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
            'status' => StatusPurchaseOrder::class,
            'harga_pagu_acuan' => 'decimal:2',
            'tanggal_po' => 'date',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function prediksiHarga(): BelongsTo
    {
        return $this->belongsTo(PrediksiHarga::class, 'prediksi_harga_id');
    }

    public function perusahaanBuyer(): BelongsTo
    {
        return $this->belongsTo(PerusahaanBuyer::class, 'perusahaan_buyer_id');
    }

    public function poItems(): HasMany
    {
        return $this->hasMany(PoItem::class, 'purchase_order_id');
    }

    public function chatNegosiasi(): HasMany
    {
        return $this->hasMany(ChatNegosiasi::class, 'purchase_order_id');
    }

    public function pengiriman(): HasMany
    {
        return $this->hasMany(Pengiriman::class, 'purchase_order_id');
    }

    public function penerimaanBarang(): HasMany
    {
        return $this->hasMany(PenerimaanBarang::class, 'purchase_order_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'purchase_order_id');
    }
}
