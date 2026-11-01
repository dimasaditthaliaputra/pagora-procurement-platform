<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatusKerjaSama;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'uuid',
    'tenant_id',
    'vendor_id',
    'requested_by',
    'status',
    'rejection_reason',
    'block_reason',
    'blocked_by',
    'blocked_at',
])]
#[Hidden(['id'])]
class KerjaSama extends Model
{
    use HasUuids;

    protected $table = 'partnerships';

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
            'status' => StatusKerjaSama::class,
            'blocked_at' => 'datetime',
        ];
    }

    public function perusahaanBuyer(): BelongsTo
    {
        return $this->belongsTo(PerusahaanBuyer::class, 'tenant_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }
}
