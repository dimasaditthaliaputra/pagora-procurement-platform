<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RoleName;
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
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $google_id
 * @property string|null $avatar
 * @property-read string|null $avatar_url
 * @property int $role_id
 * @property int|null $tenant_id
 * @property int|null $vendor_id
 * @property bool $is_active
 * @property-read Role|null $role
 * @property-read PerusahaanBuyer|null $perusahaanBuyer
 * @property-read Vendor|null $vendor
 */
#[Fillable([
    'uuid',
    'name',
    'email',
    'email_verified_at',
    'password',
    'google_id',
    'avatar',
    'role_id',
    'tenant_id',
    'vendor_id',
    'is_active',
])]
#[Hidden(['id', 'tenant_id', 'vendor_id', 'password', 'remember_token'])]
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
            'is_active' => 'boolean',
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
        return $this->belongsTo(PerusahaanBuyer::class, 'tenant_id');
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
     * Resolve the user's role as an enum, or null when the role is unknown.
     */
    public function roleName(): ?RoleName
    {
        return RoleName::tryFrom((string) $this->role?->name);
    }

    /**
     * Determine whether the user holds any of the given roles.
     */
    public function hasRole(RoleName ...$roles): bool
    {
        return in_array($this->roleName(), $roles, true);
    }

    /**
     * Determine whether the user's role grants the permission (e.g. `komoditas.create`).
     * Only call this from policies; read fresh each request, so role changes apply immediately.
     */
    public function hasPermission(string $permission): bool
    {
        return $this->role?->hasPermission($permission) ?? false;
    }

    /**
     * Determine whether the user is a superadmin.
     */
    public function isSuperadmin(): bool
    {
        return $this->hasRole(RoleName::Superadmin);
    }

    /**
     * Determine whether the user is in the manajemen role.
     */
    public function isManajemen(): bool
    {
        return $this->hasRole(RoleName::Manajemen);
    }

    /**
     * Determine whether the user is an owner of a buyer company.
     */
    public function isOwner(): bool
    {
        return $this->hasRole(RoleName::Owner);
    }

    /**
     * Determine whether the user is in the purchasing (finance) role.
     */
    public function isPurchasing(): bool
    {
        return $this->hasRole(RoleName::Purchasing);
    }

    /**
     * Determine whether the user is a warehouse QC officer.
     */
    public function isQcGudang(): bool
    {
        return $this->hasRole(RoleName::QcGudang);
    }

    /**
     * Determine whether the user is a vendor user.
     */
    public function isVendor(): bool
    {
        return $this->hasRole(RoleName::Vendor);
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
