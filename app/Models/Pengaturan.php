<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'grup',
    'kunci',
    'nilai',
    'terenkripsi',
    'diubah_oleh',
])]
#[Hidden(['id', 'nilai'])]
class Pengaturan extends Model
{
    protected $table = 'pengaturan';

    /**
     * Get the user who last changed this setting.
     */
    public function pengubah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diubah_oleh');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'terenkripsi' => 'boolean',
        ];
    }
}
