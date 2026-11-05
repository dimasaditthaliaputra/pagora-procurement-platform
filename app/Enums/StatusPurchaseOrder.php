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
    case PartiallyReceived = 'partially_received';
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
            self::PartiallyReceived => 'Diterima Sebagian',
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
        ], true);
    }

    /**
     * Apakah PO dapat dilaporkan pengiriman fisiknya oleh Vendor (BR-09, UC-14).
     */
    public function bisaDikirim(): bool
    {
        return in_array($this, [
            self::Disepakati,
            self::PartiallyReceived,
        ], true);
    }

    /**
     * Apakah PO dapat diinspeksi fisiknya oleh QC Gudang / Owner (BR-09, UC-15).
     */
    public function bisaDiinspeksi(): bool
    {
        return $this === self::DalamPengiriman;
    }
}
