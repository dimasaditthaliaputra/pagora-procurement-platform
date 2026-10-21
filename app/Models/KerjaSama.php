<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatusKerjaSama;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'uuid',
    'id_perusahaan',
    'id_vendor',
    'status_kerjasama',
    'catatan_penolakan',
])]
#[Hidden(['id'])]
class KerjaSama extends Model
{
    use HasUuids;

    protected $table = 'kerja_sama';

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
            'status_kerjasama' => StatusKerjaSama::class,
        ];
    }

    public function perusahaanBuyer(): BelongsTo
    {
        return $this->belongsTo(PerusahaanBuyer::class, 'id_perusahaan');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'id_vendor');
    }
}
