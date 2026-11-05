import { useEffect, useMemo, useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { getCoreRowModel, useReactTable } from '@tanstack/react-table';
import {
    AlertTriangle,
    ArrowRight,
    CheckCircle2,
    ClipboardCheck,
    FilterX,
    Search,
    Truck,
} from 'lucide-react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { DataTable } from '@/Components/data-table/data-table';
import { DataTablePagination } from '@/Components/data-table/data-table-pagination';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { InspectableOrderList } from '@/Pages/InspeksiQc/components/InspectableOrderCard';
import { InspeksiQcNavTabs } from '@/Pages/InspeksiQc/components/InspeksiQcNavTabs';
import { getColumns } from '@/Pages/InspeksiQc/columns';

const RESULT_FILTER_OPTIONS = [
    { value: 'all', label: 'Semua Hasil Inspeksi' },
    { value: 'accepted', label: 'Disetujui Lengkap (Sesuai)' },
    { value: 'rejected', label: 'Terdapat Penolakan (Sengketa QC)' },
];

export default function InspeksiQcIndex({
    receipts,
    inspectable_orders = [],
    statistics = {},
    filters: rawFilters = {},
    current_role = 'qc_gudang',
    can = {},
}) {
    const filters = Array.isArray(rawFilters) || !rawFilters ? {} : rawFilters;
    const data = receipts?.data ?? [];
    const meta = receipts?.meta ?? {
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: data.length,
        from: data.length ? 1 : 0,
        to: data.length,
    };

    const isVendor = current_role === 'vendor';

    const [search, setSearch] = useState(filters.search ?? '');
    const [resultFilter, setResultFilter] = useState(filters.result ?? 'all');
    const [sorting, setSorting] = useState(() => {
        if (filters.sort) {
            return [{ id: filters.sort, desc: filters.direction === 'desc' }];
        }
        return [];
    });

    const isInitialMount = useRef(true);

    const handleFilterChange = (newParams) => {
        const query = {
            search: newParams.search !== undefined ? newParams.search : search,
            result: newParams.result !== undefined ? newParams.result : resultFilter,
            sort: newParams.sort !== undefined ? newParams.sort : (sorting[0]?.id ?? undefined),
            direction: newParams.direction !== undefined ? newParams.direction : (sorting[0]?.desc ? 'desc' : 'asc'),
            page: newParams.page !== undefined ? newParams.page : meta.current_page,
            per_page: newParams.per_page !== undefined ? newParams.per_page : meta.per_page,
        };

        if (query.result === 'all') delete query.result;
        if (!query.search) delete query.search;
        if (!query.sort) {
            delete query.sort;
            delete query.direction;
        }

        router.get('/inspeksi-qc', query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['receipts', 'inspectable_orders', 'filters', 'statistics'],
        });
    };

    useEffect(() => {
        if (isInitialMount.current) {
            isInitialMount.current = false;
            return;
        }

        const timer = setTimeout(() => {
            handleFilterChange({ search, page: 1 });
        }, 400);

        return () => clearTimeout(timer);
    }, [search]);

    const handleResultFilterChange = (newResult) => {
        const target = newResult || 'all';
        setResultFilter(target);
        handleFilterChange({ result: target, page: 1 });
    };

    const handleSortChange = (columnId, isDesc) => {
        setSorting([{ id: columnId, desc: isDesc }]);
        handleFilterChange({ sort: columnId, direction: isDesc ? 'desc' : 'asc', page: 1 });
    };

    const handlePageChange = (newPage) => {
        handleFilterChange({ page: newPage });
    };

    const handlePerPageChange = (newPerPage) => {
        handleFilterChange({ per_page: newPerPage, page: 1 });
    };

    const handleResetFilter = () => {
        setSearch('');
        setResultFilter('all');
        setSorting([]);
        router.get('/inspeksi-qc', {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['receipts', 'inspectable_orders', 'filters', 'statistics'],
        });
    };

    const columns = useMemo(
        () => getColumns({ onSortChange: handleSortChange, isVendor }),
        [isVendor, sorting]
    );

    const table = useReactTable({
        data,
        columns,
        getRowId: (row) => row.uuid,
        getCoreRowModel: getCoreRowModel(),
        manualPagination: true,
        manualSorting: true,
        manualFiltering: true,
        pageCount: meta.last_page,
    });

    const stats = useMemo(() => ({
        in_transit_orders: statistics?.in_transit_orders ?? 0,
        total_inspections: statistics?.total_inspections ?? 0,
        fully_accepted: statistics?.fully_accepted ?? 0,
        with_rejections: statistics?.with_rejections ?? 0,
        rejected_items_count: statistics?.rejected_items_count ?? 0,
    }), [statistics]);

    const hasFilter =
        (search && search.trim() !== '') ||
        (resultFilter && resultFilter !== 'all');

    const breadcrumbs = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Inspeksi QC', href: '/inspeksi-qc' },
    ];

    return (
        <DashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Pengecekan & Inspeksi Barang - PAGORA" />

            <div className="flex flex-col gap-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
                        <ClipboardCheck className="size-6 text-primary shrink-0" />
                        Pengecekan &amp; Inspeksi Barang (QC Gudang)
                    </h1>
                    <p className="text-sm text-muted-foreground mt-1 max-w-3xl">
                        {can.create
                            ? 'Pilih pesanan berstatus Dalam Pengiriman (In Transit) untuk membuat form pengecekan fisik barang per item di dermaga gudang.'
                            : isVendor
                              ? 'Pantau hasil pemeriksaan fisik penerimaan barang oleh QC Gudang pada setiap pesanan Anda.'
                              : 'Pantau riwayat hasil inspeksi penerimaan fisik barang dan bukti ketidaksesuaian QC Gudang.'}
                    </p>
                </div>

                {/* Navigasi Sub-Fitur Inspeksi QC */}
                <InspeksiQcNavTabs
                    activeTab="inspections"
                    damageCount={stats.rejected_items_count}
                />

                {/* 4 Kartu Metrik Ringkasan Inspeksi QC */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="flex flex-col justify-between rounded-xl border border-border/60 bg-card p-4 shadow-2xs">
                        <div className="flex items-start justify-between gap-2">
                            <div className="flex items-center gap-1.5 flex-wrap">
                                <p className="text-xs font-medium text-muted-foreground">
                                    Pesanan In Transit (Siap Dicek)
                                </p>
                                {can.create && stats.in_transit_orders > 0 && (
                                    <span className="inline-flex items-center rounded-md bg-sky-500/10 px-1.5 py-0.5 text-[10px] font-medium text-sky-700 dark:text-sky-400">
                                        Perlu Dicek
                                    </span>
                                )}
                            </div>
                            <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-sky-500/10 text-sky-600 dark:bg-sky-950/50 dark:text-sky-400">
                                <Truck className="size-4" />
                            </div>
                        </div>
                        <div className="mt-3">
                            <p className="text-2xl font-bold tracking-tight text-foreground tabular-nums">
                                {stats.in_transit_orders}{' '}
                                <span className="text-sm font-normal text-muted-foreground">Pesanan</span>
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Status Dalam Pengiriman menuju gudang
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-col justify-between rounded-xl border border-border/60 bg-card p-4 shadow-2xs">
                        <div className="flex items-start justify-between gap-2">
                            <p className="text-xs font-medium text-muted-foreground">Total Hasil Inspeksi</p>
                            <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <ClipboardCheck className="size-4" />
                            </div>
                        </div>
                        <div className="mt-3">
                            <p className="text-2xl font-bold tracking-tight text-foreground tabular-nums">
                                {stats.total_inspections}{' '}
                                <span className="text-sm font-normal text-muted-foreground">Pemeriksaan</span>
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Riwayat penerimaan barang tercatat
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-col justify-between rounded-xl border border-border/60 bg-card p-4 shadow-2xs">
                        <div className="flex items-start justify-between gap-2">
                            <p className="text-xs font-medium text-muted-foreground">Disetujui Lengkap (Sesuai)</p>
                            <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400">
                                <CheckCircle2 className="size-4" />
                            </div>
                        </div>
                        <div className="mt-3">
                            <p className="text-2xl font-bold tracking-tight text-foreground tabular-nums">
                                {stats.fully_accepted}{' '}
                                <span className="text-sm font-normal text-muted-foreground">Pemeriksaan</span>
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Seluruh item diterima sesuai PO
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-col justify-between rounded-xl border border-border/60 bg-card p-4 shadow-2xs">
                        <div className="flex items-start justify-between gap-2">
                            <p className="text-xs font-medium text-muted-foreground">Terdapat Penolakan QC</p>
                            <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-destructive/10 text-destructive">
                                <AlertTriangle className="size-4" />
                            </div>
                        </div>
                        <div className="mt-3">
                            <p className="text-2xl font-bold tracking-tight text-foreground tabular-nums">
                                {stats.with_rejections}{' '}
                                <span className="text-sm font-normal text-muted-foreground">Pemeriksaan</span>
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Tercatat otomatis di Log Sengketa
                            </p>
                        </div>
                    </div>
                </div>

                {/* Antrean Ringkasan Pesanan Berstatus "In Transit" (Maks. 4 Terbaru) */}
                {can.create && inspectable_orders.length > 0 && (
                    <Card className="border-sky-500/30 bg-sky-500/5 shadow-2xs">
                        <CardHeader className="pb-3">
                            <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                                <div>
                                    <CardTitle className="text-base font-semibold flex items-center gap-2">
                                        <Truck className="size-4 text-sky-600 dark:text-sky-400 shrink-0" />
                                        Daftar Pesanan Dalam Pengiriman (In Transit) — Siap Diinspeksi
                                    </CardTitle>
                                    <CardDescription className="text-xs mt-0.5">
                                        {stats.in_transit_orders > inspectable_orders.length
                                            ? `Menampilkan ${inspectable_orders.length} pesanan terbaru dari total ${stats.in_transit_orders} pesanan In Transit. Pilih pesanan di bawah atau klik Lihat Semua.`
                                            : 'Pilih salah satu pesanan di bawah ini untuk memuat detail pesanan dan membuat Form Pengecekan Barang.'}
                                    </CardDescription>
                                </div>

                                <div className="flex flex-wrap items-center gap-2 shrink-0">
                                    <Badge variant="sky" className="w-fit tabular-nums">
                                        {stats.in_transit_orders || inspectable_orders.length} Pesanan Menunggu QC
                                    </Badge>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        asChild
                                        className="h-8 gap-1.5 cursor-pointer bg-background/90 border-sky-500/35 text-sky-700 hover:bg-sky-500/10 hover:text-sky-800 dark:text-sky-400 dark:hover:text-sky-300"
                                    >
                                        <Link href="/inspeksi-qc/pesanan-in-transit">
                                            Lihat Semua
                                            {stats.in_transit_orders > inspectable_orders.length &&
                                                ` (${stats.in_transit_orders})`}
                                            <ArrowRight className="size-3.5" />
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="pt-0">
                            <InspectableOrderList orders={inspectable_orders} />
                        </CardContent>
                    </Card>
                )}

                {/* Filter Bar Riwayat Inspeksi */}
                <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-3 p-4 rounded-lg border bg-card/60 backdrop-blur-xs">
                    <div className="flex flex-1 flex-col sm:flex-row items-center gap-3">
                        <div className="relative w-full sm:w-80">
                            <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground pointer-events-none" />
                            <Input
                                placeholder="Cari nomor PO, vendor, plat nomor, catatan..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="pl-9 h-9"
                            />
                        </div>
                        <div className="w-full sm:w-64">
                            <Select value={resultFilter} onValueChange={handleResultFilterChange}>
                                <SelectTrigger className="h-9 text-sm w-full bg-background">
                                    <SelectValue placeholder="Semua Hasil Inspeksi" />
                                </SelectTrigger>
                                <SelectContent position="popper" side="bottom" align="start" avoidCollisions={false}>
                                    {RESULT_FILTER_OPTIONS.map((opt) => (
                                        <SelectItem key={opt.value} value={opt.value}>
                                            {opt.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    {hasFilter && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={handleResetFilter}
                            className="h-9 gap-1 text-muted-foreground hover:text-foreground cursor-pointer"
                        >
                            <FilterX className="size-4" />
                            Reset Filter
                        </Button>
                    )}
                </div>

                <div className="space-y-4">
                    <DataTable
                        table={table}
                        emptyMessage="Belum ada riwayat hasil inspeksi penerimaan barang."
                    />
                    <DataTablePagination
                        meta={meta}
                        onPageChange={handlePageChange}
                        onPerPageChange={handlePerPageChange}
                    />
                </div>
            </div>
        </DashboardLayout>
    );
}

