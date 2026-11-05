import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    FileText,
    Package,
    Upload,
} from 'lucide-react';

import DashboardLayout from '@/Layouts/DashboardLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

const formatCurrency = (value) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(Number(value || 0));

const formatNumber = (value) =>
    new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 3,
    }).format(Number(value || 0));

const getLocalDate = () => {
    const date = new Date();
    const offset = date.getTimezoneOffset();
    return new Date(date.getTime() - offset * 60000)
        .toISOString()
        .slice(0, 10);
};

export default function Preview({ preview }) {
    const purchaseOrder = preview?.purchase_order;
    const items = preview?.items || [];

    const form = useForm({
        purchase_order_uuid: purchaseOrder?.uuid || '',
        nomor_invoice_vendor: '',
        tanggal_invoice: getLocalDate(),
        file_invoice: null,
    });

    const handleSubmit = (event) => {
        event.preventDefault();

        form.post('/invoice', {
            forceFormData: true,
        });
    };

    return (
        <DashboardLayout
            breadcrumbs={[
                { label: 'Invoice', href: '/invoice' },
                { label: 'Buat Invoice', href: '/invoice/create' },
                { label: 'Preview Invoice' },
            ]}
        >
            <Head title="Preview Invoice" />

            <form onSubmit={handleSubmit} className="space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            Preview Invoice
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Periksa rincian tagihan sebelum menerbitkan invoice.
                        </p>
                    </div>

                    <Button variant="outline" asChild>
                        <Link href="/invoice/create">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Kembali
                        </Link>
                    </Button>
                </div>

                <div className="rounded-xl border bg-card p-5 shadow-sm">
                    <div className="mb-4 flex items-center gap-3">
                        <div className="rounded-lg bg-primary/10 p-2">
                            <FileText className="h-5 w-5 text-primary" />
                        </div>
                        <div>
                            <h2 className="font-semibold">Informasi Purchase Order</h2>
                            <p className="text-sm text-muted-foreground">
                                Informasi PO yang akan ditagihkan.
                            </p>
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p className="text-sm text-muted-foreground">
                                Nomor Purchase Order
                            </p>
                            <p className="mt-1 font-semibold">
                                {purchaseOrder?.nomor_po || '-'}
                            </p>
                        </div>

                        <div>
                            <p className="text-sm text-muted-foreground">
                                Pembeli
                            </p>
                            <p className="mt-1 font-semibold">
                                {purchaseOrder?.tenant?.company_name || '-'}
                            </p>
                        </div>

                        <div>
                            <p className="text-sm text-muted-foreground">
                                Status Purchase Order
                            </p>
                            <p className="mt-1 font-semibold">
                                {purchaseOrder?.status?.label ||
                                    purchaseOrder?.status ||
                                    '-'}
                            </p>
                        </div>

                        <div>
                            <p className="text-sm text-muted-foreground">
                                Jumlah barang dapat ditagihkan
                            </p>
                            <p className="mt-1 font-semibold">
                                {items.length} jenis barang
                            </p>
                        </div>
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div className="flex items-center gap-3 border-b p-5">
                        <Package className="h-5 w-5 text-primary" />
                        <div>
                            <h2 className="font-semibold">Rincian Barang</h2>
                            <p className="text-sm text-muted-foreground">
                                Hanya barang yang lolos QC dan belum ditagihkan.
                            </p>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50">
                                <tr className="text-left">
                                    <th className="p-4">Barang</th>
                                    <th className="p-4 text-right">Lolos QC</th>
                                    <th className="p-4 text-right">Sudah Ditagihkan</th>
                                    <th className="p-4 text-right">Sisa Ditagihkan</th>
                                    <th className="p-4 text-right">Harga Satuan</th>
                                    <th className="p-4 text-right">Subtotal</th>
                                </tr>
                            </thead>

                            <tbody className="divide-y">
                                {items.map((item) => {
                                    const quantity = Number(
                                        item.jumlah_tersedia_ditagihkan || 0
                                    );
                                    const unitPrice = Number(
                                        item.harga_satuan_po || 0
                                    );

                                    return (
                                        <tr key={item.uuid}>
                                            <td className="p-4">
                                                <p className="font-medium">
                                                    {item.komoditas?.name || 'Barang'}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    Satuan: {item.komoditas?.unit || '-'}
                                                </p>
                                            </td>

                                            <td className="p-4 text-right">
                                                {formatNumber(item.jumlah_lolos_qc)}
                                            </td>

                                            <td className="p-4 text-right">
                                                {formatNumber(item.jumlah_sudah_ditagihkan)}
                                            </td>

                                            <td className="p-4 text-right font-medium">
                                                {formatNumber(quantity)}
                                            </td>

                                            <td className="p-4 text-right">
                                                {formatCurrency(unitPrice)}
                                            </td>

                                            <td className="p-4 text-right font-semibold">
                                                {formatCurrency(quantity * unitPrice)}
                                            </td>
                                        </tr>
                                    );
                                })}

                                {items.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan="6"
                                            className="p-8 text-center text-muted-foreground"
                                        >
                                            Tidak ada barang yang dapat ditagihkan.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="flex justify-end border-t bg-muted/30 p-5">
                        <div className="text-right">
                            <p className="text-sm text-muted-foreground">
                                Total Tagihan
                            </p>
                            <p className="mt-1 text-2xl font-bold">
                                {formatCurrency(preview?.total_amount)}
                            </p>
                        </div>
                    </div>
                </div>

                <div className="rounded-xl border bg-card p-5 shadow-sm">
                    <h2 className="mb-1 font-semibold">Data Invoice Vendor</h2>
                    <p className="mb-5 text-sm text-muted-foreground">
                        Lengkapi informasi invoice sebelum diterbitkan.
                    </p>

                    <div className="grid gap-5 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="nomor_invoice_vendor">
                                Nomor Invoice Vendor *
                            </Label>
                            <Input
                                id="nomor_invoice_vendor"
                                value={form.data.nomor_invoice_vendor}
                                onChange={(event) =>
                                    form.setData(
                                        'nomor_invoice_vendor',
                                        event.target.value
                                    )
                                }
                                placeholder="Contoh: INV/VENDOR/2026/001"
                                required
                            />
                            {form.errors.nomor_invoice_vendor && (
                                <p className="text-sm text-destructive">
                                    {form.errors.nomor_invoice_vendor}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="tanggal_invoice">
                                Tanggal Invoice *
                            </Label>
                            <Input
                                id="tanggal_invoice"
                                type="date"
                                value={form.data.tanggal_invoice}
                                onChange={(event) =>
                                    form.setData(
                                        'tanggal_invoice',
                                        event.target.value
                                    )
                                }
                                required
                            />
                            {form.errors.tanggal_invoice && (
                                <p className="text-sm text-destructive">
                                    {form.errors.tanggal_invoice}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2 sm:col-span-2">
                            <Label htmlFor="file_invoice">
                                Lampiran Invoice (PDF, maksimal 10 MB)
                            </Label>

                            <div className="flex flex-col gap-3 rounded-lg border border-dashed p-4 sm:flex-row sm:items-center">
                                <Upload className="h-6 w-6 text-muted-foreground" />
                                <Input
                                    id="file_invoice"
                                    type="file"
                                    accept=".pdf,application/pdf"
                                    onChange={(event) =>
                                        form.setData(
                                            'file_invoice',
                                            event.target.files?.[0] || null
                                        )
                                    }
                                />
                            </div>

                            {form.errors.file_invoice && (
                                <p className="text-sm text-destructive">
                                    {form.errors.file_invoice}
                                </p>
                            )}
                        </div>
                    </div>

                    {form.errors.purchase_order_uuid && (
                        <p className="mt-4 text-sm text-destructive">
                            {form.errors.purchase_order_uuid}
                        </p>
                    )}

                    <div className="mt-6 flex flex-col-reverse justify-end gap-3 border-t pt-5 sm:flex-row">
                        <Button variant="outline" asChild>
                            <Link href="/invoice/create">Batal</Link>
                        </Button>

                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                items.length === 0
                            }
                        >
                            {form.processing
                                ? 'Menerbitkan...'
                                : 'Konfirmasi & Terbitkan Invoice'}
                        </Button>
                    </div>
                </div>
            </form>
        </DashboardLayout>
    );
}
