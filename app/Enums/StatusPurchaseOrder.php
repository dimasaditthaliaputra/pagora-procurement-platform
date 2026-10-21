<?php

declare(strict_types=1);

namespace App\Enums;

enum StatusPurchaseOrder: string
{
    case Draft = 'draft';
    case MenungguTanggapan = 'menunggu_tanggapan';
    case Negosiasi = 'negosiasi';
    case Disepakati = 'disepakati';
    case DalamPengiriman = 'dalam_pengiriman';
    case DiterimaGudang = 'diterima_gudang';
    case Ditagih = 'ditagih';
    case Lunas = 'lunas';
    case Dibatalkan = 'dibatalkan';
    case Sengketa = 'sengketa';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::MenungguTanggapan => 'Menunggu Tanggapan',
            self::Negosiasi => 'Negosiasi',
            self::Disepakati => 'Disepakati',
            self::DalamPengiriman => 'Dalam Pengiriman',
            self::DiterimaGudang => 'Diterima Gudang',
            self::Ditagih => 'Ditagih',
            self::Lunas => 'Lunas',
            self::Dibatalkan => 'Dibatalkan',
            self::Sengketa => 'Sengketa',
        };
    }

    /**
     * Apakah PO masih bisa dibatalkan (sebelum disepakati).
     */
    public function bisaDibatalkan(): bool
    {
        return in_array($this, [
            self::Draft,
            self::MenungguTanggapan,
            self::Negosiasi,
        ]);
    }
}
