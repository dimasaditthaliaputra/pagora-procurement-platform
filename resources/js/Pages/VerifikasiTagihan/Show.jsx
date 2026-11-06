import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    Building2,
    CalendarDays,
    CheckCircle2,
    Download,
    FileCheck,
    FileText,
    Hash,
    Package,
    Receipt,
    Scale,
    ShieldAlert,
    ShieldCheck,
    XCircle,
} from 'lucide-react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { LoadingButton } from '@/Components/loading-button';
import { formatDate, formatNumber, formatRupiah } from '@/lib/format';
import { notify } from '@/lib/notify';

function InfoItem({ label, children, icon: Icon }) {
    return (
        <div className="grid gap-1">
            <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                {Icon && <Icon className="size-3.5" />}
                {label}
            </span>
            <span className="text-sm font-medium text-foreground">{children || '-'}</span>
        </div>
    );
}

function MatchingBadge({ status }) {
    if (status === 'matched') {
        return (
            <Badge className="bg-emerald-600 text-white hover:bg-emerald-700 flex items-center gap-1">
                <ShieldCheck className="size-3.5" />
                Sesuai (Cocok)
            </Badge>
        );
    }

    if (status === 'mismatched') {
        return (
            <Badge variant="destructive" className="flex items-center gap-1">
                <ShieldAlert className="size-3.5" />
                Tidak Sesuai
            </Badge>
        );
    }

    return (
        <Badge variant="outline" className="text-amber-600 border-amber-300 bg-amber-50 dark:bg-amber-950/40 flex items-center gap-1">
            <FileCheck className="size-3.5" />
            Menunggu Verifikasi
        </Badge>
    );
}

