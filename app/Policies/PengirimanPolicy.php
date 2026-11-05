<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Pengiriman;
use App\Models\User;

/**
 * Authorization policy for Self-Reporting Logistics (`shipments` — UC-14, FR-22, PRD §3).
 */
class PengirimanPolicy
{
    /**
     * Determine whether the user can view the shipment list.
     */
    public function viewAny(User $user): bool
    {
        if ($user->isSuperadmin()) {
            return false;
        }

        if ($user->tenant_id === null && $user->vendor_id === null) {
            return false;
        }

        return $user->hasPermission('pengiriman.view_any');
    }

    /**
     * Determine whether the user can view a specific shipment.
     */
    public function view(User $user, Pengiriman $pengiriman): bool
    {
        if ($user->isSuperadmin()) {
            return false;
        }

        $purchaseOrder = $pengiriman->purchaseOrder;

        if ($purchaseOrder === null) {
            return false;
        }

        if ($user->tenant_id !== null && (int) $user->tenant_id === (int) $purchaseOrder->perusahaan_buyer_id) {
            return $user->hasPermission('pengiriman.view_any');
        }

        if ($user->vendor_id !== null && (int) $user->vendor_id === (int) $purchaseOrder->vendor_id) {
            return $user->hasPermission('pengiriman.view_any');
        }

        return false;
    }

    /**
     * Determine whether the user can submit a self-reporting logistics form (Vendor only — UC-14, FR-22).
     */
    public function create(User $user): bool
    {
        return $user->isVendor()
            && $user->vendor_id !== null
            && $user->hasPermission('pengiriman.create');
    }
}
