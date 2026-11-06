<?php

declare(strict_types=1);

namespace App\Enums;

enum StatusSengketa: string
{
    case Terbuka = 'terbuka';
    case DalamMediasi = 'dalam_mediasi';
    case Selesai = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::Terbuka => 'Terbuka',
            self::DalamMediasi => 'Dalam Mediasi',
            self::Selesai => 'Selesai',
        };
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
