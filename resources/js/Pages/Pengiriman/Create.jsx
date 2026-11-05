import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Truck } from 'lucide-react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Button } from '@/Components/ui/button';
import { PengirimanForm } from '@/Pages/Pengiriman/PengirimanForm';

export default function PengirimanCreate({
    purchase_order_options = [],
    route_options = [],
    selected_po_uuid = null,
}) {
    const breadcrumbs = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Pengiriman', href: '/pengiriman' },
        { label: 'Formulir Pengiriman', href: '#' },
    ];

    return (
        <DashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Formulir Pengiriman Logistik - PAGORA" />

            <div className="flex flex-col gap-4 w-full">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-bold tracking-tight flex items-center gap-2">
                            <Truck className="size-5 text-primary" />
                            Formulir Pengiriman Logistik
                        </h1>
                        <p className="text-xs text-muted-foreground mt-0.5">
                            Laporkan rincian operasional armada dan estimasi tanggal tiba (Self-Reporting Logistics).
                        </p>
                    </div>
                    <Button variant="outline" size="sm" asChild className="cursor-pointer shrink-0 h-8">
                        <Link href="/pengiriman">
                            <ArrowLeft className="mr-1.5 size-3.5" />
                            Kembali ke Daftar
                        </Link>
                    </Button>
                </div>

                <PengirimanForm
                    purchaseOrderOptions={purchase_order_options}
                    routeOptions={route_options}
                    selectedPoUuid={selected_po_uuid}
                />
            </div>
        </DashboardLayout>
    );
}

