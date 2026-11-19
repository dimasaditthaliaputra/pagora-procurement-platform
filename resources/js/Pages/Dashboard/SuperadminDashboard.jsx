import { Link } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    ArrowRight,
    ArrowUpRight,
    Ban,
    Boxes,
    Building2,
    CheckCircle2,
    Clock,
    CreditCard,
    Database,
    FileCheck2,
    Globe,
    Handshake,
    Layers,
    Scale,
    Settings,
    ShieldAlert,
    ShieldCheck,
    ShoppingBag,
    TrendingUp,
    Users,
} from 'lucide-react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';
import { cn } from '@/lib/utils';

function formatRelativeDate(isoString) {
    if (!isoString) {
        return '-';
    }

    try {
        const date = new Date(isoString);
        return new Intl.DateTimeFormat('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(date);
    } catch {
        return isoString;
    }
}

function ActivityToneIcon({ status }) {
    switch (status) {
        case 'success':
            return (
                <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                    <CheckCircle2 className="size-4" />
                </div>
            );
        case 'warning':
            return (
                <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400">
                    <Clock className="size-4" />
                </div>
            );
        case 'danger':
            return (
                <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-400">
                    <ShieldAlert className="size-4" />
                </div>
            );
        default:
            return (
                <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-sky-500/10 text-sky-600 dark:text-sky-400">
                    <Activity className="size-4" />
                </div>
            );
    }
}

const ROLE_COLORS = {
    superadmin: 'bg-violet-500',
    owner: 'bg-emerald-500',
    manajemen: 'bg-sky-500',
    purchasing: 'bg-amber-500',
    qc_gudang: 'bg-orange-500',
    vendor: 'bg-teal-500',
};

const QUICK_MODULES = [
    {
        title: 'Verifikasi Tenant',
        description: 'Tinjau legalitas pendaftaran perusahaan buyer',
        href: '/master/tenant/verifikasi-dokumen',
        icon: Building2,
        tone: 'text-emerald-600 dark:text-emerald-400 bg-emerald-500/10',
    },
    {
        title: 'Verifikasi Vendor',
        description: 'Validasi NPWP, NIB, KTP, SPPKP & Akta pemasok',
        href: '/master/vendor/verifikasi-dokumen',
        icon: FileCheck2,
        tone: 'text-sky-600 dark:text-sky-400 bg-sky-500/10',
    },
    {
        title: 'Direktori & Blokir Vendor',
        description: 'Kelola status aktif & pemblokiran global pemasok',
        href: '/master/vendor',
        icon: ShieldAlert,
        tone: 'text-amber-600 dark:text-amber-400 bg-amber-500/10',
    },
    {
        title: 'Master Komoditas',
        description: 'Atur katalog komoditas, kategori & kode API BI',
        href: '/master/komoditas',
        icon: Boxes,
        tone: 'text-violet-600 dark:text-violet-400 bg-violet-500/10',
    },
    {
        title: 'Manajemen Pengguna',
        description: 'Kelola seluruh akun pengguna lintas organisasi',
        href: '/manajemen/pengguna',
        icon: Users,
        tone: 'text-blue-600 dark:text-blue-400 bg-blue-500/10',
    },
    {
        title: 'Role & Hak Akses',
        description: 'Konfigurasi matriks izin & otorisasi peran',
        href: '/manajemen/role-akses',
        icon: ShieldCheck,
        tone: 'text-teal-600 dark:text-teal-400 bg-teal-500/10',
    },
];

export default function SuperadminDashboard({ userName, dashboard }) {
    const summary = dashboard?.summary ?? {};
    const tenantsStats = summary.tenants ?? {
        total: 0,
        pending: 0,
        approved: 0,
        rejected: 0,
        active: 0,
    };
    const vendorsStats = summary.vendors ?? {
        total: 0,
        pending: 0,
        approved: 0,
        rejected: 0,
        active: 0,
        blocked: 0,
        pending_documents: 0,
    };
    const commoditiesStats = summary.commodities ?? {
        total: 0,
        active: 0,
        inactive: 0,
        with_api_code: 0,
        categories_total: 0,
        categories_active: 0,
    };
    const usersStats = summary.users ?? {
        total: 0,
        active: 0,
        inactive: 0,
    };
    const ecosystemStats = summary.ecosystem ?? {
        partnerships_total: 0,
        partnerships_agreed: 0,
        partnerships_pending: 0,
        purchase_orders_total: 0,
        disputes_open: 0,
    };

    const pendingTenants = Array.isArray(dashboard?.pending_tenants)
        ? dashboard.pending_tenants
        : [];
    const pendingVendors = Array.isArray(dashboard?.pending_vendors)
        ? dashboard.pending_vendors
        : [];
    const blockedVendors = Array.isArray(dashboard?.blocked_vendors)
        ? dashboard.blocked_vendors
        : [];
    const roleDistribution = Array.isArray(dashboard?.role_distribution)
        ? dashboard.role_distribution
        : [];
    const categoryDistribution = Array.isArray(dashboard?.category_distribution)
        ? dashboard.category_distribution
        : [];
    const monthlyGrowth = Array.isArray(dashboard?.monthly_growth)
        ? dashboard.monthly_growth
        : [];
    const recentActivities = Array.isArray(dashboard?.recent_activities)
        ? dashboard.recent_activities
        : [];
    const integrations = dashboard?.system_integrations ?? {
        app_name: 'PAGORA',
        tagline: 'E-Procurement',
        bi_active: true,
        bi_base_url: 'https://www.bi.go.id/hargapangan',
        bi_sync_schedule: 'harian',
        pg_provider: 'midtrans',
        pg_mode: 'sandbox',
        pg_configured: false,
    };

    const totalPendingVerifications =
        (tenantsStats.pending ?? 0) + (vendorsStats.pending ?? 0);

    const maxMonthlyValue = Math.max(
        1,
        ...monthlyGrowth.flatMap((m) => [
            m.tenants_count ?? 0,
            m.vendors_count ?? 0,
            m.purchase_orders_count ?? 0,
        ])
    );

    const totalUsersForBar = Math.max(1, usersStats.total || 1);

    return (
        <div className="flex flex-col gap-6 pb-8">
            {/* Header & Status Integrasi */}
            <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div className="space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge
                            variant="outline"
                            className="border-primary/30 bg-primary/5 text-primary font-semibold text-xs px-2.5 py-0.5"
                        >
                            <ShieldCheck className="size-3.5 mr-1" />
                            Pusat Kendali Superadmin
                        </Badge>
                        <Badge
                            variant="outline"
                            className={cn(
                                'text-xs font-medium px-2.5 py-0.5',
                                integrations.bi_active
                                    ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                    : 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-400'
                            )}
                        >
                            <Globe className="size-3 mr-1" />
                            API Harga Pangan BI:{' '}
                            {integrations.bi_active
                                ? `Aktif (${integrations.bi_sync_schedule})`
                                : 'Nonaktif'}
                        </Badge>
                        <Badge
                            variant="outline"
                            className="border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-400 text-xs font-medium px-2.5 py-0.5 uppercase"
                        >
                            <CreditCard className="size-3 mr-1" />
                            {integrations.pg_provider} ({integrations.pg_mode})
                        </Badge>
                    </div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        Selamat Datang, {userName || 'Superadmin'}!
                    </h1>
                    <p className="text-sm text-muted-foreground max-w-3xl">
                        Pantau antrean verifikasi registrasi Tenant &amp; legalitas Vendor,
                        kelola katalog master komoditas global, serta awasi kesehatan ekosistem
                        B2B {integrations.app_name}.
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Button asChild variant="outline" size="sm" className="h-9 gap-1.5">
                        <Link href="/pengaturan/integrasi">
                            <Settings className="size-4" />
                            <span>Pengaturan &amp; Integrasi</span>
                        </Link>
                    </Button>
                    <Button asChild size="sm" className="h-9 gap-1.5">
                        <Link href="/master/komoditas">
                            <Boxes className="size-4" />
                            <span>Master Komoditas</span>
                        </Link>
                    </Button>
                </div>
            </div>

            {/* Banner Peringatan Antrean Verifikasi bila ada yang Pending */}
            {(totalPendingVerifications > 0 || vendorsStats.pending_documents > 0) && (
                <div className="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-start gap-3">
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-500/20 text-amber-700 dark:text-amber-300 mt-0.5 sm:mt-0">
                            <AlertTriangle className="size-5" />
                        </div>
                        <div className="space-y-0.5">
                            <p className="text-sm font-semibold text-amber-950 dark:text-amber-200">
                                Terdapat Antrean Verifikasi Akun &amp; Dokumen Legalitas
                            </p>
                            <p className="text-xs text-amber-800/90 dark:text-amber-300/90">
                                {tenantsStats.pending > 0 && (
                                    <span>
                                        <strong>{tenantsStats.pending} Perusahaan Buyer (Tenant)</strong>{' '}
                                        menunggu persetujuan
                                    </span>
                                )}
                                {tenantsStats.pending > 0 &&
                                    (vendorsStats.pending > 0 ||
                                        vendorsStats.pending_documents > 0) &&
                                    ' serta '}
                                {(vendorsStats.pending > 0 ||
                                    vendorsStats.pending_documents > 0) && (
                                    <span>
                                        <strong>{vendorsStats.pending} Rekanan Vendor</strong> (
                                        {vendorsStats.pending_documents} berkas legalitas) menunggu
                                        pemeriksaan
                                    </span>
                                )}
                                . Akun baru belum dapat masuk ke sistem sebelum diverifikasi.
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2 shrink-0">
                        {tenantsStats.pending > 0 && (
                            <Button
                                asChild
                                size="sm"
                                variant="outline"
                                className="h-8 text-xs border-amber-600/30 bg-background hover:bg-amber-500/10"
                            >
                                <Link href="/master/tenant/verifikasi-dokumen?verification_status=pending">
                                    Verifikasi Tenant ({tenantsStats.pending})
                                </Link>
                            </Button>
                        )}
                        {(vendorsStats.pending > 0 ||
                            vendorsStats.pending_documents > 0) && (
                            <Button
                                asChild
                                size="sm"
                                className="h-8 text-xs bg-amber-600 hover:bg-amber-700 text-white"
                            >
                                <Link href="/master/vendor/verifikasi-dokumen?verification_status=pending">
                                    Verifikasi Vendor ({vendorsStats.pending})
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>
            )}

            {/* 4 Kartu KPI Utama Superadmin */}
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {/* KPI 1: Perusahaan Buyer (Tenant) */}
                <Card className="border-border/80 shadow-xs">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Perusahaan Buyer (Tenant)
                        </CardTitle>
                        <div className="flex size-8 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                            <Building2 className="size-4" />
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="flex items-baseline justify-between">
                            <div>
                                <div className="text-2xl font-bold tracking-tight">
                                    {tenantsStats.total}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    {tenantsStats.active} perusahaan aktif beroperasi
                                </p>
                            </div>
                            {tenantsStats.pending > 0 && (
                                <Badge className="bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30 text-[11px]">
                                    {tenantsStats.pending} Pending
                                </Badge>
                            )}
                        </div>

                        <div className="grid grid-cols-3 gap-1.5 pt-2 border-t text-[11px]">
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Disetujui</span>
                                <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                                    {tenantsStats.approved}
                                </span>
                            </div>
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Menunggu</span>
                                <span className="font-semibold text-amber-600 dark:text-amber-400">
                                    {tenantsStats.pending}
                                </span>
                            </div>
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Ditolak</span>
                                <span className="font-semibold text-rose-600 dark:text-rose-400">
                                    {tenantsStats.rejected}
                                </span>
                            </div>
                        </div>

                        <Link
                            href="/master/tenant/verifikasi-dokumen"
                            className="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline pt-0.5"
                        >
                            <span>Buka Verifikasi Tenant</span>
                            <ArrowUpRight className="size-3.5" />
                        </Link>
                    </CardContent>
                </Card>

                {/* KPI 2: Rekanan Pemasok (Vendor) */}
                <Card className="border-border/80 shadow-xs">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Rekanan Pemasok (Vendor)
                        </CardTitle>
                        <div className="flex size-8 items-center justify-center rounded-lg bg-sky-500/10 text-sky-600 dark:text-sky-400">
                            <FileCheck2 className="size-4" />
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="flex items-baseline justify-between">
                            <div>
                                <div className="text-2xl font-bold tracking-tight">
                                    {vendorsStats.total}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    {vendorsStats.active} vendor aktif •{' '}
                                    {vendorsStats.pending_documents} berkas pending
                                </p>
                            </div>
                            {vendorsStats.blocked > 0 && (
                                <Badge
                                    variant="destructive"
                                    className="text-[11px]"
                                >
                                    {vendorsStats.blocked} Diblokir
                                </Badge>
                            )}
                        </div>

                        <div className="grid grid-cols-3 gap-1.5 pt-2 border-t text-[11px]">
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Disetujui</span>
                                <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                                    {vendorsStats.approved}
                                </span>
                            </div>
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Menunggu</span>
                                <span className="font-semibold text-amber-600 dark:text-amber-400">
                                    {vendorsStats.pending}
                                </span>
                            </div>
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Diblokir</span>
                                <span className="font-semibold text-rose-600 dark:text-rose-400">
                                    {vendorsStats.blocked}
                                </span>
                            </div>
                        </div>

                        <Link
                            href="/master/vendor/verifikasi-dokumen"
                            className="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline pt-0.5"
                        >
                            <span>Buka Verifikasi Legalitas Vendor</span>
                            <ArrowUpRight className="size-3.5" />
                        </Link>
                    </CardContent>
                </Card>

                {/* KPI 3: Master Komoditas & Kategori Global */}
                <Card className="border-border/80 shadow-xs">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Katalog Komoditas Global
                        </CardTitle>
                        <div className="flex size-8 items-center justify-center rounded-lg bg-violet-500/10 text-violet-600 dark:text-violet-400">
                            <Boxes className="size-4" />
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="flex items-baseline justify-between">
                            <div>
                                <div className="text-2xl font-bold tracking-tight">
                                    {commoditiesStats.total}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Dalam {commoditiesStats.categories_total} kategori komoditas global
                                </p>
                            </div>
                            <Badge variant="secondary" className="text-[11px]">
                                {commoditiesStats.with_api_code} Kode BI
                            </Badge>
                        </div>

                        <div className="grid grid-cols-3 gap-1.5 pt-2 border-t text-[11px]">
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Aktif</span>
                                <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                                    {commoditiesStats.active}
                                </span>
                            </div>
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Nonaktif</span>
                                <span className="font-semibold text-muted-foreground">
                                    {commoditiesStats.inactive}
                                </span>
                            </div>
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Kategori</span>
                                <span className="font-semibold text-foreground">
                                    {commoditiesStats.categories_active}/
                                    {commoditiesStats.categories_total}
                                </span>
                            </div>
                        </div>

                        <Link
                            href="/master/komoditas"
                            className="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline pt-0.5"
                        >
                            <span>Kelola Master Komoditas</span>
                            <ArrowUpRight className="size-3.5" />
                        </Link>
                    </CardContent>
                </Card>

                {/* KPI 4: Pengguna & Aktivitas Ekosistem B2B */}
                <Card className="border-border/80 shadow-xs">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Pengguna &amp; Ekosistem B2B
                        </CardTitle>
                        <div className="flex size-8 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400">
                            <Users className="size-4" />
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="flex items-baseline justify-between">
                            <div>
                                <div className="text-2xl font-bold tracking-tight">
                                    {usersStats.total}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    {usersStats.active} akun aktif • {usersStats.inactive}{' '}
                                    nonaktif
                                </p>
                            </div>
                            <Badge variant="outline" className="text-[11px]">
                                6 Role Sistem
                            </Badge>
                        </div>

                        <div className="grid grid-cols-3 gap-1.5 pt-2 border-t text-[11px]">
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Kemitraan</span>
                                <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                                    {ecosystemStats.partnerships_agreed}
                                </span>
                            </div>
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Total PO</span>
                                <span className="font-semibold text-foreground">
                                    {ecosystemStats.purchase_orders_total}
                                </span>
                            </div>
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Sengketa</span>
                                <span className="font-semibold text-amber-600 dark:text-amber-400">
                                    {ecosystemStats.disputes_open}
                                </span>
                            </div>
                        </div>

                        <Link
                            href="/manajemen/pengguna"
                            className="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline pt-0.5"
                        >
                            <span>Kelola Pengguna &amp; Akses</span>
                            <ArrowUpRight className="size-3.5" />
                        </Link>
                    </CardContent>
                </Card>
            </div>

            {/* Pintasan Modul Tata Kelola Superadmin */}
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                {QUICK_MODULES.map((mod) => (
                    <Link
                        key={mod.href}
                        href={mod.href}
                        className="group flex flex-col justify-between gap-2 rounded-xl border border-border/80 bg-card p-3.5 shadow-2xs transition-all hover:border-primary/40 hover:shadow-sm"
                    >
                        <div className="flex items-center justify-between">
                            <div
                                className={cn(
                                    'flex size-8 items-center justify-center rounded-lg',
                                    mod.tone
                                )}
                            >
                                <mod.icon className="size-4" />
                            </div>
                            <ArrowRight className="size-3.5 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-foreground" />
                        </div>
                        <div>
                            <p className="text-xs font-semibold text-foreground">
                                {mod.title}
                            </p>
                            <p className="text-[11px] text-muted-foreground line-clamp-2 mt-0.5">
                                {mod.description}
                            </p>
                        </div>
                    </Link>
                ))}
            </div>

            {/* Antrean Verifikasi Prioritas: Tenant vs Vendor (UC-21, FR-36) */}
            <div className="grid gap-4 lg:grid-cols-2">
                {/* Antrean Verifikasi Tenant */}
                <Card className="border-border/80 shadow-xs flex flex-col">
                    <CardHeader className="flex flex-row items-start justify-between gap-3 pb-3">
                        <div className="space-y-1">
                            <div className="flex items-center gap-2">
                                <CardTitle className="text-base">
                                    Antrean Verifikasi Perusahaan Buyer (Tenant)
                                </CardTitle>
                                <Badge
                                    variant={
                                        tenantsStats.pending > 0 ? 'default' : 'secondary'
                                    }
                                    className="text-xs"
                                >
                                    {tenantsStats.pending} Menunggu
                                </Badge>
                            </div>
                            <CardDescription className="text-xs">
                                Pendaftaran perusahaan pembeli baru yang menunggu validasi
                                dokumen legalitas (NPWP, NIB, KTP).
                            </CardDescription>
                        </div>
                        <Button asChild variant="outline" size="sm" className="h-8 text-xs shrink-0">
                            <Link href="/master/tenant/verifikasi-dokumen">
                                Lihat Semua
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent className="flex-1">
                        {pendingTenants.length === 0 ? (
                            <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-8 px-4 text-center">
                                <CheckCircle2 className="size-8 text-emerald-500/70 mb-2" />
                                <p className="text-sm font-medium text-foreground">
                                    Tidak Ada Antrean Verifikasi Tenant
                                </p>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    Seluruh pengajuan pendaftaran perusahaan buyer telah selesai
                                    ditinjau.
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-2.5">
                                {pendingTenants.map((tenant) => (
                                    <div
                                        key={tenant.uuid}
                                        className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-xl border bg-muted/20 p-3 transition-colors hover:bg-muted/40"
                                    >
                                        <div className="min-w-0 space-y-1">
                                            <div className="flex items-center gap-2">
                                                <p className="text-sm font-semibold text-foreground truncate">
                                                    {tenant.company_name}
                                                </p>
                                                <Badge
                                                    variant="outline"
                                                    className="text-[10px] shrink-0"
                                                >
                                                    {tenant.documents_count} Berkas PDF
                                                </Badge>
                                            </div>
                                            <p className="text-xs text-muted-foreground truncate">
                                                Owner: {tenant.owner_name || '-'} (
                                                {tenant.owner_email || '-'})
                                            </p>
                                            <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted-foreground">
                                                {tenant.tax_number && (
                                                    <span>NPWP: {tenant.tax_number}</span>
                                                )}
                                                <span>
                                                    Terdaftar:{' '}
                                                    {formatRelativeDate(tenant.created_at)}
                                                </span>
                                            </div>
                                        </div>
                                        <Button
                                            asChild
                                            size="sm"
                                            className="h-8 text-xs shrink-0"
                                        >
                                            <Link
                                                href={`/master/tenant/verifikasi-dokumen?search=${encodeURIComponent(
                                                    tenant.company_name
                                                )}`}
                                            >
                                                Tinjau
                                            </Link>
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Antrean Verifikasi Legalitas Vendor */}
                <Card className="border-border/80 shadow-xs flex flex-col">
                    <CardHeader className="flex flex-row items-start justify-between gap-3 pb-3">
                        <div className="space-y-1">
                            <div className="flex items-center gap-2">
                                <CardTitle className="text-base">
                                    Antrean Verifikasi Legalitas Vendor
                                </CardTitle>
                                <Badge
                                    variant={
                                        vendorsStats.pending > 0 ? 'default' : 'secondary'
                                    }
                                    className="text-xs"
                                >
                                    {vendorsStats.pending} Vendor (
                                    {vendorsStats.pending_documents} Berkas)
                                </Badge>
                            </div>
                            <CardDescription className="text-xs">
                                Pemasok baru yang mengajukan berkas legalitas perusahaan untuk
                                diverifikasi.
                            </CardDescription>
                        </div>
                        <Button asChild variant="outline" size="sm" className="h-8 text-xs shrink-0">
                            <Link href="/master/vendor/verifikasi-dokumen">
                                Lihat Semua
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent className="flex-1">
                        {pendingVendors.length === 0 ? (
                            <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-8 px-4 text-center">
                                <CheckCircle2 className="size-8 text-emerald-500/70 mb-2" />
                                <p className="text-sm font-medium text-foreground">
                                    Tidak Ada Antrean Legalitas Vendor
                                </p>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    Seluruh dokumen legalitas rekanan pemasok telah diverifikasi.
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-2.5">
                                {pendingVendors.map((vendor) => (
                                    <div
                                        key={vendor.uuid}
                                        className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-xl border bg-muted/20 p-3 transition-colors hover:bg-muted/40"
                                    >
                                        <div className="min-w-0 space-y-1">
                                            <div className="flex flex-wrap items-center gap-1.5">
                                                <p className="text-sm font-semibold text-foreground truncate">
                                                    {vendor.company_name}
                                                </p>
                                                {vendor.business_entity && (
                                                    <Badge
                                                        variant="secondary"
                                                        className="text-[10px] uppercase"
                                                    >
                                                        {vendor.business_entity}
                                                    </Badge>
                                                )}
                                                <Badge
                                                    variant="outline"
                                                    className="text-[10px]"
                                                >
                                                    {vendor.is_pkp ? 'PKP (PPN)' : 'Non-PKP'}
                                                </Badge>
                                                <Badge className="bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30 text-[10px]">
                                                    {vendor.pending_documents_count}/
                                                    {vendor.total_documents_count} Berkas Pending
                                                </Badge>
                                            </div>
                                            <p className="text-xs text-muted-foreground truncate">
                                                {vendor.contact_email || '-'} •{' '}
                                                {vendor.phone || '-'}
                                            </p>
                                            <div className="flex flex-wrap items-center gap-2 text-[11px] text-muted-foreground">
                                                {Array.isArray(vendor.commodity_categories) &&
                                                    vendor.commodity_categories.length > 0 && (
                                                        <span className="truncate max-w-[260px]">
                                                            Kategori:{' '}
                                                            {vendor.commodity_categories.join(
                                                                ', '
                                                            )}
                                                        </span>
                                                    )}
                                                <span>
                                                    • {formatRelativeDate(vendor.created_at)}
                                                </span>
                                            </div>
                                        </div>
                                        <Button
                                            asChild
                                            size="sm"
                                            className="h-8 text-xs shrink-0"
                                        >
                                            <Link
                                                href={`/master/vendor/verifikasi-dokumen?search=${encodeURIComponent(
                                                    vendor.company_name
                                                )}`}
                                            >
                                                Tinjau
                                            </Link>
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Tren Pertumbuhan Platform (6 Bulan) & Distribusi Pengguna per Role */}
            <div className="grid gap-4 lg:grid-cols-7">
                {/* Grafik Pertumbuhan Platform 6 Bulan Terakhir */}
                <Card className="lg:col-span-4 border-border/80 shadow-xs">
                    <CardHeader className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pb-2">
                        <div>
                            <CardTitle className="text-base flex items-center gap-2">
                                <TrendingUp className="size-4 text-primary" />
                                <span>Pertumbuhan Ekosistem Platform (6 Bulan Terakhir)</span>
                            </CardTitle>
                            <CardDescription className="text-xs">
                                Perbandingan akuisisi Tenant baru, Vendor baru, dan volume
                                Purchase Order lintas platform per bulan.
                            </CardDescription>
                        </div>
                        <div className="flex flex-wrap items-center gap-3 text-xs">
                            <span className="inline-flex items-center gap-1.5">
                                <span className="size-2.5 rounded-xs bg-emerald-500" />
                                <span className="text-muted-foreground">Tenant</span>
                            </span>
                            <span className="inline-flex items-center gap-1.5">
                                <span className="size-2.5 rounded-xs bg-sky-500" />
                                <span className="text-muted-foreground">Vendor</span>
                            </span>
                            <span className="inline-flex items-center gap-1.5">
                                <span className="size-2.5 rounded-xs bg-violet-500" />
                                <span className="text-muted-foreground">Purchase Order</span>
                            </span>
                        </div>
                    </CardHeader>
                    <CardContent className="pt-4">
                        <div className="grid grid-cols-6 gap-2 sm:gap-4 items-end h-[210px] pt-6 pb-2 px-2 rounded-xl border bg-muted/15">
                            {monthlyGrowth.map((item) => {
                                const tenantPct = Math.round(
                                    ((item.tenants_count ?? 0) / maxMonthlyValue) * 100
                                );
                                const vendorPct = Math.round(
                                    ((item.vendors_count ?? 0) / maxMonthlyValue) * 100
                                );
                                const poPct = Math.round(
                                    ((item.purchase_orders_count ?? 0) / maxMonthlyValue) *
                                        100
                                );

                                return (
                                    <div
                                        key={item.month_key}
                                        className="flex flex-col items-center h-full justify-end gap-2"
                                    >
                                        <div className="flex items-end justify-center gap-1 sm:gap-1.5 w-full h-[140px]">
                                            {/* Tenant Bar */}
                                            <div className="flex flex-col items-center justify-end h-full w-3 sm:w-4">
                                                <span className="text-[10px] font-medium text-muted-foreground mb-1">
                                                    {item.tenants_count || 0}
                                                </span>
                                                <div
                                                    style={{
                                                        height: `${Math.max(
                                                            6,
                                                            tenantPct
                                                        )}%`,
                                                    }}
                                                    title={`Tenant Baru: ${item.tenants_count}`}
                                                    className="w-full rounded-t-sm bg-emerald-500 transition-all"
                                                />
                                            </div>

                                            {/* Vendor Bar */}
                                            <div className="flex flex-col items-center justify-end h-full w-3 sm:w-4">
                                                <span className="text-[10px] font-medium text-muted-foreground mb-1">
                                                    {item.vendors_count || 0}
                                                </span>
                                                <div
                                                    style={{
                                                        height: `${Math.max(
                                                            6,
                                                            vendorPct
                                                        )}%`,
                                                    }}
                                                    title={`Vendor Baru: ${item.vendors_count}`}
                                                    className="w-full rounded-t-sm bg-sky-500 transition-all"
                                                />
                                            </div>

                                            {/* PO Bar */}
                                            <div className="flex flex-col items-center justify-end h-full w-3 sm:w-4">
                                                <span className="text-[10px] font-medium text-muted-foreground mb-1">
                                                    {item.purchase_orders_count || 0}
                                                </span>
                                                <div
                                                    style={{
                                                        height: `${Math.max(6, poPct)}%`,
                                                    }}
                                                    title={`Purchase Order: ${item.purchase_orders_count}`}
                                                    className="w-full rounded-t-sm bg-violet-500 transition-all"
                                                />
                                            </div>
                                        </div>

                                        <span className="text-[11px] font-medium text-muted-foreground truncate">
                                            {item.label}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>

                        <div className="mt-4 grid grid-cols-3 gap-3 text-xs">
                            <div className="flex items-center gap-2.5 rounded-lg border bg-card p-2.5">
                                <Handshake className="size-4 text-emerald-600 shrink-0" />
                                <div>
                                    <p className="text-muted-foreground text-[11px]">
                                        Kemitraan B2B Disepakati
                                    </p>
                                    <p className="font-bold text-sm">
                                        {ecosystemStats.partnerships_agreed}{' '}
                                        <span className="text-[11px] font-normal text-muted-foreground">
                                            ({ecosystemStats.partnerships_pending} pending)
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-2.5 rounded-lg border bg-card p-2.5">
                                <ShoppingBag className="size-4 text-violet-600 shrink-0" />
                                <div>
                                    <p className="text-muted-foreground text-[11px]">
                                        Total Purchase Order
                                    </p>
                                    <p className="font-bold text-sm">
                                        {ecosystemStats.purchase_orders_total} Dokumen
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-2.5 rounded-lg border bg-card p-2.5">
                                <Scale className="size-4 text-amber-600 shrink-0" />
                                <div>
                                    <p className="text-muted-foreground text-[11px]">
                                        Sengketa Terbuka / Mediasi
                                    </p>
                                    <p className="font-bold text-sm">
                                        {ecosystemStats.disputes_open} Kasus
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Distribusi Pengguna per Role */}
                <Card className="lg:col-span-3 border-border/80 shadow-xs">
                    <CardHeader className="flex flex-row items-start justify-between gap-2 pb-3">
                        <div>
                            <CardTitle className="text-base">
                                Distribusi Pengguna per Role
                            </CardTitle>
                            <CardDescription className="text-xs">
                                Sebaran akun pengguna terdaftar pada 6 peran sistem.
                            </CardDescription>
                        </div>
                        <Button asChild variant="outline" size="sm" className="h-8 text-xs">
                            <Link href="/manajemen/role-akses">Hak Akses</Link>
                        </Button>
                    </CardHeader>
                    <CardContent className="space-y-3.5">
                        {roleDistribution.map((item) => {
                            const percentage = Math.round(
                                ((item.total ?? 0) / totalUsersForBar) * 100
                            );
                            const barColor =
                                ROLE_COLORS[item.role] || 'bg-primary';

                            return (
                                <div key={item.role} className="space-y-1.5">
                                    <div className="flex items-center justify-between text-xs">
                                        <span className="font-medium text-foreground">
                                            {item.label}
                                        </span>
                                        <span className="text-muted-foreground">
                                            <strong className="text-foreground">
                                                {item.total}
                                            </strong>{' '}
                                            akun ({item.active} aktif)
                                        </span>
                                    </div>
                                    <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                                        <div
                                            className={cn(
                                                'h-full rounded-full transition-all',
                                                barColor
                                            )}
                                            style={{
                                                width: `${
                                                    item.total > 0
                                                        ? Math.max(4, percentage)
                                                        : 0
                                                }%`,
                                            }}
                                        />
                                    </div>
                                </div>
                            );
                        })}

                        <div className="pt-2 border-t flex items-center justify-between text-xs text-muted-foreground">
                            <span>Total Akun Terdaftar</span>
                            <Link
                                href="/manajemen/pengguna"
                                className="font-semibold text-primary hover:underline inline-flex items-center gap-1"
                            >
                                <span>Kelola {usersStats.total} Pengguna</span>
                                <ArrowUpRight className="size-3.5" />
                            </Link>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Baris Bawah: Kategori Komoditas Global, Pengawasan Blokir Global Vendor, & Log Aktivitas */}
            <div className="grid gap-4 lg:grid-cols-3">
                {/* Kategori Komoditas Global (UC-09, FR-11) */}
                <Card className="border-border/80 shadow-xs flex flex-col">
                    <CardHeader className="flex flex-row items-start justify-between gap-2 pb-3">
                        <div>
                            <CardTitle className="text-base flex items-center gap-2">
                                <Layers className="size-4 text-violet-600" />
                                <span>Kategori Komoditas Global</span>
                            </CardTitle>
                            <CardDescription className="text-xs">
                                Distribusi komoditas &amp; pemasok per kategori master.
                            </CardDescription>
                        </div>
                        <Button asChild variant="outline" size="sm" className="h-8 text-xs">
                            <Link href="/master/komoditas">Kelola</Link>
                        </Button>
                    </CardHeader>
                    <CardContent className="flex-1">
                        {categoryDistribution.length === 0 ? (
                            <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-8 px-4 text-center">
                                <Database className="size-7 text-muted-foreground/50 mb-2" />
                                <p className="text-xs text-muted-foreground">
                                    Belum ada kategori komoditas global terdaftar.
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-2.5">
                                {categoryDistribution.map((cat) => (
                                    <div
                                        key={cat.uuid}
                                        className="flex items-center justify-between gap-3 rounded-lg border bg-muted/20 px-3 py-2 text-xs"
                                    >
                                        <div className="min-w-0">
                                            <div className="flex items-center gap-1.5">
                                                <span className="font-semibold text-foreground truncate">
                                                    {cat.name}
                                                </span>
                                                {!cat.is_active && (
                                                    <Badge
                                                        variant="secondary"
                                                        className="text-[10px] py-0 h-4"
                                                    >
                                                        Nonaktif
                                                    </Badge>
                                                )}
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2 shrink-0">
                                            <Badge variant="outline" className="text-[10px]">
                                                {cat.commodities_count} Komoditas
                                            </Badge>
                                            <Badge variant="secondary" className="text-[10px]">
                                                {cat.vendors_count} Vendor
                                            </Badge>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Pengawasan Blokir Global Vendor (UC-19, FR-37) */}
                <Card className="border-border/80 shadow-xs flex flex-col">
                    <CardHeader className="flex flex-row items-start justify-between gap-2 pb-3">
                        <div>
                            <CardTitle className="text-base flex items-center gap-2">
                                <Ban className="size-4 text-rose-600" />
                                <span>Vendor Diblokir Global</span>
                            </CardTitle>
                            <CardDescription className="text-xs">
                                Pemasok yang aksesnya ditangguhkan di seluruh platform.
                            </CardDescription>
                        </div>
                        <Button asChild variant="outline" size="sm" className="h-8 text-xs">
                            <Link href="/master/vendor">Direktori</Link>
                        </Button>
                    </CardHeader>
                    <CardContent className="flex-1">
                        {blockedVendors.length === 0 ? (
                            <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-8 px-4 text-center">
                                <ShieldCheck className="size-8 text-emerald-500/70 mb-2" />
                                <p className="text-sm font-medium text-foreground">
                                    Tidak Ada Vendor Diblokir Global
                                </p>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    Seluruh rekanan pemasok terverifikasi berada dalam status
                                    patuh.
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-2.5">
                                {blockedVendors.map((vendor) => (
                                    <div
                                        key={vendor.uuid}
                                        className="rounded-lg border border-rose-500/25 bg-rose-500/5 p-3 space-y-1 text-xs"
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="font-semibold text-foreground truncate">
                                                {vendor.company_name}
                                            </span>
                                            <Badge
                                                variant="destructive"
                                                className="text-[10px] py-0 h-4 shrink-0"
                                            >
                                                Diblokir
                                            </Badge>
                                        </div>
                                        <p className="text-[11px] text-muted-foreground line-clamp-2">
                                            Alasan: {vendor.block_reason}
                                        </p>
                                        <p className="text-[10px] text-muted-foreground">
                                            Oleh {vendor.blocked_by_name} •{' '}
                                            {formatRelativeDate(vendor.blocked_at)}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Aktivitas Tata Kelola Platform Terbaru */}
                <Card className="border-border/80 shadow-xs flex flex-col">
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base flex items-center gap-2">
                            <Activity className="size-4 text-primary" />
                            <span>Aktivitas Tata Kelola Terbaru</span>
                        </CardTitle>
                        <CardDescription className="text-xs">
                            Jejak perubahan verifikasi Tenant, Vendor, dan Master Data.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex-1">
                        {recentActivities.length === 0 ? (
                            <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-8 px-4 text-center">
                                <Clock className="size-7 text-muted-foreground/50 mb-2" />
                                <p className="text-xs text-muted-foreground">
                                    Belum ada riwayat aktivitas tata kelola terbaru.
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-3">
                                {recentActivities.slice(0, 6).map((act) => (
                                    <Link
                                        key={act.uuid}
                                        href={act.action_url || '/dashboard'}
                                        className="group flex items-start gap-2.5 rounded-lg p-1.5 -mx-1.5 transition-colors hover:bg-muted/50"
                                    >
                                        <ActivityToneIcon status={act.status} />
                                        <div className="min-w-0 flex-1 space-y-0.5">
                                            <div className="flex items-center justify-between gap-2">
                                                <span className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                                                    {act.category}
                                                </span>
                                                <span className="text-[10px] text-muted-foreground shrink-0">
                                                    {formatRelativeDate(act.occurred_at)}
                                                </span>
                                            </div>
                                            <p className="text-xs font-semibold text-foreground truncate group-hover:text-primary">
                                                {act.title}
                                            </p>
                                            <p className="text-[11px] text-muted-foreground line-clamp-1">
                                                {act.description}
                                            </p>
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
