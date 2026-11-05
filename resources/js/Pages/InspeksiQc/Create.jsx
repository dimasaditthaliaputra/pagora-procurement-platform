import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ClipboardCheck, ShieldAlert } from 'lucide-react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { InspeksiQcForm } from '@/Pages/InspeksiQc/InspeksiQcForm';

export default function InspeksiQcCreate({
    purchase_order_options = [],
    selected_po_uuid = null,
    damage_mode = false,
    trigger_options = [],
}) {
    const breadcrumbs = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Inspeksi QC', href: '/inspeksi-qc' },
        {
            label: damage_mode
                ? 'Form Laporan Kerusakan Barang'
                : 'Form Pengecekan Barang',
            href: '#',
        },
    ];

    return (
        <DashboardLayout breadcrumbs={breadcrumbs}>
            <Head
                title={
                    damage_mode
                        ? 'Form Laporan Kerusakan Barang (QC Gudang) - PAGORA'
                        : 'Form Pengecekan Barang (QC Gudang) - PAGORA'
                }
            />

            <div className="flex flex-col gap-6 w-full">
                <div className="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
                            {damage_mode ? (
                                <ShieldAlert className="size-6 text-destructive shrink-0" />
                            ) : (
                                <ClipboardCheck className="size-6 text-primary shrink-0" />
                            )}
                            {damage_mode
                                ? 'Laporkan Kerusakan Barang (Bagian Inspeksi QC)'
                                : 'Form Pengecekan & Inspeksi Barang'}
                        </h1>
                        <p className="text-sm text-muted-foreground mt-1 max-w-2xl">
                            {damage_mode
                                ? 'Pilih item yang mengalami kerusakan fisik atau ketidaksesuaian mutu, tentukan kategori kendala, lalu lampirkan catatan dan foto bukti untuk dicatat ke Log Sengketa.'
                                : 'Periksa kesesuaian fisik barang yang tiba di gudang per item pesanan berdasarkan dokumen Purchase Order yang telah terkunci.'}
                        </p>
                    </div>

                    <div className="flex flex-wrap sm:flex-nowrap items-center gap-2 shrink-0">
                        {damage_mode && (
                            <Button variant="outline" size="sm" asChild className="h-9">
                                <Link href="/inspeksi-qc/laporan-kerusakan">
                                    Daftar Laporan Kerusakan
                                </Link>
                            </Button>
                        )}
                        <Button variant="outline" size="sm" asChild className="h-9">
                            <Link href="/inspeksi-qc">
                                <ArrowLeft className="mr-1.5 size-4" />
                                Kembali ke Daftar Pengecekan
                            </Link>
                        </Button>
                    </div>
                </div>

                {damage_mode && (
                    <Alert variant="destructive">
                        <ShieldAlert className="size-4" />
                        <AlertTitle>
                            Mode Pelaporan Kerusakan Barang (Extend Inspeksi QC)
                        </AlertTitle>
                        <AlertDescription>
                            Tandai opsi <strong>Ditolak / Laporkan Kerusakan</strong> pada item yang
                            bermasalah, pilih kategori kendala (<strong>Barang Rusak / Tidak Sesuai</strong>{' '}
                            atau <strong>Hasil QC Ditolak</strong>), dan unggah minimal 1 foto bukti. Item
                            lain dalam pengiriman yang sama yang kondisinya baik tetap dapat ditandai{' '}
                            <strong>Disetujui</strong> agar Purchase Order tidak dibatalkan seluruhnya.
                        </AlertDescription>
                    </Alert>
                )}

                <InspeksiQcForm
                    purchaseOrderOptions={purchase_order_options}
                    initialPoUuid={selected_po_uuid}
                    damageMode={damage_mode}
                    triggerOptions={trigger_options}
                />
            </div>
        </DashboardLayout>
    );
}
