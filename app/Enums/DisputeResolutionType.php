<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a dispute is settled (`disputes.resolution_type` — PRD §9.3, FR-40). Every type needs the
 * Vendor's confirmation before Owner/Manajemen may resolve the dispute (BR-15).
 */
enum DisputeResolutionType: string
{
    case Reshipment = 'reshipment';
    case Return = 'return';
    case CreditNote = 'credit_note';

    public function label(): string
    {
        return match ($this) {
            self::Reshipment => 'Pengiriman Ulang',
            self::Return => 'Retur',
            self::CreditNote => 'Potongan Tagihan (Credit Note)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Reshipment => 'Vendor mengirim ulang item yang ditolak; PO kembali Dalam Pengiriman lalu diinspeksi ulang.',
            self::Return => 'Barang yang ditolak QC dikembalikan ke Vendor dan tidak ditagihkan.',
            self::CreditNote => 'Tagihan invoice dikurangi sebesar nominal credit note setelah dikonfirmasi Vendor.',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(static fn (self $case): array => [
            'value' => $case->value,
            'label' => $case->label(),
            'description' => $case->description(),
        ], self::cases());
    }
}
