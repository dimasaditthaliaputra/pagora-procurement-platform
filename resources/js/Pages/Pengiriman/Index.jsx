import { useEffect, useMemo, useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { getCoreRowModel, useReactTable } from '@tanstack/react-table';
import {
    CheckCircle2,
    Clock,
    FilterX,
    PackageCheck,
    Plus,
    Search,
    Truck,
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
import { getColumns } from '@/Pages/Pengiriman/columns';

export default function PengirimanIndex({
    shipments,
    statistics = {},
    filters: rawFilters = {},
    status_options = [],
    route_options = [],
    current_role = 'vendor',
    can = {},
}) {
    const filters = Array.isArray(rawFilters) || !rawFilters ? {} : rawFilters;
    const data = shipments?.data ?? [];
    const meta = shipments?.meta ?? {
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: data.length,
        from: data.length ? 1 : 0,
        to: data.length,
    };

    const isVendor = current_role === 'vendor';

    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? 'all');
    const [routeFilter, setRouteFilter] = useState(filters.route ?? 'all');
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
            status: newParams.status !== undefined ? newParams.status : status,
            route: newParams.route !== undefined ? newParams.route : routeFilter,
            sort: newParams.sort !== undefined ? newParams.sort : (sorting[0]?.id ?? undefined),
            direction: newParams.direction !== undefined ? newParams.direction : (sorting[0]?.desc ? 'desc' : 'asc'),
            page: newParams.page !== undefined ? newParams.page : meta.current_page,
            per_page: newParams.per_page !== undefined ? newParams.per_page : meta.per_page,
        };

        if (query.status === 'all') delete query.status;
        if (query.route === 'all') delete query.route;
        if (!query.search) delete query.search;
        if (!query.sort) {
            delete query.sort;
            delete query.direction;
        }

        router.get('/pengiriman', query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['shipments', 'filters', 'statistics'],
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

    const handleStatusChange = (newStatus) => {
        const target = newStatus || 'all';
        setStatus(target);
        handleFilterChange({ status: target, page: 1 });
    };

    const handleRouteChange = (newRoute) => {
        const target = newRoute || 'all';
        setRouteFilter(target);
        handleFilterChange({ route: target, page: 1 });
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
        setStatus('all');
        setRouteFilter('all');
        setSorting([]);
        router.get('/pengiriman', {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['shipments', 'filters', 'statistics'],
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
        total_shipments: statistics?.total_shipments ?? 0,
        in_transit: statistics?.in_transit ?? 0,
        arrived: statistics?.arrived ?? 0,
        ready_to_ship: statistics?.ready_to_ship ?? 0,
    }), [statistics]);

    const hasFilter =
        (search && search.trim() !== '') ||
        (status && status !== 'all') ||
        (routeFilter && routeFilter !== 'all');

    const statusFilterOptions = [
        { value: 'all', label: 'Semua Status' },
        ...status_options.map((s) => ({ value: s.value, label: s.label })),
    ];

    const routeFilterOptions = [
        { value: 'all', label: 'Semua Jalur' },
        ...route_options.map((r) => ({ value: r.value, label: r.label })),
    ];

    const breadcrumbs = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Pengiriman', href: '/pengiriman' },
    ];

    return (
        <DashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Pengiriman Logistik - PAGORA" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
                            <Truck className="size-6 text-primary" />
                            Pengiriman Logistik
                        </h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            {isVendor
                                ? 'Laporkan status logistik fisik armada pengiriman untuk Purchase Order yang telah disepakati.'
                                : 'Pantau status pengiriman fisik, informasi armada, dan estimasi kedatangan dari vendor rekanan.'}
                        </p>
                    </div>
                    {can.create && (
                        <Button asChild className="gap-2 cursor-pointer shadow-sm">
                            <Link href="/pengiriman/create">
                                <Plus className="size-4" />
                                Laporkan Pengiriman
                            </Link>
                        </Button>
                    )}
                </div>

                {/* 4 Kartu Metrik Ringkasan Logistik */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="flex flex-col justify-between rounded-xl border border-border/60 bg-card p-4 shadow-2xs">
                        <div className="flex items-start justify-between gap-2">
                            <p className="text-xs font-medium text-muted-foreground">Total Laporan Pengiriman</p>
                            <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <Truck className="size-4" />
                            </div>
                        </div>
                        <div className="mt-3">
                            <p className="text-2xl font-bold tracking-tight text-foreground tabular-nums">
                                {stats.total_shipments}{' '}
                                <span className="text-sm font-normal text-muted-foreground">Pengiriman</span>
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Riwayat pengiriman tercatat
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-col justify-between rounded-xl border border-border/60 bg-card p-4 shadow-2xs">
                        <div className="flex items-start justify-between gap-2">
                            <p className="text-xs font-medium text-muted-foreground">Dalam Pengiriman (In Transit)</p>
                            <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-sky-500/10 text-sky-600 dark:bg-sky-950/50 dark:text-sky-400">
                                <Clock className="size-4" />
                            </div>
                        </div>
                        <div className="mt-3">
                            <p className="text-2xl font-bold tracking-tight text-foreground tabular-nums">
                                {stats.in_transit}{' '}
                                <span className="text-sm font-normal text-muted-foreground">Armada</span>
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Sedang menuju gudang tujuan
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-col justify-between rounded-xl border border-border/60 bg-card p-4 shadow-2xs">
                        <div className="flex items-start justify-between gap-2">
                            <p className="text-xs font-medium text-muted-foreground">Tiba di Tujuan</p>
                            <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400">
                                <CheckCircle2 className="size-4" />
                            </div>
                        </div>
                        <div className="mt-3">
                            <p className="text-2xl font-bold tracking-tight text-foreground tabular-nums">
                                {stats.arrived}{' '}
                                <span className="text-sm font-normal text-muted-foreground">Pengiriman</span>
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Telah tiba di gudang buyer
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-col justify-between rounded-xl border border-border/60 bg-card p-4 shadow-2xs">
                        <div className="flex items-start justify-between gap-2">
                            <div className="flex items-center gap-1.5 flex-wrap">
                                <p className="text-xs font-medium text-muted-foreground">PO Siap Dikirim</p>
                                {isVendor && stats.ready_to_ship > 0 && (
                                    <span className="inline-flex items-center rounded-md bg-amber-500/10 px-1.5 py-0.5 text-[10px] font-medium text-amber-700 dark:text-amber-400">
                                        Perlu Dikirim
                                    </span>
                                )}
                            </div>
                            <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400">
                                <PackageCheck className="size-4" />
                            </div>
                        </div>
                        <div className="mt-3">
                            <p className="text-2xl font-bold tracking-tight text-foreground tabular-nums">
                                {stats.ready_to_ship}{' '}
                                <span className="text-sm font-normal text-muted-foreground">PO</span>
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Status Disepakati / Kirim Ulang
                            </p>
                        </div>
                    </div>
                </div>

                {/* Filter Bar */}
                <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-3 p-4 rounded-lg border bg-card/60 backdrop-blur-xs">
                    <div className="flex flex-1 flex-col sm:flex-row items-center gap-3">
                        <div className="relative w-full sm:w-72">
                            <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground pointer-events-none" />
                            <Input
                                placeholder="Cari nomor PO, armada, plat nomor..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="pl-9 h-9"
                            />
                        </div>
                        <div className="w-full sm:w-48">
                            <Select value={status} onValueChange={handleStatusChange}>
                                <SelectTrigger className="h-9 text-sm w-full bg-background">
                                    <SelectValue placeholder="Semua Status" />
                                </SelectTrigger>
                                <SelectContent position="popper" side="bottom" align="start" avoidCollisions={false}>
                                    {statusFilterOptions.map((opt) => (
                                        <SelectItem key={opt.value} value={opt.value}>
                                            {opt.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="w-full sm:w-44">
                            <Select value={routeFilter} onValueChange={handleRouteChange}>
                                <SelectTrigger className="h-9 text-sm w-full bg-background">
                                    <SelectValue placeholder="Semua Jalur" />
                                </SelectTrigger>
                                <SelectContent position="popper" side="bottom" align="start" avoidCollisions={false}>
                                    {routeFilterOptions.map((opt) => (
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
                        emptyMessage="Belum ada laporan pengiriman logistik yang tercatat."
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

