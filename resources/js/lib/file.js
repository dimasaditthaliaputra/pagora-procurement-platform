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
    if (!accept || typeof accept !== 'object') {
        return 'PDF';
    }

    const extensions = [
        ...new Set(
            Object.values(accept)
                .flat()
                .filter(Boolean)
                .map((ext) => ext.replace('.', '').toUpperCase())
        ),
    ];

    if (extensions.length === 0) return 'PDF';
    if (extensions.length === 1) return extensions[0];

    return `${extensions.slice(0, -1).join(', ')}, atau ${extensions.at(-1)}`;
}

/**
 * Pesan yang ramah untuk penolakan react-dropzone.
 * Mendukung pemanggilan opsi objek `{ accept, maxSize }` maupun argumen terpisah `(rejection, accept, maxSize)`.
 */
export function describeRejection(rejection, optionsOrAccept = {}, maybeMaxSize) {
    if (!rejection) {
        return 'File tidak dapat diterima.';
    }

    const options =
        optionsOrAccept && (optionsOrAccept.accept !== undefined || optionsOrAccept.maxSize !== undefined)
            ? optionsOrAccept
            : { accept: optionsOrAccept, maxSize: maybeMaxSize };

    const codes = Array.isArray(rejection.errors) ? rejection.errors.map((error) => error.code) : [];
    const maxSize = options.maxSize || 5 * 1024 * 1024;
    const accept = options.accept;

    if (codes.includes('file-too-large')) {
        return `Ukuran file terlalu besar. Maksimal ${formatBytes(maxSize)}.`;
    }

    if (codes.includes('file-invalid-type')) {
        const formats = describeAcceptedFormats(accept);
        return `Format file tidak didukung. Harap unggah berkas ${formats}.`;
    }

    if (codes.includes('file-empty')) {
        return 'File kosong (0 byte) tidak dapat diunggah.';
    }

    if (codes.includes('too-many-files')) {
        return 'Jumlah file melebihi batas yang diizinkan.';
    }

    return rejection.errors?.[0]?.message || 'File tidak dapat diterima.';
}

/**
 * Label tombol submit selama upload, contoh: "Mengunggah... 45%".
 */
export function uploadingLabel(progress, fallback = 'Mengunggah...') {
    return typeof progress === 'number' && progress > 0 && progress < 100
        ? `Mengunggah... ${progress}%`
        : fallback;
}
