<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PerusahaanBuyer;
use App\Models\User;

/**
 * Tenant legal document and registration verification policy (Superadmin only).
 */
class PerusahaanBuyerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('tenant.view_any');
    }

    public function view(User $user, PerusahaanBuyer $tenant): bool
    {
        return $user->hasPermission('tenant.view_any');
    }

    public function verify(User $user, PerusahaanBuyer $tenant): bool
    {
        return $user->hasPermission('tenant.verify');
    }
}
