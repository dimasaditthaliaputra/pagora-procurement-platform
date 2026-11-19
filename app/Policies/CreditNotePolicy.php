<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CreditNoteStatus;
use App\Models\CreditNote;
use App\Models\User;

/**
 * Only the Vendor of the billed PO confirms or refuses a credit note (FR-41, BR-16).
 * Owner/Manajemen issue credit notes through `SengketaPolicy::proposeResolution`.
 */
class CreditNotePolicy
{
    public function confirm(User $user, CreditNote $creditNote): bool
    {
        return $this->isBilledVendor($user, $creditNote)
            && $creditNote->status === CreditNoteStatus::AwaitingVendorConfirmation;
    }

    public function reject(User $user, CreditNote $creditNote): bool
    {
        return $this->confirm($user, $creditNote);
    }

    private function isBilledVendor(User $user, CreditNote $creditNote): bool
    {
        return $user->isVendor()
            && $user->vendor_id !== null
            && $user->hasPermission('sengketa.respond')
            && (int) $user->vendor_id === (int) $creditNote->invoice?->vendor_id;
    }
}
