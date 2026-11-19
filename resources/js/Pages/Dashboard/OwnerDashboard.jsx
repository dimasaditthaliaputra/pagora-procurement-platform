import React from 'react';
import { Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowUpRight,
    Boxes,
    Building2,
    ChartLine,
    CheckCircle2,
    ClipboardCheck,
    Clock,
    FileCheck2,
    FileText,
    Handshake,
    Plus,
    Receipt,
    Scale,
    ShieldAlert,
    ShieldCheck,
    Truck,
    Users,
    Wallet,
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

function formatNumber(value) {
    return new Intl.NumberFormat('id-ID').format(Number(value || 0));
}

function formatCurrency(value) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value || 0));
}

function formatCompactCurrency(value) {
    const num = Number(value || 0);
    if (num >= 1_000_000_000) {
        return `Rp ${(num / 1_000_000_000).toLocaleString('id-ID', {
            maximumFractionDigits: 2,
        })} M`;
    }
    if (num >= 1_000_000) {
        return `Rp ${(num / 1_000_000).toLocaleString('id-ID', {
            maximumFractionDigits: 1,
        })} Jt`;
    }
    return formatCurrency(num);
}

function formatDate(isoString) {
    if (!isoString) {
        return '-';
    }

    try {
        return new Intl.DateTimeFormat('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        }).format(new Date(isoString));
    } catch {
        return '-';
    }
}

function getPoStatusBadgeClass(status) {
    switch (status) {
        case 'lunas':
        case 'diterima_gudang':
        case 'disepakati':
            return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
        case 'dalam_pengiriman':
        case 'partially_received':
        case 'ready_to_pay':
        case 'ditagih':
            return 'border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300';
        case 'negosiasi':
        case 'menunggu_tanggapan':
        case 'draft':
            return 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300';
        case 'sengketa':
        case 'dibatalkan':
            return 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-300';
        default:
            return 'border-border bg-muted text-muted-foreground';
    }
}

function getMatchingBadgeClass(status) {
    switch (status) {
        case 'matched':
            return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
        case 'mismatched':
            return 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-300';
        default:
            return 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300';
    }
}

function getRoleBadgeClass(role) {
    switch (role) {
        case 'owner':
            return 'border-indigo-500/30 bg-indigo-500/10 text-indigo-700 dark:text-indigo-300';
        case 'manajemen':
            return 'border-violet-500/30 bg-violet-500/10 text-violet-700 dark:text-violet-300';
        case 'purchasing':
            return 'border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300';
        case 'qc_gudang':
            return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
        default:
            return 'border-border bg-muted text-muted-foreground';
    }
}

export default function OwnerDashboard({ userName, dashboard }) {
    const company = dashboard?.company ?? null;
    const summary = dashboard?.summary ?? {};
    const procurementStats = summary?.procurement ?? {};
    const invoiceStats = summary?.invoices ?? {};
    const employeeStats = summary?.employees ?? {};
    const governanceStats = summary?.governance ?? {};

    const recentPurchaseOrders = Array.isArray(dashboard?.recent_purchase_orders)
        ? dashboard.recent_purchase_orders
        : [];
    const recentInvoices = Array.isArray(dashboard?.recent_invoices)
        ? dashboard.recent_invoices
        : [];
    const internalTeam = Array.isArray(dashboard?.internal_team)
        ? dashboard.internal_team
        : [];
    const activeDisputes = Array.isArray(dashboard?.active_disputes)
        ? dashboard.active_disputes
        : [];
    const monthlyTrend = Array.isArray(dashboard?.monthly_trend)
        ? dashboard.monthly_trend
        : [];

    const pendingInvoicesCount = Number(invoiceStats.pending || 0);
    const mismatchedInvoicesCount = Number(invoiceStats.mismatched || 0);
    const activeDisputesCount = Number(governanceStats.disputes_active || 0);
    const inTransitQcCount = Number(governanceStats.qc_in_transit || 0);

    const hasAttentionItems =
        pendingInvoicesCount > 0 ||
        mismatchedInvoicesCount > 0 ||
        activeDisputesCount > 0 ||
        inTransitQcCount > 0;

    const maxMonthlyCount = Math.max(
        1,
        ...monthlyTrend.map((m) =>
            Math.max(
                Number(m.purchase_orders_count || 0),
                Number(m.invoices_count || 0),
                Number(m.qc_inspections_count || 0),
            ),
        ),
    );

    return (
        <div className="space-y-6 pb-8">
            {/* Header Eksekutif Owner & Pintasan Utama */}
            <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div className="min-w-0 flex-1 space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge
                            variant="outline"
                            className="border-primary/30 bg-primary/5 text-primary font-semibold text-xs px-2.5 py-0.5"
                        >
                            <Building2 className="mr-1.5 size-3.5" />
                            Eksekutif &amp; Pemilik Perusahaan (Owner)
                        </Badge>
                        {company?.company_name && (
                            <Badge
                                variant="secondary"
                                className="text-xs font-medium bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/20"
                            >
                                <CheckCircle2 className="mr-1 size-3" />
                                {company.company_name}
                            </Badge>
                        )}
                        {company?.tax_number && (
                            <Badge
                                variant="outline"
                                className="text-[11px] font-mono text-muted-foreground"
                            >
                                NPWP: {company.tax_number}
                            </Badge>
                        )}
                    </div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        Selamat Datang, {userName || 'Owner'}!
                    </h1>
                    <p className="text-sm text-muted-foreground max-w-2xl">
                        Kendalikan profil perusahaan &amp; tim internal, awasi siklus Purchase
                        Order dan verifikasi tagihan 3-Way Matching, serta pantau inspeksi QC
                        dan mediasi sengketa.
                    </p>
                </div>

                <div className="flex flex-wrap sm:flex-nowrap items-center gap-2 shrink-0">
                    <Button
                        asChild
                        variant="outline"
                        size="sm"
                        className="h-8 px-3 text-xs gap-1.5 whitespace-nowrap"
                    >
                        <Link href="/manajemen/perusahaan">
                            <Building2 className="size-3.5 shrink-0" />
                            <span>Profil Perusahaan</span>
                        </Link>
                    </Button>
                    <Button
                        asChild
                        variant="outline"
                        size="sm"
                        className="h-8 px-3 text-xs gap-1.5 whitespace-nowrap"
                    >
                        <Link href="/manajemen/pengguna">
                            <Users className="size-3.5 shrink-0" />
                            <span>Kelola Pengguna</span>
                        </Link>
                    </Button>
                    <Button
                        asChild
                        size="sm"
                        className="h-8 px-3 text-xs gap-1.5 whitespace-nowrap"
                    >
                        <Link href="/purchase-order/create">
                            <Plus className="size-3.5 shrink-0" />
                            <span>Buat Purchase Order</span>
                        </Link>
                    </Button>
                </div>
            </div>

            {/* Banner Perhatian Eksekutif (Tagihan Menunggu, Selisih 3-Way Matching, Sengketa, atau Antrean QC) */}
            {hasAttentionItems && (
                <div className="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-start gap-3">
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-500/20 text-amber-700 dark:text-amber-300 mt-0.5 sm:mt-0">
                            <AlertTriangle className="size-5" />
                        </div>
                        <div className="space-y-0.5">
                            <p className="text-sm font-semibold text-amber-950 dark:text-amber-200">
                                Ringkasan Operasional yang Memerlukan Tindak Lanjut
                            </p>
                            <p className="text-xs text-amber-800/90 dark:text-amber-300/90">
                                Status saat ini:{' '}
                                <strong>
                                    {formatNumber(pendingInvoicesCount)} tagihan menunggu
                                    verifikasi 3-Way Matching
                                </strong>
                                {mismatchedInvoicesCount > 0 && (
                                    <>
                                        ,{' '}
                                        <strong>
                                            {formatNumber(mismatchedInvoicesCount)} tagihan
                                            selisih (mismatched)
                                        </strong>
                                    </>
                                )}
                                ,{' '}
                                <strong>
                                    {formatNumber(inTransitQcCount)} pengiriman menunggu
                                    inspeksi QC
                                </strong>
                                , dan{' '}
                                <strong>
                                    {formatNumber(activeDisputesCount)} sengketa aktif
                                </strong>
                                .
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap sm:flex-nowrap items-center gap-2 shrink-0">
                        {pendingInvoicesCount > 0 && (
                            <Button
                                asChild
                                size="sm"
                                className="h-8 px-3 text-xs bg-amber-600 hover:bg-amber-700 text-white whitespace-nowrap"
                            >
                                <Link href="/verifikasi-tagihan">
                                    Verifikasi Tagihan ({pendingInvoicesCount})
                                </Link>
                            </Button>
                        )}
                        {inTransitQcCount > 0 && (
                            <Button
                                asChild
                                size="sm"
                                variant="outline"
                                className="h-8 px-3 text-xs border-amber-600/30 bg-background hover:bg-amber-500/10 whitespace-nowrap"
                            >
                                <Link href="/inspeksi-qc">
                                    Inspeksi QC ({inTransitQcCount})
                                </Link>
                            </Button>
                        )}
                        {activeDisputesCount > 0 && (
                            <Button
                                asChild
                                size="sm"
                                variant="outline"
                                className="h-8 px-3 text-xs border-amber-600/30 bg-background hover:bg-amber-500/10 whitespace-nowrap"
                            >
                                <Link href="/log-sengketa">
                                    Log Sengketa ({activeDisputesCount})
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>
            )}

            {/* 4 Kartu KPI Utama Owner */}
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {/* KPI 1: Siklus Pengadaan & Nilai PO (UC-12) */}
                <Card className="border-border/80 shadow-xs">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Purchase Order &amp; Nilai
                        </CardTitle>
                        <div className="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <FileText className="size-4" />
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="flex items-baseline justify-between gap-2">
                            <div>
                                <div className="text-2xl font-bold">
                                    {formatNumber(procurementStats.total_orders)}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Total dokumen Purchase Order
                                </p>
                            </div>
                            <Badge
                                variant="secondary"
                                className="text-[11px] font-semibold bg-primary/10 text-primary"
                            >
                                {formatCompactCurrency(procurementStats.total_agreed_value)}
                            </Badge>
                        </div>
                        <div className="grid grid-cols-3 gap-1.5 pt-1 text-[11px]">
                            <div className="rounded-md bg-muted/60 px-2 py-1.5">
                                <span className="text-muted-foreground block">Negosiasi</span>
                                <span className="font-semibold text-amber-600 dark:text-amber-400">
                                    {formatNumber(procurementStats.draft_and_negotiation)}
                                </span>
                            </div>
                            <div className="rounded-md bg-muted/60 px-2 py-1.5">
                                <span className="text-muted-foreground block">Berjalan</span>
                                <span className="font-semibold text-sky-600 dark:text-sky-400">
                                    {formatNumber(procurementStats.in_execution)}
                                </span>
                            </div>
                            <div className="rounded-md bg-muted/60 px-2 py-1.5">
                                <span className="text-muted-foreground block">Lunas</span>
                                <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                                    {formatNumber(procurementStats.completed_paid)}
                                </span>
                            </div>
                        </div>
                        <Link
                            href="/purchase-order"
                            className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline pt-1"
                        >
                            Kelola Purchase Order
                            <ArrowUpRight className="size-3.5" />
                        </Link>
                    </CardContent>
                </Card>

                {/* KPI 2: Tagihan & 3-Way Matching (UC-17) */}
                <Card className="border-border/80 shadow-xs">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Tagihan &amp; 3-Way Matching
                        </CardTitle>
                        <div className="flex size-8 items-center justify-center rounded-lg bg-sky-500/10 text-sky-600 dark:text-sky-400">
                            <Receipt className="size-4" />
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="flex items-baseline justify-between gap-2">
                            <div>
                                <div className="text-2xl font-bold">
                                    {formatNumber(invoiceStats.total)}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Invoice vendor •{' '}
                                    {formatCompactCurrency(invoiceStats.total_invoiced_amount)}
                                </p>
                            </div>
                            {pendingInvoicesCount > 0 && (
                                <Badge
                                    variant="secondary"
                                    className="bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30 text-[11px]"
                                >
                                    {pendingInvoicesCount} Menunggu
                                </Badge>
                            )}
                        </div>
                        <div className="grid grid-cols-3 gap-1.5 pt-1 text-[11px]">
                            <div className="rounded-md bg-muted/60 px-2 py-1.5">
                                <span className="text-muted-foreground block">Menunggu</span>
                                <span className="font-semibold text-amber-600 dark:text-amber-400">
                                    {formatNumber(invoiceStats.pending)}
                                </span>
                            </div>
                            <div className="rounded-md bg-muted/60 px-2 py-1.5">
                                <span className="text-muted-foreground block">Sesuai</span>
                                <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                                    {formatNumber(invoiceStats.matched)}
                                </span>
                            </div>
                            <div className="rounded-md bg-muted/60 px-2 py-1.5">
                                <span className="text-muted-foreground block">Selisih</span>
                                <span className="font-semibold text-rose-600 dark:text-rose-400">
                                    {formatNumber(invoiceStats.mismatched)}
                                </span>
                            </div>
                        </div>
                        <Link
                            href="/verifikasi-tagihan"
                            className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline pt-1"
                        >
                            Verifikasi Kelayakan Tagihan
                            <ArrowUpRight className="size-3.5" />
                        </Link>
                    </CardContent>
                </Card>

                {/* KPI 3: Tim & Pegawai Internal Perusahaan (UC-08) */}
                <Card className="border-border/80 shadow-xs">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Anggota Internal Tenant
                        </CardTitle>
                        <div className="flex size-8 items-center justify-center rounded-lg bg-violet-500/10 text-violet-600 dark:text-violet-400">
                            <Users className="size-4" />
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="flex items-baseline justify-between gap-2">
                            <div>
                                <div className="text-2xl font-bold">
                                    {formatNumber(employeeStats.active)}
                                    <span className="text-sm font-normal text-muted-foreground">
                                        {' '}
                                        / {formatNumber(employeeStats.total)}
                                    </span>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Akun pegawai internal aktif
                                </p>
                            </div>
                        </div>
                        <div className="grid grid-cols-3 gap-1.5 pt-1 text-[11px]">
                            <div className="rounded-md bg-muted/60 px-2 py-1.5">
                                <span className="text-muted-foreground block">Manajemen</span>
                                <span className="font-semibold text-violet-600 dark:text-violet-400">
                                    {formatNumber(employeeStats.manajemen_count)}
                                </span>
                            </div>
                            <div className="rounded-md bg-muted/60 px-2 py-1.5">
                                <span className="text-muted-foreground block">Purchasing</span>
                                <span className="font-semibold text-sky-600 dark:text-sky-400">
                                    {formatNumber(employeeStats.purchasing_count)}
                                </span>
                            </div>
                            <div className="rounded-md bg-muted/60 px-2 py-1.5">
                                <span className="text-muted-foreground block">QC Gudang</span>
                                <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                                    {formatNumber(employeeStats.qc_gudang_count)}
                                </span>
                            </div>
                        </div>
                        <Link
                            href="/manajemen/pengguna"
                            className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline pt-1"
                        >
                            Kelola Anggota Perusahaan
                            <ArrowUpRight className="size-3.5" />
                        </Link>
                    </CardContent>
                </Card>

                {/* KPI 4: Mitra Vendor, QC Gudang & Sengketa (UC-10, UC-15, UC-18) */}
                <Card className="border-border/80 shadow-xs">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Mitra, QC &amp; Sengketa
                        </CardTitle>
                        <div className="flex size-8 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                            <Handshake className="size-4" />
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="flex items-baseline justify-between gap-2">
                            <div>
                                <div className="text-2xl font-bold">
                                    {formatNumber(governanceStats.partnerships_agreed)}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Mitra vendor aktif •{' '}
                                    {formatNumber(governanceStats.tenant_active_commodities)}{' '}
                                    komoditas
                                </p>
                            </div>
                            {activeDisputesCount > 0 && (
                                <Badge
                                    variant="secondary"
                                    className="bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-500/30 text-[11px]"
                                >
                                    {activeDisputesCount} Sengketa
                                </Badge>
                            )}
                        </div>
                        <div className="grid grid-cols-3 gap-1.5 pt-1 text-[11px]">
                            <div className="rounded-md bg-muted/60 px-2 py-1.5">
                                <span className="text-muted-foreground block">QC Lolos</span>
                                <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                                    {formatNumber(governanceStats.qc_accepted_items)}
                                </span>
                            </div>
                            <div className="rounded-md bg-muted/60 px-2 py-1.5">
                                <span className="text-muted-foreground block">QC Ditolak</span>
                                <span className="font-semibold text-rose-600 dark:text-rose-400">
                                    {formatNumber(governanceStats.qc_rejected_items)}
                                </span>
                            </div>
                            <div className="rounded-md bg-muted/60 px-2 py-1.5">
                                <span className="text-muted-foreground block">Sengketa</span>
                                <span className="font-semibold text-amber-600 dark:text-amber-400">
                                    {formatNumber(activeDisputesCount)}
                                </span>
                            </div>
                        </div>
                        <Link
                            href="/master/vendor"
                            className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline pt-1"
                        >
                            Kelola Kemitraan &amp; Vendor
                            <ArrowUpRight className="size-3.5" />
                        </Link>
                    </CardContent>
                </Card>
            </div>

            {/* Grid Utama Baris 1: Purchase Order Terbaru & Verifikasi Tagihan 3-Way Matching */}
            <div className="grid gap-6 lg:grid-cols-2">
                {/* Daftar Purchase Order Terbaru (UC-12) */}
                <Card className="border-border/80 shadow-xs flex flex-col">
                    <CardHeader className="flex flex-row items-center justify-between pb-3">
                        <div className="space-y-1">
                            <CardTitle className="text-base font-semibold flex items-center gap-2">
                                Dokumen Purchase Order Terbaru
                                <Badge variant="secondary" className="text-xs">
                                    {formatNumber(procurementStats.total_orders)} PO
                                </Badge>
                            </CardTitle>
                            <CardDescription className="text-xs">
                                Pantau progres negosiasi harga per item, pengiriman logistik, dan
                                nilai kesepakatan PO.
                            </CardDescription>
                        </div>
                        <Button asChild variant="outline" size="sm" className="h-8 text-xs">
                            <Link href="/purchase-order">Semua PO</Link>
                        </Button>
                    </CardHeader>
                    <CardContent className="flex-1">
                        {recentPurchaseOrders.length === 0 ? (
                            <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-10 px-4 text-center">
                                <FileText className="size-8 text-muted-foreground/60 mb-2" />
                                <p className="text-sm font-medium">
                                    Belum Ada Dokumen Purchase Order
                                </p>
                                <p className="text-xs text-muted-foreground mt-0.5 max-w-sm">
                                    Buat Purchase Order pertama perusahaan Anda kepada mitra vendor
                                    yang telah bekerja sama.
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-2.5">
                                {recentPurchaseOrders.map((po) => (
                                    <div
                                        key={po.uuid}
                                        className="flex items-center justify-between gap-3 rounded-xl border border-border/70 bg-card/50 p-3 hover:bg-muted/40 transition-colors"
                                    >
                                        <div className="min-w-0 flex-1 space-y-1">
                                            <div className="flex items-center gap-2 flex-wrap">
                                                <span className="text-sm font-semibold truncate">
                                                    {po.po_number}
                                                </span>
                                                <Badge
                                                    variant="outline"
                                                    className={`text-[10px] px-1.5 py-0 ${getPoStatusBadgeClass(
                                                        po.status,
                                                    )}`}
                                                >
                                                    {po.status_label}
                                                </Badge>
                                                <span className="text-[11px] font-medium text-muted-foreground">
                                                    • {formatNumber(po.items_count)} item
                                                </span>
                                            </div>
                                            <div className="flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
                                                <span className="font-medium text-foreground/80">
                                                    {po.vendor_name}
                                                </span>
                                                <span>Oleh: {po.created_by_name}</span>
                                                <span>
                                                    {formatDate(po.tanggal_po || po.created_at)}
                                                </span>
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-2.5 shrink-0">
                                            <div className="text-right">
                                                <span className="text-xs font-semibold block">
                                                    {formatCurrency(po.total_amount)}
                                                </span>
                                                <span className="text-[10px] text-muted-foreground">
                                                    Nilai PO
                                                </span>
                                            </div>
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="secondary"
                                                className="h-8 text-xs shrink-0"
                                            >
                                                <Link href={`/purchase-order/${po.uuid}`}>
                                                    Detail
                                                </Link>
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Antrean Verifikasi Tagihan & 3-Way Matching (UC-17) */}
                <Card className="border-border/80 shadow-xs flex flex-col">
                    <CardHeader className="flex flex-row items-center justify-between pb-3">
                        <div className="space-y-1">
                            <CardTitle className="text-base font-semibold flex items-center gap-2">
                                Verifikasi Tagihan &amp; 3-Way Matching
                                <Badge variant="secondary" className="text-xs">
                                    {formatNumber(invoiceStats.pending)} Menunggu
                                </Badge>
                            </CardTitle>
                            <CardDescription className="text-xs">
                                Validasi kecocokan antara harga kesepakatan PO, penerimaan QC
                                Gudang, dan tagihan Vendor.
                            </CardDescription>
                        </div>
                        <Button asChild variant="outline" size="sm" className="h-8 text-xs">
                            <Link href="/verifikasi-tagihan">Semua Tagihan</Link>
                        </Button>
                    </CardHeader>
                    <CardContent className="flex-1">
                        {recentInvoices.length === 0 ? (
                            <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-10 px-4 text-center">
                                <FileCheck2 className="size-8 text-emerald-500/80 mb-2" />
                                <p className="text-sm font-medium">
                                    Belum Ada Tagihan Masuk dari Vendor
                                </p>
                                <p className="text-xs text-muted-foreground mt-0.5 max-w-sm">
                                    Tagihan digital yang diterbitkan Vendor atas barang lolos QC
                                    akan otomatis tampil di sini.
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-2.5">
                                {recentInvoices.map((inv) => (
                                    <div
                                        key={inv.uuid}
                                        className="flex items-center justify-between gap-3 rounded-xl border border-border/70 bg-card/50 p-3 hover:bg-muted/40 transition-colors"
                                    >
                                        <div className="min-w-0 flex-1 space-y-1">
                                            <div className="flex items-center gap-2 flex-wrap">
                                                <span className="text-sm font-semibold truncate">
                                                    {inv.invoice_number}
                                                </span>
                                                <Badge
                                                    variant="outline"
                                                    className={`text-[10px] px-1.5 py-0 ${getMatchingBadgeClass(
                                                        inv.matching_status,
                                                    )}`}
                                                >
                                                    {inv.matching_status_label}
                                                </Badge>
                                            </div>
                                            <div className="flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
                                                <span className="font-mono">
                                                    PO: {inv.po_number}
                                                </span>
                                                <span>{inv.vendor_name}</span>
                                                <span>
                                                    {formatDate(inv.invoice_date || inv.created_at)}
                                                </span>
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-2.5 shrink-0">
                                            <div className="text-right">
                                                <span className="text-xs font-semibold block">
                                                    {formatCurrency(inv.amount)}
                                                </span>
                                                <span className="text-[10px] text-muted-foreground">
                                                    Tagihan
                                                </span>
                                            </div>
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="secondary"
                                                className="h-8 text-xs shrink-0"
                                            >
                                                <Link href={`/verifikasi-tagihan/${inv.uuid}`}>
                                                    Tinjau
                                                </Link>
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Grid Baris 2: Tren Eksekusi 6 Bulan + Profil Perusahaan & Anggota Tim Internal */}
            <div className="grid gap-6 lg:grid-cols-12">
                {/* Tren Eksekusi Pengadaan & Nilai Transaksi (7 kolom) */}
                <Card className="border-border/80 shadow-xs lg:col-span-7">
                    <CardHeader className="pb-3">
                        <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <CardTitle className="text-base font-semibold">
                                    Tren Eksekusi Pengadaan &amp; Tagihan (6 Bulan Terakhir)
                                </CardTitle>
                                <CardDescription className="text-xs">
                                    Perbandingan bulanan dokumen PO, berita acara inspeksi QC,
                                    tagihan masuk, dan nilai transaksi PO.
                                </CardDescription>
                            </div>
                            <div className="flex items-center gap-3 text-xs pt-1 sm:pt-0">
                                <span className="inline-flex items-center gap-1.5">
                                    <span className="size-2.5 rounded-full bg-primary" />
                                    PO
                                </span>
                                <span className="inline-flex items-center gap-1.5">
                                    <span className="size-2.5 rounded-full bg-emerald-500" />
                                    QC Gudang
                                </span>
                                <span className="inline-flex items-center gap-1.5">
                                    <span className="size-2.5 rounded-full bg-sky-500" />
                                    Invoice
                                </span>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="space-y-3">
                            {monthlyTrend.map((bucket) => {
                                const poPct = Math.round(
                                    (Number(bucket.purchase_orders_count || 0) /
                                        maxMonthlyCount) *
                                        100,
                                );
                                const qcPct = Math.round(
                                    (Number(bucket.qc_inspections_count || 0) /
                                        maxMonthlyCount) *
                                        100,
                                );
                                const invPct = Math.round(
                                    (Number(bucket.invoices_count || 0) / maxMonthlyCount) *
                                        100,
                                );

                                return (
                                    <div
                                        key={bucket.month_key}
                                        className="rounded-lg border border-border/60 bg-muted/20 p-3 space-y-2"
                                    >
                                        <div className="flex items-center justify-between text-xs">
                                            <span className="font-semibold">
                                                {bucket.label}
                                            </span>
                                            <div className="flex flex-wrap items-center gap-3 text-muted-foreground">
                                                <span>
                                                    <strong className="text-foreground">
                                                        {formatNumber(
                                                            bucket.purchase_orders_count,
                                                        )}
                                                    </strong>{' '}
                                                    PO
                                                </span>
                                                <span>
                                                    <strong className="text-emerald-600 dark:text-emerald-400">
                                                        {formatNumber(
                                                            bucket.qc_inspections_count,
                                                        )}
                                                    </strong>{' '}
                                                    QC
                                                </span>
                                                <span>
                                                    <strong className="text-sky-600 dark:text-sky-400">
                                                        {formatNumber(bucket.invoices_count)}
                                                    </strong>{' '}
                                                    Invoice
                                                </span>
                                                <Badge
                                                    variant="outline"
                                                    className="text-[10px] font-mono px-1.5 py-0"
                                                >
                                                    {formatCompactCurrency(bucket.po_value)}
                                                </Badge>
                                            </div>
                                        </div>
                                        <div className="grid grid-cols-3 gap-2">
                                            <div className="h-1.5 w-full rounded-full bg-muted overflow-hidden">
                                                <div
                                                    className="h-full rounded-full bg-primary transition-all"
                                                    style={{
                                                        width: `${Math.max(
                                                            poPct,
                                                            bucket.purchase_orders_count > 0
                                                                ? 10
                                                                : 0,
                                                        )}%`,
                                                    }}
                                                />
                                            </div>
                                            <div className="h-1.5 w-full rounded-full bg-muted overflow-hidden">
                                                <div
                                                    className="h-full rounded-full bg-emerald-500 transition-all"
                                                    style={{
                                                        width: `${Math.max(
                                                            qcPct,
                                                            bucket.qc_inspections_count > 0
                                                                ? 10
                                                                : 0,
                                                        )}%`,
                                                    }}
                                                />
                                            </div>
                                            <div className="h-1.5 w-full rounded-full bg-muted overflow-hidden">
                                                <div
                                                    className="h-full rounded-full bg-sky-500 transition-all"
                                                    style={{
                                                        width: `${Math.max(
                                                            invPct,
                                                            bucket.invoices_count > 0 ? 10 : 0,
                                                        )}%`,
                                                    }}
                                                />
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>

                        {/* Ringkasan Identitas & Legalitas Perusahaan (UC-07) */}
                        {company && (
                            <div className="rounded-xl border border-border/70 bg-muted/30 p-3.5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div className="space-y-1 min-w-0">
                                    <div className="flex items-center gap-2 flex-wrap">
                                        <span className="text-xs font-semibold">
                                            Identitas Perusahaan: {company.company_name}
                                        </span>
                                        <Badge
                                            variant="outline"
                                            className="text-[10px] px-1.5 py-0 border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                                        >
                                            Terverifikasi
                                        </Badge>
                                    </div>
                                    <p className="text-xs text-muted-foreground truncate">
                                        NPWP: {company.tax_number || '-'} • Telp:{' '}
                                        {company.phone || '-'} • Alamat:{' '}
                                        {company.address || '-'}
                                    </p>
                                </div>
                                <Button
                                    asChild
                                    variant="outline"
                                    size="sm"
                                    className="h-8 px-3 text-xs shrink-0 whitespace-nowrap"
                                >
                                    <Link href="/manajemen/perusahaan">
                                        Ubah Profil Perusahaan
                                    </Link>
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Tim Internal Perusahaan (UC-08) & Sengketa Aktif (UC-18) (5 kolom) */}
                <Card className="border-border/80 shadow-xs lg:col-span-5 flex flex-col">
                    <CardHeader className="flex flex-row items-center justify-between pb-3">
                        <div className="space-y-1">
                            <CardTitle className="text-base font-semibold">
                                Anggota Internal Perusahaan
                            </CardTitle>
                            <CardDescription className="text-xs">
                                Daftar pengguna internal beserta distribusi role dan status akun.
                            </CardDescription>
                        </div>
                        <Button asChild variant="outline" size="sm" className="h-8 text-xs">
                            <Link href="/manajemen/pengguna">Kelola</Link>
                        </Button>
                    </CardHeader>
                    <CardContent className="space-y-4 flex-1">
                        {internalTeam.length === 0 ? (
                            <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-8 px-4 text-center">
                                <Users className="size-7 text-muted-foreground/60 mb-2" />
                                <p className="text-sm font-medium">
                                    Belum Ada Anggota Internal Lain
                                </p>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    Tambahkan pegawai untuk role Manajemen, Purchasing, atau QC
                                    Gudang.
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-2">
                                {internalTeam.map((member) => (
                                    <div
                                        key={member.uuid}
                                        className="flex items-center justify-between gap-2.5 rounded-lg border border-border/60 bg-muted/20 px-3 py-2"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2">
                                                <span className="text-xs font-semibold truncate">
                                                    {member.name}
                                                </span>
                                                <span
                                                    className={`size-2 rounded-full shrink-0 ${
                                                        member.is_active
                                                            ? 'bg-emerald-500'
                                                            : 'bg-zinc-400'
                                                    }`}
                                                    title={
                                                        member.is_active
                                                            ? 'Akun Aktif'
                                                            : 'Nonaktif'
                                                    }
                                                />
                                            </div>
                                            <span className="text-[11px] text-muted-foreground truncate block">
                                                {member.email}
                                            </span>
                                        </div>
                                        <Badge
                                            variant="outline"
                                            className={`text-[10px] px-2 py-0.5 shrink-0 ${getRoleBadgeClass(
                                                member.role,
                                            )}`}
                                        >
                                            {member.role_label}
                                        </Badge>
                                    </div>
                                ))}
                            </div>
                        )}

                        {/* Ringkasan Sengketa Aktif (UC-18) */}
                        <div className="rounded-xl border border-border/70 bg-card/60 p-3 space-y-2">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <Scale className="size-4 text-amber-600 dark:text-amber-400" />
                                    <span className="text-xs font-semibold">
                                        Status Mediasi &amp; Log Sengketa
                                    </span>
                                </div>
                                <Link
                                    href="/log-sengketa"
                                    className="text-[11px] font-medium text-primary hover:underline"
                                >
                                    Buka Log Sengketa
                                </Link>
                            </div>

                            {activeDisputes.length === 0 ? (
                                <p className="text-xs text-muted-foreground">
                                    Tidak ada sengketa pengadaan yang sedang terbuka. Total{' '}
                                    <strong>
                                        {formatNumber(governanceStats.disputes_resolved)}
                                    </strong>{' '}
                                    kasus sengketa telah diselesaikan.
                                </p>
                            ) : (
                                <div className="space-y-1.5">
                                    {activeDisputes.slice(0, 2).map((d) => (
                                        <div
                                            key={d.uuid}
                                            className="flex items-center justify-between gap-2 rounded-lg bg-muted/50 px-2.5 py-1.5 text-xs"
                                        >
                                            <div className="min-w-0 flex-1">
                                                <span className="font-medium truncate block">
                                                    {d.po_number} • {d.vendor_name}
                                                </span>
                                                <span className="text-[11px] text-muted-foreground truncate block">
                                                    {d.trigger_type_label}
                                                </span>
                                            </div>
                                            <Link
                                                href={`/log-sengketa/${d.uuid}`}
                                                className="text-[11px] font-semibold text-primary hover:underline shrink-0"
                                            >
                                                Tinjau
                                            </Link>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Pintasan Lintas Modul Owner (REQ-TEN-03) */}
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Link
                    href="/analitik-harga"
                    className="group flex items-center justify-between rounded-xl border border-border/70 bg-card p-3.5 hover:border-primary/40 hover:bg-muted/30 transition-all"
                >
                    <div className="flex items-center gap-3 min-w-0">
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <ChartLine className="size-4" />
                        </div>
                        <div className="min-w-0">
                            <p className="text-xs font-semibold truncate">
                                Analitik &amp; Harga Pagu
                            </p>
                            <p className="text-[11px] text-muted-foreground truncate">
                                Tren harga BI &amp; regresi linier
                            </p>
                        </div>
                    </div>
                    <ArrowUpRight className="size-4 text-muted-foreground group-hover:text-primary transition-colors shrink-0" />
                </Link>

                <Link
                    href="/inspeksi-qc"
                    className="group flex items-center justify-between rounded-xl border border-border/70 bg-card p-3.5 hover:border-primary/40 hover:bg-muted/30 transition-all"
                >
                    <div className="flex items-center gap-3 min-w-0">
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                            <ClipboardCheck className="size-4" />
                        </div>
                        <div className="min-w-0">
                            <p className="text-xs font-semibold truncate">
                                Inspeksi Fisik QC Gudang
                            </p>
                            <p className="text-[11px] text-muted-foreground truncate">
                                {formatNumber(governanceStats.qc_total_receipts)} berita acara •{' '}
                                {formatNumber(inTransitQcCount)} in transit
                            </p>
                        </div>
                    </div>
                    <ArrowUpRight className="size-4 text-muted-foreground group-hover:text-primary transition-colors shrink-0" />
                </Link>

                <Link
                    href="/master/komoditas"
                    className="group flex items-center justify-between rounded-xl border border-border/70 bg-card p-3.5 hover:border-primary/40 hover:bg-muted/30 transition-all"
                >
                    <div className="flex items-center gap-3 min-w-0">
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-violet-500/10 text-violet-600 dark:text-violet-400">
                            <Boxes className="size-4" />
                        </div>
                        <div className="min-w-0">
                            <p className="text-xs font-semibold truncate">
                                Komoditas &amp; Toleransi Susut
                            </p>
                            <p className="text-[11px] text-muted-foreground truncate">
                                {formatNumber(governanceStats.tenant_active_commodities)} dari{' '}
                                {formatNumber(governanceStats.global_active_commodities)}{' '}
                                komoditas aktif
                            </p>
                        </div>
                    </div>
                    <ArrowUpRight className="size-4 text-muted-foreground group-hover:text-primary transition-colors shrink-0" />
                </Link>

                <Link
                    href="/manajemen/role-akses"
                    className="group flex items-center justify-between rounded-xl border border-border/70 bg-card p-3.5 hover:border-primary/40 hover:bg-muted/30 transition-all"
                >
                    <div className="flex items-center gap-3 min-w-0">
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400">
                            <ShieldCheck className="size-4" />
                        </div>
                        <div className="min-w-0">
                            <p className="text-xs font-semibold truncate">
                                Matriks Role &amp; Hak Akses
                            </p>
                            <p className="text-[11px] text-muted-foreground truncate">
                                Pengaturan izin fitur internal
                            </p>
                        </div>
                    </div>
                    <ArrowUpRight className="size-4 text-muted-foreground group-hover:text-primary transition-colors shrink-0" />
                </Link>
            </div>
        </div>
    );
}
