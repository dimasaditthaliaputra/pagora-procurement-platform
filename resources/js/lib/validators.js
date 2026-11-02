/**
 * Validasi format di sisi UI untuk umpan balik cepat. Backend tetap sumber kebenaran:
 * pola di sini harus sama dengan aturan di Form Request / app/Rules.
 */

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

// Sama dengan App\Rules\PhoneNumber (PATTERN, MIN_DIGITS, MAX_DIGITS).
const PHONE_PATTERN = /^\+?[0-9\s().-]+$/;
const PHONE_MIN_DIGITS = 8;
const PHONE_MAX_DIGITS = 15;

// Sama dengan App\Rules\Npwp (PATTERN).
const NPWP_PATTERN = /^(\d{2}\.\d{3}\.\d{3}\.\d{1}-\d{3}\.\d{3}|\d{15}|\d{16})$/;

// Sama dengan KomoditasService::SHRINKAGE_TOLERANCE_PATTERN.
const SHRINKAGE_TOLERANCE_PATTERN = /^(?:100(?:\.0{1,2})?|\d{1,2}(?:\.\d{1,2})?)$/;

export function isEmail(value) {
    return EMAIL_PATTERN.test(String(value ?? '').trim());
}

export function isPhoneNumber(value) {
    const trimmed = String(value ?? '').trim();
    const digits = trimmed.replace(/\D/g, '').length;

    return PHONE_PATTERN.test(trimmed) && digits >= PHONE_MIN_DIGITS && digits <= PHONE_MAX_DIGITS;
}

export function isNpwp(value) {
    return NPWP_PATTERN.test(String(value ?? '').trim());
}

/**
 * Samakan format nomor NPWP ke format kanonikal:
 * - 15 digit angka -> XX.XXX.XXX.X-XXX.XXX
 * - 16 digit angka -> 16 digit angka murni
 * Padanan App\Helpers\NpwpHelper.
 */
export function normalizeNpwp(value) {
    const trimmed = String(value ?? '').trim();
    if (!trimmed) return trimmed;

    const digits = trimmed.replace(/\D/g, '');

    if (digits.length === 15) {
        return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5, 8)}.${digits.slice(8, 9)}-${digits.slice(9, 12)}.${digits.slice(12, 15)}`;
    }

    if (digits.length === 16) {
        return digits;
    }

    return trimmed;
}

/**
 * Samakan format nomor telepon ke E.164 (Indonesia +62 sebagai default); padanan App\Helpers\PhoneHelper.
 * Input yang tidak berbentuk nomor telepon dikembalikan apa adanya agar ditolak oleh validasi.
 */
export function normalizePhoneNumber(value) {
    const trimmed = String(value ?? '').trim();

    if (trimmed === '' || !PHONE_PATTERN.test(trimmed)) return trimmed;

    const digits = trimmed.replace(/\D/g, '');
    const withCountryCode = (number) => `+${number.startsWith('620') ? `62${number.slice(3)}` : number}`;

    if (trimmed.startsWith('+')) return withCountryCode(digits);
    if (digits.startsWith('00')) return withCountryCode(digits.slice(2));
    if (digits.startsWith('0')) return `+62${digits.slice(1)}`;
    if (digits.startsWith('62')) return withCountryCode(digits);
    if (digits.startsWith('8')) return `+62${digits}`;

    return digits;
}

export function isShrinkageTolerance(value) {
    return SHRINKAGE_TOLERANCE_PATTERN.test(String(value ?? '').trim());
}
