import { Head, Link } from '@inertiajs/react';
import { Edit, ArrowLeft } from 'lucide-react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Button } from '@/Components/ui/button';
import { KomoditasForm } from '@/Pages/Master/Komoditas/KomoditasForm';

export default function KomoditasEdit({
    komoditas,
    satuan_options = [],
    kategori_options = [],
}) {
    const item = komoditas?.data ?? komoditas;

    const breadcrumbs = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Master Data', href: '#' },
        { label: 'Komoditas', href: '/master/komoditas' },
        { label: `Ubah ${item?.nama_komoditas ?? ''}`, href: '#' },
    ];

    return (
        <DashboardLayout breadcrumbs={breadcrumbs}>
            <Head title={`Ubah ${item?.nama_komoditas ?? 'Komoditas'} - PAGORA`} />

            <div className="flex flex-col gap-4 w-full">
                {/* 1 Row Heading with Back Button at Top-Right */}
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-bold tracking-tight flex items-center gap-2">
                            <Edit className="size-5 text-primary" />
                            Ubah Data Komoditas
                        </h1>
                        <p className="text-xs text-muted-foreground mt-0.5">
                            Perbarui informasi bahan baku <strong className="text-foreground">{item?.nama_komoditas}</strong>.
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
                    initialData={item}
                    isEdit={true}
                    satuan_options={satuan_options}
                    kategori_options={kategori_options}
                />
            </div>
        </DashboardLayout>
    );
}
