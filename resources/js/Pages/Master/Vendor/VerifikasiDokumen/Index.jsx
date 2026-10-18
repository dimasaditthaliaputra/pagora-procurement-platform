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
    ShieldAlert,
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

export default function VerifikasiDokumenIndex({
    documents,
    filters = {},
}) {
    const data = documents?.data ?? [];
    const meta = documents?.meta ?? {
        current_page: documents?.current_page ?? 1,
        last_page: documents?.last_page ?? 1,
        per_page: documents?.per_page ?? 10,
        total: documents?.total ?? data.length,
        from: documents?.from ?? 1,
        to: documents?.to ?? data.length,
    };

    const [search, setSearch] = useState(filters.search ?? '');
    const [statusVerifikasi, setStatusVerifikasi] = useState(
        filters.status_verifikasi ?? 'all'
    );
    const [loading, setLoading] = useState(false);

    // Dialog state
    const [selectedDoc, setSelectedDoc] = useState(null);
    const [approveDialogOpen, setApproveDialogOpen] = useState(false);
    const [rejectDialogOpen, setRejectDialogOpen] = useState(false);
    const [detailDialogOpen, setDetailDialogOpen] = useState(false);

    const isFirstRender = useRef(true);

    // Sync state when props change
    useEffect(() => {
        setSearch(filters.search ?? '');
        setStatusVerifikasi(filters.status_verifikasi ?? 'all');
    }, [filters.search, filters.status_verifikasi]);

    const applyFilters = (newFilters = {}) => {
        const merged = {
            search: search.trim() || undefined,
            status_verifikasi: statusVerifikasi !== 'all' ? statusVerifikasi : undefined,
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

        router.get('/master/vendor/verifikasi-dokumen', cleanParams, {
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
        applyFilters({ status_verifikasi: val !== 'all' ? val : undefined });
    };

    const handleReset = () => {
        setSearch('');
        setStatusVerifikasi('all');
        router.get('/master/vendor/verifikasi-dokumen', {}, {
            preserveState: true,
            replace: true,
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    };

    const isFiltered = search !== '' || (statusVerifikasi !== 'all' && statusVerifikasi !== '');

    // Action handlers for dialogs
    const handleOpenApprove = (doc) => {
        setSelectedDoc(doc);
        setApproveDialogOpen(true);
    };

    const handleOpenReject = (doc) => {
        setSelectedDoc(doc);
        setRejectDialogOpen(true);
    };

    const handleOpenDetail = (doc) => {
        setSelectedDoc(doc);
        setDetailDialogOpen(true);
    };

    // Columns
    const columns = useMemo(
        () =>
            getColumns({
                onApprove: handleOpenApprove,
                onReject: handleOpenReject,
                onViewDetail: handleOpenDetail,
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
        { label: 'Data Vendor', href: '/master/vendor/verifikasi-dokumen' },
        { label: 'Verifikasi Dokumen' },
    ];

    // Count stats from current dataset for summary cards
    const stats = useMemo(() => {
        const pendingCount = data.filter((d) => d.status_verifikasi === 'pending').length;
        const approvedCount = data.filter((d) => d.status_verifikasi === 'approved').length;
        const rejectedCount = data.filter((d) => d.status_verifikasi === 'rejected').length;
        return {
            total: meta.total,
            pending: pendingCount,
            approved: approvedCount,
            rejected: rejectedCount,
        };
    }, [data, meta.total]);

    return (
        <DashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Verifikasi Dokumen Vendor - Master Data" />

            <div className="flex flex-col gap-6">
                {/* Header Section */}
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <div className="flex size-9 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:bg-emerald-950/50">
                                <FileCheck2 className="size-5" />
                            </div>
                            <h1 className="text-2xl font-bold tracking-tight text-foreground">
                                Verifikasi Legalitas Vendor
                            </h1>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Validasi dokumen legalitas dan perizinan calon vendor rekanan PAGORA.
                        </p>
                    </div>
                </div>

                {/* Stats Summary Cards */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <Card className="border-border/60 shadow-2xs">
                        <CardContent className="p-4 flex items-center justify-between">
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">Total Dokumen</p>
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
                                placeholder="Cari nama vendor, email, nomor..."
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
                document={selectedDoc}
                open={approveDialogOpen}
                onOpenChange={setApproveDialogOpen}
            />

            <RejectionDialog
                document={selectedDoc}
                open={rejectDialogOpen}
                onOpenChange={setRejectDialogOpen}
            />

            <DetailDialog
                document={selectedDoc}
                open={detailDialogOpen}
                onOpenChange={setDetailDialogOpen}
                onApprove={handleOpenApprove}
                onReject={handleOpenReject}
            />
        </DashboardLayout>
    );
}
