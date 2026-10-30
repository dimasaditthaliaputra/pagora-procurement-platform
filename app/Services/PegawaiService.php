<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Notifications\AkunPegawaiDibuatNotification;
use App\Repositories\Contracts\PerusahaanBuyerRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\VendorRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
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
     * Roles visible in buyer-company filter dropdowns.
     *
     * @var array<string, string>
     */
    public const BUYER_COMPANY_FILTER_ROLES = [
        'owner' => 'Owner (Pemilik Perusahaan)',
        'manajemen' => 'Manajemen (Admin Internal)',
        'buyer' => 'Buyer (Tim Purchasing)',
        'qc_gudang' => 'QC Gudang (Inspeksi Mutu)',
    ];

    /**
     * Allowed roles for owner accounts to create/manage.
     *
     * @var array<string, string>
     */
    public const OWNER_ROLES = [
        'manajemen' => 'Manajemen (Admin Internal)',
        'buyer' => 'Buyer (Tim Purchasing)',
        'qc_gudang' => 'QC Gudang (Inspeksi Mutu)',
    ];

    /**
     * Allowed roles for manajemen accounts to create/manage.
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
     * Allowed roles for vendor-side accounts.
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

    /**
     * Default selected columns for user listing.
     *
     * @var list<string>
     */
    private const DEFAULT_COLUMNS = [
        'id',
        'uuid',
        'nama_lengkap',
        'email',
        'avatar',
        'role_id',
        'perusahaan_id',
        'vendor_id',
        'status_aktif',
        'created_at',
        'updated_at',
    ];

    public function __construct(
        protected readonly UserRepositoryInterface $userRepository,
        protected readonly PerusahaanBuyerRepositoryInterface $perusahaanBuyerRepository,
        protected readonly VendorRepositoryInterface $vendorRepository,
        protected readonly RoleRepositoryInterface $roleRepository
    ) {}

    /**
     * Paginate employees or vendor users (low-level helper).
     *
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $columns
     */
    public function paginate(
        array $filters,
        int $perPage = 10,
        array $columns = self::DEFAULT_COLUMNS,
        ?int $vendorId = null,
        ?int $perusahaanId = null,
        bool $isSuperadmin = false
    ): LengthAwarePaginator {
        return $this->userRepository->paginateInternal(
            $filters,
            $perPage,
            $columns,
            $vendorId,
            $perusahaanId,
            $isSuperadmin
        );
    }

    /**
     * Paginate user accounts strictly scoped to the actor's company unless superadmin.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateForActor(array $filters, User $actor, int $perPage = 10): LengthAwarePaginator
    {
        $isSuperadmin = $actor->isSuperadmin();
        $vendorId = null;
        $perusahaanId = null;
        $unassignedOnly = false;
        $forceEmpty = false;

        if ($isSuperadmin) {
            $perusahaanFilter = trim((string) ($filters['perusahaan'] ?? ''));

            if ($perusahaanFilter === 'tanpa_perusahaan') {
                $unassignedOnly = true;
            } elseif ($perusahaanFilter !== '' && $perusahaanFilter !== 'all') {
                if (Str::isUuid($perusahaanFilter)) {
                    $perusahaanBuyer = $this->perusahaanBuyerRepository->findByUuid($perusahaanFilter);
                    if ($perusahaanBuyer !== null) {
                        $perusahaanId = (int) $perusahaanBuyer->getKey();
                    } else {
                        $vendor = $this->vendorRepository->findByUuid($perusahaanFilter);
                        if ($vendor !== null) {
                            $vendorId = (int) $vendor->getKey();
                        } else {
                            $forceEmpty = true;
                        }
                    }
                } else {
                    $forceEmpty = true;
                }
            }
        } elseif ($actor->isVendor()) {
            if ($actor->vendor_id !== null) {
                $vendorId = (int) $actor->vendor_id;
            } else {
                $forceEmpty = true;
            }
        } elseif ($actor->isOwner() || $actor->isManajemen()) {
            if ($actor->perusahaan_id !== null) {
                $perusahaanId = (int) $actor->perusahaan_id;
            } else {
                $forceEmpty = true;
            }
        } else {
            $forceEmpty = true;
        }

        return $this->userRepository->paginateInternal(
            $filters,
            $perPage,
            self::DEFAULT_COLUMNS,
            $vendorId,
            $perusahaanId,
            $isSuperadmin,
            $unassignedOnly,
            $forceEmpty
        );
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
     * Find a user by UUID and verify the actor has permission to view it.
     *
     * @throws ModelNotFoundException
     * @throws AuthorizationException
     */
    public function findByUuidForActor(string $uuid, User $actor): User
    {
        $user = $this->findByUuid($uuid);

        if (! $actor->isSuperadmin()) {
            $this->ensureActorSameCompany($actor, $user, 'melihat');
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
     * Create a new employee or vendor account scoped to the appropriate company.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function create(array $data, User $creator): User
    {
        $roleName = (string) ($data['role'] ?? '');
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

        $role = $this->roleRepository->findByName($roleName);

        if (! $role) {
            throw ValidationException::withMessages([
                'role' => 'Role pegawai tidak valid atau tidak diizinkan.',
            ]);
        }

        [$perusahaanId, $vendorId] = $this->resolveCompanyIdsForCreate($data, $roleName, $creator);

        return DB::transaction(function () use ($data, $role, $roleName, $perusahaanId, $vendorId): User {
            $defaultPassword = self::DEFAULT_PASSWORD;

            $payload = [
                'uuid' => (string) Str::uuid(),
                'nama_lengkap' => trim((string) $data['nama_lengkap']),
                'email' => strtolower(trim((string) $data['email'])),
                'password' => Hash::make($defaultPassword),
                'role_id' => $role->getKey(),
                'perusahaan_id' => $perusahaanId,
                'vendor_id' => $vendorId,
                // Stays inactive until the user passes the first-login OTP.
                'status_aktif' => false,
                'email_verified_at' => null,
            ];

            $user = $this->userRepository->create($payload);

            $roleLabel = self::ALL_ROLES[$roleName] ?? ucfirst($roleName);

            try {
                $user->notify(new AkunPegawaiDibuatNotification($user, $defaultPassword, $roleLabel));
            } catch (Throwable $e) {
                Log::warning('Gagal mengirimkan email notifikasi akun pegawai baru: '.$e->getMessage(), [
                    'user_uuid' => $user->uuid,
                    'email' => $user->email,
                ]);
            }

            return $user->load([
                'role',
                'perusahaanBuyer:id,uuid,nama_perusahaan',
                'vendor:id,uuid,nama_perusahaan',
            ]);
        });
    }

    /**
     * Update an internal employee or vendor account with company & role isolation.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ModelNotFoundException
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function update(string $uuid, array $data, User $actor): User
    {
        $user = $this->findByUuid($uuid);
        $isSuperadmin = $actor->isSuperadmin();

        if (! $isSuperadmin) {
            $this->ensureActorSameCompany($actor, $user, 'mengubah');
        }

        $roleName = (string) ($data['role'] ?? '');
        $allowedRoles = array_keys($this->getAllowedRolesForUser($actor));
        $targetCurrentRole = $user->role?->nama_role ?? '';

        if (! $isSuperadmin) {
            if ($user->getKey() === $actor->getKey()) {
                if ($roleName !== $targetCurrentRole) {
                    throw ValidationException::withMessages([
                        'role' => 'Anda tidak dapat mengubah jabatan akun Anda sendiri.',
                    ]);
                }

                if (isset($data['status_aktif']) && ! (bool) $data['status_aktif']) {
                    throw ValidationException::withMessages([
                        'status_aktif' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.',
                    ]);
                }
            } else {
                if (! in_array($targetCurrentRole, $allowedRoles, true)) {
                    throw new AuthorizationException('Anda tidak memiliki izin untuk mengubah akun dengan jabatan tersebut.');
                }

                if (! in_array($roleName, $allowedRoles, true)) {
                    throw ValidationException::withMessages([
                        'role' => 'Role yang dipilih tidak diizinkan untuk akun Anda.',
                    ]);
                }
            }
        } elseif (! in_array($roleName, $allowedRoles, true)) {
            throw ValidationException::withMessages([
                'role' => 'Role yang dipilih tidak diizinkan untuk akun Anda.',
            ]);
        }

        $role = $this->roleRepository->findByName($roleName);

        if (! $role) {
            throw ValidationException::withMessages([
                'role' => 'Role yang dipilih tidak ditemukan dalam sistem.',
            ]);
        }

        $companyUpdates = $isSuperadmin
            ? $this->resolveCompanyUpdatesForSuperadmin($data, $roleName)
            : [];

        return DB::transaction(function () use ($user, $data, $role, $companyUpdates): User {
            $payload = [
                'nama_lengkap' => trim((string) $data['nama_lengkap']),
                'email' => strtolower(trim((string) $data['email'])),
                'role_id' => $role->getKey(),
                'status_aktif' => (bool) ($data['status_aktif'] ?? true),
                ...$companyUpdates,
            ];

            if (! empty($data['password'])) {
                $payload['password'] = Hash::make((string) $data['password']);
            }

            $this->userRepository->update($user, $payload);

            return $user->fresh()->load([
                'role',
                'perusahaanBuyer:id,uuid,nama_perusahaan',
                'vendor:id,uuid,nama_perusahaan',
            ]);
        });
    }

    /**
     * Soft delete an internal employee or vendor account with company & role isolation.
     *
     * @throws ModelNotFoundException
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function delete(string $uuid, ?User $currentUser = null): bool
    {
        $user = $this->findByUuid($uuid);

        if ($currentUser !== null && $user->getKey() === $currentUser->getKey()) {
            throw ValidationException::withMessages([
                'error' => 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif.',
            ]);
        }

        if ($currentUser !== null && ! $currentUser->isSuperadmin()) {
            $this->ensureActorSameCompany($currentUser, $user, 'menghapus');

            $allowedRoles = array_keys($this->getAllowedRolesForUser($currentUser));
            $targetCurrentRole = $user->role?->nama_role ?? '';

            if (! in_array($targetCurrentRole, $allowedRoles, true)) {
                throw new AuthorizationException('Anda tidak memiliki izin untuk menghapus akun dengan jabatan tersebut.');
            }
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
        return $this->formatMapOptions(self::INTERNAL_ROLES);
    }

    /**
     * Get assignable role options based on the requesting user's role.
     *
     * @return list<array{value: string, label: string}>
     */
    public function getRoleOptionsForUser(User $user): array
    {
        return $this->formatMapOptions($this->getAllowedRolesForUser($user));
    }

    /**
     * Get toolbar filter role options appropriate for the requesting user's scope.
     *
     * @return list<array{value: string, label: string}>
     */
    public function getFilterRoleOptionsForUser(User $user): array
    {
        $roleName = $user->role?->nama_role ?? '';

        $map = match (true) {
            $roleName === 'superadmin' => self::ALL_ROLES,
            in_array($roleName, ['owner', 'manajemen'], true) => self::BUYER_COMPANY_FILTER_ROLES,
            $user->vendor_id !== null => self::VENDOR_ROLES,
            default => [],
        };

        return $this->formatMapOptions($map);
    }

    /**
     * Get combined buyer & vendor company filter options for superadmin.
     *
     * @return list<array{value: string, label: string}>
     */
    public function getPerusahaanFilterOptions(): array
    {
        $options = [];

        foreach ($this->perusahaanBuyerRepository->getAllOrdered() as $perusahaan) {
            $options[] = [
                'value' => $perusahaan->uuid,
                'label' => "{$perusahaan->nama_perusahaan} (Buyer)",
            ];
        }

        foreach ($this->vendorRepository->getAllOrdered() as $vendor) {
            $options[] = [
                'value' => $vendor->uuid,
                'label' => "{$vendor->nama_perusahaan} (Vendor)",
            ];
        }

        return $options;
    }

    /**
     * Get buyer company options for superadmin form selection.
     *
     * @return list<array{value: string, label: string}>
     */
    public function getPerusahaanBuyerOptions(): array
    {
        $options = [];

        foreach ($this->perusahaanBuyerRepository->getAllOrdered() as $perusahaan) {
            $options[] = [
                'value' => $perusahaan->uuid,
                'label' => $perusahaan->nama_perusahaan,
            ];
        }

        return $options;
    }

    /**
     * Get vendor company options for superadmin form selection.
     *
     * @return list<array{value: string, label: string}>
     */
    public function getVendorOptions(): array
    {
        $options = [];

        foreach ($this->vendorRepository->getAllOrdered() as $vendor) {
            $options[] = [
                'value' => $vendor->uuid,
                'label' => $vendor->nama_perusahaan,
            ];
        }

        return $options;
    }

    /**
     * Get the current company summary for a non-superadmin user.
     *
     * @return array{uuid: string, nama: string, tipe: string}|null
     */
    public function getCurrentPerusahaanPayload(User $user): ?array
    {
        $user->loadMissing(['perusahaanBuyer:id,uuid,nama_perusahaan', 'vendor:id,uuid,nama_perusahaan']);

        if ($user->perusahaanBuyer !== null) {
            return [
                'uuid' => $user->perusahaanBuyer->uuid,
                'nama' => $user->perusahaanBuyer->nama_perusahaan,
                'tipe' => 'buyer',
            ];
        }

        if ($user->vendor !== null) {
            return [
                'uuid' => $user->vendor->uuid,
                'nama' => $user->vendor->nama_perusahaan,
                'tipe' => 'vendor',
            ];
        }

        return null;
    }

    /**
     * Ensure a non-superadmin actor belongs to the exact same company as the target user.
     *
     * @throws AuthorizationException
     */
    private function ensureActorSameCompany(User $actor, User $target, string $actionVerb): void
    {
        if ($target->isSuperadmin()) {
            throw new AuthorizationException("Anda tidak memiliki izin untuk {$actionVerb} akun Super Administrator.");
        }

        if ($actor->isVendor()) {
            if ($actor->vendor_id === null || $target->vendor_id !== $actor->vendor_id || $target->perusahaan_id !== null) {
                throw new AuthorizationException("Anda tidak memiliki izin untuk {$actionVerb} akun dari perusahaan lain.");
            }

            return;
        }

        if ($actor->isOwner() || $actor->isManajemen()) {
            if ($actor->perusahaan_id === null || $target->perusahaan_id !== $actor->perusahaan_id || $target->vendor_id !== null) {
                throw new AuthorizationException("Anda tidak memiliki izin untuk {$actionVerb} akun dari perusahaan lain.");
            }

            return;
        }

        throw new AuthorizationException("Anda tidak memiliki izin untuk {$actionVerb} akun pengguna.");
    }

    /**
     * Resolve `perusahaan_id` and `vendor_id` when creating a new user.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: int|null, 1: int|null}
     *
     * @throws ValidationException
     */
    private function resolveCompanyIdsForCreate(array $data, string $roleName, User $creator): array
    {
        if ($creator->isSuperadmin()) {
            if ($roleName === 'superadmin') {
                return [null, null];
            }

            if ($roleName === 'vendor') {
                return [null, $this->resolveVendorId($data['vendor_uuid'] ?? null)];
            }

            return [$this->resolvePerusahaanBuyerId($data['perusahaan_uuid'] ?? null), null];
        }

        if ($creator->isVendor()) {
            if ($creator->vendor_id === null) {
                throw ValidationException::withMessages([
                    'role' => 'Akun Anda belum terhubung dengan perusahaan vendor mana pun.',
                ]);
            }

            return [null, (int) $creator->vendor_id];
        }

        if ($creator->perusahaan_id === null) {
            throw ValidationException::withMessages([
                'role' => 'Akun Anda belum terhubung dengan perusahaan mana pun.',
            ]);
        }

        return [(int) $creator->perusahaan_id, null];
    }

    /**
     * Resolve `perusahaan_id` / `vendor_id` updates when a superadmin updates a user.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, int|null>
     *
     * @throws ValidationException
     */
    private function resolveCompanyUpdatesForSuperadmin(array $data, string $roleName): array
    {
        if ($roleName === 'superadmin') {
            return [
                'perusahaan_id' => null,
                'vendor_id' => null,
            ];
        }

        if ($roleName === 'vendor') {
            $updates = ['perusahaan_id' => null];
            if (array_key_exists('vendor_uuid', $data)) {
                $updates['vendor_id'] = $this->resolveVendorId($data['vendor_uuid']);
            }

            return $updates;
        }

        $updates = ['vendor_id' => null];
        if (array_key_exists('perusahaan_uuid', $data)) {
            $updates['perusahaan_id'] = $this->resolvePerusahaanBuyerId($data['perusahaan_uuid']);
        }

        return $updates;
    }

    /**
     * Translate a buyer company UUID to its internal ID.
     *
     * @throws ValidationException
     */
    private function resolvePerusahaanBuyerId(mixed $uuid): ?int
    {
        $clean = trim((string) ($uuid ?? ''));
        if ($clean === '') {
            return null;
        }

        $perusahaan = $this->perusahaanBuyerRepository->findByUuid($clean);
        if (! $perusahaan) {
            throw ValidationException::withMessages([
                'perusahaan_uuid' => 'Perusahaan buyer yang dipilih tidak ditemukan.',
            ]);
        }

        return (int) $perusahaan->getKey();
    }

    /**
     * Translate a vendor UUID to its internal ID.
     *
     * @throws ValidationException
     */
    private function resolveVendorId(mixed $uuid): ?int
    {
        $clean = trim((string) ($uuid ?? ''));
        if ($clean === '') {
            return null;
        }

        $vendor = $this->vendorRepository->findByUuid($clean);
        if (! $vendor) {
            throw ValidationException::withMessages([
                'vendor_uuid' => 'Perusahaan vendor yang dipilih tidak ditemukan.',
            ]);
        }

        return (int) $vendor->getKey();
    }

    /**
     * Format associative `[value => label]` map into list of `{value, label}` objects.
     *
     * @param  array<string, string>  $map
     * @return list<array{value: string, label: string}>
     */
    private function formatMapOptions(array $map): array
    {
        $options = [];
        foreach ($map as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }
}
