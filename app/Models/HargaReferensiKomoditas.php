<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'uuid',
    'commodity_id',
    'date',
    'price',
    'source',
])]
#[Hidden(['id', 'commodity_id'])]
class HargaReferensiKomoditas extends Model
{
    use HasUuids;

    public const SOURCE_BANK_INDONESIA = 'bank_indonesia';

    public const SOURCE_SEEDER = 'seeder';

    public const UPDATED_AT = null;

    protected $table = 'commodity_reference_prices';

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
            'date' => 'date',
            'price' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function komoditas(): BelongsTo
    {
        return $this->belongsTo(Komoditas::class, 'commodity_id');
    }
}
