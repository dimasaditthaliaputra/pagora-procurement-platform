import { useState, useEffect, useRef, useMemo } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { useReactTable, getCoreRowModel } from '@tanstack/react-table';
import { Plus, Search, FilterX, FileText, Clock, CircleDollarSign, AlertTriangle } from 'lucide-react';
import { cn } from '@/lib/utils';
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
import { getColumns } from '@/Pages/PurchaseOrder/columns';
import { STATUS_PO } from '@/Pages/PurchaseOrder/constants';
import { KonfirmasiNegosiasiDialog } from '@/Pages/PurchaseOrder/components/KonfirmasiNegosiasiDialog';
import { HapusDrafDialog } from '@/Pages/PurchaseOrder/components/HapusDrafDialog';

export default function PurchaseOrderIndex({
    purchase_orders,
    statistics = {},
    filters: rawFilters = {},
    status_options = [],
    can = {},
}) {
    const filters = Array.isArray(rawFilters) || !rawFilters ? {} : rawFilters;
    const data = purchase_orders?.data ?? [];
    const meta = purchase_orders?.meta ?? {
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: data.length,
        from: data.length ? 1 : 0,
        to: data.length,
    };

    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? 'all');
    const [sorting, setSorting] = useState(() => {
        if (filters.sort) {
            return [{ id: filters.sort, desc: filters.direction === 'desc' }];
        }
        return [];
    });
    const [konfirmasiPo, setKonfirmasiPo] = useState(null);
    const [hapusPo, setHapusPo] = useState(null);

    const isInitialMount = useRef(true);

    const handleFilterChange = (newParams) => {
        const query = {
            search: newParams.search !== undefined ? newParams.search : search,
            status: newParams.status !== undefined ? newParams.status : status,
            sort: newParams.sort !== undefined ? newParams.sort : (sorting[0]?.id ?? undefined),
            direction: newParams.direction !== undefined ? newParams.direction : (sorting[0]?.desc ? 'desc' : 'asc'),
            page: newParams.page !== undefined ? newParams.page : meta.current_page,
            per_page: newParams.per_page !== undefined ? newParams.per_page : meta.per_page,
        };

        if (query.status === 'all') delete query.status;
        if (!query.search) delete query.search;
        if (!query.sort) {
            delete query.sort;
            delete query.direction;
        }

        router.get('/purchase-order', query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['purchase_orders', 'filters'],
        });
    };

    // Debounce search
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

    const handleStatusFilter = (newStatus) => {
        const target = newStatus || 'all';
        setStatus(target);
        handleFilterChange({ status: target, page: 1 });
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
        setSorting([]);
        router.get('/purchase-order', {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['purchase_orders', 'filters'],
        });
    };

    const columns = getColumns({
        onSortChange: handleSortChange,
        onKonfirmasi: (po) => setKonfirmasiPo(po),
        onHapus: (po) => setHapusPo(po),
    });

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
        total_aktif: {
            count: statistics?.total_aktif?.count ?? 0,
            draft_count: statistics?.total_aktif?.draft_count ?? 0,
        },
        menunggu_tindakan: {
            count: statistics?.menunggu_tindakan?.count ?? 0,
        },
        total_nilai_berjalan: {
            amount: statistics?.total_nilai_berjalan?.amount ?? 0,
            formatted: statistics?.total_nilai_berjalan?.formatted ?? 'Rp 0',
        },
        perlu_perhatian: {
            count: statistics?.perlu_perhatian?.count ?? 0,
        },
    }), [statistics]);

    const hasFilter = (search && search.trim() !== '') || (status && status !== 'all');

    const filterOptions = [
        { value: 'all', label: 'Semua Status' },
        ...status_options.map((s) => ({ value: s.value, label: s.label })),
    ];

    const breadcrumbs = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Purchase Order', href: '/purchase-order' },
    ];

    return (
        <DashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Purchase Order - PAGORA" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
                            <FileText className="size-6 text-primary" />
                            Purchase Order
                        </h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Buat pesanan bahan baku, negosiasikan harga dengan vendor, dan pantau statusnya.
                        </p>
                    </div>
                    {can.create && (
                        <Button asChild className="gap-2 cursor-pointer shadow-sm">
                            <Link href="/purchase-order/create">
                                <Plus className="size-4" />
                                Buat Purchase Order
                            </Link>
                        </Button>
                    )}
                </div>

                {/* 4 Kartu Metrik Ringkasan PO */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    {/* 1. Total PO Aktif */}
                    <div className="flex flex-col justify-between rounded-xl border border-border/60 bg-card p-4 shadow-2xs">
                        <div className="flex items-start justify-between gap-2">
                            <p className="text-xs font-medium text-muted-foreground">Total PO Aktif</p>
                            <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400">
                                <FileText className="size-4" />
                            </div>
                        </div>
                        <div className="mt-3">
                            <p className="text-2xl font-bold tracking-tight text-foreground">
                                {stats.total_aktif.count} <span className="text-sm font-normal text-muted-foreground">PO</span>
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {stats.total_aktif.draft_count} draf belum dikirim
                            </p>
                        </div>
                    </div>

                    {/* 2. Menunggu Tindakan (Action Needed) */}
                    <div className="flex flex-col justify-between rounded-xl border border-border/60 bg-card p-4 shadow-2xs">
                        <div className="flex items-start justify-between gap-2">
                            <div className="flex items-center gap-1.5 flex-wrap">
                                <p className="text-xs font-medium text-muted-foreground">Menunggu Tindakan</p>
                                <span className="inline-flex items-center rounded-md bg-amber-500/10 px-1.5 py-0.5 text-[10px] font-medium text-amber-700 dark:text-amber-400">
                                    Action Needed
                                </span>
                            </div>
                            <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400">
                                <Clock className="size-4" />
                            </div>
                        </div>
                        <div className="mt-3">
                            <p className="text-2xl font-bold tracking-tight text-foreground">
                                {stats.menunggu_tindakan.count} <span className="text-sm font-normal text-muted-foreground">PO</span>
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Tawaran baru & siap bayar
                            </p>
                        </div>
                    </div>

                    {/* 3. Total Nilai PO Berjalan (IDR) */}
                    <div className="flex flex-col justify-between rounded-xl border border-border/60 bg-card p-4 shadow-2xs">
                        <div className="flex items-start justify-between gap-2">
                            <p className="text-xs font-medium text-muted-foreground">Total Nilai PO Berjalan (IDR)</p>
                            <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400">
                                <CircleDollarSign className="size-4" />
                            </div>
                        </div>
                        <div className="mt-3">
                            <p className="text-2xl font-bold tracking-tight text-foreground truncate" title={stats.total_nilai_berjalan.formatted}>
                                {stats.total_nilai_berjalan.formatted}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Komitmen anggaran saat ini
                            </p>
                        </div>
                    </div>

                    {/* 4. Perlu Perhatian / Sengketa */}
                    <div className="flex flex-col justify-between rounded-xl border border-border/60 bg-card p-4 shadow-2xs">
                        <div className="flex items-start justify-between gap-2">
                            <div className="flex items-center gap-1.5 flex-wrap">
                                <p className="text-xs font-medium text-muted-foreground">Perlu Perhatian / Sengketa</p>
                                {stats.perlu_perhatian.count > 0 && (
                                    <span className="inline-flex items-center rounded-md bg-destructive/10 px-1.5 py-0.5 text-[10px] font-medium text-destructive">
                                        Perhatian
                                    </span>
                                )}
                            </div>
                            <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-destructive/10 text-destructive dark:bg-destructive/20">
                                <AlertTriangle className="size-4" />
                            </div>
                        </div>
                        <div className="mt-3">
                            <p className="text-2xl font-bold tracking-tight text-foreground">
                                {stats.perlu_perhatian.count} <span className="text-sm font-normal text-muted-foreground">PO</span>
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Sengketa QC / 3-Way Matching
                            </p>
                        </div>
                    </div>
                </div>

                <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-3 p-4 rounded-lg border bg-card/60 backdrop-blur-xs">
                    <div className="flex flex-1 flex-col sm:flex-row items-center gap-3">
                        <div className="relative w-full sm:w-72">
                            <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground pointer-events-none" />
                            <Input
                                placeholder="Cari nomor PO, vendor, komoditas..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="pl-9 h-9"
                            />
                        </div>
                        <div className="w-full sm:w-56">
                            <Select
                                value={status}
                                onValueChange={handleStatusFilter}
                            >
                                <SelectTrigger className="h-9 text-sm w-full bg-background">
                                    <SelectValue placeholder="Semua Status" />
                                </SelectTrigger>
                                <SelectContent
                                    position="popper"
                                    side="bottom"
                                    align="start"
                                    avoidCollisions={false}
                                    className="max-h-60"
                                >
                                    {filterOptions.map((opt) => (
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
                    <DataTable table={table} emptyMessage="Belum ada purchase order yang sesuai." />
                    <DataTablePagination
                        meta={meta}
                        onPageChange={handlePageChange}
                        onPerPageChange={handlePerPageChange}
                    />
                </div>
            </div>

            <KonfirmasiNegosiasiDialog
                po={konfirmasiPo}
                open={Boolean(konfirmasiPo)}
                onOpenChange={(open) => !open && setKonfirmasiPo(null)}
            />
            <HapusDrafDialog
                po={hapusPo}
                open={Boolean(hapusPo)}
                onOpenChange={(open) => !open && setHapusPo(null)}
                onDeleted={() => router.reload({ only: ['purchase_orders'] })}
            />
        </DashboardLayout>
    );
}
