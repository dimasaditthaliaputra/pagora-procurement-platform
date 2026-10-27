import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, FilePlus } from 'lucide-react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Button } from '@/Components/ui/button';
import { PurchaseOrderForm } from '@/Pages/PurchaseOrder/PurchaseOrderForm';

export default function PurchaseOrderCreate() {
    const breadcrumbs = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Purchase Order', href: '/purchase-order' },
        { label: 'Buat Baru', href: '#' },
    ];

    return (
        <DashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Buat Purchase Order - PAGORA" />

            <div className="flex flex-col gap-4 w-full">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-bold tracking-tight flex items-center gap-2">
                            <FilePlus className="size-5 text-primary" />
                            Buat Purchase Order
                        </h1>
                        <p className="text-xs text-muted-foreground mt-0.5">
                            PO tersimpan sebagai draf. Anda bisa langsung mengonfirmasi untuk memulai negosiasi.
                        </p>
                    </div>
                    <Button variant="outline" size="sm" asChild className="cursor-pointer shrink-0 h-8">
                        <Link href="/purchase-order">
                            <ArrowLeft className="mr-1.5 size-3.5" />
                            Kembali ke Daftar
                        </Link>
                    </Button>
                </div>

                <PurchaseOrderForm />
            </div>
        </DashboardLayout>
    );
}
