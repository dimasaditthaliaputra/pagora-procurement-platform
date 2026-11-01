<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'uuid',
    'slug',
    'company_name',
    'tax_number',
    'contact_email',
    'phone',
    'address',
    'kategori_komoditas',
    'verification_status',
    'is_active',
    'block_reason',
    'blocked_by',
    'blocked_at',
])]
#[Hidden(['id'])]
class Vendor extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'vendors';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'slug',
        'company_name',
        'tax_number',
        'contact_email',
        'phone',
        'address',
        'kategori_komoditas',
        'verification_status',
        'is_active',
        'block_reason',
        'blocked_by',
        'blocked_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'id',
    ];

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
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verification_status' => VerificationStatus::class,
            'is_active' => 'boolean',
            'blocked_at' => 'datetime',
        ];
    }

    /**
     * User who blocked this vendor if applicable.
     */
    public function blockedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    /**
     * Legal documents submitted by this vendor.
     */
    public function legalDocuments(): HasMany
    {
        return $this->hasMany(DokumenLegalitasVendor::class, 'vendor_id');
    }

    /**
     * Users associated with this vendor.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'vendor_id');
    }
}
