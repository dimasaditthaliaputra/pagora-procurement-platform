import { useState, useEffect, useMemo, useRef } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    useReactTable,
    getCoreRowModel,
} from '@tanstack/react-table';
import {
    Building2,
    CheckCircle2,
    FilterX,
    Search,
    ShieldAlert,
    ShieldCheck,
    Users,
    Clock,
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
import { BlokirDialog } from './components/BlokirDialog';
import { BukaBlokirDialog } from './components/BukaBlokirDialog';
import { VendorDetailDialog } from './components/VendorDetailDialog';

export default function VendorIndex({
    vendors,
    filters = {},
    can_manage = false,
}) {
    const data = vendors?.data ?? [];
    const meta = vendors?.meta ?? {
        current_page: vendors?.current_page ?? 1,
        last_page: vendors?.last_page ?? 1,
        per_page: vendors?.per_page ?? 10,
        total: vendors?.total ?? data.length,
        from: vendors?.from ?? 1,
        to: vendors?.to ?? data.length,
    };

    const [search, setSearch] = useState(filters.search ?? '');
    const [statusAktif, setStatusAktif] = useState(filters.status_aktif ?? 'all');
    const [statusVerifikasi, setStatusVerifikasi] = useState(filters.status_verifikasi ?? 'all');
    const [loading, setLoading] = useState(false);

    // Dialog state
    const [selectedVendor, setSelectedVendor] = useState(null);
    const [blokirDialogOpen, setBlokirDialogOpen] = useState(false);
    const [bukaBlokirDialogOpen, setBukaBlokirDialogOpen] = useState(false);
    const [detailDialogOpen, setDetailDialogOpen] = useState(false);

    const isFirstRender = useRef(true);

    // Sync state when props change
    useEffect(() => {
        setSearch(filters.search ?? '');
        setStatusAktif(filters.status_aktif ?? 'all');
        setStatusVerifikasi(filters.status_verifikasi ?? 'all');
    }, [filters.search, filters.status_aktif, filters.status_verifikasi]);

    const applyFilters = (newFilters = {}) => {
        const merged = {
            search: search.trim() || undefined,
            status_aktif: statusAktif !== 'all' ? statusAktif : undefined,
            status_verifikasi: statusVerifikasi !== 'all' ? statusVerifikasi : undefined,
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

        router.get('/master/vendor', cleanParams, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['vendors', 'filters'],
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    };

    // Debounce search input
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

    const handleStatusAktifChange = (val) => {
        setStatusAktif(val);
        applyFilters({ status_aktif: val !== 'all' ? val : undefined });
    };

    const handleStatusVerifikasiChange = (val) => {
        setStatusVerifikasi(val);
        applyFilters({ status_verifikasi: val !== 'all' ? val : undefined });
    };

    const handleResetFilters = () => {
        setSearch('');
        setStatusAktif('all');
        setStatusVerifikasi('all');
        router.get('/master/vendor', {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['vendors', 'filters'],
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    };

    const handlePageChange = (page) => {
        router.get(
            '/master/vendor',
            {
                search: search.trim() || undefined,
                status_aktif: statusAktif !== 'all' ? statusAktif : undefined,
                status_verifikasi: statusVerifikasi !== 'all' ? statusVerifikasi : undefined,
                per_page: meta.per_page,
                page,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['vendors', 'filters'],
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            }
        );
    };

    const handlePerPageChange = (per_page) => {
        router.get(
            '/master/vendor',
            {
                search: search.trim() || undefined,
                status_aktif: statusAktif !== 'all' ? statusAktif : undefined,
                status_verifikasi: statusVerifikasi !== 'all' ? statusVerifikasi : undefined,
                per_page,
                page: 1,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['vendors', 'filters'],
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            }
        );
    };

    // Calculate basic statistics from current data / total
    const stats = useMemo(() => {
        const total = meta.total || data.length;
        const blocked = data.filter((v) => !v.status_aktif).length;
        const active = data.filter((v) => v.status_aktif).length;
        const pending = data.filter((v) => v.status_verifikasi === 'pending').length;
        return { total, blocked, active, pending };
    }, [data, meta.total]);

    // Setup action handlers
    const handleOpenBlock = (vendor) => {
        setSelectedVendor(vendor);
        setBlokirDialogOpen(true);
    };

    const handleOpenUnblock = (vendor) => {
        setSelectedVendor(vendor);
        setBukaBlokirDialogOpen(true);
    };

    const handleOpenDetail = (vendor) => {
        setSelectedVendor(vendor);
        setDetailDialogOpen(true);
    };

    const columns = useMemo(
        () =>
            getColumns({
                onBlock: handleOpenBlock,
                onUnblock: handleOpenUnblock,
                onViewDetail: handleOpenDetail,
                canManage: can_manage,
            }),
        [can_manage]
    );

    const table = useReactTable({
        data,
        columns,
        getRowId: (row) => row.uuid,
        getCoreRowModel: getCoreRowModel(),
        manualPagination: true,
        pageCount: meta.last_page,
    });

    const hasActiveFilters =
        Boolean(search) || statusAktif !== 'all' || statusVerifikasi !== 'all';

    return (
        <DashboardLayout
            breadcrumbs={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'Master Data', href: '#' },
                { label: 'Data Rekanan Vendor', href: '/master/vendor' },
            ]}
        >
            <Head title="Manajemen Rekanan & Pemblokiran Vendor" />

            <div className="flex flex-col gap-6">
                {/* Header */}
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                            <Building2 className="size-6 text-emerald-600" />
                            Data Rekanan Vendor
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Kelola status kemitraan rekanan, verifikasi legalitas, serta pengendalian pemblokiran vendor.
                        </p>
                    </div>
                </div>

                {/* Summary Stat Cards */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <Card className="border-border/60 shadow-sm">
                        <CardContent className="flex items-center gap-3 p-4">
                            <div className="flex size-10 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400">
                                <Users className="size-5" />
                            </div>
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">
                                    Total Rekanan
                                </p>
                                <p className="text-2xl font-bold tracking-tight text-foreground">
                                    {stats.total}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border/60 shadow-sm">
                        <CardContent className="flex items-center gap-3 p-4">
                            <div className="flex size-10 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400">
                                <CheckCircle2 className="size-5" />
                            </div>
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">
                                    Vendor Aktif
                                </p>
                                <p className="text-2xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">
                                    {stats.active}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border/60 shadow-sm">
                        <CardContent className="flex items-center gap-3 p-4">
                            <div className="flex size-10 items-center justify-center rounded-lg bg-destructive/10 text-destructive">
                                <ShieldAlert className="size-5" />
                            </div>
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">
                                    Vendor Diblokir
                                </p>
                                <p className="text-2xl font-bold tracking-tight text-destructive">
                                    {stats.blocked}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border/60 shadow-sm">
                        <CardContent className="flex items-center gap-3 p-4">
                            <div className="flex size-10 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400">
                                <Clock className="size-5" />
                            </div>
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">
                                    Pending Review
                                </p>
                                <p className="text-2xl font-bold tracking-tight text-amber-600 dark:text-amber-400">
                                    {stats.pending}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filter & Search Bar */}
                <Card className="border-border/60 shadow-sm">
                    <CardContent className="p-4">
                        <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            {/* Search Input */}
                            <div className="relative flex-1 md:max-w-xs">
                                <Search className="absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    type="text"
                                    placeholder="Cari nama, email, komoditas..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="pl-8"
                                />
                            </div>

                            {/* Filters */}
                            <div className="flex flex-wrap items-center gap-2">
                                <div className="w-40">
                                    <Select
                                        value={statusAktif}
                                        onValueChange={handleStatusAktifChange}
                                    >
                                        <SelectTrigger className="h-9">
                                            <SelectValue placeholder="Status Rekanan" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">Semua Status</SelectItem>
                                            <SelectItem value="1">Aktif</SelectItem>
                                            <SelectItem value="0">DIBLOKIR</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="w-44">
                                    <Select
                                        value={statusVerifikasi}
                                        onValueChange={handleStatusVerifikasiChange}
                                    >
                                        <SelectTrigger className="h-9">
                                            <SelectValue placeholder="Status Legalitas" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">Semua Legalitas</SelectItem>
                                            <SelectItem value="terverifikasi">Terverifikasi</SelectItem>
                                            <SelectItem value="pending">Pending</SelectItem>
                                            <SelectItem value="ditolak">Ditolak</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                {hasActiveFilters && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={handleResetFilters}
                                        className="h-9 gap-1 text-muted-foreground hover:text-foreground"
                                    >
                                        <FilterX className="size-4" />
                                        Reset
                                    </Button>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Data Table */}
                <div className="rounded-md border bg-card shadow-sm">
                    <DataTable
                        table={table}
                        columns={columns}
                        loading={loading}
                        emptyMessage="Tidak ada data rekanan vendor yang cocok dengan filter."
                    />
                    <div className="border-t p-4">
                        <DataTablePagination
                            table={table}
                            pageCount={meta.last_page}
                            onPageChange={handlePageChange}
                            onPerPageChange={handlePerPageChange}
                        />
                    </div>
                </div>
            </div>

            {/* Modals & Dialogs */}
            <BlokirDialog
                vendor={selectedVendor}
                open={blokirDialogOpen}
                onOpenChange={setBlokirDialogOpen}
            />

            <BukaBlokirDialog
                vendor={selectedVendor}
                open={bukaBlokirDialogOpen}
                onOpenChange={setBukaBlokirDialogOpen}
            />

            <VendorDetailDialog
                vendor={selectedVendor}
                open={detailDialogOpen}
                onOpenChange={setDetailDialogOpen}
                onBlock={handleOpenBlock}
                onUnblock={handleOpenUnblock}
                canManage={can_manage}
            />
        </DashboardLayout>
    );
}
