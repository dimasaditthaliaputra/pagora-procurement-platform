import { useState, useEffect, useMemo, useRef } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    useReactTable,
    getCoreRowModel,
} from '@tanstack/react-table';
import {
    Building2,
    CheckCircle2,
    Clock,
    FileCheck2,
    FilterX,
    Search,
    ShieldCheck,
    XCircle,
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
import { ApprovalDialog } from './components/ApprovalDialog';
import { RejectionDialog } from './components/RejectionDialog';
import { DetailDialog } from './components/DetailDialog';

export default function VerifikasiTenantIndex({
    tenants,
    stats: serverStats,
    filters = {},
}) {
    const data = tenants?.data ?? [];
    const meta = tenants?.meta ?? {
        current_page: tenants?.current_page ?? 1,
        last_page: tenants?.last_page ?? 1,
        per_page: tenants?.per_page ?? 10,
        total: tenants?.total ?? data.length,
        from: tenants?.from ?? 1,
        to: tenants?.to ?? data.length,
    };

    const [search, setSearch] = useState(filters.search ?? '');
    const [statusVerifikasi, setStatusVerifikasi] = useState(
        filters.verification_status ?? 'all'
    );
    const [loading, setLoading] = useState(false);

    // Dialog state
    const [selectedTenant, setSelectedTenant] = useState(null);
    const [approveDialogOpen, setApproveDialogOpen] = useState(false);
    const [rejectDialogOpen, setRejectDialogOpen] = useState(false);
    const [detailDialogOpen, setDetailDialogOpen] = useState(false);

    const isFirstRender = useRef(true);

    // Sync state when props change
    useEffect(() => {
        setSearch(filters.search ?? '');
        setStatusVerifikasi(filters.verification_status ?? 'all');
    }, [filters.search, filters.verification_status]);

    const applyFilters = (newFilters = {}) => {
        const merged = {
            search: search.trim() || undefined,
            verification_status: statusVerifikasi !== 'all' ? statusVerifikasi : undefined,
            per_page: meta.per_page,
            page: 1,
            ...newFilters,
        };

        const cleanParams = {};
        Object.entries(merged).forEach(([key, val]) => {
            if (val !== undefined && val !== null && val !== '' && val !== 'all') {
                cleanParams[key] = val;
            }
        });

        router.get('/master/tenant/verifikasi-dokumen', cleanParams, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    };

    // Debounced search
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }

        const timer = setTimeout(() => {
            applyFilters({ search: search.trim() || undefined });
        }, 400);

        return () => clearTimeout(timer);
    }, [search]);

    const handleStatusChange = (val) => {
        setStatusVerifikasi(val);
        applyFilters({ verification_status: val !== 'all' ? val : undefined });
    };

    const handleReset = () => {
        setSearch('');
        setStatusVerifikasi('all');
        router.get('/master/tenant/verifikasi-dokumen', {}, {
            preserveState: true,
            replace: true,
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    };

    const isFiltered = search !== '' || (statusVerifikasi !== 'all' && statusVerifikasi !== '');

    // Action handlers for dialogs
    const handleOpenApprove = (tenant) => {
        setSelectedTenant(tenant);
        setApproveDialogOpen(true);
    };

    const handleOpenReject = (tenant) => {
        setSelectedTenant(tenant);
        setRejectDialogOpen(true);
    };

    const handleOpenDetail = (tenant) => {
        setSelectedTenant(tenant);
        setDetailDialogOpen(true);
    };

    // Columns
    const columns = useMemo(
        () =>
            getColumns({
                onViewDetail: handleOpenDetail,
                onApprove: handleOpenApprove,
                onReject: handleOpenReject,
            }),
        []
    );

    const table = useReactTable({
        data,
        columns,
        getCoreRowModel: getCoreRowModel(),
        manualPagination: true,
        pageCount: meta.last_page,
        getRowId: (row) => row.uuid,
    });

    const breadcrumbs = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Master Data' },
        { label: 'Verifikasi Tenant' },
    ];

    // Summary stats (menggunakan data agregat keseluruhan dari server agar tidak berubah saat memfilter status)
    const stats = useMemo(() => {
        if (serverStats) {
            return serverStats;
        }

        const pendingCount = data.filter((d) => d.verification_status === 'pending').length;
        const approvedCount = data.filter((d) => d.verification_status === 'approved').length;
        const rejectedCount = data.filter((d) => d.verification_status === 'rejected').length;
        return {
            total: meta.total,
            pending: pendingCount,
            approved: approvedCount,
            rejected: rejectedCount,
        };
    }, [serverStats, data, meta.total]);

    return (
        <DashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Verifikasi Dokumen & Legalitas Tenant - Master Data" />

            <div className="flex flex-col gap-6">
                {/* Header Section */}
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <div className="flex size-9 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:bg-emerald-950/50">
                                <FileCheck2 className="size-5" />
                            </div>
                            <h1 className="text-2xl font-bold tracking-tight text-foreground">
                                Verifikasi Legalitas Tenant
                            </h1>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Validasi dokumen legalitas, NPWP, dan registrasi perusahaan calon tenant (buyer) PAGORA.
                        </p>
                    </div>
                </div>

                {/* Stats Summary Cards */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <Card className="border-border/60 shadow-2xs">
                        <CardContent className="p-4 flex items-center justify-between">
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">Total Pendaftar</p>
                                <p className="text-2xl font-bold text-foreground mt-1">{stats.total}</p>
                            </div>
                            <div className="size-9 rounded-lg bg-muted flex items-center justify-center text-muted-foreground">
                                <Building2 className="size-5" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="border-border/60 shadow-2xs">
                        <CardContent className="p-4 flex items-center justify-between">
                            <div>
                                <p className="text-xs font-medium text-amber-600 dark:text-amber-400">Menunggu Verifikasi</p>
                                <p className="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">{stats.pending}</p>
                            </div>
                            <div className="size-9 rounded-lg bg-amber-500/10 flex items-center justify-center text-amber-600">
                                <Clock className="size-5" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="border-border/60 shadow-2xs">
                        <CardContent className="p-4 flex items-center justify-between">
                            <div>
                                <p className="text-xs font-medium text-emerald-600 dark:text-emerald-400">Disetujui</p>
                                <p className="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{stats.approved}</p>
                            </div>
                            <div className="size-9 rounded-lg bg-emerald-500/10 flex items-center justify-center text-emerald-600">
                                <CheckCircle2 className="size-5" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="border-border/60 shadow-2xs">
                        <CardContent className="p-4 flex items-center justify-between">
                            <div>
                                <p className="text-xs font-medium text-destructive">Ditolak</p>
                                <p className="text-2xl font-bold text-destructive mt-1">{stats.rejected}</p>
                            </div>
                            <div className="size-9 rounded-lg bg-destructive/10 flex items-center justify-center text-destructive">
                                <XCircle className="size-5" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filter and Search Bar */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-1 flex-wrap items-center gap-2">
                        <div className="relative w-full sm:w-72">
                            <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                            <Input
                                placeholder="Cari nama perusahaan, NPWP, email..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="pl-9 h-9 text-sm"
                            />
                        </div>

                        <div className="w-full sm:w-52">
                            <Select
                                value={statusVerifikasi}
                                onValueChange={handleStatusChange}
                            >
                                <SelectTrigger className="h-9 text-sm">
                                    <SelectValue placeholder="Status Verifikasi" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Semua Status</SelectItem>
                                    <SelectItem value="pending">Menunggu Verifikasi</SelectItem>
                                    <SelectItem value="approved">Disetujui</SelectItem>
                                    <SelectItem value="rejected">Ditolak</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {isFiltered && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={handleReset}
                                className="h-9 px-2.5 text-xs text-muted-foreground hover:text-foreground"
                            >
                                <FilterX className="mr-1.5 size-3.5" />
                                Reset Filter
                            </Button>
                        )}
                    </div>
                </div>

                {/* Data Table */}
                <div className="rounded-lg border bg-card shadow-2xs">
                    <DataTable
                        table={table}
                        columns={columns}
                        loading={loading}
                    />
                    <div className="border-t p-4">
                        <DataTablePagination
                            table={table}
                            meta={meta}
                            onPageChange={(page) => applyFilters({ page })}
                            onPerPageChange={(per_page) => applyFilters({ per_page, page: 1 })}
                        />
                    </div>
                </div>
            </div>

            {/* Dialogs */}
            <ApprovalDialog
                tenant={selectedTenant}
                open={approveDialogOpen}
                onOpenChange={setApproveDialogOpen}
            />

            <RejectionDialog
                tenant={selectedTenant}
                open={rejectDialogOpen}
                onOpenChange={setRejectDialogOpen}
            />

            <DetailDialog
                tenant={tenants?.data?.find((t) => t.uuid === selectedTenant?.uuid) ?? selectedTenant}
                open={detailDialogOpen}
                onOpenChange={setDetailDialogOpen}
                onApprove={handleOpenApprove}
                onReject={handleOpenReject}
            />
        </DashboardLayout>
    );
}

