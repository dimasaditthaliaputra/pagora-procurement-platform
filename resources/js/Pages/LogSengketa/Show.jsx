import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Building2,
    Camera,
    ClipboardCheck,
    Download,
    FileText,
    History,
    Lock,
    MessagesSquare,
    Paperclip,
    Receipt,
    Scale,
    Truck,
} from 'lucide-react';
import { formatDate, formatNumber, formatRupiah, formatTime } from '@/lib/format';
import { cn } from '@/lib/utils';
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
import { SengketaStatusBadge, SengketaTriggerBadge } from '@/Pages/LogSengketa/columns';
import { TinjauanSengketaForm } from '@/Pages/LogSengketa/components/TinjauanSengketaForm';

function DetailRow({ label, children }) {
    return (
        <div className="grid gap-0.5">
            <span className="text-xs text-muted-foreground">{label}</span>
            <div className="text-sm font-medium text-foreground">{children}</div>
        </div>
    );
}

function formatDateTime(value) {
    if (!value) return '-';
    return `${formatDate(value)}, ${formatTime(value)} WIB`;
}

const LOG_TYPE_STYLES = {
    status_change: 'bg-sky-500',
    evidence: 'bg-amber-500',
    resolution: 'bg-emerald-500',
    chat_snapshot: 'bg-violet-500',
    note: 'bg-zinc-400',
};

