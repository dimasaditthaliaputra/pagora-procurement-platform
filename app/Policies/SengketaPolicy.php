<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StatusSengketa;
use App\Models\Sengketa;
use App\Models\User;

/**
 * Authorization policy for Procurement Dispute Review & Log Sengketa
 * (`sengketa` / `disputes` — UC-18, FR-31, FR-10, FR-38, BR-15, BR-17, RULES §4.5).
 *
 * - Viewing & exporting dispute logs (`viewAny`, `view`, `export`): Tenant internal governance roles
 *   (Manajemen, Owner, Purchasing) for their own Tenant only. Superadmin is excluded from transaction data.
 * - Recording review/mediation progress (`review`): strictly Manajemen and Owner for non-resolved disputes
 *   belonging to their Tenant.
 */
class SengketaPolicy
{
    /**
     * Determine whether the user can view the dispute log list (`Log Sengketa`).
     */
    public function viewAny(User $user): bool
    {
        if ($user->isSuperadmin() || $user->tenant_id === null) {
            return false;
        }

        return ($user->isManajemen() || $user->isOwner() || $user->isPurchasing())
            && $user->hasPermission('sengketa.view_any');
    }

    /**
     * Determine whether the user can view a specific dispute and its complete audit log.
     */
    public function view(User $user, Sengketa $sengketa): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        $po = $sengketa->purchaseOrder;
        if ($po === null) {
            return false;
        }

        return (int) $user->tenant_id === (int) $po->perusahaan_buyer_id;
    }

    /**
     * Determine whether the user can export/download the official dispute document (`FR-31`, `UC-18` step 8).
     */
    public function export(User $user, Sengketa $sengketa): bool
    {
        return $this->view($user, $sengketa);
    }

    /**
     * Determine whether the user (Manajemen / Owner) can record a review/mediation note
     * and move an open dispute into mediation (`UC-18` step 3, `PRD §9.3`, `BR-17`).
     */
    public function review(User $user, Sengketa $sengketa): bool
    {
        if ($user->isSuperadmin() || $user->tenant_id === null) {
            return false;
        }

        if (! ($user->isManajemen() || $user->isOwner()) || ! $user->hasPermission('sengketa.review')) {
            return false;
        }

        $po = $sengketa->purchaseOrder;
        if ($po === null || (int) $user->tenant_id !== (int) $po->perusahaan_buyer_id) {
            return false;
        }

        return $sengketa->status !== StatusSengketa::Selesai;
    }
}
