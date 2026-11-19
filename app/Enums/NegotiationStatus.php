<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Per-item negotiation status (`po_item.status_item` → target `purchase_order_items.negotiation_status`, PRD §9).
 */
enum NegotiationStatus: string
{
    /** Waiting for a Purchasing proposal, or for the Vendor to answer it. */
    case Pending = 'pending';

    /** Vendor proposed another price; waiting for Purchasing. */
    case Countered = 'countered';

    case Agreed = 'agreed';

    /** Purchasing dropped the item without agreement (Q-24, BR-06). */
    case Removed = 'removed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Countered => 'Ditawar Ulang',
            self::Agreed => 'Disepakati',
            self::Removed => 'Dikeluarkan',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::Countered;
    }
}
