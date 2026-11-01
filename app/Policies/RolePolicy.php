<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

/**
 * Role & hak akses screen: the six roles are fixed, only their permissions and description change.
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('role.view_any');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->hasPermission('role.update');
    }
}
