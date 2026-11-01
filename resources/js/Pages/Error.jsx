import React from 'react';
import { Head } from '@inertiajs/react';
import { ArrowLeft, HomeIcon, LayoutDashboard, RotateCw } from 'lucide-react';
import { ErrorState } from '@/Components/ErrorState';

const goBack = () => window.history.back();
const reload = () => window.location.reload();

const ERRORS = {
    403: {
        title: 'Akses Ditolak',
        description: 'Anda tidak memiliki izin untuk membuka halaman ini.',
        actions: [
            { label: 'Ke Dasbor', icon: LayoutDashboard, href: '/dashboard' },
            { label: 'Kembali', icon: ArrowLeft, onClick: goBack, variant: 'outline' },
        ],
    },
    404: {
        title: 'Halaman Tidak Ditemukan',
        description: 'Halaman yang Anda cari mungkin telah dipindahkan atau tidak tersedia.',
        actions: [
            { label: 'Ke Beranda', icon: HomeIcon, href: '/' },
            { label: 'Kembali', icon: ArrowLeft, onClick: goBack, variant: 'outline' },
        ],
    },
    429: {
        title: 'Terlalu Banyak Permintaan',
        description: 'Anda melakukan terlalu banyak percobaan. Tunggu sebentar lalu coba lagi.',
        actions: [
            { label: 'Coba Lagi', icon: RotateCw, onClick: reload },
            { label: 'Kembali', icon: ArrowLeft, onClick: goBack, variant: 'outline' },
        ],
    },
    503: {
        title: 'Sedang Dalam Pemeliharaan',
        description: 'Sistem sedang dalam pemeliharaan terjadwal. Silakan kembali beberapa saat lagi.',
        actions: [{ label: 'Muat Ulang', icon: RotateCw, onClick: reload }],
    },
    500: {
        title: 'Terjadi Kesalahan Server',
        description: 'Maaf, terjadi kendala pada sistem kami. Silakan coba beberapa saat lagi.',
        actions: [
            { label: 'Muat Ulang', icon: RotateCw, onClick: reload },
            { label: 'Ke Beranda', icon: HomeIcon, href: '/', variant: 'outline' },
        ],
    },
};

export default function Error({ status, request_id: requestId = null }) {
    const error = ERRORS[status] ?? ERRORS[500];
    const description = requestId
        ? `${error.description} Kode referensi: ${requestId}`
        : error.description;

    return (
        <>
            <Head title={`${status} — ${error.title}`} />
            <ErrorState code={status} {...error} description={description} />
        </>
    );
}
