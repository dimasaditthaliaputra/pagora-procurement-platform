<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TenantCommodityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'commodity_id',
    'is_active',
    'shrinkage_tolerance',
])]
#[Hidden(['id'])]
class TenantCommodity extends Model
{
    /** @use HasFactory<TenantCommodityFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'shrinkage_tolerance' => 'decimal:2',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(PerusahaanBuyer::class, 'tenant_id');
    }

    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Komoditas::class, 'commodity_id');
    }

    /**
     * Shrinkage tolerance used for this Tenant: the override, else the commodity default (BR-23).
     */
    public function effectiveShrinkageTolerance(): string
    {
        return (string) ($this->shrinkage_tolerance ?? $this->commodity->default_shrinkage_tolerance);
    }
}
