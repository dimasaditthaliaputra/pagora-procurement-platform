import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    Building2,
    Calendar,
    Camera,
    ClipboardCheck,
    FileText,
    Image as ImageIcon,
    Lock,
    Scale,
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
import { TambahBuktiKerusakanForm } from '@/Pages/InspeksiQc/components/TambahBuktiKerusakanForm';
import {
    DamageTriggerBadge,
    DisputeStatusBadge,
} from '@/Pages/InspeksiQc/damageColumns';

function DetailRow({ label, children }) {
    return (
        <div className="grid gap-0.5">
            <span className="text-xs text-muted-foreground">{label}</span>
            <div className="text-sm font-medium text-foreground">{children}</div>
        </div>
    );
}

export default function InspeksiQcDamageReportShow({ damage_report: rawReport }) {
    const report = rawReport?.data ?? rawReport;
    const po = report?.purchase_order;
    const shipment = report?.shipment;
    const dispute = report?.dispute;
    const photos = report?.photos ?? [];
    const logs = dispute?.logs ?? [];
    const hasShrinkageTolerance = typeof report?.shrinkage_tolerance === 'number';

    const [previewPhoto, setPreviewPhoto] = useState(null);

    const breadcrumbs = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Inspeksi QC', href: '/inspeksi-qc' },
        { label: 'Laporan Kerusakan Barang', href: '/inspeksi-qc/laporan-kerusakan' },
        { label: `${report?.commodity_name ?? 'Item'} (${po?.po_number ?? '-'})`, href: '#' },
    ];

    return (
        <DashboardLayout breadcrumbs={breadcrumbs}>
            <Head
                title={`Laporan Kerusakan ${report?.commodity_name ?? ''} - ${po?.po_number ?? ''} - PAGORA`}
            />

            <div className="flex flex-col gap-5 w-full">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-bold tracking-tight flex flex-wrap items-center gap-2">
                            <ShieldAlert className="size-5 text-destructive" />
                            <span>Berita Acara Laporan Kerusakan Barang</span>
                            {po?.po_number && (
                                <span className="font-mono text-muted-foreground">
                                    · {po.po_number}
                                </span>
                            )}
                            <DamageTriggerBadge
                                triggerType={dispute?.trigger_type}
                                label={dispute?.trigger_type_label}
                            />
                            {dispute && (
                                <DisputeStatusBadge
                                    status={dispute.status}
                                    label={dispute.status_label}
                                />
                            )}
                        </h1>
                        <p className="text-xs text-muted-foreground mt-1">
                            Dilaporkan oleh {report?.inspector?.name ?? 'QC Gudang'} pada{' '}
                            {formatDate(report?.inspected_at || report?.created_at)}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center md:justify-end gap-2 shrink-0">
                        <Button variant="outline" size="sm" asChild className="h-8">
                            <Link href="/inspeksi-qc/laporan-kerusakan">
                                <ArrowLeft className="mr-1.5 size-3.5" />
                                Daftar Laporan Kerusakan
                            </Link>
                        </Button>
                        {report?.can?.view_receipt && report?.goods_receipt_uuid && (
                            <Button variant="outline" size="sm" asChild className="h-8">
                                <Link href={`/inspeksi-qc/${report.goods_receipt_uuid}`}>
                                    <ClipboardCheck className="mr-1.5 size-3.5" />
                                    Dokumen Inspeksi QC
                                </Link>
                            </Button>
                        )}
                        {report?.can?.view_shipment && shipment?.uuid && (
                            <Button variant="outline" size="sm" asChild className="h-8">
                                <Link href={`/pengiriman/${shipment.uuid}`}>
                                    <Truck className="mr-1.5 size-3.5" />
                                    Pengiriman
                                </Link>
                            </Button>
                        )}
                        {report?.can?.view_purchase_order && po?.uuid && (
                            <Button variant="outline" size="sm" asChild className="h-8">
                                <Link href={`/purchase-order/${po.uuid}`}>
                                    <FileText className="mr-1.5 size-3.5" />
                                    Dokumen PO
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                {/* Banner Peringatan Susut / Immutability */}
                <Alert variant="destructive">
                    <AlertTriangle className="size-4" />
                    <AlertTitle>
                        Item Ditolak &amp; Tercatat Otomatis pada Siklus Sengketa
                    </AlertTitle>
                    <AlertDescription>
                        Komoditas <strong>{report?.commodity_name}</strong> dilaporkan tidak sesuai
                        dengan kategori{' '}
                        <strong>{dispute?.trigger_type_label ?? 'Hasil QC Ditolak'}</strong>. Bukti
                        foto dan catatan di halaman ini bersifat{' '}
                        <strong>permanen (immutable)</strong> sebagai acuan mediasi maupun
                        pengiriman ulang.
                    </AlertDescription>
                </Alert>

                {/* Rincian Kuantitas, Penyusutan & Armada */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <Card className="shadow-sm lg:col-span-2">
                        <CardHeader className="py-3.5 px-5 border-b">
                            <CardTitle className="text-base font-semibold">
                                Rincian Komoditas, Penyusutan Fisik &amp; Armada
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <DetailRow label="Komoditas Barang">
                                <span className="text-base font-bold">{report?.commodity_name}</span>
                            </DetailRow>
                            <DetailRow label="Harga Satuan &amp; Subtotal PO">
                                <span className="tabular-nums">
                                    {formatRupiah(report?.unit_price)} / {report?.unit} · Total{' '}
                                    {formatRupiah(report?.subtotal)}
                                </span>
                            </DetailRow>
                            <DetailRow label="Kuantitas Dipesan vs Diterima Fisik">
                                <span className="tabular-nums">
                                    Dipesan:{' '}
                                    <strong>
                                        {formatNumber(report?.ordered_quantity)} {report?.unit}
                                    </strong>{' '}
                                    · Diterima:{' '}
                                    <strong className="text-destructive">
                                        {formatNumber(report?.received_quantity)} {report?.unit}
                                    </strong>
                                </span>
                            </DetailRow>
                            <DetailRow label="Selisih / Penyusutan Fisik">
                                <div className="flex flex-wrap items-center gap-2 tabular-nums">
                                    <span>
                                        {formatNumber(report?.shortage_quantity)} {report?.unit} (
                                        {formatNumber(report?.shrinkage_percent)}%)
                                    </span>
                                    {hasShrinkageTolerance && (
                                        <Badge
                                            variant={
                                                report?.exceeds_shrinkage_tolerance
                                                    ? 'destructive'
                                                    : 'outline'
                                            }
                                            className="text-[11px]"
                                        >
                                            Batas Toleransi Susut:{' '}
                                            {formatNumber(report.shrinkage_tolerance)}%
                                        </Badge>
                                    )}
                                </div>
                            </DetailRow>
                            <DetailRow label="Nomor Plat Armada Dicocokkan">
                                <span className="inline-flex items-center rounded-md border bg-muted/40 px-2.5 py-0.5 font-mono text-sm font-bold tracking-wider">
                                    {report?.matched_plate_number || '-'}
                                </span>
                            </DetailRow>
                            <DetailRow label="Armada &amp; Jalur Pengiriman">
                                <span>
                                    {shipment?.carrier_name ?? '-'} ({shipment?.route ?? '-'})
                                </span>
                            </DetailRow>
                            <DetailRow label="Petugas Pemeriksa QC Gudang">
                                <span className="inline-flex items-center gap-1.5">
                                    <UserCheck className="size-4 text-muted-foreground" />
                                    {report?.inspector?.name ?? 'QC Gudang'}
                                </span>
                            </DetailRow>
                            <DetailRow label="Waktu Pemeriksaan">
                                <span className="inline-flex items-center gap-1.5 tabular-nums">
                                    <Calendar className="size-4 text-muted-foreground" />
                                    {formatDate(report?.inspected_at || report?.created_at)}
                                </span>
                            </DetailRow>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm lg:col-span-1">
                        <CardHeader className="py-3.5 px-5 border-b">
                            <CardTitle className="text-base font-semibold">
                                Pihak Terkait &amp; Status PO
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-5 grid gap-4">
                            <DetailRow label="Nomor &amp; Status Purchase Order">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-mono font-bold">{po?.po_number}</span>
                                    <Badge variant="outline">{po?.status_label}</Badge>
                                </div>
                            </DetailRow>
                            <DetailRow label="Vendor Pengirim">
                                <span className="inline-flex items-center gap-1.5">
                                    <Building2 className="size-4 text-muted-foreground shrink-0" />
                                    {po?.vendor?.company_name ?? 'Vendor'}
                                </span>
                            </DetailRow>
                            <DetailRow label="Perusahaan Pembeli (Gudang)">
                                <span className="inline-flex items-center gap-1.5">
                                    <Building2 className="size-4 text-primary shrink-0" />
                                    {po?.tenant?.company_name ?? 'Perusahaan Buyer'}
                                </span>
                            </DetailRow>
                            {dispute && (
                                <DetailRow label="Status Siklus Sengketa">
                                    <div className="flex items-center gap-2">
                                        <Scale className="size-4 text-muted-foreground" />
                                        <DisputeStatusBadge
                                            status={dispute.status}
                                            label={dispute.status_label}
                                        />
                                    </div>
                                </DetailRow>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Catatan & Galeri Foto Bukti Kerusakan Awal */}
                <Card className="shadow-sm border-destructive/30">
                    <CardHeader className="py-3.5 px-5 border-b bg-destructive/[0.03]">
                        <CardTitle className="text-base font-semibold flex items-center gap-2">
                            <Camera className="size-4 text-destructive" />
                            Catatan &amp; Dokumentasi Foto Kerusakan Awal ({photos.length} Foto —
                            Immutable)
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-5 space-y-4">
                        <div className="rounded-lg border bg-muted/20 p-4 space-y-1">
                            <span className="text-xs font-semibold text-muted-foreground block">
                                Kronologi / Rincian Ketidaksesuaian saat Pemeriksaan Fisik:
                            </span>
                            <p className="text-sm text-foreground whitespace-pre-line">
                                {report?.notes || '-'}
                            </p>
                        </div>

                        {photos.length > 0 && (
                            <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                                {photos.map((photo) => (
                                    <button
                                        key={photo.index}
                                        type="button"
                                        onClick={() =>
                                            setPreviewPhoto({
                                                url: photo.url,
                                                title: `${report.commodity_name} — Bukti Awal #${photo.index + 1}`,
                                            })
                                        }
                                        className="group relative aspect-video overflow-hidden rounded-lg border bg-muted cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                    >
                                        <img
                                            src={photo.url}
                                            alt={`Bukti kerusakan ${report.commodity_name} #${photo.index + 1}`}
                                            className="size-full object-cover transition-transform duration-200 group-hover:scale-105"
                                        />
                                    </button>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Riwayat Log Sengketa (Immutable Timeline) */}
                {dispute && (
                    <Card className="shadow-sm">
                        <CardHeader className="py-3.5 px-5 border-b">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <CardTitle className="text-base font-semibold flex items-center gap-2">
                                    <Scale className="size-4 text-primary" />
                                    Kronologi Log Sengketa ({logs.length} Entri Immutable)
                                </CardTitle>
                                <DisputeStatusBadge
                                    status={dispute.status}
                                    label={dispute.status_label}
                                />
                            </div>
                        </CardHeader>
                        <CardContent className="p-5">
                            {logs.length > 0 ? (
                                <ol className="space-y-3">
                                    {logs.map((log, idx) => (
                                        <li
                                            key={log.uuid}
                                            className="rounded-lg border bg-card p-3.5 space-y-2"
                                        >
                                            <div className="flex flex-wrap items-center justify-between gap-2 text-xs">
                                                <div className="flex items-center gap-2">
                                                    <span className="inline-flex size-5 items-center justify-center rounded-full bg-muted font-bold text-[11px]">
                                                        {idx + 1}
                                                    </span>
                                                    <strong className="text-foreground font-semibold">
                                                        {log.author_name}
                                                    </strong>
                                                    <Badge variant="outline" className="text-[10px]">
                                                        {log.type === 'evidence'
                                                            ? 'Bukti Foto'
                                                            : log.type === 'status_change'
                                                              ? 'Pembukaan Sengketa'
                                                              : 'Catatan'}
                                                    </Badge>
                                                </div>
                                                <span className="text-muted-foreground tabular-nums">
                                                    {formatDate(log.created_at)}
                                                </span>
                                            </div>

                                            <p className="text-sm text-foreground whitespace-pre-line">
                                                {log.message}
                                            </p>

                                            {log.has_attachment && log.attachment_url && (
                                                <div className="pt-1">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            setPreviewPhoto({
                                                                url: log.attachment_url,
                                                                title: `Lampiran Log Sengketa #${idx + 1} — ${log.author_name}`,
                                                            })
                                                        }
                                                        className="h-8 gap-1.5 text-xs cursor-pointer"
                                                    >
                                                        <ImageIcon className="size-3.5 text-primary" />
                                                        Lihat Lampiran Foto Bukti
                                                    </Button>
                                                </div>
                                            )}
                                        </li>
                                    ))}
                                </ol>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Belum ada entri pada log sengketa.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Form Tambah Bukti Kerusakan Lanjutan (Append-Only — QC Gudang / Owner) */}
                <div id="tambah-bukti">
                    {report?.can?.report_additional_evidence ? (
                        <TambahBuktiKerusakanForm
                            itemUuid={report.uuid}
                            commodityName={report.commodity_name}
                            poNumber={po?.po_number ?? '-'}
                        />
                    ) : dispute?.status === 'selesai' || dispute?.status === 'resolved' ? (
                        <Alert>
                            <Lock className="size-4 text-muted-foreground" />
                            <AlertTitle>Sengketa Telah Diselesaikan (Terkunci Permanen)</AlertTitle>
                            <AlertDescription>
                                Sengketa untuk laporan kerusakan ini telah berstatus{' '}
                                <strong>{dispute.status_label}</strong> sehingga seluruh riwayat log
                                dan bukti dikunci secara permanen sesuai aturan audit PAGORA.
                            </AlertDescription>
                        </Alert>
                    ) : null}
                </div>
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
                        <DialogTitle>{previewPhoto?.title ?? 'Foto Bukti Kerusakan QC'}</DialogTitle>
                        <DialogDescription>
                            Bukti visual ketidaksesuaian penerimaan barang yang tersimpan secara
                            immutable.
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

