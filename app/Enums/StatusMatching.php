<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status matching invoice (PRD §9.1, design-db.md §4.21, UC-17).
 */
enum StatusMatching: string
{
    case Pending = 'pending';
    case Matched = 'matched';
    case Mismatched = 'mismatched';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Verifikasi',
            self::Matched => 'Sesuai',
            self::Mismatched => 'Tidak Sesuai',
        };
    }

    /**
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

