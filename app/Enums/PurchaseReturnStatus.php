<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Purchase return lifecycle (`purchase_returns.status` — PRD §9.1, BR-27).
 * `confirmed` → `completed` is the only change allowed on a confirmed return (BR-17).
 */
enum PurchaseReturnStatus: string
{
    case Draft = 'draft';
    case AwaitingVendorConfirmation = 'awaiting_vendor_confirmation';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';

    /** The Vendor has received the returned goods back. */
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::AwaitingVendorConfirmation => 'Menunggu Konfirmasi Vendor',
            self::Confirmed => 'Dikonfirmasi',
            self::Rejected => 'Ditolak',
            self::Completed => 'Selesai',
        };
    }

    /**
     * Confirmed by the Vendor (also once the goods have arrived back).
     */
    public function isConfirmed(): bool
    {
        return $this === self::Confirmed || $this === self::Completed;
    }
}
