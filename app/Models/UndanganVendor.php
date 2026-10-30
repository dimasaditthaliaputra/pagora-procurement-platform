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
    'nama_perusahaan',
    'email',
    'kategori_komoditas',
    'pesan',
    'token',
    'status',
    'invited_by',
    'expires_at',
    'registered_at',
])]
#[Hidden(['id'])]
class UndanganVendor extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'undangan_vendor';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'nama_perusahaan',
        'email',
        'kategori_komoditas',
        'pesan',
        'token',
        'status',
        'invited_by',
        'expires_at',
        'registered_at',
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
            'expires_at' => 'datetime',
            'registered_at' => 'datetime',
        ];
    }

    /**
     * User who created and sent this invitation.
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Check if the invitation is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Check if the invitation is valid for registration.
     */
    public function isValid(): bool
    {
        return $this->status === 'pending' && ! $this->isExpired();
    }
}
