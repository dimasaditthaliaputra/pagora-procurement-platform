<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Per-item QC inspection statuses (`goods_receipt_items.status` — PRD §9.4, UC-15, BR-10).
 */
enum StatusItemPenerimaan: string
{
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Accepted => 'Disetujui',
            self::Rejected => 'Ditolak',
        };
    }
}

