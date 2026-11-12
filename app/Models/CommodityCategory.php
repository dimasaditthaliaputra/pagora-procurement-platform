<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CommodityCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'uuid',
    'name',
    'description',
    'is_active',
])]
#[Hidden(['id'])]
class CommodityCategory extends Model
{
    /** @use HasFactory<CommodityCategoryFactory> */
    use HasFactory, HasUuids, SoftDeletes;

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
            'is_active' => 'boolean',
        ];
    }

    public function commodities(): HasMany
    {
        return $this->hasMany(Komoditas::class, 'commodity_category_id');
    }

    /**
     * Vendors supplying commodities in this category.
     */
    public function vendors(): BelongsToMany
    {
        return $this->belongsToMany(
            Vendor::class,
            'vendor_commodity_categories',
            'commodity_category_id',
            'vendor_id'
        );
    }
}
