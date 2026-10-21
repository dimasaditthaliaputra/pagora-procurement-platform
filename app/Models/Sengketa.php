<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatusSengketa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'uuid',
    'invoice_id',
    'dibuka_oleh',
    'status',
    'tanggal_dibuka',
    'tanggal_selesai',
])]
#[Hidden(['id'])]
class Sengketa extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'sengketa';

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
            'status' => StatusSengketa::class,
            'tanggal_dibuka' => 'datetime',
            'tanggal_selesai' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function pembuka(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuka_oleh');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(LogSengketa::class, 'sengketa_id');
    }
}
