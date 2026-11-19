<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PurchaseReturnStatus;
use App\Models\PurchaseReturn;
use App\Models\User;

/**
 * Only the Vendor of the PO confirms, refuses, and finally marks a return as received back (FR-42, BR-27).
 * Owner/Manajemen issue returns through `SengketaPolicy::proposeResolution`.
 */
class PurchaseReturnPolicy
{
    public function confirm(User $user, PurchaseReturn $purchaseReturn): bool
    {
        return $this->isPoVendor($user, $purchaseReturn)
            && $purchaseReturn->status === PurchaseReturnStatus::AwaitingVendorConfirmation;
    }

    public function reject(User $user, PurchaseReturn $purchaseReturn): bool
    {
        return $this->confirm($user, $purchaseReturn);
    }

    /**
     * The returned goods arrived back at the Vendor (`confirmed` → `completed`).
     */
    public function complete(User $user, PurchaseReturn $purchaseReturn): bool
    {
        return $this->isPoVendor($user, $purchaseReturn)
            && $purchaseReturn->status === PurchaseReturnStatus::Confirmed;
    }

    private function isPoVendor(User $user, PurchaseReturn $purchaseReturn): bool
    {
        return $user->isVendor()
            && $user->vendor_id !== null
            && $user->hasPermission('sengketa.respond')
            && (int) $user->vendor_id === (int) $purchaseReturn->purchaseOrder?->vendor_id;
    }
}
