import { useEffect, useMemo, useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { getCoreRowModel, useReactTable } from '@tanstack/react-table';
import {
    AlertTriangle,
    Calendar,
    CheckCircle2,
    ClipboardCheck,
    FilterX,
    Plus,
    RefreshCw,
    Search,
    Truck,
} from 'lucide-react';
import { formatDate } from '@/lib/format';
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
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
                            <ClipboardCheck className="size-6 text-primary" />
                            Pengecekan &amp; Inspeksi Barang (QC Gudang)
                        </h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            {can.create
                                ? 'Pilih pesanan berstatus Dalam Pengiriman (In Transit) untuk membuat form pengecekan fisik barang per item di dermaga gudang.'
                                : isVendor
                                  ? 'Pantau hasil pemeriksaan fisik penerimaan barang oleh QC Gudang pada setiap pesanan Anda.'
                                  : 'Pantau riwayat hasil inspeksi penerimaan fisik barang dan bukti ketidaksesuaian QC Gudang.'}
                        </p>
                    </div>
                    {can.create && (
                        <Button asChild className="gap-2 cursor-pointer shadow-sm min-h-10">
                            <Link href="/inspeksi-qc/create">
                                <Plus className="size-4" />
                                Buat Form Pengecekan Barang
                            </Link>
                        </Button>
                    )}
                </div>

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

                {/* Antrean Pesanan Berstatus "In Transit" untuk QC Gudang / Owner */}
                {can.create && inspectable_orders.length > 0 && (
                    <Card className="border-sky-500/30 bg-sky-500/5 shadow-2xs">
                        <CardHeader className="pb-3">
                            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                <div>
                                    <CardTitle className="text-base font-semibold flex items-center gap-2">
                                        <Truck className="size-4 text-sky-600 dark:text-sky-400" />
                                        Daftar Pesanan Dalam Pengiriman (In Transit) — Siap Diinspeksi
                                    </CardTitle>
                                    <CardDescription className="text-xs mt-0.5">
                                        Pilih salah satu pesanan di bawah ini untuk memuat detail pesanan dan membuat Form Pengecekan Barang.
                                    </CardDescription>
                                </div>
                                <Badge variant="sky" className="w-fit">
                                    {inspectable_orders.length} Pesanan Menunggu QC
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent className="grid grid-cols-1 md:grid-cols-2 gap-3 pt-0">
                            {inspectable_orders.map((order) => (
                                <div
                                    key={order.uuid}
                                    className="flex flex-col justify-between gap-3 rounded-xl border bg-card p-4 shadow-2xs"
                                >
                                    <div className="space-y-2">
                                        <div className="flex items-start justify-between gap-2">
                                            <div>
                                                <div className="flex items-center gap-2 flex-wrap">
                                                    <span className="font-mono text-sm font-bold text-foreground">
                                                        {order.po_number}
                                                    </span>
                                                    <Badge variant="sky" className="text-[11px]">
                                                        In Transit
                                                    </Badge>
                                                    {order.shipment?.is_reshipment && (
                                                        <Badge variant="warning" className="gap-1 text-[10px]">
                                                            <RefreshCw className="size-2.5" />
                                                            Pengiriman Ulang
                                                        </Badge>
                                                    )}
                                                </div>
                                                <p className="text-xs font-medium text-muted-foreground mt-0.5">
                                                    Vendor: {order.vendor_name}
                                                </p>
                                            </div>
                                            <span className="text-xs font-semibold text-foreground shrink-0">
                                                {order.items_count} Item
                                            </span>
                                        </div>

                                        {order.shipment && (
                                            <div className="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg border bg-muted/30 px-3 py-2 text-xs text-muted-foreground">
                                                <span>
                                                    Jalur: <strong className="text-foreground">{order.shipment.route}</strong>
                                                </span>
                                                <span>·</span>
                                                <span>
                                                    Armada: <strong className="text-foreground">{order.shipment.carrier_name}</strong>
                                                </span>
                                                <span>·</span>
                                                <span className="font-mono font-semibold text-foreground">
                                                    {order.shipment.vehicle_plate_number}
                                                </span>
                                                <span className="inline-flex items-center gap-1 ml-auto tabular-nums">
                                                    <Calendar className="size-3" />
                                                    ETA {formatDate(order.shipment.estimated_arrival_date)}
                                                </span>
                                            </div>
                                        )}
                                    </div>

                                    <div className="flex items-center justify-end pt-1">
                                        <Button asChild size="sm" className="w-full sm:w-auto min-h-10 gap-1.5 cursor-pointer">
                                            <Link href={`/inspeksi-qc/create?po=${order.uuid}`}>
                                                <ClipboardCheck className="size-4" />
                                                Pilih &amp; Buat Form Pengecekan
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            ))}
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

