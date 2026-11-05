<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Physical shipment status reported by Vendor and confirmed by Warehouse QC (UC-14, UC-15, PRD §8.3).
 */
enum StatusPengiriman: string
{
    case InTransit = 'in_transit';
    case Arrived = 'arrived';

    /**
     * Human-readable Indonesian label for UI presentation.
     */
    public function label(): string
    {
        return match ($this) {
            self::InTransit => 'Dalam Pengiriman',
            self::Arrived => 'Tiba di Tujuan',
        };
    }

    /**
     * Available physical transport routes for self-reporting logistics (UC-14, FR-22).
     *
     * @return list<string>
     */
    public static function routes(): array
    {
        return [
            'Darat',
            'Laut',
            'Udara',
            'Kereta Api',
        ];
    }
}
