import { useEffect, useMemo, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { getCoreRowModel, useReactTable } from '@tanstack/react-table';
import {
    CheckCircle2,
    FilterX,
    Gavel,
    Scale,
    Search,
    ShieldAlert,
} from 'lucide-react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { DataTable } from '@/Components/data-table/data-table';
import { DataTablePagination } from '@/Components/data-table/data-table-pagination';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { getSengketaColumns } from '@/Pages/LogSengketa/columns';
import { cn } from '@/lib/utils';

function StatCard({ title, value, unit, caption, icon: Icon, tone }) {
    return (
        <div className="flex flex-col justify-between rounded-xl border border-border/60 bg-card p-4 shadow-2xs">
            <div className="flex items-start justify-between gap-2">
                <p className="text-xs font-medium text-muted-foreground">{title}</p>
                <div className={cn('flex size-9 shrink-0 items-center justify-center rounded-lg', tone)}>
                    <Icon className="size-4" />
                </div>
            </div>
            <div className="mt-3">
                <p className="text-2xl font-bold tracking-tight text-foreground tabular-nums">
                    {value}{' '}
                    <span className="text-sm font-normal text-muted-foreground">{unit}</span>
                </p>
                <p className="mt-1 text-xs text-muted-foreground">{caption}</p>
            </div>
        </div>
    );
}

export default function LogSengketaIndex({
    disputes,
    statistics = {},
    status_options = [],
    trigger_options = [],
    filters: rawFilters = {},
}) {
    const filters = Array.isArray(rawFilters) || !rawFilters ? {} : rawFilters;
    const data = disputes?.data ?? [];
    const meta = disputes?.meta ?? {
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: data.length,
        from: data.length ? 1 : 0,
        to: data.length,
    };

    const [search, setSearch] = useState(filters.search ?? '');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'all');
    const [triggerFilter, setTriggerFilter] = useState(filters.trigger_type || 'all');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [dateError, setDateError] = useState('');
    const [sorting, setSorting] = useState(() =>
        filters.sort ? [{ id: filters.sort, desc: filters.direction === 'desc' }] : []
    );
    const [loading, setLoading] = useState(false);

    const isInitialMount = useRef(true);

    const applyFilters = (overrides = {}) => {
        const activeSort = overrides.sort !== undefined ? overrides.sort : sorting[0]?.id;
        const activeDirection =
            overrides.direction !== undefined
                ? overrides.direction
                : sorting[0]?.desc
                  ? 'desc'
                  : 'asc';

        const query = {
            search: overrides.search !== undefined ? overrides.search : search,
            status: overrides.status !== undefined ? overrides.status : statusFilter,
            trigger_type: overrides.trigger_type !== undefined ? overrides.trigger_type : triggerFilter,
            date_from: overrides.date_from !== undefined ? overrides.date_from : dateFrom,
            date_to: overrides.date_to !== undefined ? overrides.date_to : dateTo,
            sort: activeSort,
            direction: activeDirection,
            page: overrides.page !== undefined ? overrides.page : meta.current_page,
            per_page: overrides.per_page !== undefined ? overrides.per_page : meta.per_page,
        };

        if (!query.search || !query.search.trim()) delete query.search;
        if (query.status === 'all') delete query.status;
        if (query.trigger_type === 'all') delete query.trigger_type;
        if (!query.date_from) delete query.date_from;
        if (!query.date_to) delete query.date_to;
        if (!query.sort) {
            delete query.sort;
            delete query.direction;
        }

        router.get('/log-sengketa', query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['disputes', 'filters', 'statistics'],
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    };

    useEffect(() => {
        if (isInitialMount.current) {
            isInitialMount.current = false;
            return;
        }

        const timer = setTimeout(() => applyFilters({ search, page: 1 }), 400);

        return () => clearTimeout(timer);
    }, [search]);

    const handleStatusChange = (value) => {
        const target = value || 'all';
        setStatusFilter(target);
        applyFilters({ status: target, page: 1 });
    };

    const handleTriggerChange = (value) => {
        const target = value || 'all';
        setTriggerFilter(target);
        applyFilters({ trigger_type: target, page: 1 });
    };

    const handleDateChange = (field, value) => {
        const nextFrom = field === 'date_from' ? value : dateFrom;
        const nextTo = field === 'date_to' ? value : dateTo;

        if (field === 'date_from') setDateFrom(value);
        if (field === 'date_to') setDateTo(value);

        if (nextFrom && nextTo && nextTo < nextFrom) {
            setDateError('Tanggal akhir tidak boleh lebih awal dari tanggal mulai.');
            return;
        }

        setDateError('');
        applyFilters({ date_from: nextFrom, date_to: nextTo, page: 1 });
    };

    const handleSortChange = (columnId, direction) => {
        setSorting([{ id: columnId, desc: direction === 'desc' }]);
        applyFilters({ sort: columnId, direction, page: 1 });
    };

    const handleResetFilter = () => {
        setSearch('');
        setStatusFilter('all');
        setTriggerFilter('all');
        setDateFrom('');
        setDateTo('');
        setDateError('');
        setSorting([]);
        router.get('/log-sengketa', {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['disputes', 'filters', 'statistics'],
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    };

    const columns = useMemo(() => getSengketaColumns({ onSortChange: handleSortChange }), [sorting]);

    const table = useReactTable({
        data,
        columns,
        getRowId: (row) => row.uuid,
        getCoreRowModel: getCoreRowModel(),
        manualPagination: true,
        manualSorting: true,
        manualFiltering: true,
        pageCount: meta.last_page,
        state: { sorting },
    });

    const hasFilter =
        (search && search.trim() !== '') ||
        statusFilter !== 'all' ||
        triggerFilter !== 'all' ||
        Boolean(dateFrom) ||
        Boolean(dateTo);

    const emptyMessage = hasFilter
        ? 'Data sengketa tidak ditemukan. Coba ubah kata kunci atau filter pencarian.'
        : 'Belum ada sengketa pengadaan yang tercatat.';

    const breadcrumbs = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Log Sengketa', href: '#' },
    ];

    return (
        <DashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Log Sengketa - Meninjau Kendala Pengadaan - PAGORA" />

            <div className="flex flex-col gap-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
                        <Scale className="size-6 text-primary shrink-0" />
                        Log Sengketa — Kendala Pengadaan
                    </h1>
                    <p className="text-sm text-muted-foreground mt-1 max-w-3xl">
                        Tinjau seluruh kendala pengadaan beserta bukti pemeriksaan QC, riwayat negosiasi,
                        dan jejak audit Log Sengketa yang bersifat permanen. Dokumen sengketa dapat diunduh
                        untuk keperluan mediasi.
                    </p>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <StatCard
                        title="Total Sengketa Tercatat"
                        value={statistics.total ?? 0}
                        unit="Sengketa"
                        caption={`${statistics.qc_triggered ?? 0} dari QC · ${statistics.invoice_triggered ?? 0} dari tagihan`}
                        icon={Scale}
                        tone="bg-muted text-foreground"
                    />
                    <StatCard
                        title="Terbuka (Perlu Ditinjau)"
                        value={statistics.open ?? 0}
                        unit="Sengketa"
                        caption="Belum ditinjau Manajemen / Owner"
                        icon={ShieldAlert}
                        tone="bg-amber-500/10 text-amber-600 dark:text-amber-400"
                    />
                    <StatCard
                        title="Dalam Mediasi"
                        value={statistics.in_mediation ?? 0}
                        unit="Sengketa"
                        caption="Sedang dimediasi dengan Vendor"
                        icon={Gavel}
                        tone="bg-sky-500/10 text-sky-600 dark:text-sky-400"
                    />
                    <StatCard
                        title="Selesai"
                        value={statistics.resolved ?? 0}
                        unit="Sengketa"
                        caption="Penyelesaian telah diverifikasi"
                        icon={CheckCircle2}
                        tone="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                    />
                </div>

                <div className="flex flex-col gap-3 p-4 rounded-lg border bg-card/60">
                    <div className="flex flex-col lg:flex-row lg:items-end gap-3">
                        <div className="relative w-full lg:w-80 lg:self-end">
                            <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground pointer-events-none" />
                            <Input
                                placeholder="Cari nomor PO, vendor, komoditas, alasan..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="pl-9 h-9"
                                aria-label="Cari sengketa"
                            />
                        </div>

                        <div className="w-full lg:w-48">
                            <Select value={statusFilter} onValueChange={handleStatusChange}>
                                <SelectTrigger className="h-9 text-sm w-full bg-background" aria-label="Filter status">
                                    <SelectValue placeholder="Semua Status" />
                                </SelectTrigger>
                                <SelectContent position="popper" side="bottom" align="start">
                                    <SelectItem value="all">Semua Status</SelectItem>
                                    {status_options.map((opt) => (
                                        <SelectItem key={opt.value} value={opt.value}>
                                            {opt.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="w-full lg:w-64">
                            <Select value={triggerFilter} onValueChange={handleTriggerChange}>
                                <SelectTrigger className="h-9 text-sm w-full bg-background" aria-label="Filter kategori pemicu">
                                    <SelectValue placeholder="Semua Kategori Sengketa" />
                                </SelectTrigger>
                                <SelectContent position="popper" side="bottom" align="start">
                                    <SelectItem value="all">Semua Kategori Sengketa</SelectItem>
                                    {trigger_options.map((opt) => (
                                        <SelectItem key={opt.value} value={opt.value}>
                                            {opt.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid grid-cols-2 gap-2 w-full lg:w-auto">
                            <div className="space-y-1">
                                <Label htmlFor="date_from" className="text-xs text-muted-foreground">
                                    Dibuka dari
                                </Label>
                                <Input
                                    id="date_from"
                                    type="date"
                                    value={dateFrom}
                                    max={dateTo || undefined}
                                    onChange={(e) => handleDateChange('date_from', e.target.value)}
                                    className="h-9 bg-background"
                                />
                            </div>
                            <div className="space-y-1">
                                <Label htmlFor="date_to" className="text-xs text-muted-foreground">
                                    Sampai
                                </Label>
                                <Input
                                    id="date_to"
                                    type="date"
                                    value={dateTo}
                                    min={dateFrom || undefined}
                                    onChange={(e) => handleDateChange('date_to', e.target.value)}
                                    aria-invalid={Boolean(dateError)}
                                    className={cn(
                                        'h-9 bg-background',
                                        dateError && 'border-destructive focus-visible:ring-destructive'
                                    )}
                                />
                            </div>
                        </div>

                        {hasFilter && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={handleResetFilter}
                                className="h-9 gap-1 text-muted-foreground hover:text-foreground cursor-pointer lg:ml-auto"
                            >
                                <FilterX className="size-4" />
                                Reset Filter
                            </Button>
                        )}
                    </div>
                    {dateError && <p className="text-sm text-destructive">{dateError}</p>}
                </div>

                <div className="space-y-4">
                    <DataTable table={table} loading={loading} emptyMessage={emptyMessage} />
                    <DataTablePagination
                        meta={meta}
                        onPageChange={(page) => applyFilters({ page })}
                        onPerPageChange={(perPage) => applyFilters({ per_page: perPage, page: 1 })}
                    />
                </div>
            </div>
        </DashboardLayout>
    );
}

