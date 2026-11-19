<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StatusSengketa;
use App\Models\Sengketa;
use App\Models\User;

/**
 * Authorization policy for procurement disputes (`disputes` — UC-18, FR-31, FR-38, FR-40, BR-15, BR-17, RULES §4.5).
 *
 * - Viewing & exporting (`viewAny`, `view`, `export`): Tenant governance roles (Owner, Manajemen, Purchasing)
 *   for their own Tenant, and the Vendor of the disputed PO. Superadmin is excluded from transaction data.
 * - Review notes, proposing a settlement and resolving (`review`, `proposeResolution`, `resolve`):
 *   Owner and Manajemen only, while the dispute is not resolved.
 * - Responding & confirming a reshipment (`respond`): the Vendor of the disputed PO, while not resolved (BR-15:
 *   the Vendor never changes the dispute to `resolved`).
 */
class SengketaPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isSuperadmin()) {
            return false;
        }

        if ($user->isVendor()) {
            return $user->vendor_id !== null && $user->hasPermission('sengketa.respond');
        }

        return $user->tenant_id !== null
            && ($user->isManajemen() || $user->isOwner() || $user->isPurchasing())
            && $user->hasPermission('sengketa.view_any');
    }

    public function view(User $user, Sengketa $sengketa): bool
    {
        return $this->viewAny($user) && $this->isParty($user, $sengketa);
    }

    public function export(User $user, Sengketa $sengketa): bool
    {
        return $this->view($user, $sengketa);
    }

    /**
     * Record a review/mediation note or ask the Vendor for a response (`UC-18` step 3).
     */
    public function review(User $user, Sengketa $sengketa): bool
    {
        return $this->isTenantGovernance($user, 'sengketa.review', $sengketa)
            && $sengketa->status !== StatusSengketa::Resolved;
    }

    /**
     * Choose the settlement and issue its documents (`UC-18` step 4–5).
     */
    public function proposeResolution(User $user, Sengketa $sengketa): bool
    {
        return $this->isTenantGovernance($user, 'sengketa.resolve', $sengketa)
            && $sengketa->status !== StatusSengketa::Resolved;
    }

    /**
     * Set the dispute to `resolved` after verifying the settlement (`UC-18` step 6, BR-15).
     */
    public function resolve(User $user, Sengketa $sengketa): bool
    {
        return $this->proposeResolution($user, $sengketa);
    }

    /**
     * Vendor response: notes, evidence, and confirming or refusing a reshipment (`UC-18` step 5, 5a).
     */
    public function respond(User $user, Sengketa $sengketa): bool
    {
        return $user->isVendor()
            && $user->hasPermission('sengketa.respond')
            && $this->isParty($user, $sengketa)
            && $sengketa->status !== StatusSengketa::Resolved;
    }

    private function isTenantGovernance(User $user, string $permission, Sengketa $sengketa): bool
    {
        return $user->tenant_id !== null
            && ! $user->isSuperadmin()
            && ($user->isManajemen() || $user->isOwner())
            && $user->hasPermission($permission)
            && $this->isParty($user, $sengketa);
    }

    private function isParty(User $user, Sengketa $sengketa): bool
    {
        $po = $sengketa->purchaseOrder;
        if ($po === null) {
            return false;
        }

        if ($user->isVendor()) {
            return $user->vendor_id !== null && (int) $user->vendor_id === (int) $po->vendor_id;
        }

        return $user->tenant_id !== null && (int) $user->tenant_id === (int) $po->perusahaan_buyer_id;
    }
}
