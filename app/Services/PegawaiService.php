<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RoleName;
use App\Models\PerusahaanBuyer;
use App\Models\User;
use App\Models\Vendor;
use App\Notifications\AkunPegawaiDibuatNotification;
use App\Repositories\Contracts\PerusahaanBuyerRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\VendorRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class PegawaiService
{
    /**
     * Default initial password fallback.
     */
    public const DEFAULT_PASSWORD = 'password123';

    /**
     * Generate a secure, unique, and human-friendly default temporary password.
     */
    public static function generateDefaultPassword(): string
    {
        return 'Pgr-'.Str::random(6).'-'.random_int(1000, 9999);
    }

    /**
     * All available roles in the system (for superadmin).
     *
     * @var array<string, string>
     */
    public const ALL_ROLES = [
        'superadmin' => 'Super Administrator',
        'owner' => 'Owner (Pemilik Perusahaan)',
        'manajemen' => 'Manajemen (Admin Internal)',
        'purchasing' => 'Purchasing / Finance',
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
        'purchasing' => 'Purchasing / Finance',
        'qc_gudang' => 'QC Gudang (Inspeksi Mutu)',
    ];

    /**
     * Default selected columns for user listing.
     *
     * @var list<string>
     */
    private const DEFAULT_COLUMNS = [
        'id',
        'uuid',
        'name',
        'email',
        'avatar',
        'role_id',
        'tenant_id',
        'vendor_id',
        'is_active',
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
        } elseif ($actor->tenant_id !== null) {
            $perusahaanId = (int) $actor->tenant_id;
        } elseif ($actor->vendor_id !== null) {
            $vendorId = (int) $actor->vendor_id;
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
        Gate::forUser($actor)->authorize('view', $user);

        return $user;
    }

    /**
     * Determine which roles a creator is allowed to assign (see RoleName::assignableRoles()).
     *
     * @return array<string, string>
     */
    public function getAllowedRolesForUser(User $user): array
    {
        if (! Gate::forUser($user)->allows('create', User::class)) {
            return [];
        }

        $allowed = array_map(
            fn (RoleName $role): string => $role->value,
            $user->roleName()?->assignableRoles() ?? []
        );

        return array_intersect_key(self::ALL_ROLES, array_flip($allowed));
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

        $companyName = null;
        if ($perusahaanId !== null) {
            $companyName = PerusahaanBuyer::whereKey($perusahaanId)->value('company_name');
        } elseif ($vendorId !== null) {
            $companyName = Vendor::whereKey($vendorId)->value('company_name');
        }

        return DB::transaction(function () use ($data, $role, $roleName, $perusahaanId, $vendorId, $companyName): User {
            $defaultPassword = self::generateDefaultPassword();

            $payload = [
                'uuid' => (string) Str::uuid(),
                'name' => trim((string) $data['name']),
                'email' => strtolower(trim((string) $data['email'])),
                'password' => Hash::make($defaultPassword),
                'role_id' => $role->getKey(),
                'tenant_id' => $perusahaanId,
                'vendor_id' => $vendorId,
                // Stays inactive until the user passes the first-login OTP.
                'is_active' => false,
                'email_verified_at' => null,
            ];

            $user = $this->userRepository->create($payload);

            $roleLabel = self::ALL_ROLES[$roleName] ?? ucfirst($roleName);

            try {
                $user->notify(new AkunPegawaiDibuatNotification($user, $defaultPassword, $roleLabel, $companyName));
            } catch (Throwable $e) {
                Log::warning('Gagal mengirimkan email notifikasi akun pegawai baru: '.$e->getMessage(), [
                    'user_uuid' => $user->uuid,
                    'email' => $user->email,
                ]);
            }

            return $user->load([
                'role',
                'perusahaanBuyer:id,uuid,company_name',
                'vendor:id,uuid,company_name',
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
        Gate::forUser($actor)->authorize('update', $user);

        $isSuperadmin = $actor->isSuperadmin();
        $roleName = (string) ($data['role'] ?? '');
        $allowedRoles = array_keys($this->getAllowedRolesForUser($actor));
        $targetCurrentRole = $user->role?->name ?? '';

        if (! $isSuperadmin && $user->is($actor)) {
            if ($roleName !== $targetCurrentRole) {
                throw ValidationException::withMessages([
                    'role' => 'Anda tidak dapat mengubah jabatan akun Anda sendiri.',
                ]);
            }

            if (isset($data['is_active']) && ! (bool) $data['is_active']) {
                throw ValidationException::withMessages([
                    'is_active' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.',
                ]);
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
                'name' => trim((string) $data['name']),
                'email' => strtolower(trim((string) $data['email'])),
                'role_id' => $role->getKey(),
                'is_active' => (bool) ($data['is_active'] ?? true),
                ...$companyUpdates,
            ];

            if (! empty($data['password'])) {
                $payload['password'] = Hash::make((string) $data['password']);
            }

            $this->userRepository->update($user, $payload);

            return $user->fresh()->load([
                'role',
                'perusahaanBuyer:id,uuid,company_name',
                'vendor:id,uuid,company_name',
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
    public function delete(string $uuid, User $actor): bool
    {
        $user = $this->findByUuid($uuid);
        if ($user->is($actor)) {
            throw ValidationException::withMessages([
                'error' => 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif.',
            ]);
        }

        Gate::forUser($actor)->authorize('delete', $user);

        return $this->userRepository->delete($user);
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
        $map = match (true) {
            $user->isSuperadmin() => self::ALL_ROLES,
            $user->tenant_id !== null => self::BUYER_COMPANY_FILTER_ROLES,
            $user->vendor_id !== null => array_intersect_key(self::ALL_ROLES, ['vendor' => true]),
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
                'label' => "{$perusahaan->company_name} (Buyer)",
            ];
        }

        foreach ($this->vendorRepository->getAllOrdered() as $vendor) {
            $options[] = [
                'value' => $vendor->uuid,
                'label' => "{$vendor->company_name} (Vendor)",
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
                'label' => $perusahaan->company_name,
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
                'label' => $vendor->company_name,
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
        $user->loadMissing(['perusahaanBuyer:id,uuid,company_name', 'vendor:id,uuid,company_name']);

        if ($user->perusahaanBuyer !== null) {
            return [
                'uuid' => $user->perusahaanBuyer->uuid,
                'nama' => $user->perusahaanBuyer->company_name,
                'tipe' => 'buyer',
            ];
        }

        if ($user->vendor !== null) {
            return [
                'uuid' => $user->vendor->uuid,
                'nama' => $user->vendor->company_name,
                'tipe' => 'vendor',
            ];
        }

        return null;
    }

    /**
     * Resolve `tenant_id` and `vendor_id` when creating a new user.
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

            return [$this->resolvePerusahaanBuyerId($data['tenant_uuid'] ?? null), null];
        }

        if ($creator->isVendor()) {
            if ($creator->vendor_id === null) {
                throw ValidationException::withMessages([
                    'role' => 'Akun Anda belum terhubung dengan perusahaan vendor mana pun.',
                ]);
            }

            return [null, (int) $creator->vendor_id];
        }

        if ($creator->tenant_id === null) {
            throw ValidationException::withMessages([
                'role' => 'Akun Anda belum terhubung dengan perusahaan mana pun.',
            ]);
        }

        return [(int) $creator->tenant_id, null];
    }

    /**
     * Resolve `tenant_id` / `vendor_id` updates when a superadmin updates a user.
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
                'tenant_id' => null,
                'vendor_id' => null,
            ];
        }

        if ($roleName === 'vendor') {
            $updates = ['tenant_id' => null];
            if (array_key_exists('vendor_uuid', $data)) {
                $updates['vendor_id'] = $this->resolveVendorId($data['vendor_uuid']);
            }

            return $updates;
        }

        $updates = ['vendor_id' => null];
        if (array_key_exists('tenant_uuid', $data)) {
            $updates['tenant_id'] = $this->resolvePerusahaanBuyerId($data['tenant_uuid']);
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
                'tenant_uuid' => 'Perusahaan buyer yang dipilih tidak ditemukan.',
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
