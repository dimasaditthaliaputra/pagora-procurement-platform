import { useEffect, useMemo, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { getCoreRowModel, useReactTable } from '@tanstack/react-table';
import {
    AlertTriangle,
    FilterX,
    PackageX,
    Scale,
    Search,
    ShieldAlert,
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
import { InspeksiQcNavTabs } from '@/Pages/InspeksiQc/components/InspeksiQcNavTabs';
import { getDamageColumns } from '@/Pages/InspeksiQc/damageColumns';

export default function InspeksiQcDamageReports({
    damage_reports,
    inspectable_orders_count = 0,
    statistics = {},
    trigger_options = [],
    dispute_status_options = [],
    filters: rawFilters = {},
    current_role = 'qc_gudang',
    can = {},
}) {
    const filters = Array.isArray(rawFilters) || !rawFilters ? {} : rawFilters;
    const data = damage_reports?.data ?? [];
    const meta = damage_reports?.meta ?? {
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: data.length,
        from: data.length ? 1 : 0,
        to: data.length,
    };

    const isVendor = current_role === 'vendor';

    const [search, setSearch] = useState(filters.search ?? '');
    const [triggerFilter, setTriggerFilter] = useState(filters.trigger_type ?? 'all');
    const [disputeStatusFilter, setDisputeStatusFilter] = useState(filters.dispute_status ?? 'all');
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
            trigger_type:
                newParams.trigger_type !== undefined ? newParams.trigger_type : triggerFilter,
            dispute_status:
                newParams.dispute_status !== undefined
                    ? newParams.dispute_status
                    : disputeStatusFilter,
            sort: newParams.sort !== undefined ? newParams.sort : (sorting[0]?.id ?? undefined),
            direction:
                newParams.direction !== undefined
                    ? newParams.direction
                    : sorting[0]?.desc
                      ? 'desc'
                      : 'asc',
            page: newParams.page !== undefined ? newParams.page : meta.current_page,
            per_page: newParams.per_page !== undefined ? newParams.per_page : meta.per_page,
        };

        if (query.trigger_type === 'all') delete query.trigger_type;
        if (query.dispute_status === 'all') delete query.dispute_status;
        if (!query.search) delete query.search;
        if (!query.sort) {
            delete query.sort;
            delete query.direction;
        }

        router.get('/inspeksi-qc/laporan-kerusakan', query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['damage_reports', 'filters', 'statistics'],
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

    const handleTriggerFilterChange = (val) => {
        const target = val || 'all';
        setTriggerFilter(target);
        handleFilterChange({ trigger_type: target, page: 1 });
    };

    const handleDisputeStatusFilterChange = (val) => {
        const target = val || 'all';
        setDisputeStatusFilter(target);
        handleFilterChange({ dispute_status: target, page: 1 });
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
        setTriggerFilter('all');
        setDisputeStatusFilter('all');
        setSorting([]);
        router.get(
            '/inspeksi-qc/laporan-kerusakan',
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['damage_reports', 'filters', 'statistics'],
            }
        );
    };

    const columns = useMemo(
        () => getDamageColumns({ onSortChange: handleSortChange, isVendor }),
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

    const stats = useMemo(
        () => ({
            total_damage_reports: statistics?.total_damage_reports ?? 0,
            goods_mismatch_count: statistics?.goods_mismatch_count ?? 0,
            qc_rejected_count: statistics?.qc_rejected_count ?? 0,
            open_disputes_count: statistics?.open_disputes_count ?? 0,
        }),
        [statistics]
    );

    const hasFilter =
        (search && search.trim() !== '') ||
        (triggerFilter && triggerFilter !== 'all') ||
        (disputeStatusFilter && disputeStatusFilter !== 'all');

    const breadcrumbs = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Inspeksi QC', href: '/inspeksi-qc' },
        { label: 'Laporan Kerusakan Barang', href: '#' },
    ];

    return (
        <DashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Laporan Kerusakan Barang (QC Gudang) - PAGORA" />

            <div className="flex flex-col gap-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
                        <ShieldAlert className="size-6 text-destructive shrink-0" />
                        Laporan Kerusakan &amp; Ketidaksesuaian Barang
                    </h1>
                    <p className="text-sm text-muted-foreground mt-1 max-w-3xl">
                        {can.create
                            ? 'Kelola daftar laporan kerusakan fisik maupun ketidaksesuaian mutu barang hasil inspeksi QC Gudang beserta foto bukti dan log sengketanya.'
                            : 'Pantau seluruh rekaman laporan kerusakan barang, foto bukti QC Gudang, dan status sengketa terkait.'}
                    </p>
                </div>

                {/* Navigasi Sub-Fitur Inspeksi QC */}
                <InspeksiQcNavTabs
                    activeTab="damage_reports"
                    damageCount={stats.total_damage_reports}
                />

                {/* 4 Kartu Metrik Ringkasan Laporan Kerusakan */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="flex flex-col justify-between rounded-xl border border-border/60 bg-card p-4 shadow-2xs">
                        <div className="flex items-start justify-between gap-2">
                            <p className="text-xs font-medium text-muted-foreground">
                                Total Item Dilaporkan Rusak / Ditolak
                            </p>
                            <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-destructive/10 text-destructive">
                                <ShieldAlert className="size-4" />
                            </div>
                        </div>
                        <div className="mt-3">
                            <p className="text-2xl font-bold tracking-tight text-foreground tabular-nums">
                                {stats.total_damage_reports}{' '}
                                <span className="text-sm font-normal text-muted-foreground">
                                    Item
                                </span>
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Akumulasi laporan kerusakan QC Gudang
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-col justify-between rounded-xl border border-border/60 bg-card p-4 shadow-2xs">
                        <div className="flex items-start justify-between gap-2">
                            <p className="text-xs font-medium text-muted-foreground">
                                Kerusakan Fisik / Tidak Sesuai Pesanan
                            </p>
                            <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-400">
                                <PackageX className="size-4" />
                            </div>
                        </div>
                        <div className="mt-3">
                            <p className="text-2xl font-bold tracking-tight text-foreground tabular-nums">
                                {stats.goods_mismatch_count}{' '}
                                <span className="text-sm font-normal text-muted-foreground">
                                    Item
                                </span>
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Kemasan rusak, cacat fisik, atau susut berlebih
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-col justify-between rounded-xl border border-border/60 bg-card p-4 shadow-2xs">
                        <div className="flex items-start justify-between gap-2">
                            <p className="text-xs font-medium text-muted-foreground">
                                Kualitas / Mutu Ditolak QC
                            </p>
                            <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400">
                                <AlertTriangle className="size-4" />
                            </div>
                        </div>
                        <div className="mt-3">
                            <p className="text-2xl font-bold tracking-tight text-foreground tabular-nums">
                                {stats.qc_rejected_count}{' '}
                                <span className="text-sm font-normal text-muted-foreground">
                                    Item
                                </span>
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Standar mutu komoditas tidak terpenuhi
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-col justify-between rounded-xl border border-border/60 bg-card p-4 shadow-2xs">
                        <div className="flex items-start justify-between gap-2">
                            <p className="text-xs font-medium text-muted-foreground">
                                Sengketa Kerusakan Masih Terbuka
                            </p>
                            <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-sky-500/10 text-sky-600 dark:text-sky-400">
                                <Scale className="size-4" />
                            </div>
                        </div>
                        <div className="mt-3">
                            <p className="text-2xl font-bold tracking-tight text-foreground tabular-nums">
                                {stats.open_disputes_count}{' '}
                                <span className="text-sm font-normal text-muted-foreground">
                                    Sengketa
                                </span>
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Menunggu tindak lanjut mediasi / pengiriman ulang
                            </p>
                        </div>
                    </div>
                </div>

                {/* Filter Bar */}
                <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-3 p-4 rounded-lg border bg-card/60 backdrop-blur-xs">
                    <div className="flex flex-1 flex-col sm:flex-row flex-wrap items-center gap-3">
                        <div className="relative w-full sm:w-80">
                            <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground pointer-events-none" />
                            <Input
                                placeholder="Cari komoditas, nomor PO, vendor, catatan..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="pl-9 h-9"
                            />
                        </div>

                        <div className="w-full sm:w-64">
                            <Select value={triggerFilter} onValueChange={handleTriggerFilterChange}>
                                <SelectTrigger className="h-9 text-sm w-full bg-background">
                                    <SelectValue placeholder="Semua Kategori Kerusakan" />
                                </SelectTrigger>
                                <SelectContent
                                    position="popper"
                                    side="bottom"
                                    align="start"
                                    avoidCollisions={false}
                                >
                                    <SelectItem value="all">Semua Kategori Kerusakan</SelectItem>
                                    {trigger_options.map((opt) => (
                                        <SelectItem key={opt.value} value={opt.value}>
                                            {opt.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="w-full sm:w-52">
                            <Select
                                value={disputeStatusFilter}
                                onValueChange={handleDisputeStatusFilterChange}
                            >
                                <SelectTrigger className="h-9 text-sm w-full bg-background">
                                    <SelectValue placeholder="Semua Status Sengketa" />
                                </SelectTrigger>
                                <SelectContent
                                    position="popper"
                                    side="bottom"
                                    align="start"
                                    avoidCollisions={false}
                                >
                                    <SelectItem value="all">Semua Status Sengketa</SelectItem>
                                    {dispute_status_options.map((opt) => (
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

                {/* Server-side Data Table */}
                <div className="space-y-4">
                    <DataTable
                        table={table}
                        emptyMessage="Belum ada laporan kerusakan atau penolakan barang pada pemeriksaan QC Gudang."
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

