<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StatusPurchaseOrder;
use App\Models\PurchaseOrder;
use App\Models\User;

/**
 * Purchase order policy (UC-12, UC-13, RULES §4.5).
 */
class PurchaseOrderPolicy
{
    /**
     * Determine whether the user can view any purchase orders.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('purchase_order.view_any');
    }

    /**
     * Determine whether the user can view the specific purchase order.
     */
    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        if ($user->isSuperadmin()) {
            return false;
        }

        if ($user->tenant_id !== null && (int) $user->tenant_id === (int) $purchaseOrder->perusahaan_buyer_id) {
            return $user->hasPermission('purchase_order.view_any');
        }

        if ($user->vendor_id !== null && (int) $user->vendor_id === (int) $purchaseOrder->vendor_id) {
            return $purchaseOrder->status !== StatusPurchaseOrder::Draft
                && $user->hasPermission('purchase_order.view_any');
        }

        return false;
    }

    /**
     * Determine whether the user can create purchase orders.
     */
    public function create(User $user): bool
    {
        return $user->tenant_id !== null && $user->hasPermission('purchase_order.create');
    }

    /**
     * Determine whether the user can update the purchase order (draft only).
     */
    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->tenant_id !== null
            && (int) $user->tenant_id === (int) $purchaseOrder->perusahaan_buyer_id
            && $user->hasPermission('purchase_order.create')
            && $purchaseOrder->status === StatusPurchaseOrder::Draft;
    }

    /**
     * Determine whether the user can delete the purchase order (draft only, soft delete).
     */
    public function delete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->update($user, $purchaseOrder);
    }

    /**
     * Determine whether the user can confirm/submit the purchase order to negotiation.
     */
    public function konfirmasi(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->update($user, $purchaseOrder);
    }

    /**
     * Determine whether the user can open/view the negotiation room.
     */
    public function negosiasi(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->view($user, $purchaseOrder)
            && $purchaseOrder->status !== StatusPurchaseOrder::Draft;
    }

    /**
     * Determine whether the user can submit an offer in negotiation.
     */
    public function tawar(User $user, PurchaseOrder $purchaseOrder): bool
    {
        if ($purchaseOrder->status !== StatusPurchaseOrder::Negosiasi) {
            return false;
        }

        if ($user->vendor_id !== null && (int) $user->vendor_id === (int) $purchaseOrder->vendor_id) {
            return $user->hasPermission('purchase_order.view_any');
        }

        if ($user->tenant_id !== null && (int) $user->tenant_id === (int) $purchaseOrder->perusahaan_buyer_id) {
            return $user->hasPermission('purchase_order.create');
        }

        return false;
    }

    /**
     * Determine whether the user can agree to the price and lock the purchase order.
     */
    public function setujui(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->tawar($user, $purchaseOrder);
    }

    /**
     * Determine whether the user can cancel the purchase order during negotiation.
     */
    public function batalkan(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->tenant_id !== null
            && (int) $user->tenant_id === (int) $purchaseOrder->perusahaan_buyer_id
            && $user->hasPermission('purchase_order.create')
            && $purchaseOrder->status->bisaDibatalkan();
    }

    /**
     * Determine whether the vendor user can report physical shipment for this PO (UC-14, BR-09).
     */
    public function kirim(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->isVendor()
            && $user->vendor_id !== null
            && (int) $user->vendor_id === (int) $purchaseOrder->vendor_id
            && $user->hasPermission('pengiriman.create')
            && $purchaseOrder->status->bisaDikirim();
    }
}
