<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\KomoditasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'uuid',
    'slug',
    'nama_komoditas',
    'sku',
    'kategori',
    'satuan',
    'kode_referensi_api',
    'spesifikasi',
    'status_aktif',
])]
#[Hidden(['id'])]
class Komoditas extends Model
{
    /** @use HasFactory<KomoditasFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'komoditas';

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
            'status_aktif' => 'boolean',
        ];
    }
}
