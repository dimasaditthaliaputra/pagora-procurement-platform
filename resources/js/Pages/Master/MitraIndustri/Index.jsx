import { useState, useEffect, useMemo, useRef } from 'react';
import { Head, router } from '@inertiajs/react';
import { useReactTable, getCoreRowModel } from '@tanstack/react-table';
import {
    Building2,
    CheckCircle2,
    Clock,
    FilterX,
    Handshake,
    MailPlus,
    Search,
    ShieldAlert,
    ShieldCheck,
    Store,
    Users,
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
import { Badge } from '@/Components/ui/badge';
import { cn } from '@/lib/utils';

// Import Tenant Dialogs & Columns
import { getColumns as getTenantColumns } from '@/Pages/Master/Tenant/VerifikasiDokumen/columns';
import { ApprovalDialog as TenantApprovalDialog } from '@/Pages/Master/Tenant/VerifikasiDokumen/components/ApprovalDialog';
import { RejectionDialog as TenantRejectionDialog } from '@/Pages/Master/Tenant/VerifikasiDokumen/components/RejectionDialog';
import { DetailDialog as TenantDetailDialog } from '@/Pages/Master/Tenant/VerifikasiDokumen/components/DetailDialog';

// Import Vendor Verification Dialogs & Columns
import { getColumns as getVendorDocColumns } from '@/Pages/Master/Vendor/VerifikasiDokumen/columns';
import { ApprovalDialog as VendorDocApprovalDialog } from '@/Pages/Master/Vendor/VerifikasiDokumen/components/ApprovalDialog';
import { RejectionDialog as VendorDocRejectionDialog } from '@/Pages/Master/Vendor/VerifikasiDokumen/components/RejectionDialog';
import { DetailDialog as VendorDocDetailDialog } from '@/Pages/Master/Vendor/VerifikasiDokumen/components/DetailDialog';

// Import Vendor Rekanan Dialogs & Columns
import { getColumns as getVendorRekananColumns } from '@/Pages/Master/Vendor/columns';
import { BlokirDialog as VendorBlokirDialog } from '@/Pages/Master/Vendor/components/BlokirDialog';
import { BukaBlokirDialog as VendorBukaBlokirDialog } from '@/Pages/Master/Vendor/components/BukaBlokirDialog';
import { VendorDetailDialog } from '@/Pages/Master/Vendor/components/VendorDetailDialog';
import { UndangVendorDialog } from '@/Pages/Master/Vendor/components/UndangVendorDialog';

export default function MitraIndustriIndex({
    active_tab = 'tenant',
    active_vendor_section = 'verifikasi',
    tenants,
    tenant_stats = {},
    vendor_stats = {},
    vendor_docs,
    vendor_rekanan,
    kategori_options = [],
    can_manage = true,
    filters = {},
}) {
    const isFirstRender = useRef(true);

    // Filter states
    const [search, setSearch] = useState(filters.search ?? '');
    const [statusFilter, setStatusFilter] = useState(filters.status ?? 'all');
    const [statusAktifFilter, setStatusAktifFilter] = useState(filters.is_active ?? 'all');
    const [loading, setLoading] = useState(false);

    // Sync filters when props change
    useEffect(() => {
        setSearch(filters.search ?? '');
        setStatusFilter(filters.status ?? 'all');
        setStatusAktifFilter(filters.is_active ?? 'all');
    }, [filters.search, filters.status, filters.is_active]);

    // Dialog states for Tenant
    const [selectedTenant, setSelectedTenant] = useState(null);
    const [tenantApproveOpen, setTenantApproveOpen] = useState(false);
    const [tenantRejectOpen, setTenantRejectOpen] = useState(false);
    const [tenantDetailOpen, setTenantDetailOpen] = useState(false);

    // Dialog states for Vendor Doc
    const [selectedVendorDoc, setSelectedVendorDoc] = useState(null);
    const [selectedVendorForDoc, setSelectedVendorForDoc] = useState(null);
    const [vendorDocApproveOpen, setVendorDocApproveOpen] = useState(false);
    const [vendorDocRejectOpen, setVendorDocRejectOpen] = useState(false);
    const [vendorDocDetailOpen, setVendorDocDetailOpen] = useState(false);

    // Dialog states for Vendor Rekanan
    const [selectedVendorRekanan, setSelectedVendorRekanan] = useState(null);
    const [vendorBlokirOpen, setVendorBlokirOpen] = useState(false);
    const [vendorBukaBlokirOpen, setVendorBukaBlokirOpen] = useState(false);
    const [vendorDetailOpen, setVendorDetailOpen] = useState(false);
    const [undangVendorOpen, setUndangVendorOpen] = useState(false);

    // Apply URL filter change
    const applyFilters = (overrides = {}) => {
        const nextParams = {
            tab: active_tab,
            vendor_section: active_vendor_section,
            search: search.trim() || undefined,
            status: statusFilter !== 'all' ? statusFilter : undefined,
            is_active: statusAktifFilter !== 'all' ? statusAktifFilter : undefined,
            per_page: filters.per_page ?? 10,
            page: 1,
            ...overrides,
        };

        // Clean undefined
        Object.keys(nextParams).forEach((k) => {
            if (nextParams[k] === undefined) delete nextParams[k];
        });

        router.get('/manajemen/mitra-industri', nextParams, {
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
            if (search !== (filters.search ?? '')) {
                applyFilters({ search: search.trim() || undefined, page: 1 });
            }
        }, 350);

        return () => clearTimeout(timer);
    }, [search]);

    const handleTabChange = (nextTab) => {
        setSearch('');
        setStatusFilter('all');
        setStatusAktifFilter('all');
        router.get(
            '/manajemen/mitra-industri',
            {
                tab: nextTab,
                vendor_section: nextTab === 'vendor' ? active_vendor_section : undefined,
                page: 1,
            },
            {
                preserveState: false,
                preserveScroll: true,
            }
        );
    };

    const handleVendorSectionChange = (nextSection) => {
        setSearch('');
        setStatusFilter('all');
        setStatusAktifFilter('all');
        router.get(
            '/manajemen/mitra-industri',
            {
                tab: 'vendor',
                vendor_section: nextSection,
                page: 1,
            },
            {
                preserveState: false,
                preserveScroll: true,
            }
        );
    };

    const handleResetFilter = () => {
        setSearch('');
        setStatusFilter('all');
        setStatusAktifFilter('all');
        router.get(
            '/manajemen/mitra-industri',
            {
                tab: active_tab,
                vendor_section: active_tab === 'vendor' ? active_vendor_section : undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    };

    // Tenant Table Setup
    const tenantData = tenants?.data ?? [];
    const tenantMeta = tenants?.meta ?? {
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: tenantData.length,
    };
    const tenantColumns = useMemo(
        () =>
            getTenantColumns({
                onViewDetail: (tenant) => {
                    setSelectedTenant(tenant);
                    setTenantDetailOpen(true);
                },
                onApprove: (tenant) => {
                    setSelectedTenant(tenant);
                    setTenantApproveOpen(true);
                },
                onReject: (tenant) => {
                    setSelectedTenant(tenant);
                    setTenantRejectOpen(true);
                },
            }),
        []
    );
    const tenantTable = useReactTable({
        data: tenantData,
        columns: tenantColumns,
        getRowId: (row) => row.uuid,
        getCoreRowModel: getCoreRowModel(),
        manualPagination: true,
        pageCount: tenantMeta.last_page,
    });

    // Vendor Doc Table Setup
    const vendorDocData = vendor_docs?.data ?? [];
    const vendorDocMeta = vendor_docs?.meta ?? {
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: vendorDocData.length,
    };
    const vendorDocColumns = useMemo(
        () =>
            getVendorDocColumns({
                onViewDetail: (vendor) => {
                    setSelectedVendorForDoc(vendor);
                    setVendorDocDetailOpen(true);
                },
            }),
        []
    );
    const vendorDocTable = useReactTable({
        data: vendorDocData,
        columns: vendorDocColumns,
        getRowId: (row) => row.uuid,
        getCoreRowModel: getCoreRowModel(),
        manualPagination: true,
        pageCount: vendorDocMeta.last_page,
    });

    // Vendor Rekanan Table Setup
    const vendorRekananData = vendor_rekanan?.data ?? [];
    const vendorRekananMeta = vendor_rekanan?.meta ?? {
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: vendorRekananData.length,
    };
    const vendorRekananColumns = useMemo(
        () =>
            getVendorRekananColumns({
                onBlock: (vendor) => {
                    setSelectedVendorRekanan(vendor);
                    setVendorBlokirOpen(true);
                },
                onUnblock: (vendor) => {
                    setSelectedVendorRekanan(vendor);
                    setVendorBukaBlokirOpen(true);
                },
                onViewDetail: (vendor) => {
                    setSelectedVendorRekanan(vendor);
                    setVendorDetailOpen(true);
                },
                canManage: can_manage,
                isTenant: false,
            }),
        [can_manage]
    );
    const vendorRekananTable = useReactTable({
        data: vendorRekananData,
        columns: vendorRekananColumns,
        getRowId: (row) => row.uuid,
        getCoreRowModel: getCoreRowModel(),
        manualPagination: true,
        pageCount: vendorRekananMeta.last_page,
    });

    const hasFilter =
        Boolean(search) || statusFilter !== 'all' || statusAktifFilter !== 'all';

    return (
        <DashboardLayout
            breadcrumbs={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'Manajemen', href: '#' },
                { label: 'Mitra Industri', href: '/manajemen/mitra-industri' },
            ]}
        >
            <Head title="Manajemen Mitra Industri - Superadmin - PAGORA" />

            <div className="flex flex-col gap-6">
                {/* Header */}
                <div className="flex flex-col gap-2">
                    <h1 className="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                        <Handshake className="size-6 text-primary shrink-0" />
                        Manajemen Mitra Industri
                    </h1>
                    <p className="text-sm text-muted-foreground max-w-3xl">
                        Pusat kendali mitra platform: kelola verifikasi legalitas tenant perusahaan buyer
                        serta tata kelola vendor rekanan industri secara terpadu.
                    </p>
                </div>

                {/* Primary Tabs: Manajemen Tenant vs Manajemen Vendor */}
                <div className="flex items-center gap-2 border-b pb-3">
                    <button
                        type="button"
                        onClick={() => handleTabChange('tenant')}
                        className={cn(
                            'inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold transition-all cursor-pointer',
                            active_tab === 'tenant'
                                ? 'bg-primary text-primary-foreground shadow-xs'
                                : 'bg-muted/60 text-muted-foreground hover:bg-muted hover:text-foreground'
                        )}
                    >
                        <Building2 className="size-4 shrink-0" />
                        <span>Manajemen Tenant</span>
                        {typeof tenant_stats?.pending_tenants === 'number' && tenant_stats.pending_tenants > 0 && (
                            <Badge variant="destructive" className="ml-1 px-1.5 py-0 text-[10px] leading-4">
                                {tenant_stats.pending_tenants}
                            </Badge>
                        )}
                    </button>

                    <button
                        type="button"
                        onClick={() => handleTabChange('vendor')}
                        className={cn(
                            'inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold transition-all cursor-pointer',
                            active_tab === 'vendor'
                                ? 'bg-primary text-primary-foreground shadow-xs'
                                : 'bg-muted/60 text-muted-foreground hover:bg-muted hover:text-foreground'
                        )}
                    >
                        <Store className="size-4 shrink-0" />
                        <span>Manajemen Vendor</span>
                        {typeof vendor_stats?.pending_review_vendors === 'number' && vendor_stats.pending_review_vendors > 0 && (
                            <Badge variant="destructive" className="ml-1 px-1.5 py-0 text-[10px] leading-4">
                                {vendor_stats.pending_review_vendors}
                            </Badge>
                        )}
                    </button>
                </div>

                {/* TAB 1: MANAJEMEN TENANT */}
                {active_tab === 'tenant' && (
                    <div className="flex flex-col gap-6">
                        {/* 4 Stat Cards */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <Card className="shadow-2xs">
                                <CardContent className="p-4 flex items-center justify-between">
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground">Total Tenant Terdaftar</p>
                                        <p className="text-2xl font-bold tracking-tight text-foreground mt-1 tabular-nums">
                                            {tenant_stats.total_tenants ?? 0}
                                        </p>
                                    </div>
                                    <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary shrink-0">
                                        <Building2 className="size-5" />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="shadow-2xs">
                                <CardContent className="p-4 flex items-center justify-between">
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground">Tenant Terverifikasi</p>
                                        <p className="text-2xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400 mt-1 tabular-nums">
                                            {tenant_stats.verified_tenants ?? 0}
                                        </p>
                                    </div>
                                    <div className="flex size-10 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 shrink-0">
                                        <CheckCircle2 className="size-5" />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="shadow-2xs">
                                <CardContent className="p-4 flex items-center justify-between">
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground">Menunggu Verifikasi</p>
                                        <p className="text-2xl font-bold tracking-tight text-amber-600 dark:text-amber-400 mt-1 tabular-nums">
                                            {tenant_stats.pending_tenants ?? 0}
                                        </p>
                                    </div>
                                    <div className="flex size-10 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 shrink-0">
                                        <Clock className="size-5" />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="shadow-2xs">
                                <CardContent className="p-4 flex items-center justify-between">
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground">Pendaftaran Ditolak</p>
                                        <p className="text-2xl font-bold tracking-tight text-destructive mt-1 tabular-nums">
                                            {tenant_stats.rejected_tenants ?? 0}
                                        </p>
                                    </div>
                                    <div className="flex size-10 items-center justify-center rounded-lg bg-destructive/10 text-destructive shrink-0">
                                        <XCircle className="size-5" />
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Filter Bar */}
                        <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                            <div className="flex flex-1 items-center gap-2 max-w-sm">
                                <div className="relative w-full">
                                    <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground pointer-events-none" />
                                    <Input
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        placeholder="Cari tenant, email, NPWP..."
                                        className="pl-9 h-9 text-xs"
                                    />
                                </div>
                            </div>

                            <div className="flex items-center gap-2">
                                <Select
                                    value={statusFilter}
                                    onValueChange={(val) => {
                                        setStatusFilter(val);
                                        applyFilters({ status: val, page: 1 });
                                    }}
                                >
                                    <SelectTrigger className="w-[180px] h-9 text-xs">
                                        <SelectValue placeholder="Status Verifikasi" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Semua Status</SelectItem>
                                        <SelectItem value="pending">Menunggu Verifikasi</SelectItem>
                                        <SelectItem value="approved">Terverifikasi</SelectItem>
                                        <SelectItem value="rejected">Ditolak</SelectItem>
                                    </SelectContent>
                                </Select>

                                {hasFilter && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={handleResetFilter}
                                        className="h-9 px-2 text-xs text-muted-foreground hover:text-foreground"
                                    >
                                        <FilterX className="size-4 mr-1" />
                                        Reset
                                    </Button>
                                )}
                            </div>
                        </div>

                        {/* Tenant DataTable */}
                        <div className="rounded-xl border bg-card shadow-2xs overflow-hidden">
                            <DataTable
                                table={tenantTable}
                                emptyMessage={hasFilter ? 'Tenant tidak ditemukan dengan filter saat ini.' : 'Belum ada pendaftaran tenant.'}
                            />
                            <div className="p-3 border-t">
                                <DataTablePagination table={tenantTable} />
                            </div>
                        </div>

                        {/* Tenant Dialogs */}
                        <TenantApprovalDialog
                            tenant={selectedTenant}
                            open={tenantApproveOpen}
                            onOpenChange={setTenantApproveOpen}
                            actionUrl={`/manajemen/mitra-industri/tenant/${selectedTenant?.uuid}/verifikasi`}
                        />
                        <TenantRejectionDialog
                            tenant={selectedTenant}
                            open={tenantRejectOpen}
                            onOpenChange={setTenantRejectOpen}
                            actionUrl={`/manajemen/mitra-industri/tenant/${selectedTenant?.uuid}/verifikasi`}
                        />
                        <TenantDetailDialog
                            tenant={selectedTenant}
                            open={tenantDetailOpen}
                            onOpenChange={setTenantDetailOpen}
                            onApprove={(tenant) => {
                                setSelectedTenant(tenant);
                                setTenantDetailOpen(false);
                                setTenantApproveOpen(true);
                            }}
                            onReject={(tenant) => {
                                setSelectedTenant(tenant);
                                setTenantDetailOpen(false);
                                setTenantRejectOpen(true);
                            }}
                        />
                    </div>
                )}

                {/* TAB 2: MANAJEMEN VENDOR (MENYATUKAN VERIFIKASI VENDOR & DATA REKANAN VENDOR) */}
                {active_tab === 'vendor' && (
                    <div className="flex flex-col gap-6">
                        {/* 4 Stat Cards for Vendor */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <Card className="shadow-2xs">
                                <CardContent className="p-4 flex items-center justify-between">
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground">Total Vendor</p>
                                        <p className="text-2xl font-bold tracking-tight text-foreground mt-1 tabular-nums">
                                            {vendor_stats.total_vendors ?? 0}
                                        </p>
                                    </div>
                                    <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary shrink-0">
                                        <Store className="size-5" />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="shadow-2xs">
                                <CardContent className="p-4 flex items-center justify-between">
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground">Vendor Aktif</p>
                                        <p className="text-2xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400 mt-1 tabular-nums">
                                            {vendor_stats.active_vendors ?? 0}
                                        </p>
                                    </div>
                                    <div className="flex size-10 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 shrink-0">
                                        <CheckCircle2 className="size-5" />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="shadow-2xs">
                                <CardContent className="p-4 flex items-center justify-between">
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground">Vendor Diblokir</p>
                                        <p className="text-2xl font-bold tracking-tight text-destructive mt-1 tabular-nums">
                                            {vendor_stats.blocked_vendors ?? 0}
                                        </p>
                                    </div>
                                    <div className="flex size-10 items-center justify-center rounded-lg bg-destructive/10 text-destructive shrink-0">
                                        <ShieldAlert className="size-5" />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="shadow-2xs">
                                <CardContent className="p-4 flex items-center justify-between">
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground">Pending Review</p>
                                        <p className="text-2xl font-bold tracking-tight text-amber-600 dark:text-amber-400 mt-1 tabular-nums">
                                            {vendor_stats.pending_review_vendors ?? 0}
                                        </p>
                                    </div>
                                    <div className="flex size-10 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 shrink-0">
                                        <Clock className="size-5" />
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Sub-Tabs: Verifikasi Legalitas vs Rekanan Vendor */}
                        <div className="flex flex-wrap items-center justify-between gap-3 bg-muted/40 p-1.5 rounded-xl border">
                            <div className="flex items-center gap-1">
                                <button
                                    type="button"
                                    onClick={() => handleVendorSectionChange('verifikasi')}
                                    className={cn(
                                        'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all cursor-pointer',
                                        active_vendor_section === 'verifikasi'
                                            ? 'bg-background text-foreground shadow-2xs border border-border/60'
                                            : 'text-muted-foreground hover:text-foreground hover:bg-background/50'
                                    )}
                                >
                                    <ShieldCheck className="size-3.5" />
                                    <span>Verifikasi Legalitas</span>
                                </button>

                                <button
                                    type="button"
                                    onClick={() => handleVendorSectionChange('rekanan')}
                                    className={cn(
                                        'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all cursor-pointer',
                                        active_vendor_section === 'rekanan'
                                            ? 'bg-background text-foreground shadow-2xs border border-border/60'
                                            : 'text-muted-foreground hover:text-foreground hover:bg-background/50'
                                    )}
                                >
                                    <Building2 className="size-3.5" />
                                    <span>Rekanan Vendor</span>
                                </button>
                            </div>

                            {active_vendor_section === 'rekanan' && can_manage && (
                                <Button
                                    size="sm"
                                    onClick={() => setUndangVendorOpen(true)}
                                    className="h-8 gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white cursor-pointer shadow-2xs text-xs"
                                >
                                    <MailPlus className="size-3.5" />
                                    Undang Vendor Baru
                                </Button>
                            )}
                        </div>

                        {/* SUB-SECTION A: VERIFIKASI LEGALITAS VENDOR */}
                        {active_vendor_section === 'verifikasi' && (
                            <div className="flex flex-col gap-4">
                                <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                                    <div className="flex flex-1 items-center gap-2 max-w-sm">
                                        <div className="relative w-full">
                                            <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground pointer-events-none" />
                                            <Input
                                                value={search}
                                                onChange={(e) => setSearch(e.target.value)}
                                                placeholder="Cari vendor, email, komoditas..."
                                                className="pl-9 h-9 text-xs"
                                            />
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <Select
                                            value={statusFilter}
                                            onValueChange={(val) => {
                                                setStatusFilter(val);
                                                applyFilters({ status: val, page: 1 });
                                            }}
                                        >
                                            <SelectTrigger className="w-[180px] h-9 text-xs">
                                                <SelectValue placeholder="Status Legalitas" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">Semua Status</SelectItem>
                                                <SelectItem value="pending">Menunggu Verifikasi</SelectItem>
                                                <SelectItem value="approved">Terverifikasi</SelectItem>
                                                <SelectItem value="rejected">Ditolak</SelectItem>
                                            </SelectContent>
                                        </Select>

                                        {hasFilter && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={handleResetFilter}
                                                className="h-9 px-2 text-xs text-muted-foreground hover:text-foreground"
                                            >
                                                <FilterX className="size-4 mr-1" />
                                                Reset
                                            </Button>
                                        )}
                                    </div>
                                </div>

                                <div className="rounded-xl border bg-card shadow-2xs overflow-hidden">
                                    <DataTable
                                        table={vendorDocTable}
                                        emptyMessage={hasFilter ? 'Dokumen vendor tidak ditemukan.' : 'Belum ada dokumen legalitas vendor.'}
                                    />
                                    <div className="p-3 border-t">
                                        <DataTablePagination table={vendorDocTable} />
                                    </div>
                                </div>

                                <VendorDocApprovalDialog
                                    document={selectedVendorDoc}
                                    open={vendorDocApproveOpen}
                                    onOpenChange={setVendorDocApproveOpen}
                                    actionUrl={`/manajemen/mitra-industri/vendor/${selectedVendorDoc?.uuid}/verifikasi`}
                                />
                                <VendorDocRejectionDialog
                                    document={selectedVendorDoc}
                                    open={vendorDocRejectOpen}
                                    onOpenChange={setVendorDocRejectOpen}
                                    actionUrl={`/manajemen/mitra-industri/vendor/${selectedVendorDoc?.uuid}/verifikasi`}
                                />
                                <VendorDocDetailDialog
                                    vendor={selectedVendorForDoc}
                                    open={vendorDocDetailOpen}
                                    onOpenChange={setVendorDocDetailOpen}
                                    onApprove={(doc) => {
                                        setSelectedVendorDoc(doc);
                                        setVendorDocApproveOpen(true);
                                    }}
                                    onReject={(doc) => {
                                        setSelectedVendorDoc(doc);
                                        setVendorDocRejectOpen(true);
                                    }}
                                />
                            </div>
                        )}

                        {/* SUB-SECTION B: DATA REKANAN VENDOR */}
                        {active_vendor_section === 'rekanan' && (
                            <div className="flex flex-col gap-4">
                                <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                                    <div className="flex flex-1 items-center gap-2 max-w-sm">
                                        <div className="relative w-full">
                                            <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground pointer-events-none" />
                                            <Input
                                                value={search}
                                                onChange={(e) => setSearch(e.target.value)}
                                                placeholder="Cari rekanan vendor, email, telp..."
                                                className="pl-9 h-9 text-xs"
                                            />
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-2 flex-wrap">
                                        <Select
                                            value={statusAktifFilter}
                                            onValueChange={(val) => {
                                                setStatusAktifFilter(val);
                                                applyFilters({ is_active: val, page: 1 });
                                            }}
                                        >
                                            <SelectTrigger className="w-[140px] h-9 text-xs">
                                                <SelectValue placeholder="Status Rekanan" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">Semua Status</SelectItem>
                                                <SelectItem value="true">Aktif</SelectItem>
                                                <SelectItem value="false">Diblokir</SelectItem>
                                            </SelectContent>
                                        </Select>

                                        <Select
                                            value={statusFilter}
                                            onValueChange={(val) => {
                                                setStatusFilter(val);
                                                applyFilters({ status: val, page: 1 });
                                            }}
                                        >
                                            <SelectTrigger className="w-[150px] h-9 text-xs">
                                                <SelectValue placeholder="Status Legalitas" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">Semua Legalitas</SelectItem>
                                                <SelectItem value="approved">Terverifikasi</SelectItem>
                                                <SelectItem value="pending">Pending</SelectItem>
                                                <SelectItem value="rejected">Ditolak</SelectItem>
                                            </SelectContent>
                                        </Select>

                                        {hasFilter && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={handleResetFilter}
                                                className="h-9 px-2 text-xs text-muted-foreground hover:text-foreground"
                                            >
                                                <FilterX className="size-4 mr-1" />
                                                Reset
                                            </Button>
                                        )}
                                    </div>
                                </div>

                                <div className="rounded-xl border bg-card shadow-2xs overflow-hidden">
                                    <DataTable
                                        table={vendorRekananTable}
                                        emptyMessage={hasFilter ? 'Rekanan vendor tidak ditemukan.' : 'Belum ada data rekanan vendor.'}
                                    />
                                    <div className="p-3 border-t">
                                        <DataTablePagination table={vendorRekananTable} />
                                    </div>
                                </div>

                                <VendorBlokirDialog
                                    vendor={selectedVendorRekanan}
                                    open={vendorBlokirOpen}
                                    onOpenChange={setVendorBlokirOpen}
                                    actionUrl={`/manajemen/mitra-industri/vendor/${selectedVendorRekanan?.uuid}/blokir`}
                                />
                                <VendorBukaBlokirDialog
                                    vendor={selectedVendorRekanan}
                                    open={vendorBukaBlokirOpen}
                                    onOpenChange={setVendorBukaBlokirOpen}
                                    actionUrl={`/manajemen/mitra-industri/vendor/${selectedVendorRekanan?.uuid}/buka-blokir`}
                                />
                                <VendorDetailDialog
                                    vendor={selectedVendorRekanan}
                                    open={vendorDetailOpen}
                                    onOpenChange={setVendorDetailOpen}
                                />
                                <UndangVendorDialog
                                    open={undangVendorOpen}
                                    onOpenChange={setUndangVendorOpen}
                                    kategoriOptions={kategori_options}
                                    actionUrl="/manajemen/mitra-industri/vendor/undang"
                                />
                            </div>
                        )}
                    </div>
                )}
            </div>
        </DashboardLayout>
    );
}

