<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Dispute lifecycle (`disputes.status` — PRD §9.3). Separate from the PO status (BR-14).
 */
enum StatusSengketa: string
{
    case Open = 'open';
    case AwaitingResponse = 'awaiting_response';
    case InMediation = 'in_mediation';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Terbuka',
            self::AwaitingResponse => 'Menunggu Tanggapan',
            self::InMediation => 'Dalam Mediasi',
            self::Resolved => 'Selesai',
        };
    }

    /**
     * Every status except `resolved` keeps the purchase order frozen (BR-14).
     */
    public function isOpen(): bool
    {
        return $this !== self::Resolved;
    }

    /**
     * All enum values as strings for validation rules.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $case): string => $case->value,
            self::cases()
        );
    }

    /**
     * Formatted options for frontend Select / filter controls.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            self::cases()
        );
    }
}
