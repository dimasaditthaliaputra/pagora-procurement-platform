<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Phone number in a common Indonesian or international format: 081234567890, +62 812-3456-7890, (021) 555 1234.
 * Mirrors `isPhoneNumber()` in resources/js/lib/validators.js.
 */
class PhoneNumber implements ValidationRule
{
    public const PATTERN = '/^\+?[0-9\s().-]+$/';

    public const MIN_DIGITS = 8;

    public const MAX_DIGITS = 15;

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = trim((string) $value);
        $digits = strlen((string) preg_replace('/\D/', '', $value));

        if (
            preg_match(self::PATTERN, $value) !== 1
            || $digits < self::MIN_DIGITS
            || $digits > self::MAX_DIGITS
        ) {
            $fail('Format :attribute tidak valid. Gunakan 8–15 digit angka, contoh: 081234567890.');
        }
    }
}
