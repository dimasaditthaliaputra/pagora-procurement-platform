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
    'sengketa_id',
    'user_id',
    'isi_pesan',
    'file_bukti',
])]
#[Hidden(['id'])]
class LogSengketa extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'log_sengketa';

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
            'created_at' => 'datetime',
        ];
    }

    /**
     * Records are immutable: block updates at the application level.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new \LogicException('LogSengketa bersifat immutable dan tidak dapat diubah.');
    }

    /**
     * Records are immutable: block deletes at the application level.
     */
    public function delete(): ?bool
    {
        throw new \LogicException('LogSengketa bersifat immutable dan tidak dapat dihapus.');
    }

    public function sengketa(): BelongsTo
    {
        return $this->belongsTo(Sengketa::class, 'sengketa_id');
    }

    public function penulis(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
