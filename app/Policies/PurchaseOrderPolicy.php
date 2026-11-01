<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Purchase order (UC-12, UC-13).
 *
 * The PO module is still a frontend prototype; add `view`/`update` (Tenant/Vendor
 * ownership + status checks) once the purchase_orders table is migrated.
 */
class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('purchase_order.view_any');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('purchase_order.create');
    }
}
