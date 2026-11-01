<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Application settings (platform management).
 */
class PengaturanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('pengaturan.view_any');
    }

    public function update(User $user): bool
    {
        return $user->hasPermission('pengaturan.update');
    }
}
