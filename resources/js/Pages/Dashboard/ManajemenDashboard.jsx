import { Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowUpRight,
    Ban,
    Boxes,
    Building2,
    CheckCircle2,
    ClipboardCheck,
    FileWarning,
    Handshake,
    Scale,
    ShieldAlert,
    Truck,
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

function formatDateTime(isoString) {
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

function formatQty(val, unit = 'kg') {
    const num = Number(val ?? 0);
    const formatted = new Intl.NumberFormat('id-ID', {
        maximumFractionDigits: 2,
    }).format(num);
    return `${formatted} ${unit}`;
}

export default function ManajemenDashboard({ userName, dashboard }) {
    const company = dashboard?.company ?? null;
    const summary = dashboard?.summary ?? {};

    const disputesStats = summary.disputes ?? {
        total: 0,
        open: 0,
        in_mediation: 0,
        resolved: 0,
    };
    const partnershipsStats = summary.partnerships ?? {
        total: 0,
        agreed: 0,
        pending_incoming: 0,
        pending_outgoing: 0,
        blocked: 0,
        invitations_pending: 0,
    };
    const qcStats = summary.qc_inspections ?? {
        total_receipts: 0,
        in_transit_orders: 0,
        accepted_items: 0,
        rejected_items: 0,
    };
    const commoditiesStats = summary.commodities ?? {
        global_active_total: 0,
        tenant_active: 0,
        custom_tolerance_count: 0,
        active_purchase_orders: 0,
    };

    const activeDisputes = Array.isArray(dashboard?.active_disputes)
        ? dashboard.active_disputes
        : [];
    const vendorPartnerships = Array.isArray(dashboard?.vendor_partnerships)
        ? dashboard.vendor_partnerships
        : [];
    const recentQcRejections = Array.isArray(dashboard?.recent_qc_rejections)
        ? dashboard.recent_qc_rejections
        : [];
    const tenantCommodities = Array.isArray(dashboard?.tenant_commodities)
        ? dashboard.tenant_commodities
        : [];
    const monthlyTrend = Array.isArray(dashboard?.monthly_trend)
        ? dashboard.monthly_trend
        : [];

    const activeDisputesCount =
        (disputesStats.open ?? 0) + (disputesStats.in_mediation ?? 0);
    const pendingPartnershipsCount =
        (partnershipsStats.pending_incoming ?? 0) +
        (partnershipsStats.pending_outgoing ?? 0);

    const maxMonthlyVal = Math.max(
        1,
        ...monthlyTrend.flatMap((m) => [
            m.purchase_orders_count ?? 0,
            m.qc_inspections_count ?? 0,
            m.disputes_count ?? 0,
        ])
    );

    return (
        <div className="flex flex-col gap-6 pb-8">
            {/* Header & Identitas Perusahaan Buyer (Tenant) */}
            <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div className="min-w-0 flex-1 space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge
                            variant="outline"
                            className="border-primary/30 bg-primary/5 text-primary font-semibold text-xs px-2.5 py-0.5"
                        >
                            <Scale className="size-3.5 mr-1" />
                            Tata Kelola &amp; Mediasi Tenant (Manajemen)
                        </Badge>
                        {company?.company_name && (
                            <Badge
                                variant="outline"
                                className="border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 text-xs font-medium px-2.5 py-0.5"
                            >
                                <Building2 className="size-3 mr-1" />
                                {company.company_name}
                            </Badge>
                        )}
                        {company?.tax_number && (
                            <Badge variant="secondary" className="text-xs font-mono">
                                NPWP: {company.tax_number}
                            </Badge>
                        )}
                    </div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        Selamat Datang, {userName || 'Manajemen'}!
                    </h1>
                    <p className="text-sm text-muted-foreground max-w-2xl">
                        Pantau kendala pengadaan &amp; mediasi Log Sengketa, kelola kerja sama
                        serta pemblokiran rekanan Vendor, dan awasi hasil inspeksi fisik QC
                        Gudang.
                    </p>
                </div>

                <div className="flex flex-wrap sm:flex-nowrap items-center gap-2 shrink-0">
                    <Button
                        asChild
                        variant="outline"
                        size="sm"
                        className="h-8 px-3 text-xs gap-1.5 whitespace-nowrap"
                    >
                        <Link href="/inspeksi-qc/laporan-kerusakan">
                            <ClipboardCheck className="size-3.5 shrink-0" />
                            <span>Laporan Kerusakan QC</span>
                        </Link>
                    </Button>
                    <Button
                        asChild
                        variant="outline"
                        size="sm"
                        className="h-8 px-3 text-xs gap-1.5 whitespace-nowrap"
                    >
                        <Link href="/master/vendor">
                            <Handshake className="size-3.5 shrink-0" />
                            <span>Rekanan Vendor</span>
                        </Link>
                    </Button>
                    <Button
                        asChild
                        size="sm"
                        className="h-8 px-3 text-xs gap-1.5 whitespace-nowrap"
                    >
                        <Link href="/log-sengketa">
                            <Scale className="size-3.5 shrink-0" />
                            <span>Buka Log Sengketa</span>
                        </Link>
                    </Button>
                </div>
            </div>

            {/* Banner Peringatan Sengketa Pengadaan Aktif */}
            {activeDisputesCount > 0 && (
                <div className="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-start gap-3">
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-500/20 text-amber-700 dark:text-amber-300 mt-0.5 sm:mt-0">
                            <AlertTriangle className="size-5" />
                        </div>
                        <div className="space-y-0.5">
                            <p className="text-sm font-semibold text-amber-950 dark:text-amber-200">
                                Terdapat {activeDisputesCount} Sengketa Pengadaan yang Memerlukan
                                Tinjauan &amp; Mediasi Manajemen
                            </p>
                            <p className="text-xs text-amber-800/90 dark:text-amber-300/90">
                                Rincian:{' '}
                                <strong>{disputesStats.open} sengketa berstatus Terbuka</strong>{' '}
                                (menunggu tinjauan awal) dan{' '}
                                <strong>
                                    {disputesStats.in_mediation} sengketa Dalam Mediasi
                                </strong>
                                . Selama sengketa terbuka, bagian Purchase Order yang terdampak
                                dibekukan.
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2 shrink-0">
                        {disputesStats.open > 0 && (
                            <Button
                                asChild
                                size="sm"
                                className="h-8 text-xs bg-amber-600 hover:bg-amber-700 text-white"
                            >
                                <Link href="/log-sengketa?status=terbuka">
                                    Tinjau Sengketa Terbuka ({disputesStats.open})
                                </Link>
                            </Button>
                        )}
                        {disputesStats.in_mediation > 0 && (
                            <Button
                                asChild
                                size="sm"
                                variant="outline"
                                className="h-8 text-xs border-amber-600/30 bg-background hover:bg-amber-500/10"
                            >
                                <Link href="/log-sengketa?status=dalam_mediasi">
                                    Dalam Mediasi ({disputesStats.in_mediation})
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>
            )}

            {/* 4 Kartu KPI Utama Manajemen */}
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {/* KPI 1: Kendala & Log Sengketa (UC-18) */}
                <Card className="border-border/80 shadow-xs">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Kendala &amp; Log Sengketa
                        </CardTitle>
                        <div className="flex size-8 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400">
                            <Scale className="size-4" />
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="flex items-baseline justify-between">
                            <div>
                                <div className="text-2xl font-bold tracking-tight">
                                    {activeDisputesCount}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Sengketa aktif dari {disputesStats.total} total kasus
                                </p>
                            </div>
                            {disputesStats.open > 0 && (
                                <Badge className="bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-500/30 text-[11px]">
                                    {disputesStats.open} Terbuka
                                </Badge>
                            )}
                        </div>

                        <div className="grid grid-cols-3 gap-1.5 pt-2 border-t text-[11px]">
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Terbuka</span>
                                <span className="font-semibold text-rose-600 dark:text-rose-400">
                                    {disputesStats.open}
                                </span>
                            </div>
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Mediasi</span>
                                <span className="font-semibold text-amber-600 dark:text-amber-400">
                                    {disputesStats.in_mediation}
                                </span>
                            </div>
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Selesai</span>
                                <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                                    {disputesStats.resolved}
                                </span>
                            </div>
                        </div>

                        <Link
                            href="/log-sengketa"
                            className="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline pt-0.5"
                        >
                            <span>Tinjau &amp; Mediasi Sengketa</span>
                            <ArrowUpRight className="size-3.5" />
                        </Link>
                    </CardContent>
                </Card>

                {/* KPI 2: Kemitraan & Rekanan Vendor (UC-10, UC-19, UC-20) */}
                <Card className="border-border/80 shadow-xs">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Mitra Rekanan Vendor
                        </CardTitle>
                        <div className="flex size-8 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                            <Handshake className="size-4" />
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="flex items-baseline justify-between">
                            <div>
                                <div className="text-2xl font-bold tracking-tight">
                                    {partnershipsStats.agreed}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Mitra disepakati •{' '}
                                    {partnershipsStats.invitations_pending} undangan aktif
                                </p>
                            </div>
                            {partnershipsStats.blocked > 0 && (
                                <Badge variant="destructive" className="text-[11px]">
                                    {partnershipsStats.blocked} Diblokir
                                </Badge>
                            )}
                        </div>

                        <div className="grid grid-cols-3 gap-1.5 pt-2 border-t text-[11px]">
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Sepakat</span>
                                <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                                    {partnershipsStats.agreed}
                                </span>
                            </div>
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Menunggu</span>
                                <span className="font-semibold text-amber-600 dark:text-amber-400">
                                    {pendingPartnershipsCount}
                                </span>
                            </div>
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Diblokir</span>
                                <span className="font-semibold text-rose-600 dark:text-rose-400">
                                    {partnershipsStats.blocked}
                                </span>
                            </div>
                        </div>

                        <Link
                            href="/master/vendor"
                            className="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline pt-0.5"
                        >
                            <span>Kelola Rekanan &amp; Undangan Vendor</span>
                            <ArrowUpRight className="size-3.5" />
                        </Link>
                    </CardContent>
                </Card>

                {/* KPI 3: Inspeksi QC Gudang & Laporan Kerusakan (UC-15) */}
                <Card className="border-border/80 shadow-xs">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Inspeksi Fisik QC Gudang
                        </CardTitle>
                        <div className="flex size-8 items-center justify-center rounded-lg bg-sky-500/10 text-sky-600 dark:text-sky-400">
                            <ClipboardCheck className="size-4" />
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="flex items-baseline justify-between">
                            <div>
                                <div className="text-2xl font-bold tracking-tight">
                                    {qcStats.total_receipts}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Berita acara QC • {qcStats.in_transit_orders} PO dalam
                                    pengiriman
                                </p>
                            </div>
                            {qcStats.rejected_items > 0 && (
                                <Badge className="bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-500/30 text-[11px]">
                                    {qcStats.rejected_items} Item Ditolak
                                </Badge>
                            )}
                        </div>

                        <div className="grid grid-cols-3 gap-1.5 pt-2 border-t text-[11px]">
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Disetujui</span>
                                <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                                    {qcStats.accepted_items}
                                </span>
                            </div>
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Ditolak</span>
                                <span className="font-semibold text-rose-600 dark:text-rose-400">
                                    {qcStats.rejected_items}
                                </span>
                            </div>
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">In Transit</span>
                                <span className="font-semibold text-sky-600 dark:text-sky-400">
                                    {qcStats.in_transit_orders}
                                </span>
                            </div>
                        </div>

                        <Link
                            href="/inspeksi-qc"
                            className="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline pt-0.5"
                        >
                            <span>Lihat Riwayat Inspeksi &amp; Kerusakan</span>
                            <ArrowUpRight className="size-3.5" />
                        </Link>
                    </CardContent>
                </Card>

                {/* KPI 4: Komoditas & Toleransi Susut Tenant (UC-09, FR-35) */}
                <Card className="border-border/80 shadow-xs">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Komoditas &amp; Toleransi Susut
                        </CardTitle>
                        <div className="flex size-8 items-center justify-center rounded-lg bg-violet-500/10 text-violet-600 dark:text-violet-400">
                            <Boxes className="size-4" />
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="flex items-baseline justify-between">
                            <div>
                                <div className="text-2xl font-bold tracking-tight">
                                    {commoditiesStats.tenant_active}
                                    <span className="text-sm font-normal text-muted-foreground">
                                        {' '}
                                        / {commoditiesStats.global_active_total}
                                    </span>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Komoditas diaktifkan untuk pengadaan PO
                                </p>
                            </div>
                            <Badge variant="secondary" className="text-[11px]">
                                {commoditiesStats.custom_tolerance_count} Toleransi Khusus
                            </Badge>
                        </div>

                        <div className="grid grid-cols-3 gap-1.5 pt-2 border-t text-[11px]">
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Aktif</span>
                                <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                                    {commoditiesStats.tenant_active}
                                </span>
                            </div>
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Global</span>
                                <span className="font-semibold text-foreground">
                                    {commoditiesStats.global_active_total}
                                </span>
                            </div>
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Total PO</span>
                                <span className="font-semibold text-violet-600 dark:text-violet-400">
                                    {commoditiesStats.active_purchase_orders}
                                </span>
                            </div>
                        </div>

                        <Link
                            href="/notifikasi"
                            className="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline pt-0.5"
                        >
                            <span>Pantau Notifikasi Pengadaan</span>
                            <ArrowUpRight className="size-3.5" />
                        </Link>
                    </CardContent>
                </Card>
            </div>

            {/* Baris Prioritas: Antrean Sengketa Aktif vs Laporan Penolakan & Kerusakan QC */}
            <div className="grid gap-4 lg:grid-cols-2">
                {/* Antrean Sengketa Terbuka & Dalam Mediasi (UC-18) */}
                <Card className="border-border/80 shadow-xs flex flex-col">
                    <CardHeader className="flex flex-row items-start justify-between gap-3 pb-3">
                        <div className="space-y-1">
                            <div className="flex items-center gap-2">
                                <CardTitle className="text-base">
                                    Antrean Tinjauan &amp; Mediasi Sengketa
                                </CardTitle>
                                <Badge
                                    variant={
                                        activeDisputesCount > 0 ? 'default' : 'secondary'
                                    }
                                    className="text-xs"
                                >
                                    {activeDisputesCount} Aktif
                                </Badge>
                            </div>
                            <CardDescription className="text-xs">
                                Kendala pengadaan (penolakan QC / selisih 3-Way Matching) yang
                                membutuhkan catatan tinjauan mediasi.
                            </CardDescription>
                        </div>
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            className="h-8 text-xs shrink-0"
                        >
                            <Link href="/log-sengketa">Semua Sengketa</Link>
                        </Button>
                    </CardHeader>
                    <CardContent className="flex-1">
                        {activeDisputes.length === 0 ? (
                            <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-8 px-4 text-center">
                                <CheckCircle2 className="size-8 text-emerald-500/70 mb-2" />
                                <p className="text-sm font-medium text-foreground">
                                    Tidak Ada Sengketa Pengadaan Terbuka
                                </p>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    Seluruh transaksi pengadaan perusahaan berjalan normal tanpa
                                    kendala aktif.
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-2.5">
                                {activeDisputes.map((dispute) => {
                                    const isOpen = dispute.status === 'terbuka';
                                    return (
                                        <div
                                            key={dispute.uuid}
                                            className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-xl border bg-muted/20 p-3 transition-colors hover:bg-muted/40"
                                        >
                                            <div className="min-w-0 space-y-1">
                                                <div className="flex flex-wrap items-center gap-1.5">
                                                    <span className="font-mono text-xs font-bold text-foreground">
                                                        {dispute.short_code}
                                                    </span>
                                                    <Badge
                                                        className={cn(
                                                            'text-[10px]',
                                                            isOpen
                                                                ? 'bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-500/30'
                                                                : 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30'
                                                        )}
                                                    >
                                                        {dispute.status_label}
                                                    </Badge>
                                                    <Badge
                                                        variant="outline"
                                                        className="text-[10px]"
                                                    >
                                                        {dispute.po_number}
                                                    </Badge>
                                                </div>
                                                <p className="text-xs font-medium text-foreground truncate">
                                                    {dispute.trigger_type_label} •{' '}
                                                    <span className="text-muted-foreground font-normal">
                                                        {dispute.vendor_name}
                                                    </span>
                                                </p>
                                                {dispute.reason && (
                                                    <p className="text-[11px] text-muted-foreground line-clamp-1">
                                                        Alasan: {dispute.reason}
                                                    </p>
                                                )}
                                                <p className="text-[10px] text-muted-foreground">
                                                    Dibuka: {formatDateTime(dispute.opened_at)}
                                                </p>
                                            </div>
                                            <Button
                                                asChild
                                                size="sm"
                                                className="h-8 text-xs shrink-0"
                                            >
                                                <Link href={`/log-sengketa/${dispute.uuid}`}>
                                                    Tinjau
                                                </Link>
                                            </Button>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Laporan Kerusakan & Penolakan Fisik QC Terbaru (UC-15 «extend») */}
                <Card className="border-border/80 shadow-xs flex flex-col">
                    <CardHeader className="flex flex-row items-start justify-between gap-3 pb-3">
                        <div className="space-y-1">
                            <div className="flex items-center gap-2">
                                <CardTitle className="text-base">
                                    Laporan Penolakan &amp; Kerusakan Barang QC
                                </CardTitle>
                                <Badge
                                    variant={
                                        qcStats.rejected_items > 0
                                            ? 'destructive'
                                            : 'secondary'
                                    }
                                    className="text-xs"
                                >
                                    {qcStats.rejected_items} Item Ditolak
                                </Badge>
                            </div>
                            <CardDescription className="text-xs">
                                Item penerimaan gudang yang ditolak karena mutu atau susut
                                melebihi toleransi.
                            </CardDescription>
                        </div>
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            className="h-8 text-xs shrink-0"
                        >
                            <Link href="/inspeksi-qc/laporan-kerusakan">
                                Semua Laporan
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent className="flex-1">
                        {recentQcRejections.length === 0 ? (
                            <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-8 px-4 text-center">
                                <CheckCircle2 className="size-8 text-emerald-500/70 mb-2" />
                                <p className="text-sm font-medium text-foreground">
                                    Belum Ada Penolakan Barang QC
                                </p>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    Seluruh barang yang diterima di gudang memenuhi standar mutu
                                    dan toleransi susut.
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-2.5">
                                {recentQcRejections.map((item) => (
                                    <div
                                        key={item.uuid}
                                        className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-xl border bg-muted/20 p-3 transition-colors hover:bg-muted/40"
                                    >
                                        <div className="min-w-0 space-y-1">
                                            <div className="flex flex-wrap items-center gap-1.5">
                                                <p className="text-sm font-semibold text-foreground truncate">
                                                    {item.commodity_name}
                                                </p>
                                                <Badge
                                                    variant="outline"
                                                    className="text-[10px]"
                                                >
                                                    {item.po_number}
                                                </Badge>
                                                <Badge
                                                    variant="secondary"
                                                    className="text-[10px]"
                                                >
                                                    {item.photos_count} Foto Bukti
                                                </Badge>
                                            </div>
                                            <p className="text-xs text-muted-foreground truncate">
                                                Vendor: {item.vendor_name} • Diterima{' '}
                                                <strong className="text-foreground">
                                                    {formatQty(
                                                        item.received_quantity,
                                                        item.unit
                                                    )}
                                                </strong>{' '}
                                                dari{' '}
                                                {formatQty(item.ordered_quantity, item.unit)}
                                            </p>
                                            {item.notes && (
                                                <p className="text-[11px] text-rose-600 dark:text-rose-400 line-clamp-1">
                                                    Catatan QC: {item.notes}
                                                </p>
                                            )}
                                            <p className="text-[10px] text-muted-foreground">
                                                Diperiksa: {formatDateTime(item.inspected_at)}
                                            </p>
                                        </div>
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                            className="h-8 text-xs shrink-0"
                                        >
                                            <Link
                                                href={`/inspeksi-qc/laporan-kerusakan/${item.uuid}`}
                                            >
                                                Detail Bukti
                                            </Link>
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Grafik Tren Operasional 6 Bulan & Status Mitra Rekanan Vendor */}
            <div className="grid gap-4 lg:grid-cols-7">
                {/* Grafik Tren 6 Bulan Terakhir */}
                <Card className="lg:col-span-4 border-border/80 shadow-xs">
                    <CardHeader className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pb-2">
                        <div>
                            <CardTitle className="text-base">
                                Tren Pengadaan, Inspeksi QC &amp; Sengketa (6 Bulan Terakhir)
                            </CardTitle>
                            <CardDescription className="text-xs">
                                Volume aktivitas Purchase Order, pemeriksaan fisik gudang, dan
                                pembukaan sengketa perusahaan Anda per bulan.
                            </CardDescription>
                        </div>
                        <div className="flex flex-wrap items-center gap-3 text-xs">
                            <span className="inline-flex items-center gap-1.5">
                                <span className="size-2.5 rounded-xs bg-emerald-500" />
                                <span className="text-muted-foreground">Purchase Order</span>
                            </span>
                            <span className="inline-flex items-center gap-1.5">
                                <span className="size-2.5 rounded-xs bg-sky-500" />
                                <span className="text-muted-foreground">Inspeksi QC</span>
                            </span>
                            <span className="inline-flex items-center gap-1.5">
                                <span className="size-2.5 rounded-xs bg-amber-500" />
                                <span className="text-muted-foreground">Sengketa</span>
                            </span>
                        </div>
                    </CardHeader>
                    <CardContent className="pt-4">
                        <div className="grid grid-cols-6 gap-2 sm:gap-4 items-end h-[210px] pt-6 pb-2 px-2 rounded-xl border bg-muted/15">
                            {monthlyTrend.map((item) => {
                                const poPct = Math.round(
                                    ((item.purchase_orders_count ?? 0) / maxMonthlyVal) * 100
                                );
                                const qcPct = Math.round(
                                    ((item.qc_inspections_count ?? 0) / maxMonthlyVal) * 100
                                );
                                const disputePct = Math.round(
                                    ((item.disputes_count ?? 0) / maxMonthlyVal) * 100
                                );

                                return (
                                    <div
                                        key={item.month_key}
                                        className="flex flex-col items-center h-full justify-end gap-2"
                                    >
                                        <div className="flex items-end justify-center gap-1 sm:gap-1.5 w-full h-[140px]">
                                            <div className="flex flex-col items-center justify-end h-full w-3 sm:w-4">
                                                <span className="text-[10px] font-medium text-muted-foreground mb-1">
                                                    {item.purchase_orders_count || 0}
                                                </span>
                                                <div
                                                    style={{
                                                        height: `${Math.max(6, poPct)}%`,
                                                    }}
                                                    title={`Purchase Order: ${item.purchase_orders_count}`}
                                                    className="w-full rounded-t-sm bg-emerald-500 transition-all"
                                                />
                                            </div>

                                            <div className="flex flex-col items-center justify-end h-full w-3 sm:w-4">
                                                <span className="text-[10px] font-medium text-muted-foreground mb-1">
                                                    {item.qc_inspections_count || 0}
                                                </span>
                                                <div
                                                    style={{
                                                        height: `${Math.max(6, qcPct)}%`,
                                                    }}
                                                    title={`Inspeksi QC: ${item.qc_inspections_count}`}
                                                    className="w-full rounded-t-sm bg-sky-500 transition-all"
                                                />
                                            </div>

                                            <div className="flex flex-col items-center justify-end h-full w-3 sm:w-4">
                                                <span className="text-[10px] font-medium text-muted-foreground mb-1">
                                                    {item.disputes_count || 0}
                                                </span>
                                                <div
                                                    style={{
                                                        height: `${Math.max(
                                                            6,
                                                            disputePct
                                                        )}%`,
                                                    }}
                                                    title={`Sengketa: ${item.disputes_count}`}
                                                    className="w-full rounded-t-sm bg-amber-500 transition-all"
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
                                <Truck className="size-4 text-sky-600 shrink-0" />
                                <div>
                                    <p className="text-muted-foreground text-[11px]">
                                        PO Dalam Pengiriman
                                    </p>
                                    <p className="font-bold text-sm">
                                        {qcStats.in_transit_orders} Pesanan
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-2.5 rounded-lg border bg-card p-2.5">
                                <FileWarning className="size-4 text-rose-600 shrink-0" />
                                <div>
                                    <p className="text-muted-foreground text-[11px]">
                                        Item Ditolak QC
                                    </p>
                                    <p className="font-bold text-sm">
                                        {qcStats.rejected_items} Item
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-2.5 rounded-lg border bg-card p-2.5">
                                <CheckCircle2 className="size-4 text-emerald-600 shrink-0" />
                                <div>
                                    <p className="text-muted-foreground text-[11px]">
                                        Sengketa Diselesaikan
                                    </p>
                                    <p className="font-bold text-sm">
                                        {disputesStats.resolved} Kasus
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Status Kemitraan & Pemblokiran Rekanan Vendor (UC-10, UC-19) */}
                <Card className="lg:col-span-3 border-border/80 shadow-xs flex flex-col">
                    <CardHeader className="flex flex-row items-start justify-between gap-2 pb-3">
                        <div>
                            <CardTitle className="text-base">
                                Status Kemitraan Rekanan Vendor
                            </CardTitle>
                            <CardDescription className="text-xs">
                                Mitra pemasok aktif, pengajuan menunggu, dan blokir tingkat
                                Tenant.
                            </CardDescription>
                        </div>
                        <Button asChild variant="outline" size="sm" className="h-8 text-xs">
                            <Link href="/master/vendor">Kelola</Link>
                        </Button>
                    </CardHeader>
                    <CardContent className="flex-1">
                        {vendorPartnerships.length === 0 ? (
                            <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-8 px-4 text-center">
                                <Handshake className="size-8 text-muted-foreground/50 mb-2" />
                                <p className="text-sm font-medium text-foreground">
                                    Belum Ada Kemitraan Vendor
                                </p>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    Undang vendor baru atau kelola rekanan pemasok di menu Data
                                    Rekanan Vendor.
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-2.5">
                                {vendorPartnerships.map((partner) => {
                                    const isBlocked = partner.status === 'blocked';
                                    const isPending = partner.status === 'pending';
                                    const isAgreed = partner.status === 'agreed';

                                    return (
                                        <div
                                            key={partner.uuid}
                                            className="rounded-xl border bg-muted/20 p-3 space-y-1 text-xs"
                                        >
                                            <div className="flex items-center justify-between gap-2">
                                                <div className="flex items-center gap-1.5 min-w-0">
                                                    <span className="font-semibold text-foreground truncate">
                                                        {partner.vendor_name}
                                                    </span>
                                                    {partner.vendor_entity && (
                                                        <Badge
                                                            variant="secondary"
                                                            className="text-[10px] py-0 h-4 uppercase shrink-0"
                                                        >
                                                            {partner.vendor_entity}
                                                        </Badge>
                                                    )}
                                                </div>
                                                <Badge
                                                    className={cn(
                                                        'text-[10px] py-0 h-5 shrink-0',
                                                        isAgreed &&
                                                            'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border-emerald-500/30',
                                                        isPending &&
                                                            'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30',
                                                        isBlocked &&
                                                            'bg-rose-500/15 text-rose-700 dark:text-rose-400 border-rose-500/30'
                                                    )}
                                                >
                                                    {partner.status_label}
                                                </Badge>
                                            </div>
                                            <p className="text-[11px] text-muted-foreground truncate">
                                                {partner.vendor_email || '-'} • Diajukan oleh:{' '}
                                                {partner.requested_by === 'vendor'
                                                    ? 'Vendor'
                                                    : 'Perusahaan Anda'}
                                            </p>
                                            {isBlocked && partner.block_reason && (
                                                <p className="text-[11px] text-rose-600 dark:text-rose-400 line-clamp-1 flex items-center gap-1">
                                                    <Ban className="size-3 shrink-0" />
                                                    <span>Alasan blokir: {partner.block_reason}</span>
                                                </p>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Baris Bawah: Konfigurasi Komoditas & Toleransi Susut Tenant (UC-09, FR-35) */}
            <Card className="border-border/80 shadow-xs">
                <CardHeader className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pb-3">
                    <div>
                        <CardTitle className="text-base flex items-center gap-2">
                            <Boxes className="size-4 text-violet-600" />
                            <span>
                                Status Aktivasi Komoditas &amp; Toleransi Susut Perusahaan
                            </span>
                        </CardTitle>
                        <CardDescription className="text-xs">
                            Daftar komoditas katalog global beserta status aktivasi dan batas
                            toleransi susut fisik yang berlaku di gudang perusahaan Anda.
                        </CardDescription>
                    </div>
                    <Badge variant="outline" className="text-xs w-fit">
                        {commoditiesStats.tenant_active} Aktif dari{' '}
                        {commoditiesStats.global_active_total} Komoditas Global
                    </Badge>
                </CardHeader>
                <CardContent>
                    {tenantCommodities.length === 0 ? (
                        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-8 px-4 text-center">
                            <ShieldAlert className="size-8 text-muted-foreground/50 mb-2" />
                            <p className="text-sm font-medium text-foreground">
                                Belum Ada Katalog Komoditas Global
                            </p>
                            <p className="text-xs text-muted-foreground mt-0.5">
                                Katalog komoditas akan tampil setelah ditambahkan oleh Superadmin
                                platform.
                            </p>
                        </div>
                    ) : (
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {tenantCommodities.map((com) => (
                                <div
                                    key={com.uuid}
                                    className="flex items-center justify-between gap-3 rounded-xl border bg-muted/20 p-3 text-xs"
                                >
                                    <div className="min-w-0 space-y-1">
                                        <div className="flex items-center gap-1.5">
                                            <span className="font-semibold text-foreground truncate">
                                                {com.name}
                                            </span>
                                            <span className="font-mono text-[10px] text-muted-foreground shrink-0">
                                                ({com.sku})
                                            </span>
                                        </div>
                                        <p className="text-[11px] text-muted-foreground truncate">
                                            Kategori: {com.category_name} • Satuan: {com.unit}
                                        </p>
                                        <p className="text-[11px] text-muted-foreground">
                                            Toleransi Susut:{' '}
                                            <strong className="text-foreground">
                                                {com.effective_shrinkage_tolerance}%
                                            </strong>{' '}
                                            {com.has_custom_tolerance && (
                                                <span className="text-[10px] text-violet-600 dark:text-violet-400">
                                                    (Khusus Tenant)
                                                </span>
                                            )}
                                        </p>
                                    </div>
                                    <Badge
                                        className={cn(
                                            'text-[10px] shrink-0',
                                            com.is_active_for_tenant
                                                ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border-emerald-500/30'
                                                : 'bg-muted text-muted-foreground border-border'
                                        )}
                                    >
                                        {com.is_active_for_tenant ? 'Aktif' : 'Nonaktif'}
                                    </Badge>
                                </div>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
