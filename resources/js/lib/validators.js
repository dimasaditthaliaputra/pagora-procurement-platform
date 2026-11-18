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
 * Pembatasan input nomor NPWP (hanya angka murni):
 * - Hanya mengizinkan digit angka (0-9).
 * - Maksimal 16 digit angka (sesuai NPWP 16 / NIK).
 */
export function limitNpwpInput(value) {
    if (!value) return '';
    return String(value).replace(/\D/g, '').slice(0, 16);
}

/**
 * Pembatasan input nomor NIB (hanya angka murni):
 * - Hanya mengizinkan digit angka (0-9).
 * - Maksimal 13 digit angka sesuai standar OSS.
 */
export function limitNibInput(value) {
    if (!value) return '';
    return String(value).replace(/\D/g, '').slice(0, 13);
}

/**
 * Pembatasan input nomor KTP / NIK penanggung jawab (hanya angka murni):
 * - Hanya mengizinkan digit angka (0-9).
 * - Maksimal tepat 16 digit angka sesuai standar Dukcapil.
 */
export function limitKtpInput(value) {
    if (!value) return '';
    return String(value).replace(/\D/g, '').slice(0, 16);
}

/**
 * Pembatasan input nomor SPPKP:
 * - Mengizinkan alfanumerik, garis miring, titik, strip, spasi.
 * - Maksimal 50 karakter.
 */
export function limitSppkpInput(value) {
    if (!value) return '';
    return String(value)
        .replace(/[^a-zA-Z0-9\/\.\-\s]/g, '')
        .slice(0, 50);
}

/**
 * Pembatasan input nomor Akta Pendirian:
 * - Mengizinkan alfanumerik, garis miring, titik, strip, koma, spasi.
 * - Maksimal 50 karakter.
 */
export function limitAktaInput(value) {
    if (!value) return '';
    return String(value)
        .replace(/[^a-zA-Z0-9\/\.\-\,\s]/g, '')
        .slice(0, 50);
}

/**
 * Pembatasan input nomor SK Pengesahan Kemenkumham:
 * - Mengizinkan alfanumerik, garis miring, titik, strip, koma, spasi.
 * - Maksimal 50 karakter (contoh: AHU-0012345.AH.01.01.TAHUN 2023).
 */
export function limitSkKemenkumhamInput(value) {
    if (!value) return '';
    return String(value)
        .replace(/[^a-zA-Z0-9\/\.\-\,\s]/g, '')
        .slice(0, 50);
}

/**
 * Pembatasan input nomor telepon (hanya angka murni):
 * - Hanya mengizinkan digit angka (0-9).
 * - Maksimal 15 digit angka sesuai standar E.164.
 */
export function limitPhoneInput(value) {
    if (!value) return '';
    return String(value).replace(/\D/g, '').slice(0, 15);
}

/**
 * Auto-formatting nomor NPWP saat pengguna mengetik:
 * - Mengizinkan maksimal 16 digit angka.
 * - Format otomatis hingga 15 digit dengan tanda titik dan minus (XX.XXX.XXX.X-XXX.XXX).
 * - Jika 16 digit (NIK / NPWP 16 sesuai PMK 112/2022), dikembalikan 16 digit angka murni.
 */
export function formatNpwp(value) {
    if (!value) return '';
    const digits = String(value).replace(/\D/g, '').slice(0, 16);

    if (digits.length === 16) {
        return digits;
    }

    let res = '';
    if (digits.length > 0) res += digits.slice(0, 2);
    if (digits.length > 2) res += '.' + digits.slice(2, 5);
    if (digits.length > 5) res += '.' + digits.slice(5, 8);
    if (digits.length > 8) res += '.' + digits.slice(8, 9);
    if (digits.length > 9) res += '-' + digits.slice(9, 12);
    if (digits.length > 12) res += '.' + digits.slice(12, 15);

    return res;
}

/**
 * Auto-formatting nomor NIB: format resmi adalah 13 digit angka biasa.
 * Memfilter karakter non-digit secara interaktif saat mengetik.
 */
export function formatNib(value) {
    if (!value) return '';
    return String(value).replace(/\D/g, '').slice(0, 13);
}

/**
 * Auto-formatting nomor KTP / NIK penanggung jawab: 16 digit angka biasa.
 * Memfilter karakter non-digit secara interaktif saat mengetik.
 */
export function formatKtp(value) {
    if (!value) return '';
    return String(value).replace(/\D/g, '').slice(0, 16);
}

/**
 * Auto-formatting nomor SPPKP (Surat Pengukuhan Pengusaha Kena Pajak):
 * Format nomor keputusan resmi kantor dinas pajak (huruf, angka, strip, garis miring, titik).
 */
export function formatSppkp(value) {
    if (!value) return '';
    return String(value)
        .toUpperCase()
        .replace(/[^A-Z0-9\/\.\-\s]/g, '')
        .slice(0, 50);
}

/**
 * Auto-formatting nomor Akta Pendirian / SK Kemenkumham:
 * Mengizinkan alfanumerik, garis miring, titik, strip, koma, spasi.
 */
export function formatAkta(value) {
    if (!value) return '';
    let res = String(value)
        .replace(/[^a-zA-Z0-9\/\.\-\,\s]/g, '')
        .slice(0, 100);

    if (/^ahu/i.test(res)) {
        res = res.toUpperCase();
    }

    return res;
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
