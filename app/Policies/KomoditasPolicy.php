<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Komoditas;
use App\Models\User;

/**
 * Master komoditas global (UC-09); mutations are platform-only permissions.
 */
class KomoditasPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('komoditas.view_any');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('komoditas.create');
    }

    public function update(User $user, Komoditas $komoditas): bool
    {
        return $user->hasPermission('komoditas.update');
    }

    public function delete(User $user, Komoditas $komoditas): bool
    {
        return $user->hasPermission('komoditas.delete');
    }
}
