<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Rules\PhoneNumber;

/**
 * Phone number normalization to E.164 with Indonesia (+62) as the default country.
 * Mirrors `normalizePhoneNumber()` in resources/js/lib/validators.js.
 *
 *   0812-3456-7890 → +6281234567890      (021) 555 1234 → +62215551234
 *   6281234567890  → +6281234567890      +62 0812 3456 7890 → +6281234567890
 *   0062812345678  → +62812345678        +65 6123 4567 → +6561234567
 */
class PhoneHelper
{
    public const COUNTRY_CODE = '62';

    /**
     * Normalize to E.164. Blank input becomes null; input that is not shaped like a phone number
     * (e.g. contains letters) is returned trimmed so the PhoneNumber rule can reject it.
     */
    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match(PhoneNumber::PATTERN, $value) !== 1) {
            return $value;
        }

        $digits = (string) preg_replace('/\D/', '', $value);

        if (str_starts_with($value, '+')) {
            return self::withCountryCode($digits);
        }

        return match (true) {
            str_starts_with($digits, '00') => self::withCountryCode(substr($digits, 2)),
            str_starts_with($digits, '0') => '+'.self::COUNTRY_CODE.substr($digits, 1),
            str_starts_with($digits, self::COUNTRY_CODE) => self::withCountryCode($digits),
            str_starts_with($digits, '8') => '+'.self::COUNTRY_CODE.$digits,
            default => $digits,
        };
    }

    /**
     * Prefix "+" and drop the stray trunk "0" people type after +62 (+62 0812… → +62812…).
     */
    private static function withCountryCode(string $digits): string
    {
        if (str_starts_with($digits, self::COUNTRY_CODE.'0')) {
            $digits = self::COUNTRY_CODE.substr($digits, strlen(self::COUNTRY_CODE) + 1);
        }

        return '+'.$digits;
    }
}
