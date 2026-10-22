<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Notifications\AkunPegawaiDibuatNotification;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class PegawaiService
{
    /**
     * Default initial password assigned to newly created employee accounts.
     */
    public const DEFAULT_PASSWORD = 'password123';

    /**
     * All available roles in the system (for superadmin).
     *
     * @var array<string, string>
     */
    public const ALL_ROLES = [
        'superadmin' => 'Super Administrator',
        'owner' => 'Owner (Pemilik Perusahaan)',
        'manajemen' => 'Manajemen (Admin Internal)',
        'buyer' => 'Buyer (Tim Purchasing)',
        'qc_gudang' => 'QC Gudang (Inspeksi Mutu)',
        'vendor' => 'Vendor',
    ];

    /**
     * Allowed roles for owner accounts.
     *
     * @var array<string, string>
     */
    public const OWNER_ROLES = [
        'manajemen' => 'Manajemen (Admin Internal)',
        'buyer' => 'Buyer (Tim Purchasing)',
        'qc_gudang' => 'QC Gudang (Inspeksi Mutu)',
    ];

    /**
     * Allowed roles for manajemen accounts.
     *
     * @var array<string, string>
     */
    public const MANAJEMEN_ROLES = [
        'buyer' => 'Buyer (Tim Purchasing)',
        'qc_gudang' => 'QC Gudang (Inspeksi Mutu)',
    ];

    /**
     * Allowed internal roles for buyer-side employee accounts.
     *
     * @var array<string, string>
     */
    public const INTERNAL_ROLES = self::MANAJEMEN_ROLES;

    /**
     * Allowed roles for vendor-side accounts (created by owner/manajemen vendor).
     *
     * @var array<string, string>
     */
    public const VENDOR_ROLES = [
        'vendor' => 'Vendor',
    ];

    /**
     * Roles that superadmin can create (semua roles).
     *
     * @var array<string, string>
     */
    public const SUPERADMIN_ROLES = self::ALL_ROLES;

    public function __construct(
        protected UserRepositoryInterface $userRepository
    ) {}

    /**
     * Paginate employees or vendor users.
     *
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $columns
     */
    public function paginate(
        array $filters,
        int $perPage = 10,
        array $columns = [
            'id',
            'uuid',
            'nama_lengkap',
            'email',
            'avatar',
            'role_id',
            'vendor_id',
            'status_aktif',
            'created_at',
            'updated_at',
        ],
        ?int $vendorId = null
    ): LengthAwarePaginator {
        return $this->userRepository->paginateInternal($filters, $perPage, $columns, $vendorId);
    }

    /**
     * Find an employee by UUID or throw ModelNotFoundException.
     *
     * @throws ModelNotFoundException
     */
    public function findByUuid(string $uuid): User
    {
        $user = $this->userRepository->findByUuid($uuid);

        if (! $user) {
            throw (new ModelNotFoundException('Akun pegawai tidak ditemukan.'))->setModel(User::class, [$uuid]);
        }

        return $user;
    }

    /**
     * Determine which roles a creator is allowed to assign.
     *
     * - superadmin: semua roles
     * - owner: manajemen, buyer, qc_gudang
     * - manajemen: buyer, qc_gudang
     * - vendor user: vendor
     *
     * @return array<string, string>
     */
    public function getAllowedRolesForUser(User $user): array
    {
        $roleName = $user->role?->nama_role ?? '';

        return match (true) {
            $roleName === 'superadmin' => self::ALL_ROLES,
            $roleName === 'owner' => self::OWNER_ROLES,
            $roleName === 'manajemen' => self::MANAJEMEN_ROLES,
            $user->vendor_id !== null => self::VENDOR_ROLES,
            default => [],
        };
    }

    /**
     * Create a new employee or vendor account.
     *
     * Allowed roles per creator:
     * - superadmin : semua roles
     * - owner      : manajemen, buyer, qc_gudang
     * - manajemen  : buyer, qc_gudang
     * - vendor     : vendor
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function create(array $data, User $creator): User
    {
        $roleName = (string) ($data['role'] ?? '');
        $isVendorUser = $creator->vendor_id !== null;

        // Determine which roles this creator is allowed to assign
        $allowedRolesMap = $this->getAllowedRolesForUser($creator);
        $allowedRoles = array_keys($allowedRolesMap);

        if (empty($allowedRoles)) {
            throw ValidationException::withMessages([
                'role' => 'Anda tidak memiliki izin untuk membuat akun pengguna.',
            ]);
        }

        if (! in_array($roleName, $allowedRoles, true)) {
            throw ValidationException::withMessages([
                'role' => 'Role yang dipilih tidak diizinkan untuk akun Anda.',
            ]);
        }

        $role = Role::where('nama_role', $roleName)->first();

        if (! $role) {
            throw ValidationException::withMessages([
                'role' => 'Role pegawai tidak valid atau tidak diizinkan.',
            ]);
        }

        return DB::transaction(function () use ($data, $role, $roleName, $creator, $isVendorUser): User {
            $defaultPassword = self::DEFAULT_PASSWORD;

            $payload = [
                'uuid' => (string) Str::uuid(),
                'nama_lengkap' => trim((string) $data['nama_lengkap']),
                'email' => strtolower(trim((string) $data['email'])),
                'password' => Hash::make($defaultPassword),
                'role_id' => $role->id,
                // Stays inactive until the user passes the first-login OTP.
                'status_aktif' => false,
                'email_verified_at' => null,
            ];

            // If the creator is a vendor user, scope the new account to the same vendor
            if ($isVendorUser) {
                $payload['vendor_id'] = $creator->vendor_id;
            }

            $user = $this->userRepository->create($payload);

            $roleLabel = self::ALL_ROLES[$roleName] ?? ucfirst($roleName);

            // Send notification email with initial access credentials
            try {
                $user->notify(new AkunPegawaiDibuatNotification($user, $defaultPassword, $roleLabel));
            } catch (Throwable $e) {
                Log::warning('Gagal mengirimkan email notifikasi akun pegawai baru: '.$e->getMessage(), [
                    'user_uuid' => $user->uuid,
                    'email' => $user->email,
                ]);
            }

            return $user->load('role');
        });
    }

    /**
     * Update an internal employee or vendor account.
     *
     * If $actor is a vendor user, the target must belong to the same vendor.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function update(string $uuid, array $data, User $actor): User
    {
        $user = $this->findByUuid($uuid);

        // Vendor users can only manage accounts within their own vendor
        if ($actor->vendor_id !== null && $user->vendor_id !== $actor->vendor_id) {
            throw ValidationException::withMessages([
                'error' => 'Anda tidak memiliki izin untuk mengubah akun dari vendor lain.',
            ]);
        }

        $roleName = (string) ($data['role'] ?? '');
        $allowedRoles = array_keys($this->getAllowedRolesForUser($actor));

        if (! in_array($roleName, $allowedRoles, true) && $roleName !== ($user->role?->nama_role ?? '')) {
            throw ValidationException::withMessages([
                'role' => 'Role yang dipilih tidak diizinkan untuk akun Anda.',
            ]);
        }

        $role = Role::where('nama_role', $roleName)->first();

        if (! $role) {
            throw ValidationException::withMessages([
                'role' => 'Role yang dipilih tidak ditemukan dalam sistem.',
            ]);
        }

        return DB::transaction(function () use ($user, $data, $role): User {
            $payload = [
                'nama_lengkap' => trim((string) $data['nama_lengkap']),
                'email' => strtolower(trim((string) $data['email'])),
                'role_id' => $role->id,
                'status_aktif' => (bool) ($data['status_aktif'] ?? true),
            ];

            // If a new password is provided, hash and update it
            if (! empty($data['password'])) {
                $payload['password'] = Hash::make((string) $data['password']);
            }

            $this->userRepository->update($user, $payload);

            return $user->fresh()->load('role');
        });
    }

    /**
     * Soft delete an internal employee or vendor account.
     *
     * If $actor is a vendor user, the target must belong to the same vendor.
     *
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function delete(string $uuid, ?User $currentUser = null): bool
    {
        $user = $this->findByUuid($uuid);

        if ($currentUser !== null && $user->id === $currentUser->id) {
            throw ValidationException::withMessages([
                'error' => 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif.',
            ]);
        }

        // Vendor users can only delete accounts within their own vendor
        if ($currentUser !== null && $currentUser->vendor_id !== null && $user->vendor_id !== $currentUser->vendor_id) {
            throw ValidationException::withMessages([
                'error' => 'Anda tidak memiliki izin untuk menghapus akun dari vendor lain.',
            ]);
        }

        return $this->userRepository->delete($user);
    }

    /**
     * Get available role options formatted for frontend Combobox / Select.
     *
     * @return list<array{value: string, label: string}>
     */
    public function getRoleOptions(): array
    {
        $options = [];
        foreach (self::INTERNAL_ROLES as $value => $label) {
            $options[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        return $options;
    }

    /**
     * Get role options based on the requesting user's role.
     *
     * - superadmin  → semua roles
     * - owner       → manajemen, buyer, qc_gudang
     * - manajemen   → buyer, qc_gudang
     * - vendor user → vendor
     *
     * @return list<array{value: string, label: string}>
     */
    public function getRoleOptionsForUser(User $user): array
    {
        $map = $this->getAllowedRolesForUser($user);

        $options = [];
        foreach ($map as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }
}
