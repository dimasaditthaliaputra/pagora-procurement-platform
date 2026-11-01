<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validasi format Nomor Pokok Wajib Pajak (NPWP) Indonesia:
 * - Format standar 15 digit bertitik: 01.234.567.8-901.000
 * - Format 15 digit angka: 012345678901000
 * - Format 16 digit angka (NPWP 16 / NIK sesuai PMK 112/2022): 0123456789010001
 *
 * Sesuai PRD.md §9.4 (NPWP format valid) dan design-db.md (§4.3 tenants.tax_number & §4.4 vendors.tax_number).
 * Terhubung dengan validator UI `isNpwp()` di resources/js/lib/validators.js.
 */
class Npwp implements ValidationRule
{
    public const PATTERN = '/^(\d{2}\.\d{3}\.\d{3}\.\d{1}-\d{3}\.\d{3}|\d{15}|\d{16})$/';

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = trim((string) $value);

        if (preg_match(self::PATTERN, $value) !== 1) {
            $fail('Format :attribute tidak valid. Gunakan format 15 digit (contoh: 01.234.567.8-901.000) atau 16 digit angka.');
        }
    }
}
