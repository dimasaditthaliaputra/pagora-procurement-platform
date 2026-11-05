import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ClipboardCheck } from 'lucide-react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Button } from '@/Components/ui/button';
import { InspeksiQcForm } from '@/Pages/InspeksiQc/InspeksiQcForm';

export default function InspeksiQcCreate({
    purchase_order_options = [],
    selected_po_uuid = null,
}) {
    const breadcrumbs = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Inspeksi QC', href: '/inspeksi-qc' },
        { label: 'Form Pengecekan Barang', href: '#' },
    ];

    return (
        <DashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Form Pengecekan Barang (QC Gudang) - PAGORA" />

            <div className="flex flex-col gap-6 w-full">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
                            <ClipboardCheck className="size-6 text-primary" />
                            Form Pengecekan &amp; Inspeksi Barang
                        </h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Periksa kesesuaian fisik barang yang tiba di gudang per item pesanan berdasarkan dokumen Purchase Order yang telah terkunci.
                        </p>
                    </div>

                    <Button variant="outline" size="sm" asChild className="h-9 w-fit">
                        <Link href="/inspeksi-qc">
                            <ArrowLeft className="mr-1.5 size-4" />
                            Kembali ke Daftar Pengecekan
                        </Link>
                    </Button>
                </div>

                <InspeksiQcForm
                    purchaseOrderOptions={purchase_order_options}
                    initialPoUuid={selected_po_uuid}
                />
            </div>
        </DashboardLayout>
    );
}

