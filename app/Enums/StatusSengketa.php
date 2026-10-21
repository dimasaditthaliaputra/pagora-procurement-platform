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
}
