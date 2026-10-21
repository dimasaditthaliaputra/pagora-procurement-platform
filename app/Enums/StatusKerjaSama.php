<?php

declare(strict_types=1);

namespace App\Enums;

enum StatusKerjaSama: string
{
    case Diajukan = 'diajukan';
    case Aktif = 'aktif';
    case Ditolak = 'ditolak';
    case Berakhir = 'berakhir';

    public function label(): string
    {
        return match ($this) {
            self::Diajukan => 'Diajukan',
            self::Aktif => 'Aktif',
            self::Ditolak => 'Ditolak',
            self::Berakhir => 'Berakhir',
        };
    }
}
