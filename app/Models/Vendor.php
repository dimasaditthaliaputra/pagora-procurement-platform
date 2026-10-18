<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'uuid',
    'slug',
    'nama_perusahaan',
    'email_kontak',
    'no_telepon',
    'alamat',
    'kategori_komoditas',
    'dokumen_legalitas',
    'status_verifikasi',
    'status_aktif',
    'alasan_pemblokiran',
    'blocked_by',
    'diblokir_pada',
])]
#[Hidden(['id'])]
class Vendor extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'vendor';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'slug',
        'nama_perusahaan',
        'email_kontak',
        'no_telepon',
        'alamat',
        'kategori_komoditas',
        'dokumen_legalitas',
        'status_verifikasi',
        'status_aktif',
        'alasan_pemblokiran',
        'blocked_by',
        'diblokir_pada',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'id',
    ];

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
     * Get the route key for the model.
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
            'diblokir_pada' => 'datetime',
        ];
    }

    /**
     * User who blocked this vendor if applicable.
     */
    public function blockedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }
}
