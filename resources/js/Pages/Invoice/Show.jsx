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
} from 'lucide-react';

import { formatDate, formatNumber, formatRupiah } from '@/lib/format';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
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

                {/* Hasil Pencocokan */}
                {data.matching_result && (
                    <Card className="shadow-sm">
                        <CardHeader className="border-b px-5 py-3.5">
                            <CardTitle className="text-base font-semibold">
                                Hasil Pencocokan Invoice
                            </CardTitle>
                        </CardHeader>

                        <CardContent className="p-5">
                            <pre className="overflow-x-auto whitespace-pre-wrap text-sm">
                                {JSON.stringify(data.matching_result, null, 2)}
                            </pre>
                        </CardContent>
                    </Card>
                )}
            </div>
        </DashboardLayout>
    );
}