export default function LogSengketaShow({ dispute: rawDispute }) {
    const dispute = rawDispute?.data ?? rawDispute;
    const po = dispute?.purchase_order;
    const item = dispute?.disputed_item;
    const qc = item?.qc_inspection;
    const invoice = dispute?.disputed_invoice;
    const messages = dispute?.negotiation_messages ?? [];
    const logs = dispute?.logs ?? [];
    const isResolved = dispute?.status === 'selesai' || dispute?.status === 'resolved';
    const isOpenStatus = dispute?.status === 'terbuka' || dispute?.status === 'open';

    const [previewPhoto, setPreviewPhoto] = useState(null);

    const breadcrumbs = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Log Sengketa', href: '/log-sengketa' },
        { label: `${dispute?.po_number ?? '-'} ${dispute?.short_code ?? ''}`, href: '#' },
    ];

    return (
        <DashboardLayout breadcrumbs={breadcrumbs}>
            <Head title={`Sengketa ${dispute?.po_number ?? ''} - Log Sengketa - PAGORA`} />

            <div className="flex flex-col gap-5 w-full">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-start justify-between gap-4">
                    <div className="space-y-2">
                        <h1 className="text-xl font-bold tracking-tight flex flex-wrap items-center gap-2">
                            <Scale className="size-5 text-primary" />
                            <span>Detail Sengketa</span>
                            <span className="font-mono text-muted-foreground">· {dispute?.po_number}</span>
                        </h1>
                        <div className="flex flex-wrap items-center gap-2">
                            <SengketaStatusBadge status={dispute?.status} label={dispute?.status_label} />
                            <SengketaTriggerBadge
                                triggerType={dispute?.trigger_type}
                                label={dispute?.trigger_type_label}
                            />
                            <span className="text-xs text-muted-foreground">
                                Kode {dispute?.short_code} · Dibuka {formatDateTime(dispute?.opened_at)} oleh{' '}
                                {dispute?.opened_by_name}
                            </span>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2 shrink-0">
                        <Button variant="outline" asChild className="h-9 gap-1.5">
                            <Link href="/log-sengketa">
                                <ArrowLeft className="size-4" />
                                Kembali
                            </Link>
                        </Button>
                        {dispute?.can?.export && (
                            <Button asChild className="h-9 gap-1.5">
                                <a href={dispute.export_url}>
                                    <Download className="size-4" />
                                    Unduh Sengketa (PDF)
                                </a>
                            </Button>
                        )}
                    </div>
                </div>

                {isResolved && (
                    <Alert className="bg-emerald-500/5 border-emerald-500/30">
                        <Lock className="size-4 text-emerald-600" />
                        <AlertTitle className="text-sm font-semibold">Sengketa Telah Selesai</AlertTitle>
                        <AlertDescription className="text-xs text-muted-foreground">
                            Diselesaikan pada {formatDateTime(dispute?.resolved_at)}. Rekaman sengketa dan Log
                            Sengketa bersifat permanen dan tidak dapat diubah.
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">
                    <div className="lg:col-span-2 space-y-5">
                        {/* Ringkasan Kendala */}
                        <Card className="shadow-sm">
                            <CardHeader className="border-b">
                                <CardTitle className="text-base font-semibold flex items-center gap-2">
                                    <FileText className="size-4 text-primary" />
                                    Ringkasan Kendala
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="p-5 space-y-4">
                                <div className="rounded-lg border bg-muted/30 p-3">
                                    <p className="text-xs font-medium text-muted-foreground mb-1">
                                        Alasan / Kronologi Pembukaan Sengketa
                                    </p>
                                    <p className="text-sm text-foreground whitespace-pre-line">
                                        {dispute?.reason || 'Tidak ada catatan alasan.'}
                                    </p>
                                </div>
                                {dispute?.trigger_description && (
                                    <p className="text-xs text-muted-foreground">
                                        <strong className="text-foreground">Kategori pemicu:</strong>{' '}
                                        {dispute.trigger_description}
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        {/* Objek Sengketa: Item QC */}
                        {item && (
                            <Card className="shadow-sm">
                                <CardHeader className="border-b">
                                    <CardTitle className="text-base font-semibold flex items-center gap-2">
                                        <ClipboardCheck className="size-4 text-primary" />
                                        Item Dipersoalkan & Bukti Pemeriksaan QC
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="p-5 space-y-5">
                                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                                        <DetailRow label="Komoditas">{item.commodity_name}</DetailRow>
                                        <DetailRow label="Kuantitas Pesanan">
                                            <span className="tabular-nums">
                                                {formatNumber(item.ordered_quantity)} {item.unit}
                                            </span>
                                        </DetailRow>
                                        <DetailRow label="Harga Satuan Final">
                                            <span className="tabular-nums">
                                                {formatRupiah(item.unit_price)} / {item.unit}
                                            </span>
                                        </DetailRow>
                                        <DetailRow label="Toleransi Susut">
                                            <span className="tabular-nums">{formatNumber(item.shrinkage_tolerance)}%</span>
                                        </DetailRow>
                                    </div>

                                    {qc ? (
                                        <>
                                            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 rounded-lg border bg-muted/20 p-4">
                                                <DetailRow label="Diterima Fisik">
                                                    <span className="tabular-nums">
                                                        {formatNumber(qc.received_quantity)} {item.unit}
                                                    </span>
                                                </DetailRow>
                                                <DetailRow label="Selisih / Susut">
                                                    <span
                                                        className={cn(
                                                            'tabular-nums',
                                                            qc.exceeds_tolerance && 'text-destructive'
                                                        )}
                                                    >
                                                        {formatNumber(qc.shortage_quantity)} {item.unit} (
                                                        {formatNumber(qc.shrinkage_percentage)}%)
                                                    </span>
                                                </DetailRow>
                                                <DetailRow label="Petugas QC">{qc.inspector_name}</DetailRow>
                                                <DetailRow label="Waktu Inspeksi">
                                                    {formatDateTime(qc.inspected_at)}
                                                </DetailRow>
                                            </div>

                                            {qc.notes && (
                                                <div>
                                                    <p className="text-xs font-medium text-muted-foreground mb-1">
                                                        Catatan Pemeriksaan QC
                                                    </p>
                                                    <p className="text-sm text-foreground whitespace-pre-line">{qc.notes}</p>
                                                </div>
                                            )}

                                            <div className="space-y-2">
                                                <p className="text-xs font-medium text-muted-foreground flex items-center gap-1.5">
                                                    <Camera className="size-3.5" />
                                                    Foto Bukti QC ({qc.photos.length})
                                                </p>
                                                {qc.photos.length > 0 ? (
                                                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                                        {qc.photos.map((photo) => (
                                                            <button
                                                                key={photo.index}
                                                                type="button"
                                                                onClick={() => setPreviewPhoto(photo)}
                                                                className="group relative aspect-square overflow-hidden rounded-lg border bg-muted cursor-pointer focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                                                            >
                                                                <img
                                                                    src={photo.url}
                                                                    alt={`Foto bukti QC ${photo.index + 1}`}
                                                                    loading="lazy"
                                                                    className="size-full object-cover transition-transform group-hover:scale-105"
                                                                />
                                                            </button>
                                                        ))}
                                                    </div>
                                                ) : (
                                                    <p className="text-xs text-muted-foreground">Tidak ada foto bukti.</p>
                                                )}
                                            </div>
                                        </>
                                    ) : (
                                        <p className="text-xs text-muted-foreground">
                                            Belum ada rekaman inspeksi QC untuk item ini.
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                        )}

                        {/* Objek Sengketa: Invoice */}
                        {invoice && (
                            <Card className="shadow-sm">
                                <CardHeader className="border-b">
                                    <CardTitle className="text-base font-semibold flex items-center gap-2">
                                        <Receipt className="size-4 text-primary" />
                                        Tagihan Dipersoalkan (3-Way Matching)
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="p-5 grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <DetailRow label="Nomor Invoice">
                                        <span className="font-mono">{invoice.vendor_invoice_number}</span>
                                    </DetailRow>
                                    <DetailRow label="Tanggal Invoice">{formatDate(invoice.invoice_date)}</DetailRow>
                                    <DetailRow label="Nominal Tagihan">
                                        <span className="tabular-nums">{formatRupiah(invoice.total_amount)}</span>
                                    </DetailRow>
                                    <DetailRow label="Hasil Matching">{invoice.matching_status_label}</DetailRow>
                                </CardContent>
                            </Card>
                        )}

                        {/* Riwayat Smart Negotiation Chat */}
                        <Card className="shadow-sm">
                            <CardHeader className="border-b">
                                <CardTitle className="text-base font-semibold flex items-center gap-2">
                                    <MessagesSquare className="size-4 text-primary" />
                                    Riwayat Negosiasi PO
                                    <Badge variant="outline" className="ml-auto">{messages.length} pesan</Badge>
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="p-5">
                                {messages.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Tidak ada riwayat negosiasi untuk Purchase Order ini.
                                    </p>
                                ) : (
                                    <div className="max-h-96 overflow-y-auto space-y-3 pr-1">
                                        {messages.map((msg) => (
                                            <div
                                                key={msg.uuid}
                                                className={cn('flex', msg.is_vendor_sender ? 'justify-start' : 'justify-end')}
                                            >
                                                <div
                                                    className={cn(
                                                        'max-w-[85%] rounded-xl border px-3 py-2',
                                                        msg.is_vendor_sender ? 'bg-muted/40' : 'bg-primary/5 border-primary/20'
                                                    )}
                                                >
                                                    <p className="text-[11px] font-semibold text-muted-foreground">
                                                        {msg.sender_name} · {msg.sender_role_label}
                                                    </p>
                                                    {msg.message && (
                                                        <p className="text-sm text-foreground whitespace-pre-line">{msg.message}</p>
                                                    )}
                                                    {msg.offer_amount !== null && msg.offer_amount > 0 && (
                                                        <p className="text-xs font-semibold text-foreground tabular-nums mt-1">
                                                            Tawaran: {formatRupiah(msg.offer_amount)}
                                                        </p>
                                                    )}
                                                    <p className="text-[10px] text-muted-foreground mt-1 tabular-nums">
                                                        {formatDateTime(msg.created_at)}
                                                    </p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Jejak Audit Log Sengketa */}
                        <Card className="shadow-sm">
                            <CardHeader className="border-b">
                                <CardTitle className="text-base font-semibold flex items-center gap-2">
                                    <History className="size-4 text-primary" />
                                    Log Sengketa
                                    <Badge variant="outline" className="ml-auto gap-1">
                                        <Lock className="size-3" />
                                        Permanen
                                    </Badge>
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="p-5">
                                {logs.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">Belum ada entri Log Sengketa.</p>
                                ) : (
                                    <ol className="relative space-y-5 border-l border-border pl-5">
                                        {logs.map((log) => (
                                            <li key={log.uuid} className="relative">
                                                <span
                                                    className={cn(
                                                        'absolute -left-[26px] top-1.5 size-2.5 rounded-full ring-4 ring-background',
                                                        LOG_TYPE_STYLES[log.type] ?? LOG_TYPE_STYLES.note
                                                    )}
                                                />
                                                <div className="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                                    <span className="text-sm font-semibold text-foreground">{log.author_name}</span>
                                                    <span className="text-xs text-muted-foreground">{log.author_role_label}</span>
                                                    <Badge variant="outline" className="text-[10px] px-1.5 py-0">
                                                        {log.type_label}
                                                    </Badge>
                                                </div>
                                                <p className="text-[11px] text-muted-foreground tabular-nums">
                                                    {formatDateTime(log.created_at)}
                                                </p>
                                                {log.message && (
                                                    <p className="mt-1.5 text-sm text-foreground whitespace-pre-line">{log.message}</p>
                                                )}
                                                {log.has_attachment && (
                                                    <a
                                                        href={log.attachment_url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="mt-2 inline-flex items-center gap-1.5 rounded-md border bg-muted/30 px-2 py-1 text-xs font-medium text-foreground hover:bg-muted"
                                                    >
                                                        <Paperclip className="size-3.5" />
                                                        {log.attachment_is_image ? 'Lihat Foto Bukti' : 'Buka Lampiran'}
                                                    </a>
                                                )}
                                            </li>
                                        ))}
                                    </ol>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Panel Samping */}
                    <div className="lg:col-span-1 space-y-5">
                        {dispute?.can?.review && (
                            <TinjauanSengketaForm
                                disputeUuid={dispute.uuid}
                                poNumber={dispute.po_number}
                                isOpenStatus={isOpenStatus}
                            />
                        )}

                        {po && (
                            <Card className="shadow-sm">
                                <CardHeader className="pb-3 border-b">
                                    <CardTitle className="text-sm font-semibold flex items-center gap-2">
                                        <Building2 className="size-4 text-primary" />
                                        Purchase Order Terkait
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="p-5 space-y-3 text-sm">
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="font-mono font-bold text-foreground">{po.po_number}</span>
                                        <Badge variant="sky">{po.status_label}</Badge>
                                    </div>
                                    <DetailRow label="Vendor">
                                        {po.vendor_name}
                                        {po.vendor_email && (
                                            <span className="block text-xs font-normal text-muted-foreground">
                                                {po.vendor_email}
                                            </span>
                                        )}
                                    </DetailRow>
                                    <DetailRow label="Tanggal PO">{formatDate(po.po_date)}</DetailRow>
                                    <DetailRow label="Dibuat Oleh">{po.created_by_name}</DetailRow>
                                    <DetailRow label="Total Nilai Kesepakatan">
                                        <span className="tabular-nums">{formatRupiah(po.total_amount)}</span>
                                    </DetailRow>

                                    {po.latest_shipment && (
                                        <div className="rounded-lg border bg-muted/20 p-3 space-y-1">
                                            <p className="text-xs font-medium text-muted-foreground flex items-center gap-1.5">
                                                <Truck className="size-3.5" />
                                                Pengiriman Terakhir
                                            </p>
                                            <p className="text-xs text-foreground">
                                                {po.latest_shipment.route} — {po.latest_shipment.carrier_name}
                                            </p>
                                            <p className="text-xs font-mono font-semibold text-foreground">
                                                {po.latest_shipment.vehicle_plate_number}
                                            </p>
                                        </div>
                                    )}

                                    <div className="pt-2 border-t space-y-2">
                                        <p className="text-xs font-medium text-muted-foreground">
                                            Item PO ({po.items.length})
                                        </p>
                                        <ul className="space-y-1.5">
                                            {po.items.map((poItem) => (
                                                <li
                                                    key={poItem.uuid}
                                                    className={cn(
                                                        'flex items-center justify-between gap-2 text-xs rounded-md px-2 py-1',
                                                        item?.uuid === poItem.uuid && 'bg-destructive/5 text-destructive'
                                                    )}
                                                >
                                                    <span className="truncate">{poItem.commodity_name}</span>
                                                    <span className="tabular-nums shrink-0">
                                                        {formatNumber(poItem.quantity)} {poItem.unit}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>

            <Dialog open={Boolean(previewPhoto)} onOpenChange={(open) => !open && setPreviewPhoto(null)}>
                <DialogContent className="sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>Foto Bukti Pemeriksaan QC</DialogTitle>
                        <DialogDescription>{item?.commodity_name} · {dispute?.po_number}</DialogDescription>
                    </DialogHeader>
                    {previewPhoto && (
                        <img
                            src={previewPhoto.url}
                            alt="Pratinjau foto bukti QC"
                            className="w-full max-h-[70vh] object-contain rounded-lg bg-muted"
                        />
                    )}
                </DialogContent>
            </Dialog>
        </DashboardLayout>
    );
}

