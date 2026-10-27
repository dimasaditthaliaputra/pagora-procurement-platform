import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Edit } from 'lucide-react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Button } from '@/Components/ui/button';
import { PurchaseOrderForm } from '@/Pages/PurchaseOrder/PurchaseOrderForm';
import { PoNotFound } from '@/Pages/PurchaseOrder/components/PoNotFound';
import { isEditable } from '@/Pages/PurchaseOrder/constants';
import { findPurchaseOrder } from '@/Pages/PurchaseOrder/mock-data';

export default function PurchaseOrderEdit({ uuid }) {
    // TODO(backend): receive `purchase_order` prop from the controller instead.
    const po = findPurchaseOrder(uuid);

    if (!po) return <PoNotFound />;
    if (!isEditable(po)) return <PoNotFound message={`${po.nomor_po} sudah dikonfirmasi sehingga tidak bisa diubah.`} />;

    const breadcrumbs = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Purchase Order', href: '/purchase-order' },
        { label: po.nomor_po, href: `/purchase-order/${po.uuid}` },
        { label: 'Ubah Draf', href: '#' },
    ];

    return (
        <DashboardLayout breadcrumbs={breadcrumbs}>
            <Head title={`Ubah ${po.nomor_po} - PAGORA`} />

            <div className="flex flex-col gap-4 w-full">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-bold tracking-tight flex items-center gap-2">
                            <Edit className="size-5 text-primary" />
                            Ubah Draf {po.nomor_po}
                        </h1>
                        <p className="text-xs text-muted-foreground mt-0.5">
                            Ubah vendor, komoditas, atau kuantitas sebelum dikirim ke vendor.
                        </p>
                    </div>
                    <Button variant="outline" size="sm" asChild className="cursor-pointer shrink-0 h-8">
                        <Link href={`/purchase-order/${po.uuid}`}>
                            <ArrowLeft className="mr-1.5 size-3.5" />
                            Kembali
                        </Link>
                    </Button>
                </div>

                <PurchaseOrderForm initialData={po} />
            </div>
        </DashboardLayout>
    );
}
