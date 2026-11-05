import { useState, useEffect, useRef } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    FileText,
    Plus,
    Search,
    FilterX,
    Receipt,
    Clock,
    CircleCheck,
    CircleX,
    Eye,
} from 'lucide-react';

import DashboardLayout from '@/Layouts/DashboardLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { DataTablePagination } from '@/Components/data-table/data-table-pagination';

const formatRupiah = (value) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));

const matchingLabels = {
    pending: 'Menunggu Verifikasi',
    matched: 'Sesuai',
    mismatched: 'Tidak Sesuai',
};

const matchingStyles = {
    pending: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
    matched: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
    mismatched: 'bg-destructive/10 text-destructive',
};

export default function InvoiceIndex({
    invoices,
    filters: rawFilters = {},
}) {
    const filters = Array.isArray(rawFilters) ? {} : rawFilters;
    const data = invoices?.data ?? [];

    const meta = invoices?.meta ?? {
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: data.length,
        from: data.length ? 1 : 0,
        to: data.length,
    };

    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status_matching ?? 'all');
    const isInitialMount = useRef(true);

    const handleFilterChange = (params = {}) => {
        const query = {
            search: params.search ?? search,
            status_matching: params.status_matching ?? status,
            page: params.page ?? meta.current_page,
            per_page: meta.per_page,
        };

        if (!query.search) delete query.search;
        if (query.status_matching === 'all') delete query.status_matching;

        router.get('/invoice', query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['invoices', 'filters'],
        });
    };

    useEffect(() => {
        if (isInitialMount.current) {
            isInitialMount.current = false;
            return;
        }

        const timer = setTimeout(() => {
            handleFilterChange({ page: 1 });
        }, 400);

        return () => clearTimeout(timer);
    }, [search]);

    const handleReset = () => {
        setSearch('');
        setStatus('all');

        router.get('/invoice', {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['invoices', 'filters'],
        });
    };

    const handleStatusChange = (value) => {
        setStatus(value);
        handleFilterChange({
            status_matching: value,
            page: 1,
        });
    };

    const totalTagihan = data.reduce(
        (total, invoice) => total + Number(invoice.jumlah_tagihan ?? 0),
        0
    );

    const pendingCount = data.filter(
        (invoice) => invoice.status_matching === 'pending'
    ).length;

    const matchedCount = data.filter(
        (invoice) => invoice.status_matching === 'matched'
    ).length;

    const hasFilter = Boolean(search.trim()) || status !== 'all';

    const breadcrumbs = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Invoice Vendor', href: '/invoice' },
    ];

    return (
        <DashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Invoice Vendor - PAGORA" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight">
                            <Receipt className="size-6 text-primary" />
                            Invoice Vendor
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Kelola dan terbitkan tagihan berdasarkan barang yang telah lolos pemeriksaan QC.
                        </p>
                    </div>

                    <Button asChild className="gap-2 shadow-sm">
                        <Link href="/invoice/create">
                            <Plus className="size-4" />
                            Terbitkan Tagihan
                        </Link>
                    </Button>
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div className="rounded-xl border border-border/60 bg-card p-4 shadow-2xs">
                        <div className="flex items-center justify-between">
                            <p className="text-xs font-medium text-muted-foreground">
                                Total Invoice
                            </p>
                            <FileText className="size-5 text-primary" />
                        </div>
                        <p className="mt-3 text-2xl font-bold">
                            {meta.total ?? data.length}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Invoice diterbitkan
                        </p>
                    </div>

                    <div className="rounded-xl border border-border/60 bg-card p-4 shadow-2xs">
                        <div className="flex items-center justify-between">
                            <p className="text-xs font-medium text-muted-foreground">
                                Menunggu Verifikasi
                            </p>
                            <Clock className="size-5 text-amber-600" />
                        </div>
                        <p className="mt-3 text-2xl font-bold">{pendingCount}</p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Invoice berstatus pending pada halaman ini
                        </p>
                    </div>

                    <div className="rounded-xl border border-border/60 bg-card p-4 shadow-2xs">
                        <div className="flex items-center justify-between">
                            <p className="text-xs font-medium text-muted-foreground">
                                Nilai Tagihan Halaman Ini
                            </p>
                            <Receipt className="size-5 text-emerald-600" />
                        </div>
                        <p className="mt-3 truncate text-xl font-bold">
                            {formatRupiah(totalTagihan)}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {matchedCount} invoice sudah sesuai
                        </p>
                    </div>
                </div>

                <div className="flex flex-col gap-3 rounded-lg border bg-card/60 p-4 backdrop-blur-xs lg:flex-row lg:items-center lg:justify-between">
                    <div className="flex flex-1 flex-col gap-3 sm:flex-row">
                        <div className="relative w-full sm:max-w-sm">
                            <Search className="pointer-events-none absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                            <Input
                                placeholder="Cari nomor invoice atau nomor PO..."
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                className="h-9 pl-9"
                            />
                        </div>

                        <div className="w-full sm:w-56">
                            <Select
                                value={status}
                                onValueChange={handleStatusChange}
                            >
                                <SelectTrigger className="h-9 bg-background">
                                    <SelectValue placeholder="Semua Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Semua Status</SelectItem>
                                    <SelectItem value="pending">Menunggu Verifikasi</SelectItem>
                                    <SelectItem value="matched">Sesuai</SelectItem>
                                    <SelectItem value="mismatched">Tidak Sesuai</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    {hasFilter && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={handleReset}
                            className="gap-1 text-muted-foreground"
                        >
                            <FilterX className="size-4" />
                            Reset Filter
                        </Button>
                    )}
                </div>

                <div className="overflow-hidden rounded-xl border bg-card">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/40">
                                <tr>
                                    <th className="px-4 py-3 text-left font-semibold">Nomor Invoice</th>
                                    <th className="px-4 py-3 text-left font-semibold">Nomor PO</th>
                                    <th className="px-4 py-3 text-left font-semibold">Tanggal</th>
                                    <th className="px-4 py-3 text-right font-semibold">Total Tagihan</th>
                                    <th className="px-4 py-3 text-center font-semibold">Status Matching</th>
                                    <th className="px-4 py-3 text-center font-semibold">Aksi</th>
                                </tr>
                            </thead>

                            <tbody className="divide-y">
                                {data.length > 0 ? (
                                    data.map((invoice) => (
                                        <tr key={invoice.uuid} className="transition-colors hover:bg-muted/30">
                                            <td className="whitespace-nowrap px-4 py-3 font-medium">
                                                {invoice.nomor_invoice_vendor}
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-3">
                                                {invoice.purchase_order?.nomor_po ?? '-'}
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-3">
                                                {invoice.tanggal_invoice ?? '-'}
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-3 text-right font-medium">
                                                {formatRupiah(invoice.jumlah_tagihan)}
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <span className={`inline-flex rounded-md px-2 py-1 text-xs font-medium ${matchingStyles[invoice.status_matching] ?? 'bg-muted text-muted-foreground'}`}>
                                                    {matchingLabels[invoice.status_matching] ?? invoice.status_matching ?? '-'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <Button asChild variant="outline" size="sm" className="gap-1">
                                                    <Link href={`/invoice/${invoice.uuid}`}>
                                                        <Eye className="size-4" />
                                                        Detail
                                                    </Link>
                                                </Button>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-12 text-center">
                                            <div className="flex flex-col items-center gap-2 text-muted-foreground">
                                                <FileText className="size-9 opacity-40" />
                                                <p className="font-medium">Belum ada invoice</p>
                                                <p className="text-xs">
                                                    Invoice yang sudah diterbitkan akan tampil di sini.
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                <DataTablePagination
                    meta={meta}
                    onPageChange={(page) => handleFilterChange({ page })}
                    onPerPageChange={(perPage) => {
                        router.get('/invoice', {
                            search,
                            status_matching: status === 'all' ? undefined : status,
                            per_page: perPage,
                            page: 1,
                        }, {
                            preserveState: true,
                            preserveScroll: true,
                            replace: true,
                            only: ['invoices', 'filters'],
                        });
                    }}
                />
            </div>
        </DashboardLayout>
    );
}
