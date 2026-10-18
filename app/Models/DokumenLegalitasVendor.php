<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DokumenLegalitasVendor extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'dokumen_legalitas_vendor';

    public $timestamps = false;

    /**
     * Attributes that can be mass assigned.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'vendor_id',
        'jenis_dokumen',
        'nomor_dokumen',
        'file_url',
        'status_verifikasi',
        'verif_by',
        'diverifikasi_pada',
        'alasan_penolakan',
    ];

    /**
     * Hide internal ID and private file path from serialized data.
     *
     * @var list<string>
     */
    protected $hidden = [
        'id',
        'file_url',
    ];

    /**
     * Use UUID as the public identifier.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Cast attributes to their appropriate types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'diverifikasi_pada' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verif_by');
    }
}
