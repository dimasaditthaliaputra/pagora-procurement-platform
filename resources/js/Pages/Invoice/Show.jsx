import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    FileText,
    Package,
    Receipt,
    CalendarDays,
    Building2,
    Hash,
    Download,
    CheckCircle2,
    AlertTriangle,
    XCircle,
    ShieldAlert,
} from 'lucide-react';

import { formatDate, formatNumber, formatRupiah } from '@/lib/format';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
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

function Info({ label, children, icon: Icon }) {
    return (
        <div className="grid gap-1">
            <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                {Icon && <Icon className="size-3.5" />}
                {label}
            </span>
            <span className="text-sm font-medium">{children || '-'}</span>
        </div>
    );
}

function MatchingBadge({ status }) {
    const variants = {
        pending: 'secondary',
        matched: 'default',
        mismatched: 'destructive',
    };

    const labels = {
        pending: 'Menunggu Pencocokan',
        matched: 'Sesuai',
        mismatched: 'Tidak Sesuai',
    };

    return (
        <Badge variant={variants[status] || 'secondary'}>
            {labels[status] || status || 'Belum Diketahui'}
        </Badge>
    );
}

export default function InvoiceShow({ invoice }) {
    const data = invoice?.data ?? invoice;

    if (!data) {
        return (
            <DashboardLayout>
                <div className="rounded-lg border p-8 text-center">
                    <FileText className="mx-auto mb-3 size-10 text-muted-foreground" />
                    <h2 className="font-semibold">Invoice tidak ditemukan</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Data invoice tidak tersedia atau tidak dapat diakses.
                    </p>
                    <Button asChild className="mt-4">
                        <Link href="/invoice">Kembali ke Invoice</Link>
                    </Button>
                </div>
            </DashboardLayout>
        );
    }

    const items = data.items || [];
    const purchaseOrder = data.purchase_order || {};
    const vendor = data.vendor || {};

    const breadcrumbs = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Invoice', href: '/invoice' },
        { label: data.nomor_invoice_vendor || 'Detail Invoice' },
    ];

    return (
        <DashboardLayout breadcrumbs={breadcrumbs}>
            <Head title={`${data.nomor_invoice_vendor || 'Detail Invoice'} - PAGORA`} />

            <div className="flex w-full flex-col gap-4">
                {/* Header Invoice dan Tombol Aksi */}
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-start">
                    <div>
                        <h1 className="flex flex-wrap items-center gap-2 text-xl font-bold tracking-tight">
                            <Receipt className="size-5 text-primary" />
                            <span className="font-mono">
                                {data.nomor_invoice_vendor}
                            </span>
                            <MatchingBadge status={data.status_matching} />
                        </h1>

                        <p className="mt-1 text-xs text-muted-foreground">
                            Invoice vendor · {formatDate(data.tanggal_invoice)}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button variant="outline" size="sm" asChild className="h-9">
                            <Link href="/invoice">
                                <ArrowLeft className="mr-1.5 size-4" />
                                Kembali
                            </Link>
                        </Button>

                        <Button size="sm" className="h-9" asChild>
                            <a
                                href={`/invoice/${data.uuid}/download`}
                                download
                            >
                                <Download className="mr-1.5 size-4" />
                                Download PDF
                            </a>
                        </Button>
                    </div>
                </div>

                {/* Informasi Invoice dan Purchase Order */}
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <Card className="shadow-sm lg:col-span-1">
                        <CardHeader className="border-b px-5 py-3.5">
                            <CardTitle className="text-base font-semibold">
                                Informasi Invoice
                            </CardTitle>
                        </CardHeader>

                        <CardContent className="grid gap-4 p-5">
                            <Info label="Nomor Invoice" icon={Hash}>
                                {data.nomor_invoice_vendor}
                            </Info>

                            <Info label="Tanggal Invoice" icon={CalendarDays}>
                                {formatDate(data.tanggal_invoice)}
                            </Info>

                            <Info label="Status Pencocokan">
                                <MatchingBadge status={data.status_matching} />
                            </Info>

                            <Info label="Tanggal Dibuat">
                                {formatDate(data.created_at)}
                            </Info>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm lg:col-span-2">
                        <CardHeader className="border-b px-5 py-3.5">
                            <CardTitle className="text-base font-semibold">
                                Informasi Purchase Order
                            </CardTitle>
                        </CardHeader>

                        <CardContent className="grid gap-4 p-5 sm:grid-cols-2">
                            <Info label="Nomor PO" icon={FileText}>
                                {purchaseOrder.nomor_po}
                            </Info>

                            <Info label="Status PO">
                                {purchaseOrder.status?.label ||
                                    purchaseOrder.status ||
                                    '-'}
                            </Info>

                            <Info label="Vendor" icon={Building2}>
                                {vendor.company_name || vendor.nama_perusahaan}
                            </Info>

                            <Info label="Jumlah Jenis Barang" icon={Package}>
                                {items.length} jenis barang
                            </Info>

                            {purchaseOrder.uuid && (
                                <div className="sm:col-span-2">
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={`/purchase-order/${purchaseOrder.uuid}`}>
                                            Lihat Purchase Order
                                        </Link>
                                    </Button>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Rincian Barang Invoice */}
                <Card className="shadow-sm">
                    <CardHeader className="border-b px-5 py-3.5">
                        <CardTitle className="flex items-center gap-2 text-base font-semibold">
                            <Package className="size-4 text-primary" />
                            Rincian Barang Invoice
                        </CardTitle>
                    </CardHeader>

                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="pl-5">Komoditas</TableHead>
                                    <TableHead className="text-right">Kuantitas</TableHead>
                                    <TableHead className="text-right">Harga Satuan</TableHead>
                                    <TableHead className="pr-5 text-right">Subtotal</TableHead>
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                {items.map((item) => {
                                    const commodity =
                                        item.commodity ||
                                        item.purchase_order_item?.commodity ||
                                        item.purchase_order_item?.komoditas ||
                                        {};

                                    return (
                                        <TableRow key={item.uuid}>
                                            <TableCell className="pl-5 font-medium">
                                                {commodity.name ||
                                                    commodity.nama_komoditas ||
                                                    'Komoditas'}

                                                <p className="text-xs font-normal text-muted-foreground">
                                                    Satuan: {commodity.unit || commodity.satuan || '-'}
                                                </p>
                                            </TableCell>

                                            <TableCell className="text-right tabular-nums">
                                                {formatNumber(item.quantity)}
                                            </TableCell>

                                            <TableCell className="text-right tabular-nums">
                                                {formatRupiah(item.unit_price)}
                                            </TableCell>

                                            <TableCell className="pr-5 text-right font-semibold tabular-nums">
                                                {formatRupiah(item.subtotal)}
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}

                                {items.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={4}
                                            className="p-8 text-center text-muted-foreground"
                                        >
                                            Belum ada rincian barang pada invoice ini.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>

                            <TableFooter>
                                <TableRow className="text-base font-bold">
                                    <TableCell colSpan={3} className="pl-5">
                                        Total Tagihan
                                    </TableCell>

                                    <TableCell className="pr-5 text-right tabular-nums">
                                        {formatRupiah(data.jumlah_tagihan)}
                                    </TableCell>
                                </TableRow>
                            </TableFooter>
                        </Table>
                    </CardContent>
                </Card>

                {/* Hasil Pencocokan 3-Way Matching */}
                {data.matching_result && (
                    <Card className="shadow-sm">
                        <CardHeader className="border-b px-5 py-3.5">
                            <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2 text-base font-semibold">
                                        <Package className="size-4 text-primary" />
                                        Hasil Pencocokan 3 Dokumen (3-Way Matching)
                                    </CardTitle>
                                    <CardDescription className="text-xs">
                                        Hasil verifikasi perbandingan PO, bukti penerimaan QC Gudang, dan tagihan invoice.
                                    </CardDescription>
                                </div>
                                <MatchingBadge status={data.status_matching} />
                            </div>
                        </CardHeader>

                        <CardContent className="space-y-4 p-5">
                            {data.status_matching === 'matched' ? (
                                <Alert className="border-emerald-200 bg-emerald-50/50 dark:border-emerald-900/40 dark:bg-emerald-950/20">
                                    <CheckCircle2 className="size-4 text-emerald-600 dark:text-emerald-400" />
                                    <AlertTitle className="text-sm font-semibold text-emerald-800 dark:text-emerald-300">
                                        Invoice Telah Terverifikasi (Sesuai)
                                    </AlertTitle>
                                    <AlertDescription className="text-xs text-emerald-700 dark:text-emerald-400">
                                        Seluruh kuantitas dan harga satuan pada invoice telah sesuai dengan Purchase Order dan barang yang diterima oleh QC Gudang.
                                        {data.diverifikasi_pada && (
                                            <span className="block mt-1 text-[11px] text-muted-foreground">
                                                Diverifikasi oleh {data.verifikator?.name || 'Buyer'} pada {formatDate(data.diverifikasi_pada)}.
                                            </span>
                                        )}
                                    </AlertDescription>
                                </Alert>
                            ) : data.status_matching === 'mismatched' ? (
                                <Alert className="border-rose-200 bg-rose-50/50 dark:border-rose-900/40 dark:bg-rose-950/20">
                                    <AlertTriangle className="size-4 text-rose-600 dark:text-rose-400" />
                                    <AlertTitle className="text-sm font-semibold text-rose-800 dark:text-rose-300">
                                        Ketidaksesuaian Tagihan Terdeteksi
                                    </AlertTitle>
                                    <AlertDescription className="text-xs text-rose-700 dark:text-rose-400">
                                        Ditemukan perbedaan antara kuantitas/harga pada invoice dengan data PO atau penerimaan QC Gudang. Sengketa otomatis telah dibuka untuk penyelesaian.
                                        {data.diverifikasi_pada && (
                                            <span className="block mt-1 text-[11px] text-muted-foreground">
                                                Diverifikasi oleh {data.verifikator?.name || 'Buyer'} pada {formatDate(data.diverifikasi_pada)}.
                                            </span>
                                        )}
                                    </AlertDescription>
                                </Alert>
                            ) : null}

                            {/* Dispute Info if any */}
                            {data.sengketa && data.sengketa.length > 0 && (
                                <div className="rounded-lg border border-rose-200 bg-rose-50/40 p-4 dark:border-rose-900/30 dark:bg-rose-950/10">
                                    <div className="flex items-center gap-2 font-medium text-rose-800 dark:text-rose-300 text-sm">
                                        <ShieldAlert className="size-4 text-rose-600" />
                                        Informasi Sengketa Aktif
                                    </div>
                                    <div className="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-3 text-xs">
                                        <div>
                                            <span className="text-muted-foreground">Nomor Sengketa:</span>
                                            <p className="font-mono font-medium">{data.sengketa[0]?.nomor_sengketa || '-'}</p>
                                        </div>
                                        <div>
                                            <span className="text-muted-foreground">Pemicu:</span>
                                            <p className="font-medium">{data.sengketa[0]?.trigger_type || '-'}</p>
                                        </div>
                                        <div>
                                            <span className="text-muted-foreground">Status:</span>
                                            <p className="font-medium">{data.sengketa[0]?.status || '-'}</p>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* Comparison Table */}
                            {data.matching_result?.items && data.matching_result.items.length > 0 && (
                                <div className="overflow-x-auto rounded-md border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow className="bg-muted/40 text-xs">
                                                <TableHead className="pl-4">Komoditas</TableHead>
                                                <TableHead className="text-right">Qty Pesan (PO)</TableHead>
                                                <TableHead className="text-right">Qty Diterima (QC)</TableHead>
                                                <TableHead className="text-right">Qty Ditagih</TableHead>
                                                <TableHead className="text-right">Harga PO</TableHead>
                                                <TableHead className="text-right">Harga Ditagih</TableHead>
                                                <TableHead className="pr-4 text-center">Status</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {data.matching_result.items.map((item, idx) => (
                                                <TableRow
                                                    key={idx}
                                                    className={!item.is_matched ? 'bg-rose-50/30 dark:bg-rose-950/20' : ''}
                                                >
                                                    <TableCell className="pl-4 font-medium text-xs">
                                                        <div>{item.commodity_name}</div>
                                                        <span className="text-[10px] text-muted-foreground">
                                                            Satuan: {item.unit}
                                                        </span>
                                                    </TableCell>
                                                    <TableCell className="text-right text-xs tabular-nums">
                                                        {formatNumber(item.po_quantity)} {item.unit}
                                                    </TableCell>
                                                    <TableCell className="text-right text-xs tabular-nums font-medium text-amber-700 dark:text-amber-400">
                                                        {formatNumber(item.received_quantity)} {item.unit}
                                                    </TableCell>
                                                    <TableCell className="text-right text-xs tabular-nums font-semibold">
                                                        <div className="flex items-center justify-end gap-1">
                                                            <span>{formatNumber(item.invoiced_quantity)} {item.unit}</span>
                                                            {item.quantity_matched ? (
                                                                <CheckCircle2 className="size-3 text-emerald-600 shrink-0" />
                                                            ) : (
                                                                <XCircle className="size-3 text-rose-600 shrink-0" />
                                                            )}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="text-right text-xs tabular-nums">
                                                        {formatRupiah(item.po_unit_price)}
                                                    </TableCell>
                                                    <TableCell className="text-right text-xs tabular-nums">
                                                        <div className="flex items-center justify-end gap-1">
                                                            <span>{formatRupiah(item.invoice_unit_price)}</span>
                                                            {item.price_matched ? (
                                                                <CheckCircle2 className="size-3 text-emerald-600 shrink-0" />
                                                            ) : (
                                                                <XCircle className="size-3 text-rose-600 shrink-0" />
                                                            )}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="pr-4 text-center text-xs">
                                                        {item.is_matched ? (
                                                            <Badge variant="outline" className="text-[10px] text-emerald-700 border-emerald-300 dark:text-emerald-400">
                                                                Sesuai
                                                            </Badge>
                                                        ) : (
                                                            <Badge variant="destructive" className="text-[10px]">
                                                                {item.discrepancy_reason || 'Selisih'}
                                                            </Badge>
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </DashboardLayout>
    );
}