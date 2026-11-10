<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StatusMatching;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    /**
     * Determine whether the user can view any invoices.
     * Vendor views their issued invoices; Buyer (Owner, Purchasing) views received invoices.
     */
    public function viewAny(User $user): bool
    {
        if ($user->isVendor() && $user->vendor_id !== null) {
            return $user->hasPermission('purchase_order.view_any');
        }

        if (($user->isOwner() || $user->isPurchasing()) && $user->tenant_id !== null) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view the specific invoice.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        if ($user->isVendor() && $user->vendor_id !== null) {
            return (int) $user->vendor_id === (int) $invoice->vendor_id;
        }

        if (($user->isOwner() || $user->isPurchasing()) && $user->tenant_id !== null) {
            $buyerTenantId = $invoice->purchaseOrder?->perusahaan_buyer_id;

            return (int) $user->tenant_id === (int) $buyerTenantId;
        }

        return false;
    }

    /**
     * Determine whether buyer (Owner / Purchasing) can view verification list.
     */
    public function verifyAny(User $user): bool
    {
        return ($user->isOwner() || $user->isPurchasing()) && $user->tenant_id !== null;
    }

    /**
     * Determine whether buyer (Owner / Purchasing) can execute 3-Way Matching on this invoice.
     */
    public function verify(User $user, Invoice $invoice): bool
    {
        if (! $this->verifyAny($user)) {
            return false;
        }

        $buyerTenantId = $invoice->purchaseOrder?->perusahaan_buyer_id;

        if ((int) $user->tenant_id !== (int) $buyerTenantId) {
            return false;
        }

        // Only pending matching status can be verified
        $status = is_object($invoice->status_matching)
            ? $invoice->status_matching->value
            : $invoice->status_matching;

        return $status === StatusMatching::Pending->value || $status === 'pending';
    }

    /**
     * Vendor can issue invoices.
     */
    public function create(User $user): bool
    {
        return $user->isVendor()
            && $user->vendor_id !== null
            && $user->hasPermission('purchase_order.view_any');
    }

    /**
     * Issued invoices cannot be edited.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        return false;
    }

    /**
     * Issued invoices cannot be deleted.
     */
    public function delete(User $user, Invoice $invoice): bool
    {
        return false;
    }

    public function restore(User $user, Invoice $invoice): bool
    {
        return false;
    }

    public function forceDelete(User $user, Invoice $invoice): bool
    {
        return false;
    }
}
