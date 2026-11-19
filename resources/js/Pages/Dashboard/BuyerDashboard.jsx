import { Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowUpRight,
    CheckCircle2,
    ClipboardList,
    FileText,
    Handshake,
    Receipt,
    Scale,
    ShoppingCart,
    TrendingUp,
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
import { cn } from '@/lib/utils';

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
        return `Rp ${(num / 1_000_000_000).toLocaleString('id-ID', { maximumFractionDigits: 2 })} M`;
    }
    if (num >= 1_000_000) {
        return `Rp ${(num / 1_000_000).toLocaleString('id-ID', { maximumFractionDigits: 1 })} Jt`;
    }
    return formatCurrency(num);
}

function formatDate(isoString) {
    if (!isoString) return '-';
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

function getPoStatusClass(status) {
    switch (status) {
        case 'lunas':
            return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30';
        case 'ditagih':
        case 'ready_to_pay':
            return 'bg-sky-500/15 text-sky-700 dark:text-sky-300 border-sky-500/30';
        case 'disepakati':
        case 'dalam_pengiriman':
        case 'partially_received':
        case 'diterima_gudang':
            return 'bg-blue-500/15 text-blue-700 dark:text-blue-300 border-blue-500/30';
        case 'draft':
        case 'menunggu_tanggapan':
        case 'negosiasi':
            return 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30';
        case 'sengketa':
            return 'bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-500/30';
        case 'dibatalkan':
            return 'bg-muted text-muted-foreground border-border';
        default:
            return 'bg-muted/50 text-muted-foreground border-border';
    }
}

function getMatchingStatusClass(status) {
    switch (status) {
        case 'matched':
            return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30';
        case 'mismatched':
            return 'bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-500/30';
        default:
            return 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30';
    }
}

const QUICK_MODULES = [
    {
        title: 'Purchase Order',
        description: 'Buat & kelola pesanan pengadaan',
        href: '/purchase-orders',
        icon: ShoppingCart,
        tone: 'text-sky-600 dark:text-sky-400 bg-sky-500/10',
    },
    {
        title: 'Verifikasi Tagihan',
        description: '3-Way Matching & pembayaran invoice',
        href: '/invoice',
        icon: Receipt,
        tone: 'text-emerald-600 dark:text-emerald-400 bg-emerald-500/10',
    },
    {
        title: 'Dasbor Harga',
        description: 'Pantau harga pagu & tren komoditas',
        href: '/harga-pangan',
        icon: TrendingUp,
        tone: 'text-violet-600 dark:text-violet-400 bg-violet-500/10',
    },
    {
        title: 'Kerja Sama',
        description: 'Daftar vendor mitra aktif',
        href: '/kerja-sama',
        icon: Handshake,
        tone: 'text-amber-600 dark:text-amber-400 bg-amber-500/10',
    },
    {
        title: 'Sengketa',
        description: 'Pantau & kelola sengketa aktif',
        href: '/sengketa',
        icon: Scale,
        tone: 'text-rose-600 dark:text-rose-400 bg-rose-500/10',
    },
    {
        title: 'Laporan Pengadaan',
        description: 'Riwayat PO & ringkasan pengeluaran',
        href: '/purchase-orders',
        icon: ClipboardList,
        tone: 'text-teal-600 dark:text-teal-400 bg-teal-500/10',
    },
];

export default function BuyerDashboard({ userName, dashboard }) {
    const company = dashboard?.company ?? null;
    const summary = dashboard?.summary ?? {};

    const ordersStats = summary.orders ?? {
        total: 0,
        draft_negotiation: 0,
        in_execution: 0,
        pending_payment: 0,
        completed_paid: 0,
        disputed: 0,
    };
    const invoicesStats = summary.invoices ?? {
        pending: 0,
        matched: 0,
        mismatched: 0,
        total_invoiced_amount: 0,
    };
    const disputesStats = summary.disputes ?? { active: 0 };
    const partnershipsStats = summary.partnerships ?? { active: 0 };

    const recentPurchaseOrders = Array.isArray(dashboard?.recent_purchase_orders)
        ? dashboard.recent_purchase_orders
        : [];
    const pendingInvoices = Array.isArray(dashboard?.pending_invoices)
        ? dashboard.pending_invoices
        : [];
    const activeDisputes = Array.isArray(dashboard?.active_disputes)
        ? dashboard.active_disputes
        : [];

    return (
        <div className="flex flex-col gap-6 pb-8">
            {/* Header */}
            <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div className="min-w-0 flex-1 space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge
                            variant="outline"
                            className="border-primary/30 bg-primary/5 text-primary font-semibold text-xs px-2.5 py-0.5"
                        >
                            <Wallet className="size-3.5 mr-1" />
                            Purchasing / Finance
                        </Badge>
                        {company && (
                            <Badge
                                variant="outline"
                                className="text-xs font-medium px-2.5 py-0.5 border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-400"
                            >
                                {company.company_name}
                            </Badge>
                        )}
                    </div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        Selamat Datang, {userName || 'Purchasing'}!
                    </h1>
                    <p className="text-sm text-muted-foreground max-w-3xl">
                        Pantau status Purchase Order, verifikasi tagihan (3-Way Matching), pantau
                        tren harga pagu komoditas, dan kelola sengketa pengadaan.
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Button asChild variant="outline" size="sm" className="h-9 gap-1.5">
                        <Link href="/harga-pangan">
                            <TrendingUp className="size-4" />
                            <span>Pantau Harga Pagu</span>
                        </Link>
                    </Button>
                    <Button asChild size="sm" className="h-9 gap-1.5">
                        <Link href="/purchase-orders/create">
                            <ShoppingCart className="size-4" />
                            <span>Buat Purchase Order</span>
                        </Link>
                    </Button>
                </div>
            </div>

            {/* Banner Tagihan Butuh Verifikasi */}
            {(invoicesStats.pending > 0 || invoicesStats.mismatched > 0 || disputesStats.active > 0) && (
                <div className="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-start gap-3">
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-500/20 text-amber-700 dark:text-amber-300 mt-0.5 sm:mt-0">
                            <AlertTriangle className="size-5" />
                        </div>
                        <div className="space-y-0.5">
                            <p className="text-sm font-semibold text-amber-950 dark:text-amber-200">
                                Terdapat Item yang Membutuhkan Perhatian
                            </p>
                            <p className="text-xs text-amber-800/90 dark:text-amber-300/90">
                                {invoicesStats.pending > 0 && (
                                    <span>
                                        <strong>{invoicesStats.pending} tagihan</strong> menunggu verifikasi 3-Way Matching
                                    </span>
                                )}
                                {invoicesStats.pending > 0 && invoicesStats.mismatched > 0 && ' • '}
                                {invoicesStats.mismatched > 0 && (
                                    <span>
                                        <strong>{invoicesStats.mismatched} tagihan tidak cocok</strong> perlu ditindaklanjuti
                                    </span>
                                )}
                                {disputesStats.active > 0 && (invoicesStats.pending > 0 || invoicesStats.mismatched > 0) && ' • '}
                                {disputesStats.active > 0 && (
                                    <span>
                                        <strong>{disputesStats.active} sengketa aktif</strong> menunggu penyelesaian
                                    </span>
                                )}
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2 shrink-0">
                        {(invoicesStats.pending > 0 || invoicesStats.mismatched > 0) && (
                            <Button
                                asChild
                                size="sm"
                                className="h-8 text-xs bg-amber-600 hover:bg-amber-700 text-white"
                            >
                                <Link href="/invoice">
                                    Verifikasi Tagihan ({invoicesStats.pending + invoicesStats.mismatched})
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>
            )}

            {/* 4 KPI Utama */}
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {/* KPI 1: Purchase Order */}
                <Card className="border-border/80 shadow-xs">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Purchase Order
                        </CardTitle>
                        <div className="flex size-8 items-center justify-center rounded-lg bg-sky-500/10 text-sky-600 dark:text-sky-400">
                            <ShoppingCart className="size-4" />
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="flex items-baseline justify-between">
                            <div>
                                <div className="text-2xl font-bold tracking-tight">{ordersStats.total}</div>
                                <p className="text-xs text-muted-foreground">
                                    {ordersStats.in_execution} sedang berjalan
                                </p>
                            </div>
                            {ordersStats.disputed > 0 && (
                                <Badge className="bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-500/30 text-[11px]">
                                    {ordersStats.disputed} Sengketa
                                </Badge>
                            )}
                        </div>

                        <div className="grid grid-cols-3 gap-1.5 pt-2 border-t text-[11px]">
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Draft/Neg</span>
                                <span className="font-semibold text-amber-600 dark:text-amber-400">
                                    {ordersStats.draft_negotiation}
                                </span>
                            </div>
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Bayar</span>
                                <span className="font-semibold text-sky-600 dark:text-sky-400">
                                    {ordersStats.pending_payment}
                                </span>
                            </div>
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Lunas</span>
                                <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                                    {ordersStats.completed_paid}
                                </span>
                            </div>
                        </div>

                        <Link
                            href="/purchase-orders"
                            className="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline pt-0.5"
                        >
                            <span>Kelola Purchase Order</span>
                            <ArrowUpRight className="size-3.5" />
                        </Link>
                    </CardContent>
                </Card>

                {/* KPI 2: Tagihan & 3-Way Matching */}
                <Card className="border-border/80 shadow-xs">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Tagihan & 3-Way Matching
                        </CardTitle>
                        <div className="flex size-8 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                            <Receipt className="size-4" />
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="flex items-baseline justify-between">
                            <div>
                                <div className="text-2xl font-bold tracking-tight">
                                    {formatCompactCurrency(invoicesStats.total_invoiced_amount)}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Total nilai tagihan masuk
                                </p>
                            </div>
                            {invoicesStats.mismatched > 0 && (
                                <Badge className="bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-500/30 text-[11px]">
                                    {invoicesStats.mismatched} Mismatch
                                </Badge>
                            )}
                        </div>

                        <div className="grid grid-cols-3 gap-1.5 pt-2 border-t text-[11px]">
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Pending</span>
                                <span className="font-semibold text-amber-600 dark:text-amber-400">
                                    {invoicesStats.pending}
                                </span>
                            </div>
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Cocok</span>
                                <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                                    {invoicesStats.matched}
                                </span>
                            </div>
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Mismatch</span>
                                <span className="font-semibold text-rose-600 dark:text-rose-400">
                                    {invoicesStats.mismatched}
                                </span>
                            </div>
                        </div>

                        <Link
                            href="/invoice"
                            className="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline pt-0.5"
                        >
                            <span>Verifikasi Tagihan</span>
                            <ArrowUpRight className="size-3.5" />
                        </Link>
                    </CardContent>
                </Card>

                {/* KPI 3: Sengketa Aktif */}
                <Card className="border-border/80 shadow-xs">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Sengketa Aktif
                        </CardTitle>
                        <div className="flex size-8 items-center justify-center rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-400">
                            <Scale className="size-4" />
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="flex items-baseline justify-between">
                            <div>
                                <div className="text-2xl font-bold tracking-tight">
                                    {disputesStats.active}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    {disputesStats.active === 0
                                        ? 'Tidak ada sengketa aktif'
                                        : 'Sengketa sedang berjalan'}
                                </p>
                            </div>
                        </div>

                        <div className="pt-2 border-t">
                            {disputesStats.active === 0 ? (
                                <div className="flex items-center gap-2 text-[11px] text-emerald-600 dark:text-emerald-400">
                                    <CheckCircle2 className="size-3.5" />
                                    <span>Semua transaksi berjalan lancar</span>
                                </div>
                            ) : (
                                <div className="flex items-center gap-2 text-[11px] text-rose-600 dark:text-rose-400">
                                    <AlertTriangle className="size-3.5" />
                                    <span>Perlu ditindaklanjuti bersama Manajemen</span>
                                </div>
                            )}
                        </div>

                        <Link
                            href="/sengketa"
                            className="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline pt-0.5"
                        >
                            <span>Kelola Sengketa</span>
                            <ArrowUpRight className="size-3.5" />
                        </Link>
                    </CardContent>
                </Card>

                {/* KPI 4: Vendor Mitra Aktif */}
                <Card className="border-border/80 shadow-xs">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Vendor Mitra Aktif
                        </CardTitle>
                        <div className="flex size-8 items-center justify-center rounded-lg bg-teal-500/10 text-teal-600 dark:text-teal-400">
                            <Handshake className="size-4" />
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="flex items-baseline justify-between">
                            <div>
                                <div className="text-2xl font-bold tracking-tight">
                                    {partnershipsStats.active}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Pemasok dengan kemitraan aktif (agreed)
                                </p>
                            </div>
                        </div>

                        <div className="pt-2 border-t text-[11px] text-muted-foreground">
                            Hanya vendor mitra aktif yang dapat digunakan dalam PO baru
                        </div>

                        <Link
                            href="/kerja-sama"
                            className="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline pt-0.5"
                        >
                            <span>Lihat Daftar Mitra</span>
                            <ArrowUpRight className="size-3.5" />
                        </Link>
                    </CardContent>
                </Card>
            </div>

            {/* Pintasan Modul */}
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                {QUICK_MODULES.map((mod) => (
                    <Link
                        key={mod.href + mod.title}
                        href={mod.href}
                        className="group flex flex-col justify-between gap-2 rounded-xl border border-border/80 bg-card p-3.5 shadow-2xs transition-all hover:border-primary/40 hover:shadow-sm"
                    >
                        <div className="flex items-center justify-between">
                            <div
                                className={cn(
                                    'flex size-8 items-center justify-center rounded-lg',
                                    mod.tone,
                                )}
                            >
                                <mod.icon className="size-4" />
                            </div>
                            <ArrowUpRight className="size-3.5 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-foreground" />
                        </div>
                        <div>
                            <p className="text-xs font-semibold text-foreground">{mod.title}</p>
                            <p className="text-[11px] text-muted-foreground line-clamp-2 mt-0.5">
                                {mod.description}
                            </p>
                        </div>
                    </Link>
                ))}
            </div>

            {/* Tabel Utama: PO Terbaru + Tagihan Pending */}
            <div className="grid gap-4 lg:grid-cols-2">
                {/* Purchase Order Terbaru */}
                <Card className="border-border/80 shadow-xs flex flex-col">
                    <CardHeader className="flex flex-row items-start justify-between gap-3 pb-3">
                        <div className="space-y-1">
                            <div className="flex items-center gap-2">
                                <CardTitle className="text-base">Purchase Order Terbaru</CardTitle>
                                <Badge variant="secondary" className="text-xs">
                                    {ordersStats.total} Total
                                </Badge>
                            </div>
                            <CardDescription className="text-xs">
                                Daftar PO terbaru dari seluruh vendor mitra perusahaan.
                            </CardDescription>
                        </div>
                        <Button asChild variant="outline" size="sm" className="h-8 text-xs shrink-0">
                            <Link href="/purchase-orders">Lihat Semua</Link>
                        </Button>
                    </CardHeader>
                    <CardContent className="flex-1">
                        {recentPurchaseOrders.length === 0 ? (
                            <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-8 px-4 text-center">
                                <ShoppingCart className="size-8 text-muted-foreground/40 mb-2" />
                                <p className="text-sm font-medium text-foreground">
                                    Belum Ada Purchase Order
                                </p>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    Buat PO pertama untuk memulai proses pengadaan.
                                </p>
                                <Button asChild size="sm" className="mt-3 h-8 text-xs">
                                    <Link href="/purchase-orders/create">Buat PO Baru</Link>
                                </Button>
                            </div>
                        ) : (
                            <div className="space-y-2.5">
                                {recentPurchaseOrders.map((po) => (
                                    <div
                                        key={po.uuid}
                                        className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-xl border bg-muted/20 p-3 transition-colors hover:bg-muted/40"
                                    >
                                        <div className="min-w-0 space-y-1">
                                            <div className="flex flex-wrap items-center gap-1.5">
                                                <p className="text-sm font-semibold text-foreground">
                                                    {po.po_number}
                                                </p>
                                                <Badge
                                                    className={cn(
                                                        'text-[10px] border',
                                                        getPoStatusClass(po.status),
                                                    )}
                                                >
                                                    {po.status_label}
                                                </Badge>
                                            </div>
                                            <p className="text-xs text-muted-foreground truncate">
                                                {po.vendor_name}
                                                {po.vendor_entity && (
                                                    <span className="ml-1 text-[10px] uppercase">
                                                        ({po.vendor_entity})
                                                    </span>
                                                )}
                                            </p>
                                            <div className="flex flex-wrap items-center gap-x-3 text-[11px] text-muted-foreground">
                                                <span>{po.items_count} item</span>
                                                <span className="font-medium text-foreground">
                                                    {formatCompactCurrency(po.total_amount)}
                                                </span>
                                                <span>{formatDate(po.created_at)}</span>
                                            </div>
                                        </div>
                                        <Button asChild size="sm" variant="outline" className="h-8 text-xs shrink-0">
                                            <Link href={`/purchase-orders/${po.uuid}`}>Detail</Link>
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Tagihan Menunggu Verifikasi */}
                <Card className="border-border/80 shadow-xs flex flex-col">
                    <CardHeader className="flex flex-row items-start justify-between gap-3 pb-3">
                        <div className="space-y-1">
                            <div className="flex items-center gap-2">
                                <CardTitle className="text-base">Tagihan Perlu Diverifikasi</CardTitle>
                                <Badge
                                    variant={
                                        invoicesStats.pending + invoicesStats.mismatched > 0
                                            ? 'default'
                                            : 'secondary'
                                    }
                                    className="text-xs"
                                >
                                    {invoicesStats.pending + invoicesStats.mismatched} Menunggu
                                </Badge>
                            </div>
                            <CardDescription className="text-xs">
                                Invoice dengan status <em>pending</em> atau <em>mismatched</em> yang
                                perlu ditindaklanjuti.
                            </CardDescription>
                        </div>
                        <Button asChild variant="outline" size="sm" className="h-8 text-xs shrink-0">
                            <Link href="/invoice">Lihat Semua</Link>
                        </Button>
                    </CardHeader>
                    <CardContent className="flex-1">
                        {pendingInvoices.length === 0 ? (
                            <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-8 px-4 text-center">
                                <CheckCircle2 className="size-8 text-emerald-500/70 mb-2" />
                                <p className="text-sm font-medium text-foreground">
                                    Tidak Ada Tagihan Pending
                                </p>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    Seluruh tagihan telah diverifikasi atau belum ada tagihan masuk.
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-2.5">
                                {pendingInvoices.map((invoice) => (
                                    <div
                                        key={invoice.uuid}
                                        className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-xl border bg-muted/20 p-3 transition-colors hover:bg-muted/40"
                                    >
                                        <div className="min-w-0 space-y-1">
                                            <div className="flex flex-wrap items-center gap-1.5">
                                                <p className="text-sm font-semibold text-foreground truncate">
                                                    {invoice.invoice_number}
                                                </p>
                                                <Badge
                                                    className={cn(
                                                        'text-[10px] border shrink-0',
                                                        getMatchingStatusClass(invoice.matching_status),
                                                    )}
                                                >
                                                    {invoice.matching_status_label}
                                                </Badge>
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                {invoice.vendor_name} · PO: {invoice.po_number}
                                            </p>
                                            <div className="flex items-center gap-3 text-[11px] text-muted-foreground">
                                                <span className="font-medium text-foreground">
                                                    {formatCompactCurrency(invoice.amount)}
                                                </span>
                                                <span>{formatDate(invoice.invoice_date || invoice.created_at)}</span>
                                            </div>
                                        </div>
                                        <Button asChild size="sm" className="h-8 text-xs shrink-0">
                                            <Link href={`/invoice/${invoice.uuid}`}>Verifikasi</Link>
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Sengketa Aktif */}
            {activeDisputes.length > 0 && (
                <Card className="border-border/80 shadow-xs">
                    <CardHeader className="flex flex-row items-start justify-between gap-3 pb-3">
                        <div className="space-y-1">
                            <div className="flex items-center gap-2">
                                <CardTitle className="text-base">Sengketa Aktif</CardTitle>
                                <Badge variant="destructive" className="text-xs">
                                    {activeDisputes.length} Aktif
                                </Badge>
                            </div>
                            <CardDescription className="text-xs">
                                Sengketa pengadaan yang sedang terbuka dan perlu penyelesaian.
                            </CardDescription>
                        </div>
                        <Button asChild variant="outline" size="sm" className="h-8 text-xs shrink-0">
                            <Link href="/sengketa">Lihat Semua</Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
                            {activeDisputes.map((dispute) => (
                                <div
                                    key={dispute.uuid}
                                    className="flex flex-col gap-1.5 rounded-xl border bg-muted/20 p-3 hover:bg-muted/40 transition-colors"
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <p className="text-xs font-semibold text-foreground truncate">
                                            {dispute.po_number}
                                        </p>
                                        <Badge
                                            className="text-[10px] shrink-0 bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-500/30"
                                        >
                                            {dispute.status_label}
                                        </Badge>
                                    </div>
                                    <p className="text-[11px] text-muted-foreground">
                                        {dispute.vendor_name}
                                    </p>
                                    <p className="text-[11px] text-muted-foreground line-clamp-2">
                                        {dispute.reason}
                                    </p>
                                    <Link
                                        href={`/sengketa/${dispute.uuid}`}
                                        className="text-[11px] font-semibold text-primary hover:underline"
                                    >
                                        Tinjau Sengketa →
                                    </Link>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
