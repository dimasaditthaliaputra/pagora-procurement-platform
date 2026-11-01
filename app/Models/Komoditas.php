<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\KomoditasFactory;
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
    'name',
    'sku',
    'commodity_category_id',
    'unit',
    'default_shrinkage_tolerance',
    'api_reference_code',
    'specification',
    'is_active',
])]
#[Hidden(['id'])]
class Komoditas extends Model
{
    /** @use HasFactory<KomoditasFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'commodities';

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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_shrinkage_tolerance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CommodityCategory::class, 'commodity_category_id');
    }

    public function tenantCommodities(): HasMany
    {
        return $this->hasMany(TenantCommodity::class, 'commodity_id');
    }
}
