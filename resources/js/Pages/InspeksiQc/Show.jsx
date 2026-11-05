import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    Building2,
    Calendar,
    Camera,
    CheckCircle2,
    ClipboardCheck,
    FileText,
    MapPin,
    ShieldAlert,
    Truck,
    UserCheck,
} from 'lucide-react';
import { formatDate, formatNumber, formatRupiah } from '@/lib/format';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import {
    InspectionItemStatusBadge,
    InspectionOverallBadge,
} from '@/Pages/InspeksiQc/columns';

function DetailRow({ label, children }) {
    return (
        <div className="grid gap-0.5">
            <span className="text-xs text-muted-foreground">{label}</span>
            <div className="text-sm font-medium text-foreground">{children}</div>
        </div>
    );
}

export default function InspeksiQcShow({ receipt: rawReceipt }) {
    const receipt = rawReceipt?.data ?? rawReceipt;
    const po = receipt?.purchase_order;
    const shipment = receipt?.shipment;
    const items = receipt?.items ?? [];
    const hasRejection = receipt?.overall_result === 'rejected';

    const [previewPhoto, setPreviewPhoto] = useState(null);

    const breadcrumbs = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Inspeksi QC', href: '/inspeksi-qc' },
        { label: po?.po_number ?? 'Detail Inspeksi', href: '#' },
    ];

    return (
        <DashboardLayout breadcrumbs={breadcrumbs}>
            <Head title={`Hasil Inspeksi ${po?.po_number ?? ''} - PAGORA`} />

            <div className="flex flex-col gap-4 w-full">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-bold tracking-tight flex flex-wrap items-center gap-2">
                            <ClipboardCheck className="size-5 text-primary" />
                            <span>Hasil Inspeksi Penerimaan Barang</span>
                            {po?.po_number && (
                                <span className="font-mono text-muted-foreground">
                                    · {po.po_number}
                                </span>
                            )}
                            <InspectionOverallBadge
                                result={receipt?.overall_result}
                                label={receipt?.overall_result_label}
                            />
                        </h1>
                        <p className="text-xs text-muted-foreground mt-1">
                            Diperiksa oleh {receipt?.inspector?.name ?? 'QC Gudang'} pada{' '}
                            {formatDate(receipt?.inspected_at)}
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" size="sm" asChild className="h-8">
                            <Link href="/inspeksi-qc">
                                <ArrowLeft className="mr-1.5 size-3.5" />
                                Kembali
                            </Link>
                        </Button>
                        {receipt?.can?.view_shipment && shipment?.uuid && (
                            <Button variant="outline" size="sm" asChild className="h-8">
                                <Link href={`/pengiriman/${shipment.uuid}`}>
                                    <Truck className="mr-1.5 size-3.5" />
                                    Buka Pengiriman
                                </Link>
                            </Button>
                        )}
                        {receipt?.can?.view_purchase_order && po?.uuid && (
                            <Button variant="outline" size="sm" asChild className="h-8">
                                <Link href={`/purchase-order/${po.uuid}`}>
                                    <FileText className="mr-1.5 size-3.5" />
                                    Buka Dokumen PO
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                {/* Status Banner */}
                {hasRejection ? (
                    <Alert variant="destructive">
                        <AlertTriangle className="size-4" />
                        <AlertTitle>
                            Terdapat Item Tidak Sesuai — Tercatat ke Log Sengketa
                        </AlertTitle>
                        <AlertDescription>
                            Sebanyak <strong>{receipt?.rejected_count ?? 0} item</strong> ditolak oleh QC Gudang disertai catatan dan foto bukti yang bersifat permanen (immutable). Status Purchase Order diperbarui menjadi{' '}
                            <strong>{po?.status_label ?? 'Diterima Sebagian'}</strong>.
                        </AlertDescription>
                    </Alert>
                ) : (
                    <Alert>
                        <CheckCircle2 className="size-4 text-emerald-600" />
                        <AlertTitle>Seluruh Item Disetujui dengan Status Sesuai</AlertTitle>
                        <AlertDescription>
                            Seluruh <strong>{receipt?.accepted_count ?? 0} item</strong> pada pengiriman ini telah diperiksa dan disetujui oleh QC Gudang. Status Purchase Order saat ini:{' '}
                            <strong>{po?.status_label ?? 'Diterima Gudang'}</strong>.
                        </AlertDescription>
                    </Alert>
                )}

                {/* Informasi Pemeriksaan & Pihak Terkait */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <Card className="shadow-sm lg:col-span-2">
                        <CardHeader className="py-3.5 px-5 border-b">
                            <CardTitle className="text-base font-semibold">
                                Rincian Pemeriksaan &amp; Pencocokan Armada
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <DetailRow label="Petugas Pemeriksa (QC Gudang)">
                                <span className="inline-flex items-center gap-1.5">
                                    <UserCheck className="size-4 text-muted-foreground" />
                                    {receipt?.inspector?.name ?? 'QC Gudang'}
                                </span>
                            </DetailRow>
                            <DetailRow label="Waktu Pemeriksaan">
                                <span className="inline-flex items-center gap-1.5 tabular-nums">
                                    <Calendar className="size-4 text-muted-foreground" />
                                    {formatDate(receipt?.inspected_at)}
                                </span>
                            </DetailRow>
                            <DetailRow label="Nomor Plat Dicocokkan di Gudang">
                                <span className="inline-flex items-center rounded-md border bg-muted/40 px-2.5 py-0.5 font-mono text-sm font-bold tracking-wider">
                                    {receipt?.matched_plate_number || '-'}
                                </span>
                            </DetailRow>
                            <DetailRow label="Armada &amp; Jalur Pengiriman">
                                <span>
                                    {shipment?.carrier_name ?? '-'} ({shipment?.route ?? '-'})
                                </span>
                            </DetailRow>
                            <DetailRow label="Status Purchase Order">
                                <Badge variant="outline">{po?.status_label ?? '-'}</Badge>
                            </DetailRow>
                            <DetailRow label="Catatan Umum Pemeriksaan">
                                <span className="font-normal text-muted-foreground">
                                    {receipt?.notes || 'Tidak ada catatan umum tambahan.'}
                                </span>
                            </DetailRow>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm lg:col-span-1">
                        <CardHeader className="py-3.5 px-5 border-b">
                            <CardTitle className="text-base font-semibold">
                                Pihak Pengirim &amp; Gudang Tujuan
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-5 grid gap-4">
                            <DetailRow label="Vendor Pengirim">
                                <span className="inline-flex items-center gap-1.5">
                                    <Building2 className="size-4 text-muted-foreground shrink-0" />
                                    {po?.vendor?.company_name ?? 'Vendor'}
                                </span>
                            </DetailRow>
                            <DetailRow label="Perusahaan Buyer">
                                <span className="inline-flex items-center gap-1.5">
                                    <Building2 className="size-4 text-primary shrink-0" />
                                    {po?.tenant?.company_name ?? 'Perusahaan Buyer'}
                                </span>
                            </DetailRow>
                            <DetailRow label="Lokasi Gudang Penerimaan">
                                {po?.tenant?.address ? (
                                    <span className="inline-flex items-start gap-1.5 text-xs font-normal text-muted-foreground">
                                        <MapPin className="size-3.5 shrink-0 mt-0.5" />
                                        {po.tenant.address}
                                    </span>
                                ) : (
                                    <span className="text-xs italic font-normal text-muted-foreground">
                                        Alamat gudang belum dicantumkan
                                    </span>
                                )}
                            </DetailRow>
                        </CardContent>
                    </Card>
                </div>

                {/* Daftar Hasil Inspeksi per Item */}
                <Card className="shadow-sm">
                    <CardHeader className="py-3.5 px-5 border-b">
                        <CardTitle className="text-base font-semibold">
                            Hasil Pemeriksaan Fisik per Item ({items.length} Item)
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-5 space-y-4">
                        {items.map((item, idx) => {
                            const isRejected = item.status === 'rejected';
                            const photos = item.photos ?? [];
                            const dispute = item.dispute;

                            return (
                                <div
                                    key={item.uuid}
                                    className="rounded-xl border p-4 space-y-4 bg-card"
                                >
                                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                        <div>
                                            <div className="flex items-center gap-2 flex-wrap">
                                                <span className="inline-flex size-6 items-center justify-center rounded-full bg-muted text-xs font-bold">
                                                    {idx + 1}
                                                </span>
                                                <h3 className="text-base font-semibold text-foreground">
                                                    {item.commodity_name}
                                                </h3>
                                                <InspectionItemStatusBadge
                                                    status={item.status}
                                                    label={item.status_label}
                                                />
                                            </div>
                                            <p className="text-xs text-muted-foreground mt-1 tabular-nums">
                                                Harga Satuan PO: {formatRupiah(item.unit_price)} /{' '}
                                                {item.unit} · Subtotal PO:{' '}
                                                {formatRupiah(item.subtotal)}
                                            </p>
                                        </div>

                                        <div className="flex flex-wrap items-center gap-4 text-xs sm:text-right">
                                            <div>
                                                <span className="text-muted-foreground block">
                                                    Kuantitas Dipesan
                                                </span>
                                                <span className="font-semibold text-sm text-foreground tabular-nums">
                                                    {formatNumber(item.ordered_quantity)}{' '}
                                                    {item.unit}
                                                </span>
                                            </div>
                                            <div>
                                                <span className="text-muted-foreground block">
                                                    Kuantitas Diterima QC
                                                </span>
                                                <span className="font-bold text-sm text-foreground tabular-nums">
                                                    {formatNumber(item.received_quantity)}{' '}
                                                    {item.unit}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    {isRejected && (
                                        <div className="rounded-lg border border-destructive/30 bg-destructive/5 p-4 space-y-4">
                                            <div className="space-y-1">
                                                <span className="text-xs font-semibold text-destructive flex items-center gap-1.5">
                                                    <ShieldAlert className="size-3.5" />
                                                    Catatan Ketidaksesuaian QC Gudang
                                                </span>
                                                <p className="text-sm text-foreground whitespace-pre-line">
                                                    {item.notes || '-'}
                                                </p>
                                            </div>

                                            {photos.length > 0 && (
                                                <div className="space-y-2">
                                                    <span className="text-xs font-semibold text-foreground flex items-center gap-1.5">
                                                        <Camera className="size-3.5 text-muted-foreground" />
                                                        Foto Bukti Ketidaksesuaian ({photos.length}{' '}
                                                        Foto — Immutable)
                                                    </span>
                                                    <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                                                        {photos.map((photo) => (
                                                            <button
                                                                key={photo.index}
                                                                type="button"
                                                                onClick={() =>
                                                                    setPreviewPhoto({
                                                                        url: photo.url,
                                                                        title: `${item.commodity_name} — Bukti #${photo.index + 1}`,
                                                                    })
                                                                }
                                                                className="group relative aspect-video overflow-hidden rounded-lg border bg-muted cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                                            >
                                                                <img
                                                                    src={photo.url}
                                                                    alt={`Bukti penolakan ${item.commodity_name} #${photo.index + 1}`}
                                                                    className="size-full object-cover transition-transform duration-200 group-hover:scale-105"
                                                                />
                                                            </button>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}

                                            {dispute && (
                                                <div className="rounded-lg border bg-background p-3.5 space-y-2">
                                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                                        <span className="text-xs font-semibold text-foreground">
                                                            Rekaman Log Sengketa Otomatis
                                                        </span>
                                                        <Badge variant="warning">
                                                            Status Sengketa: {dispute.status_label}
                                                        </Badge>
                                                    </div>
                                                    {Array.isArray(dispute.logs) &&
                                                        dispute.logs.length > 0 && (
                                                            <ul className="space-y-1.5 pt-1 border-t text-xs">
                                                                {dispute.logs.map((log) => (
                                                                    <li
                                                                        key={log.uuid}
                                                                        className="flex flex-col sm:flex-row sm:items-baseline justify-between gap-1 text-muted-foreground"
                                                                    >
                                                                        <span className="text-foreground">
                                                                            <strong className="font-medium">
                                                                                {log.author_name}:
                                                                            </strong>{' '}
                                                                            {log.message}
                                                                        </span>
                                                                        <span className="text-[11px] shrink-0 tabular-nums">
                                                                            {formatDate(
                                                                                log.created_at
                                                                            )}
                                                                        </span>
                                                                    </li>
                                                                ))}
                                                            </ul>
                                                        )}
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </CardContent>
                </Card>
            </div>

            {/* Modal Pratinjau Foto Bukti */}
            <Dialog
                open={Boolean(previewPhoto)}
                onOpenChange={(open) => {
                    if (!open) setPreviewPhoto(null);
                }}
            >
                <DialogContent className="sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>{previewPhoto?.title ?? 'Foto Bukti Inspeksi QC'}</DialogTitle>
                        <DialogDescription>
                            Bukti visual ketidaksesuaian penerimaan barang yang tersimpan secara immutable.
                        </DialogDescription>
                    </DialogHeader>
                    {previewPhoto && (
                        <div className="flex items-center justify-center rounded-lg bg-muted/40 p-2">
                            <img
                                src={previewPhoto.url}
                                alt={previewPhoto.title}
                                className="max-h-[70vh] w-auto rounded-md object-contain"
                            />
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </DashboardLayout>
    );
}

