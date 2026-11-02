<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VerificationStatus;
use Database\Factories\PerusahaanBuyerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property string $company_name
 * @property string|null $tax_number
 * @property string|null $legal_document_path
 * @property string|null $address
 * @property VerificationStatus $verification_status
 * @property string|null $verification_note
 * @property int|null $verified_by
 * @property Carbon|null $verified_at
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'uuid',
    'company_name',
    'tax_number',
    'legal_document_path',
    'address',
    'verification_status',
    'verification_note',
    'verified_by',
    'verified_at',
    'is_active',
])]
#[Hidden(['id'])]
class PerusahaanBuyer extends Model
{
    /** @use HasFactory<PerusahaanBuyerFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'tenants';

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
            'verification_status' => VerificationStatus::class,
            'verified_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'tenant_id');
    }

    public function verifiedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function kerjaSama(): HasMany
    {
        return $this->hasMany(KerjaSama::class, 'tenant_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'perusahaan_buyer_id');
    }
}
