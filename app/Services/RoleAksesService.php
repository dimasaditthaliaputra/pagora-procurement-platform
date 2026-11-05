<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RoleAksesService
{
    /**
     * Indonesian labels for permission modules, in display order.
     *
     * @var array<string, string>
     */
    public const MODULE_LABELS = [
        'purchase_order' => 'Purchase Order',
        'pengiriman' => 'Pengiriman Logistik',
        'komoditas' => 'Master Komoditas',
        'vendor' => 'Data Rekanan Vendor',
        'dokumen_legalitas' => 'Verifikasi Legalitas Vendor',
        'tenant' => 'Verifikasi Legalitas Tenant',
        'pengguna' => 'Manajemen Pengguna',
        'pengaturan' => 'Pengaturan Aplikasi',
        'role' => 'Role & Hak Akses',
    ];

    /**
     * Permissions the superadmin role must always keep, so nobody locks the platform out of this screen.
     *
     * @var list<string>
     */
    public const LOCKED_SUPERADMIN_PERMISSIONS = ['role.view_any', 'role.update'];

    public function __construct(
        protected readonly RoleRepositoryInterface $roleRepository,
        protected readonly PermissionRepositoryInterface $permissionRepository
    ) {}

    /**
     * @return Collection<int, Role>
     */
    public function getRoles(): Collection
    {
        return $this->roleRepository->allWithPermissionsAndUserCount();
    }

    /**
     * Permissions grouped per module for the access matrix.
     *
     * @return list<array{module: string, label: string, permissions: list<array{name: string, label: string, action: string, assignable_roles: list<string>, locked_roles: list<string>}>}>
     */
    public function getPermissionGroups(): array
    {
        $groups = $this->permissionRepository->allOrdered()
            ->groupBy('module')
            ->map(fn (Collection $permissions, string $module): array => [
                'module' => $module,
                'label' => self::MODULE_LABELS[$module] ?? ucfirst(str_replace('_', ' ', $module)),
                'permissions' => $permissions->map(fn (Permission $permission): array => [
                    'name' => $permission->name,
                    'label' => $permission->label,
                    'action' => $permission->action,
                    'assignable_roles' => $permission->assignable_roles,
                    'locked_roles' => in_array($permission->name, self::LOCKED_SUPERADMIN_PERMISSIONS, true)
                        ? [RoleName::Superadmin->value]
                        : [],
                ])->values()->all(),
            ]);

        $order = array_flip(array_keys(self::MODULE_LABELS));

        return $groups->sortBy(fn (array $group): int => $order[$group['module']] ?? PHP_INT_MAX)->values()->all();
    }

    /**
     * Replace a role's permissions and description.
     *
     * @param  list<string>  $permissionNames
     *
     * @throws ModelNotFoundException
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function updateAccess(User $actor, string $roleName, ?string $description, array $permissionNames): Role
    {
        $role = $this->roleRepository->findByName($roleName);
        $roleEnum = $role?->roleName();

        if ($role === null || $roleEnum === null) {
            throw (new ModelNotFoundException('Role tidak ditemukan.'))->setModel(Role::class, [$roleName]);
        }

        Gate::forUser($actor)->authorize('update', $role);

        $permissionNames = array_values(array_unique($permissionNames));
        $permissions = $this->permissionRepository->findByNames($permissionNames);

        if ($permissions->count() !== count($permissionNames)) {
            throw ValidationException::withMessages([
                'permissions' => 'Terdapat hak akses yang tidak dikenal.',
            ]);
        }

        $notAssignable = $permissions->reject(fn (Permission $permission): bool => $permission->isAssignableTo($roleEnum));

        if ($notAssignable->isNotEmpty()) {
            throw ValidationException::withMessages([
                'permissions' => "Hak akses berikut tidak boleh diberikan ke role {$roleEnum->label()}: "
                    .$notAssignable->pluck('label')->implode(', ').'.',
            ]);
        }

        if (
            $roleEnum === RoleName::Superadmin
            && array_diff(self::LOCKED_SUPERADMIN_PERMISSIONS, $permissionNames) !== []
        ) {
            throw ValidationException::withMessages([
                'permissions' => 'Hak akses Role & Hak Akses pada Superadmin tidak dapat dicabut.',
            ]);
        }

        return DB::transaction(fn (): Role => $this->roleRepository->updateAccess(
            $role,
            $description !== null ? trim($description) : null,
            $permissions->modelKeys()
        ));
    }
}
