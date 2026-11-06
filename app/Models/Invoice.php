<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatusMatching;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'uuid',
    'purchase_order_id',
    'vendor_id',
    'nomor_invoice_vendor',
    'jumlah_tagihan',
    'file_invoice',
    'tanggal_invoice',
    'status_matching',
    'matching_result',
    'diverifikasi_oleh',
    'diverifikasi_pada',
])]
#[Hidden(['id'])]
class Invoice extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'invoice';

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
            'jumlah_tagihan' => 'decimal:2',
            'tanggal_invoice' => 'date',
            'matching_result' => 'array',
            'diverifikasi_pada' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id');
    }

    public function sengketa(): HasMany
    {
        return $this->hasMany(Sengketa::class, 'invoice_id');
    }

    public function statusMatchingEnum(): ?StatusMatching
    {
        return is_string($this->status_matching)
            ? StatusMatching::tryFrom($this->status_matching)
            : null;
    }

    public function isMatched(): bool
    {
        return $this->status_matching === StatusMatching::Matched->value || $this->status_matching === 'matched';
    }

    public function isMismatched(): bool
    {
        return $this->status_matching === StatusMatching::Mismatched->value || $this->status_matching === 'mismatched';
    }

    public function isPending(): bool
    {
        return $this->status_matching === StatusMatching::Pending->value || $this->status_matching === 'pending';
    }
}
