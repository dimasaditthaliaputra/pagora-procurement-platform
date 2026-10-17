import { Head, Link } from '@inertiajs/react';
import { PlusCircle, ArrowLeft } from 'lucide-react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Button } from '@/Components/ui/button';
import { KomoditasForm } from '@/Pages/Master/Komoditas/KomoditasForm';

export default function KomoditasCreate({
    satuan_options = [],
    kategori_options = [],
}) {
    const breadcrumbs = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Master Data', href: '#' },
        { label: 'Komoditas', href: '/master/komoditas' },
        { label: 'Tambah Baru', href: '/master/komoditas/create' },
    ];

    return (
        <DashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Tambah Komoditas Baru - PAGORA" />

            <div className="flex flex-col gap-4 w-full">
                {/* 1 Row Heading with Back Button at Top-Right */}
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-bold tracking-tight flex items-center gap-2">
                            <PlusCircle className="size-5 text-primary" />
                            Tambah Komoditas Baru
                        </h1>
                        <p className="text-xs text-muted-foreground mt-0.5">
                            Daftarkan bahan baku baru ke dalam katalog sistem pengadaan PAGORA.
                        </p>
                    </div>

                    <Button variant="outline" size="sm" asChild className="cursor-pointer shrink-0 h-8">
                        <Link href="/master/komoditas">
                            <ArrowLeft className="mr-1.5 size-3.5" />
                            Kembali ke Daftar
                        </Link>
                    </Button>
                </div>

                <KomoditasForm
                    isEdit={false}
                    satuan_options={satuan_options}
                    kategori_options={kategori_options}
                />
            </div>
        </DashboardLayout>
    );
}
