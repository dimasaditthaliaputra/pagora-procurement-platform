<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Credit note lifecycle (`credit_notes.status` — PRD §9.1). Only `confirmed` reduces the bill (BR-16).
 */
enum CreditNoteStatus: string
{
    case Draft = 'draft';
    case AwaitingVendorConfirmation = 'awaiting_vendor_confirmation';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::AwaitingVendorConfirmation => 'Menunggu Konfirmasi Vendor',
            self::Confirmed => 'Dikonfirmasi',
            self::Rejected => 'Ditolak',
        };
    }
}
