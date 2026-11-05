<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    /**
     * Vendor can view their own invoice list.
     */
    public function viewAny(User $user): bool
    {
        return $user->vendor_id !== null
            && $user->hasPermission('purchase_order.view_any');
    }

    /**
     * Vendor can only view invoices belonging to their vendor account.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        return $this->viewAny($user)
            && (int) $user->vendor_id === (int) $invoice->vendor_id;
    }

    /**
     * Vendor can issue invoices.
     */
    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Issued invoices cannot be edited by vendors.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        return false;
    }

    /**
     * Issued invoices cannot be deleted by vendors.
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
