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
    'penerimaan_barang_id',
    'po_item_id',
    'jumlah_diterima',
    'status',
    'catatan',
    'foto_bukti_penolakan',
])]
#[Hidden(['id'])]
class PenerimaanBarangItem extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'penerimaan_barang_item';

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
            'jumlah_diterima' => 'decimal:3',
            'created_at' => 'datetime',
        ];
    }

    public function penerimaanBarang(): BelongsTo
    {
        return $this->belongsTo(PenerimaanBarang::class, 'penerimaan_barang_id');
    }

    public function poItem(): BelongsTo
    {
        return $this->belongsTo(PoItem::class, 'po_item_id');
    }
}
