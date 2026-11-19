<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per predicted day. Each forecast run inserts a new batch sharing `calculated_at`;
 * rows are never updated because purchase orders keep a reference to the batch they used.
 */
#[Fillable([
    'uuid',
    'commodity_id',
    'prediction_date',
    'predicted_price',
    'recommended_ceiling_price',
    'calculated_at',
])]
#[Hidden(['id', 'commodity_id'])]
class PrediksiHarga extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'price_predictions';

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
            'prediction_date' => 'date',
            'predicted_price' => 'decimal:2',
            'recommended_ceiling_price' => 'decimal:2',
            'calculated_at' => 'datetime',
        ];
    }

    public function komoditas(): BelongsTo
    {
        return $this->belongsTo(Komoditas::class, 'commodity_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'prediksi_harga_id');
    }
}
