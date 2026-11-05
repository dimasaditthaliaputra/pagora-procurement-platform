<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StatusSengketa;
use App\Models\PenerimaanBarang;
use App\Models\Sengketa;
use App\Models\User;

/**
 * Authorization policy for Goods Receipt / QC Inspection (`goods_receipts` — UC-15, FR-24..26, RULES §4.5).
 *
 * - Performing inspection (`create`): strictly QC Gudang and Owner of the PO's Tenant.
 * - Viewing inspection results (`viewAny`, `view`): Tenant internal roles (Owner, Manajemen, Purchasing, QC Gudang)
 *   for their own Tenant, and Vendor for their own POs (UC-16). Superadmin is excluded from transaction data.
 */
class PenerimaanBarangPolicy
{
    /**
     * Determine whether the user can view the goods receipt / QC inspection list.
     */
    public function viewAny(User $user): bool
    {
        if ($user->isSuperadmin()) {
            return false;
        }

        if ($user->tenant_id !== null) {
            return $user->hasPermission('penerimaan_barang.view_any');
        }

        if ($user->vendor_id !== null && $user->isVendor()) {
            return $user->hasPermission('penerimaan_barang.view_any');
        }

        return false;
    }

    /**
     * Determine whether the user can view a specific goods receipt / QC inspection record.
     */
    public function view(User $user, PenerimaanBarang $penerimaanBarang): bool
    {
        if ($user->isSuperadmin()) {
            return false;
        }

        $po = $penerimaanBarang->purchaseOrder;
        if ($po === null) {
            return false;
        }

        if ($user->tenant_id !== null && (int) $user->tenant_id === (int) $po->perusahaan_buyer_id) {
            return $user->hasPermission('penerimaan_barang.view_any');
        }

        if ($user->vendor_id !== null && $user->isVendor() && (int) $user->vendor_id === (int) $po->vendor_id) {
            return $user->hasPermission('penerimaan_barang.view_any');
        }

        return false;
    }

    /**
     * Determine whether the user can perform QC inspection and record goods receipt (UC-15).
     */
    public function create(User $user): bool
    {
        return ! $user->isSuperadmin()
            && $user->tenant_id !== null
            && ($user->isQcGudang() || $user->isOwner())
            && $user->hasPermission('penerimaan_barang.create');
    }

    /**
     * Determine whether the user can append supplementary damage notes/photo evidence to an open QC dispute
     * (`UC-15 «extend»`, `BR-17`, `RULES` §9.4: append-only log, disallowed once dispute is `resolved`).
     */
    public function reportDamageEvidence(User $user, PenerimaanBarang $penerimaanBarang, ?Sengketa $sengketa = null): bool
    {
        if (! $this->create($user)) {
            return false;
        }

        $po = $penerimaanBarang->purchaseOrder;
        if ($po === null || (int) $user->tenant_id !== (int) $po->perusahaan_buyer_id) {
            return false;
        }

        if ($sengketa === null || $sengketa->status === StatusSengketa::Selesai) {
            return false;
        }

        return true;
    }
}
