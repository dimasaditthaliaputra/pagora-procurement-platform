<?php

declare(strict_types=1);

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $nama_lengkap
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $google_id
 * @property string|null $avatar
 * @property-read string|null $avatar_url
 * @property int $role_id
 * @property int|null $perusahaan_id
 * @property int|null $vendor_id
 * @property bool $status_aktif
 * @property-read Role|null $role
 * @property-read PerusahaanBuyer|null $perusahaanBuyer
 * @property-read Vendor|null $vendor
 *
 * @method bool isSuperadmin()
 */
#[Fillable([
    'uuid',
    'nama_lengkap',
    'email',
    'email_verified_at',
    'password',
    'google_id',
    'avatar',
    'role_id',
    'perusahaan_id',
    'vendor_id',
    'status_aktif',
])]
#[Hidden(['id', 'perusahaan_id', 'vendor_id', 'password', 'remember_token'])]
#[Appends(['avatar_url'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable, SoftDeletes;

    protected $table = 'users';

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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the role assigned to this user.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * Get the buyer company that employs this user.
     */
    public function perusahaanBuyer(): BelongsTo
    {
        return $this->belongsTo(PerusahaanBuyer::class, 'perusahaan_id');
    }

    /**
     * Get the vendor associated with this user.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    /**
     * Send the Indonesian password reset email instead of the framework default.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Determine whether the user is a superadmin.
     */
    public function isSuperadmin(): bool
    {
        return $this->role?->nama_role === 'superadmin'
            || $this->role()->where('nama_role', 'superadmin')->exists();
    }

    /**
     * Determine whether the user is in the manajemen role.
     */
    public function isManajemen(): bool
    {
        return $this->role?->nama_role === 'manajemen'
            || $this->role()->where('nama_role', 'manajemen')->exists();
    }

    /**
     * Determine whether the user is an owner of a buyer company.
     */
    public function isOwner(): bool
    {
        return $this->role?->nama_role === 'owner'
            || $this->role()->where('nama_role', 'owner')->exists();
    }

    /**
     * Determine whether the user is a vendor user.
     */
    public function isVendor(): bool
    {
        return $this->role?->nama_role === 'vendor'
            || $this->role()->where('nama_role', 'vendor')->exists();
    }

    /**
     * Determine whether the user can access the Manajemen Pengguna feature.
     */
    public function canManagePengguna(): bool
    {
        if ($this->isSuperadmin() || $this->isOwner() || $this->isManajemen()) {
            return true;
        }

        return $this->isVendor() && $this->vendor_id !== null;
    }

    /**
     * Determine whether the user can manage vendors (superadmin or manajemen).
     */
    public function canManageVendors(): bool
    {
        return $this->isSuperadmin() || $this->isManajemen();
    }

    /**
     * Public URL of the avatar. Google sign-in stores a full URL, while uploads store a public-disk path.
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (blank($this->avatar)) {
                return null;
            }

            return Str::startsWith($this->avatar, ['http://', 'https://'])
                ? $this->avatar
                : Storage::disk('public')->url($this->avatar);
        });
    }
}
