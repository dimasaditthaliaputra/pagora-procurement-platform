<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Normalisasi nomor NPWP Indonesia ke format kanonikal:
 * - 15 digit angka: XX.XXX.XXX.X-XXX.XXX (format bertitik resmi)
 * - 16 digit angka: 16 digit angka murni (NIK / NPWP 16 PMK 112/2022)
 *
 * Menghindari duplikasi akibat variasi input (mis. bertitik vs tanpa titik)
 * dan mencegah celah race condition pada indeks unik database.
 * Terhubung dengan `normalizeNpwp()` di resources/js/lib/validators.js.
 */
class NpwpHelper
{
    /**
     * Normalisasi nomor NPWP.
     * Jika input null atau kosong, kembalikan null.
     * Jika 15 digit angka, format ke XX.XXX.XXX.X-XXX.XXX.
     * Jika 16 digit angka, kembalikan 16 digit angka.
     * Jika tidak valid, kembalikan teks hasil trim agar rule Npwp dapat memvalidasi dan menolak.
     */
    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $digits = (string) preg_replace('/\D/', '', $value);

        if (strlen($digits) === 15) {
            return sprintf(
                '%s.%s.%s.%s-%s.%s',
                substr($digits, 0, 2),
                substr($digits, 2, 3),
                substr($digits, 5, 3),
                substr($digits, 8, 1),
                substr($digits, 9, 3),
                substr($digits, 12, 3)
            );
        }

        if (strlen($digits) === 16) {
            return $digits;
        }

        return $value;
    }
}
