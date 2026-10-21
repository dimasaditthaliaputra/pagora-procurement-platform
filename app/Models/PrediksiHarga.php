<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'uuid',
    'komoditas_id',
    'tanggal_prediksi',
    'harga_prediksi',
    'harga_pagu_rekomendasi',
    'dihitung_pada',
])]
#[Hidden(['id'])]
class PrediksiHarga extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'prediksi_harga';

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
            'tanggal_prediksi' => 'date',
            'harga_prediksi' => 'decimal:2',
            'harga_pagu_rekomendasi' => 'decimal:2',
            'dihitung_pada' => 'datetime',
        ];
    }

    public function komoditas(): BelongsTo
    {
        return $this->belongsTo(Komoditas::class, 'komoditas_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'prediksi_harga_id');
    }
}
