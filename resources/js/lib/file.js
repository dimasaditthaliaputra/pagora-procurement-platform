const SIZE_UNITS = ['B', 'KB', 'MB', 'GB'];

const IMAGE_EXTENSION_PATTERN = /\.(jpe?g|png|webp|gif|avif)(\?.*)?$/i;

/**
 * Format ukuran file agar mudah dibaca, contoh: 1.5 MB.
 */
export function formatBytes(bytes) {
    if (!bytes || bytes <= 0) return '0 B';

    const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), SIZE_UNITS.length - 1);

    return `${parseFloat((bytes / 1024 ** index).toFixed(1))} ${SIZE_UNITS[index]}`;
}

/**
 * Ambil ekstensi huruf besar dari nama file atau URL, contoh: PDF.
 */
export function getFileExtension(nameOrUrl) {
    if (typeof nameOrUrl !== 'string') return '';

    const match = nameOrUrl.split('?')[0].match(/\.([a-z0-9]+)$/i);

    return match ? match[1].toUpperCase() : '';
}

/**
 * File baru (File) atau URL gambar yang sudah tersimpan di server.
 */
export function isImageSource(value) {
    if (value instanceof File) return value.type.startsWith('image/');

    return typeof value === 'string' && IMAGE_EXTENSION_PATTERN.test(value);
}

/**
 * Daftar ekstensi dari objek `accept` react-dropzone, contoh: "PDF, JPG, JPEG, atau PNG".
 */
export function describeAcceptedFormats(accept) {
    const extensions = [...new Set(Object.values(accept).flat().map((ext) => ext.replace('.', '').toUpperCase()))];

    if (extensions.length <= 1) return extensions[0] ?? '';

    return `${extensions.slice(0, -1).join(', ')}, atau ${extensions.at(-1)}`;
}

/**
 * Pesan yang ramah untuk penolakan react-dropzone.
 */
export function describeRejection(rejection, { accept, maxSize }) {
    const codes = rejection.errors.map((error) => error.code);

    if (codes.includes('file-too-large')) {
        return `Ukuran file terlalu besar. Maksimal ${formatBytes(maxSize)}.`;
    }

    if (codes.includes('file-invalid-type')) {
        return `Format file tidak didukung. Harap unggah ${describeAcceptedFormats(accept)}.`;
    }

    if (codes.includes('too-many-files')) {
        return 'Hanya satu file yang dapat diunggah.';
    }

    return 'File tidak dapat diterima.';
}

/**
 * Label tombol submit selama upload, contoh: "Mengunggah... 45%".
 */
export function uploadingLabel(progress, fallback = 'Mengunggah...') {
    return typeof progress === 'number' && progress > 0 && progress < 100
        ? `Mengunggah... ${progress}%`
        : fallback;
}
