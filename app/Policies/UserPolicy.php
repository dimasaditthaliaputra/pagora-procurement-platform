<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\User;

/**
 * Manajemen Pengguna (UC-08). The `pengguna.*` permissions decide the action; the company
 * scope (own Tenant / own Vendor, platform for Superadmin) and BR-22 role limits stay fixed.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('pengguna.view_any') && $this->hasCompanyScope($user);
    }

    public function view(User $user, User $target): bool
    {
        return $user->hasPermission('pengguna.view_any') && $this->isWithinScope($user, $target);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('pengguna.create') && $this->hasCompanyScope($user);
    }

    /**
     * Own account may be edited (role change is blocked in the service); other accounts
     * only when the target's current role is one the actor may assign.
     */
    public function update(User $user, User $target): bool
    {
        if (! $user->hasPermission('pengguna.update') || ! $this->isWithinScope($user, $target)) {
            return false;
        }

        return $user->isSuperadmin() || $user->is($target) || $this->canAssign($user, $target->roleName());
    }

    /**
     * The signed-in account can never delete itself.
     */
    public function delete(User $user, User $target): bool
    {
        if (! $user->hasPermission('pengguna.delete') || $user->is($target) || ! $this->isWithinScope($user, $target)) {
            return false;
        }

        return $user->isSuperadmin() || $this->canAssign($user, $target->roleName());
    }

    /**
     * Determine whether the actor may give the role to an account.
     */
    public function canAssign(User $user, ?RoleName $role): bool
    {
        return $role !== null
            && in_array($role, $user->roleName()?->assignableRoles() ?? [], true);
    }

    private function hasCompanyScope(User $user): bool
    {
        return $user->isSuperadmin() || $user->tenant_id !== null || $user->vendor_id !== null;
    }

    private function isWithinScope(User $user, User $target): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        if ($target->isSuperadmin()) {
            return false;
        }

        if ($user->tenant_id !== null) {
            return $target->tenant_id === $user->tenant_id && $target->vendor_id === null;
        }

        if ($user->vendor_id !== null) {
            return $target->vendor_id === $user->vendor_id && $target->tenant_id === null;
        }

        return false;
    }
}
