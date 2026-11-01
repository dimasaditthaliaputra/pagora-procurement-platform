<?php

declare(strict_types=1);

namespace App\Enums;

enum StatusKerjaSama: string
{
    case Pending = 'pending';
    case Agreed = 'agreed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Agreed => 'Disepakati',
            self::Rejected => 'Ditolak',
            self::Cancelled => 'Dibatalkan',
            self::Blocked => 'Diblokir',
        };
    }
}
