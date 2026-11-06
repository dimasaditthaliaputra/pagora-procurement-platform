import { useState, useEffect, useRef } from 'react';
import { Head, router } from '@inertiajs/react';
import { useReactTable, getCoreRowModel } from '@tanstack/react-table';
import {
    Clock,
    FileCheck2,
    FilterX,
    Receipt,
    Search,
    ShieldAlert,
    ShieldCheck,
} from 'lucide-react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { DataTable } from '@/Components/data-table/data-table';
import { DataTablePagination } from '@/Components/data-table/data-table-pagination';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { Card, CardContent } from '@/Components/ui/card';
import { getColumns } from './columns';

export default function VerifikasiTagihanIndex({
    invoices,
    counts = {},
    filters = {},
}) {
    const data = invoices?.data ?? [];
    const meta = invoices?.meta ?? {
        current_page: invoices?.current_page ?? 1,
        last_page: invoices?.last_page ?? 1,
        per_page: invoices?.per_page ?? 10,
        total: invoices?.total ?? data.length,
        from: invoices?.from ?? 1,
        to: invoices?.to ?? data.length,
    };

    const [search, setSearch] = useState(filters.search ?? '');
    const [statusMatching, setStatusMatching] = useState(filters.status_matching ?? 'all');
    const [loading, setLoading] = useState(false);

    const isFirstRender = useRef(true);

    useEffect(() => {
        setSearch(filters.search ?? '');
        setStatusMatching(filters.status_matching ?? 'all');
    }, [filters.search, filters.status_matching]);

    const applyFilters = (newFilters = {}) => {
        const merged = {
            search: search.trim() || undefined,
            status_matching: statusMatching !== 'all' ? statusMatching : undefined,
            per_page: meta.per_page,
            page: 1,
            ...newFilters,
        };

        const cleanParams = {};
        Object.entries(merged).forEach(([key, val]) => {
            if (val !== undefined && val !== '') {
                cleanParams[key] = val;
            }
        });

        router.get('/verifikasi-tagihan', cleanParams, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['invoices', 'counts', 'filters'],
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    };

    // Debounce search
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }

        const timer = setTimeout(() => {
            if (search !== (filters.search ?? '')) {
                applyFilters({ search: search.trim() || undefined });
            }
        }, 400);

        return () => clearTimeout(timer);
    }, [search]);

    const handleStatusChange = (val) => {
        setStatusMatching(val);
        applyFilters({ status_matching: val !== 'all' ? val : undefined });
    };

    const handleResetFilters = () => {
        setSearch('');
        setStatusMatching('all');
        router.get('/verifikasi-tagihan', {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['invoices', 'counts', 'filters'],
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    };

    const handlePageChange = (page) => {
        router.get(
            '/verifikasi-tagihan',
            {
                search: search.trim() || undefined,
                status_matching: statusMatching !== 'all' ? statusMatching : undefined,
                per_page: meta.per_page,
                page,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['invoices', 'counts', 'filters'],
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            }
        );
    };

    const handlePerPageChange = (per_page) => {
        router.get(
            '/verifikasi-tagihan',
            {
                search: search.trim() || undefined,
                status_matching: statusMatching !== 'all' ? statusMatching : undefined,
                per_page,
                page: 1,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['invoices', 'counts', 'filters'],
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            }
        );
    };

    const columns = getColumns();

    const table = useReactTable({
        data,
        columns,
        getCoreRowModel: getCoreRowModel(),
        manualPagination: true,
        pageCount: meta.last_page,
        getRowId: (row) => row.uuid,
        state: {
            pagination: {
                pageIndex: meta.current_page - 1,
                pageSize: meta.per_page,
            },
        },
    });

    const hasActiveFilters = (filters.search ?? '') !== '' || (filters.status_matching ?? 'all') !== 'all';

    const breadcrumbs = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Verifikasi Tagihan' },
    ];

    return (
        <DashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Verifikasi Tagihan (3-Way Matching) - PAGORA" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <Receipt className="size-6 text-emerald-600 dark:text-emerald-400" />
                            <h1 className="text-2xl font-bold tracking-tight text-foreground">
                                Verifikasi Kelayakan Tagihan (3-Way Matching)
                            </h1>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Pencocokan otomatis antara Purchase Order, bukti penerimaan gudang (QC), dan invoice tagihan vendor.
                        </p>
                    </div>
                </div>

                {/* Stat Cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card className="border-border shadow-xs">
                        <CardContent className="flex items-center gap-4 p-4">
                            <div className="flex size-10 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                                <FileCheck2 className="size-5 text-zinc-700 dark:text-zinc-300" />
                            </div>
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">
                                    Total Tagihan Masuk
                                </p>
                                <p className="text-2xl font-bold text-foreground">
                                    {counts.total ?? meta.total ?? 0}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-amber-200 bg-amber-50/50 dark:border-amber-900/50 dark:bg-amber-950/20 shadow-xs">
                        <CardContent className="flex items-center gap-4 p-4">
                            <div className="flex size-10 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/40">
                                <Clock className="size-5 text-amber-600 dark:text-amber-400" />
                            </div>
                            <div>
                                <p className="text-xs font-medium text-amber-800 dark:text-amber-300">
                                    Menunggu Verifikasi
                                </p>
                                <p className="text-2xl font-bold text-amber-900 dark:text-amber-200">
                                    {counts.pending ?? 0}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-emerald-200 bg-emerald-50/50 dark:border-emerald-900/50 dark:bg-emerald-950/20 shadow-xs">
                        <CardContent className="flex items-center gap-4 p-4">
                            <div className="flex size-10 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/40">
                                <ShieldCheck className="size-5 text-emerald-600 dark:text-emerald-400" />
                            </div>
                            <div>
                                <p className="text-xs font-medium text-emerald-800 dark:text-emerald-300">
                                    Sesuai (Cocok)
                                </p>
                                <p className="text-2xl font-bold text-emerald-900 dark:text-emerald-200">
                                    {counts.matched ?? 0}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-rose-200 bg-rose-50/50 dark:border-rose-900/50 dark:bg-rose-950/20 shadow-xs">
                        <CardContent className="flex items-center gap-4 p-4">
                            <div className="flex size-10 items-center justify-center rounded-lg bg-rose-100 dark:bg-rose-900/40">
                                <ShieldAlert className="size-5 text-rose-600 dark:text-rose-400" />
                            </div>
                            <div>
                                <p className="text-xs font-medium text-rose-800 dark:text-rose-300">
                                    Tidak Sesuai (Sengketa)
                                </p>
                                <p className="text-2xl font-bold text-rose-900 dark:text-rose-200">
                                    {counts.mismatched ?? 0}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Search & Filter */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground pointer-events-none" />
                        <Input
                            placeholder="Cari nomor invoice, nomor PO, atau nama vendor..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="pl-9"
                        />
                    </div>

                    <div className="w-full sm:w-56">
                        <Select
                            value={statusMatching}
                            onValueChange={handleStatusChange}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Status Pencocokan" />
                            </SelectTrigger>
                            <SelectContent side="bottom">
                                <SelectItem value="all">Semua Status</SelectItem>
                                <SelectItem value="pending">Menunggu Verifikasi</SelectItem>
                                <SelectItem value="matched">Sesuai (Cocok)</SelectItem>
                                <SelectItem value="mismatched">Tidak Sesuai</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {hasActiveFilters && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleResetFilters}
                            className="shrink-0"
                        >
                            <FilterX className="mr-1.5 size-4" />
                            Reset Filter
                        </Button>
                    )}
                </div>

                {/* Table */}
                <div className="rounded-xl border bg-card shadow-xs overflow-hidden">
                    <DataTable
                        table={table}
                        loading={loading}
                        emptyMessage={
                            hasActiveFilters
                                ? 'Tidak ada tagihan yang sesuai dengan kriteria filter.'
                                : 'Belum ada tagihan invoice yang masuk dari vendor rekanan.'
                        }
                    />
                    <div className="border-t px-4 py-3">
                        <DataTablePagination
                            meta={meta}
                            onPageChange={handlePageChange}
                            onPerPageChange={handlePerPageChange}
                        />
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}

