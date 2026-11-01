<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Vendor;

/**
 * Data rekanan vendor: listing, invitation and blocking.
 */
class VendorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('vendor.view_any');
    }

    public function invite(User $user): bool
    {
        return $user->hasPermission('vendor.invite');
    }

    public function block(User $user, Vendor $vendor): bool
    {
        return $user->hasPermission('vendor.block');
    }

    public function unblock(User $user, Vendor $vendor): bool
    {
        return $user->hasPermission('vendor.unblock');
    }
}