export default function VerifikasiTagihanShow({ invoice, matching_evaluation }) {
    const data = invoice?.data ?? invoice;
    const [confirmDialogOpen, setConfirmDialogOpen] = useState(false);
    const [submitting, setSubmitting] = useState(false);

    if (!data) {
        return (
            <DashboardLayout>
                <div className="rounded-lg border p-8 text-center">
                    <Receipt className="mx-auto mb-3 size-10 text-muted-foreground" />
                    <h2 className="font-semibold text-foreground">Tagihan Tidak Ditemukan</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Data invoice tagihan tidak tersedia atau Anda tidak memiliki akses.
                    </p>
                    <Button asChild className="mt-4">
                        <Link href="/verifikasi-tagihan">Kembali ke Daftar Tagihan</Link>
                    </Button>
                </div>
            </DashboardLayout>
        );
    }

    const purchaseOrder = data.purchase_order || {};
    const vendor = data.vendor || {};
    const matchingData = matching_evaluation || data.matching_result || { items: [], summary: {} };
    const matchingItems = matchingData.items || [];
    const isPending = data.status_matching === 'pending';
    const isMatched = data.status_matching === 'matched';
    const isMismatched = data.status_matching === 'mismatched';
    const disputeList = data.sengketa || [];
    const latestDispute = disputeList[0] || null;

    const handleExecuteVerification = () => {
        setSubmitting(true);
        router.post(
            `/verifikasi-tagihan/${data.uuid}/verifikasi`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    setConfirmDialogOpen(false);
                    setSubmitting(false);
                    notify.success('Verifikasi 3-Way Matching berhasil diproses.');
                },
                onError: (errors) => {
                    setSubmitting(false);
                    notify.error(errors.invoice || 'Gagal memproses verifikasi tagihan.');
                },
                onFinish: () => {
                    setSubmitting(false);
                },
            }
        );
    };

    const breadcrumbs = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Verifikasi Tagihan', href: '/verifikasi-tagihan' },
        { label: data.nomor_invoice_vendor || 'Detail Verifikasi' },
    ];

    return (
        <DashboardLayout breadcrumbs={breadcrumbs}>
            <Head title={`Verifikasi Tagihan ${data.nomor_invoice_vendor || ''} - PAGORA`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-start">
                    <div>
                        <div className="flex flex-wrap items-center gap-2.5">
                            <Receipt className="size-6 text-emerald-600 dark:text-emerald-400" />
                            <h1 className="text-2xl font-bold tracking-tight text-foreground font-mono">
                                {data.nomor_invoice_vendor}
                            </h1>
                            <MatchingBadge status={data.status_matching} />
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Pencocokan 3-Way Matching · Tanggal Invoice: {formatDate(data.tanggal_invoice)}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <Link href="/verifikasi-tagihan">
                                <ArrowLeft className="mr-1.5 size-4" />
                                Kembali
                            </Link>
                        </Button>

                        <Button variant="outline" size="sm" asChild>
                            <a href={`/invoice/${data.uuid}/download`} download>
                                <Download className="mr-1.5 size-4" />
                                Unduh PDF
                            </a>
                        </Button>

                        {isPending && (
                            <Button
                                size="sm"
                                className="bg-emerald-600 text-white hover:bg-emerald-700 gap-1.5 shadow-xs"
                                onClick={() => setConfirmDialogOpen(true)}
                            >
                                <FileCheck className="size-4" />
                                Eksekusi Verifikasi 3-Way Matching
                            </Button>
                        )}
                    </div>
                </div>

                {/* Status Notice Banner */}
                {isMatched && (
                    <Alert className="border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/30">
                        <CheckCircle2 className="size-5 text-emerald-600 dark:text-emerald-400" />
                        <AlertTitle className="text-emerald-900 dark:text-emerald-200 font-semibold">
                            Tagihan Sesuai & Disetujui (3-Way Matching Berhasil)
                        </AlertTitle>
                        <AlertDescription className="text-emerald-800 dark:text-emerald-300 text-xs mt-1">
                            Seluruh parameter kuantitas, harga kesepakatan, dan subtotal telah dicocokkan dengan Purchase Order dan bukti penerimaan QC Gudang. Status Purchase Order kini <strong>Siap Dibayar (ready_to_pay)</strong>.
                            {data.diverifikasi_pada && (
                                <span className="block mt-0.5">
                                    Diverifikasi pada: {formatDate(data.diverifikasi_pada)}
                                    {data.verifikator?.name && ` oleh ${data.verifikator.name}`}.
                                </span>
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                {isMismatched && (
                    <Alert variant="destructive">
                        <AlertTriangle className="size-5" />
                        <AlertTitle className="font-semibold">
                            Hasil 3-Way Matching: Tidak Sesuai (Sengketa Pengadaan Terbuka)
                        </AlertTitle>
                        <AlertDescription className="text-xs mt-1 space-y-2">
                            <p>
                                Tagihan ini memiliki ketidaksesuaian antara kuantitas penerimaan gudang, harga kesepakatan PO, atau nominal tagihan. Sistem telah membuka sengketa pengadaan secara otomatis untuk penanganan lebih lanjut.
                            </p>
                            {latestDispute && (
                                <div className="mt-2 rounded-md bg-destructive/10 p-2.5 text-xs">
                                    <p className="font-semibold text-destructive-foreground">
                                        Pemicu Sengketa: {latestDispute.trigger_label || latestDispute.trigger_type}
                                    </p>
                                    <p className="mt-0.5 text-destructive-foreground/90 italic">
                                        "{latestDispute.reason}"
                                    </p>
                                    <div className="mt-2">
                                        <Button
                                            size="sm"
                                            variant="secondary"
                                            className="h-7 text-xs gap-1.5"
                                            asChild
                                        >
                                            <Link href={`/log-sengketa/${latestDispute.uuid}`}>
                                                <Scale className="size-3.5" />
                                                Buka Log Sengketa
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                {isPending && (
                    <Alert className={matchingData.is_matched ? 'border-emerald-200 bg-emerald-50/50 dark:border-emerald-900/40 dark:bg-emerald-950/20' : 'border-amber-200 bg-amber-50/50 dark:border-amber-900/40 dark:bg-amber-950/20'}>
                        {matchingData.is_matched ? (
                            <CheckCircle2 className="size-5 text-emerald-600 dark:text-emerald-400" />
                        ) : (
                            <AlertTriangle className="size-5 text-amber-600 dark:text-amber-400" />
                        )}
                        <AlertTitle className="font-semibold">
                            {matchingData.is_matched
                                ? 'Evaluasi Otomatis: Seluruh Item Sesuai'
                                : 'Evaluasi Otomatis: Ditemukan Ketidaksesuaian'}
                        </AlertTitle>
                        <AlertDescription className="text-xs mt-1">
                            {matchingData.is_matched
                                ? 'Data pada invoice telah dicocokkan dengan PO dan bukti penerimaan QC Gudang. Silakan tekan tombol "Eksekusi Verifikasi 3-Way Matching" untuk mengonfirmasi kelayakan dan mengubah status PO menjadi Siap Dibayar.'
                                : 'Ditemukan perbedaan antara tagihan dengan kuantitas yang diterima di gudang atau harga PO. Jika Anda mengeksekusi verifikasi, sistem akan menetapkan status Tidak Sesuai dan membuka sengketa otomatis.'}
                        </AlertDescription>
                    </Alert>
                )}

                {/* Info Cards */}
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    {/* Invoice Info */}
                    <Card className="shadow-xs">
                        <CardHeader className="border-b px-5 py-3.5">
                            <CardTitle className="text-base font-semibold flex items-center gap-2">
                                <Receipt className="size-4 text-emerald-600 dark:text-emerald-400" />
                                Rincian Invoice Vendor
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3.5 p-5 sm:grid-cols-2">
                            <InfoItem label="Nomor Invoice" icon={Hash}>
                                <span className="font-mono">{data.nomor_invoice_vendor}</span>
                            </InfoItem>

                            <InfoItem label="Tanggal Invoice" icon={CalendarDays}>
                                {formatDate(data.tanggal_invoice)}
                            </InfoItem>

                            <InfoItem label="Total Nilai Tagihan">
                                <span className="text-base font-bold text-emerald-700 dark:text-emerald-400 tabular-nums">
                                    {formatRupiah(data.jumlah_tagihan)}
                                </span>
                            </InfoItem>

                            <InfoItem label="Status 3-Way Matching">
                                <MatchingBadge status={data.status_matching} />
                            </InfoItem>

                            {data.diverifikasi_pada && (
                                <div className="sm:col-span-2 pt-1 border-t">
                                    <p className="text-xs text-muted-foreground">
                                        Diverifikasi oleh: <strong className="text-foreground">{data.verifikator?.name || '-'}</strong> pada {formatDate(data.diverifikasi_pada)}
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* PO & Vendor Info */}
                    <Card className="shadow-xs">
                        <CardHeader className="border-b px-5 py-3.5">
                            <CardTitle className="text-base font-semibold flex items-center gap-2">
                                <FileText className="size-4 text-emerald-600 dark:text-emerald-400" />
                                Rincian Purchase Order & Vendor
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3.5 p-5 sm:grid-cols-2">
                            <InfoItem label="Nomor PO" icon={FileText}>
                                <div className="flex items-center gap-2">
                                    <span className="font-mono">{purchaseOrder.nomor_po || '-'}</span>
                                    {purchaseOrder.uuid && (
                                        <Button variant="ghost" size="sm" className="h-6 px-1.5 text-xs text-primary" asChild>
                                            <Link href={`/purchase-order/${purchaseOrder.uuid}`}>
                                                Lihat PO
                                            </Link>
                                        </Button>
                                    )}
                                </div>
                            </InfoItem>

                            <InfoItem label="Status PO">
                                <Badge variant="outline">
                                    {purchaseOrder.status?.label || purchaseOrder.status || '-'}
                                </Badge>
                            </InfoItem>

                            <InfoItem label="Nama Vendor" icon={Building2}>
                                {vendor.company_name || '-'}
                            </InfoItem>

                            <InfoItem label="Kontak Vendor">
                                {vendor.contact_email || vendor.phone || '-'}
                            </InfoItem>
                        </CardContent>
                    </Card>
                </div>

                {/* 3-Way Matching Comparison Table */}
                <Card className="shadow-xs">
                    <CardHeader className="border-b px-5 py-4">
                        <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2 text-base font-semibold">
                                    <Package className="size-4 text-emerald-600 dark:text-emerald-400" />
                                    Tabel Pencocokan 3 Dokumen (PO vs Penerimaan QC vs Invoice)
                                </CardTitle>
                                <CardDescription className="text-xs mt-0.5">
                                    Membandingkan 3 referensi per item: kuantitas & harga final PO, kuantitas diterima Gudang, kuantitas & nominal Invoice (Toleransi selisih = 0 per PRD Q-31).
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow className="bg-muted/40">
                                        <TableHead className="pl-5 min-w-[160px]">Komoditas</TableHead>
                                        <TableHead className="text-center bg-blue-50/50 dark:bg-blue-950/20" colSpan={2}>
                                            <div className="font-semibold text-blue-900 dark:text-blue-300">
                                                1. Purchase Order
                                            </div>
                                        </TableHead>
                                        <TableHead className="text-center bg-amber-50/50 dark:bg-amber-950/20" colSpan={1}>
                                            <div className="font-semibold text-amber-900 dark:text-amber-300">
                                                2. QC Gudang
                                            </div>
                                        </TableHead>
                                        <TableHead className="text-center bg-emerald-50/50 dark:bg-emerald-950/20" colSpan={3}>
                                            <div className="font-semibold text-emerald-900 dark:text-emerald-300">
                                                3. Tagihan (Invoice)
                                            </div>
                                        </TableHead>
                                        <TableHead className="pr-5 text-center min-w-[130px]">Hasil Evaluasi</TableHead>
                                    </TableRow>
                                    <TableRow className="text-xs text-muted-foreground border-b">
                                        <TableHead className="pl-5">Item</TableHead>
                                        <TableHead className="text-right">Qty Pesan</TableHead>
                                        <TableHead className="text-right">Harga Satuan</TableHead>
                                        <TableHead className="text-right font-medium text-amber-900 dark:text-amber-300">
                                            Qty Diterima
                                        </TableHead>
                                        <TableHead className="text-right">Qty Ditagih</TableHead>
                                        <TableHead className="text-right">Harga Satuan</TableHead>
                                        <TableHead className="text-right">Subtotal</TableHead>
                                        <TableHead className="pr-5 text-center">Status</TableHead>
                                    </TableRow>
                                </TableHeader>

                                <TableBody>
                                    {matchingItems.map((item, idx) => {
                                        const isItemMatched = item.is_matched;

                                        return (
                                            <TableRow
                                                key={item.purchase_order_item_id || idx}
                                                className={!isItemMatched ? 'bg-rose-50/30 dark:bg-rose-950/20' : ''}
                                            >
                                                {/* Komoditas */}
                                                <TableCell className="pl-5 font-medium">
                                                    <div>{item.commodity_name}</div>
                                                    <span className="text-[11px] text-muted-foreground">
                                                        Satuan: {item.unit}
                                                    </span>
                                                </TableCell>

                                                {/* PO Qty */}
                                                <TableCell className="text-right tabular-nums">
                                                    {formatNumber(item.po_quantity)} {item.unit}
                                                </TableCell>

                                                {/* PO Price */}
                                                <TableCell className="text-right tabular-nums">
                                                    {formatRupiah(item.po_unit_price)}
                                                </TableCell>

                                                {/* QC Received Qty */}
                                                <TableCell className="text-right tabular-nums font-semibold text-amber-700 dark:text-amber-400">
                                                    {formatNumber(item.received_quantity)} {item.unit}
                                                    {item.rejected_quantity > 0 && (
                                                        <span className="block text-[11px] text-rose-500 font-normal">
                                                            (Ditolak: {formatNumber(item.rejected_quantity)})
                                                        </span>
                                                    )}
                                                </TableCell>

                                                {/* Invoice Invoiced Qty */}
                                                <TableCell className="text-right tabular-nums font-semibold">
                                                    <div className="flex items-center justify-end gap-1">
                                                        <span>{formatNumber(item.invoiced_quantity)} {item.unit}</span>
                                                        {item.quantity_matched ? (
                                                            <CheckCircle2 className="size-3.5 text-emerald-600 shrink-0" />
                                                        ) : (
                                                            <XCircle className="size-3.5 text-rose-600 shrink-0" />
                                                        )}
                                                    </div>
                                                </TableCell>

                                                {/* Invoice Unit Price */}
                                                <TableCell className="text-right tabular-nums">
                                                    <div className="flex items-center justify-end gap-1">
                                                        <span>{formatRupiah(item.invoiced_unit_price)}</span>
                                                        {item.price_matched ? (
                                                            <CheckCircle2 className="size-3.5 text-emerald-600 shrink-0" />
                                                        ) : (
                                                            <XCircle className="size-3.5 text-rose-600 shrink-0" />
                                                        )}
                                                    </div>
                                                </TableCell>

                                                {/* Invoice Subtotal */}
                                                <TableCell className="text-right tabular-nums font-semibold">
                                                    <div className="flex items-center justify-end gap-1">
                                                        <span>{formatRupiah(item.invoiced_subtotal)}</span>
                                                        {item.subtotal_matched ? (
                                                            <CheckCircle2 className="size-3.5 text-emerald-600 shrink-0" />
                                                        ) : (
                                                            <XCircle className="size-3.5 text-rose-600 shrink-0" />
                                                        )}
                                                    </div>
                                                </TableCell>

                                                {/* Item Status */}
                                                <TableCell className="pr-5 text-center">
                                                    {isItemMatched ? (
                                                        <Badge className="bg-emerald-600 text-white hover:bg-emerald-700 text-[11px]">
                                                            Sesuai
                                                        </Badge>
                                                    ) : (
                                                        <div className="flex flex-col items-center gap-1">
                                                            <Badge variant="destructive" className="text-[11px]">
                                                                Selisih
                                                            </Badge>
                                                            {item.notes && item.notes.length > 0 && (
                                                                <span className="text-[10px] text-destructive max-w-[180px] leading-tight">
                                                                    {item.notes[0]}
                                                                </span>
                                                            )}
                                                        </div>
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}

                                    {matchingItems.length === 0 && (
                                        <TableRow>
                                            <TableCell colSpan={8} className="p-8 text-center text-muted-foreground">
                                                Tidak ada rincian barang untuk dicocokkan.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>

                                <TableFooter>
                                    <TableRow className="font-bold">
                                        <TableCell className="pl-5">Total Nominal</TableCell>
                                        <TableCell className="text-right" colSpan={2}>
                                            {formatRupiah(matchingData.summary?.total_po_amount || 0)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {formatRupiah(matchingData.summary?.total_received_amount || 0)}
                                        </TableCell>
                                        <TableCell className="text-right pr-5" colSpan={4}>
                                            {formatRupiah(matchingData.summary?.total_invoiced_amount || data.jumlah_tagihan)}
                                        </TableCell>
                                    </TableRow>
                                </TableFooter>
                            </Table>
                        </div>
                    </CardContent>
                </Card>

                {/* Mismatch Detail Log if any */}
                {matchingData.summary?.mismatch_notes && matchingData.summary.mismatch_notes.length > 0 && (
                    <Card className="border-rose-200 bg-rose-50/50 dark:border-rose-900/50 dark:bg-rose-950/20 shadow-xs">
                        <CardHeader className="px-5 py-3.5 border-b border-rose-200 dark:border-rose-900/40">
                            <CardTitle className="text-sm font-semibold text-rose-900 dark:text-rose-200 flex items-center gap-2">
                                <AlertTriangle className="size-4 text-rose-600" />
                                Rincian Ketidaksesuaian yang Terdeteksi
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-5">
                            <ul className="list-disc pl-5 space-y-1.5 text-xs text-rose-800 dark:text-rose-300">
                                {matchingData.summary.mismatch_notes.map((note, nIdx) => (
                                    <li key={nIdx}>{note}</li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}
            </div>

            {/* Confirmation Dialog for 3-Way Matching Execution */}
            <Dialog open={confirmDialogOpen} onOpenChange={setConfirmDialogOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <FileCheck className="size-5 text-emerald-600" />
                            Konfirmasi Verifikasi 3-Way Matching
                        </DialogTitle>
                        <DialogDescription className="text-xs pt-1">
                            {matchingData.is_matched ? (
                                <span>
                                    Seluruh data pada Invoice <strong>{data.nomor_invoice_vendor}</strong> telah dicocokkan dengan Purchase Order dan catatan penerimaan QC Gudang. Kuantitas dan harga <strong>Sesuai</strong> tanpa selisih.
                                    <br /><br />
                                    Setelah disetujui, status Purchase Order akan diperbarui menjadi <strong>Siap Dibayar (ready_to_pay)</strong>.
                                </span>
                            ) : (
                                <span>
                                    Ditemukan <strong>ketidaksesuaian</strong> pada data tagihan.
                                    <br /><br />
                                    Jika Anda melanjutkan eksekusi, status invoice akan dicatat sebagai <strong>Tidak Sesuai (mismatched)</strong> dan sistem akan <strong>membuka sengketa pengadaan secara otomatis</strong> untuk kendala transaksi ini.
                                </span>
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter className="gap-2 sm:gap-0 pt-2">
                        <Button
                            variant="outline"
                            onClick={() => setConfirmDialogOpen(false)}
                            disabled={submitting}
                        >
                            Batal
                        </Button>

                        <LoadingButton
                            loading={submitting}
                            loadingText="Memverifikasi..."
                            onClick={handleExecuteVerification}
                            className={matchingData.is_matched ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-rose-600 hover:bg-rose-700 text-white'}
                        >
                            {matchingData.is_matched ? 'Setujui & Tandai Sesuai' : 'Konfirmasi & Buka Sengketa'}
                        </LoadingButton>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </DashboardLayout>
    );
}

