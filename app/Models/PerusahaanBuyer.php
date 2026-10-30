<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'uuid',
    'nama_perusahaan',
    'nomor_npwp',
    'file_url',
    'alamat',
    'tipe_paket',
    'status_aktif',
])]
#[Hidden(['id'])]
class PerusahaanBuyer extends Model
{
    use HasUuids;

    protected $table = 'perusahaan_buyer';

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
            'status_aktif' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'perusahaan_id');
    }

    public function kerjaSama(): HasMany
    {
        return $this->hasMany(KerjaSama::class, 'id_perusahaan');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'perusahaan_buyer_id');
    }
}
