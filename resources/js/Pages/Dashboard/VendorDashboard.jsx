import { Link } from '@inertiajs/react';
import {
    ArrowUpRight,
    CheckCircle2,
    FileText,
    Handshake,
    MapPin,
    Receipt,
    ShoppingBag,
    TrendingUp,
    Truck,
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
        case 'menunggu_tanggapan':
        case 'negosiasi':
            return 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30';
        case 'sengketa':
            return 'bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-500/30';
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

function getPartnershipStatusClass(status) {
    switch (status) {
        case 'agreed':
            return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30';
        case 'pending':
        case 'diajukan':
            return 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30';
        default:
            return 'bg-muted/50 text-muted-foreground border-border';
    }
}

const QUICK_MODULES = [
    {
        title: 'Purchase Order',
        description: 'Pantau & tanggapi pesanan dari buyer',
        href: '/purchase-order',
        icon: ShoppingBag,
        tone: 'text-sky-600 dark:text-sky-400 bg-sky-500/10',
    },
    {
        title: 'Calon Mitra & Kerja Sama',
        description: 'Kelola kemitraan dengan perusahaan buyer',
        href: '/kerja-sama/calon-mitra',
        icon: Handshake,
        tone: 'text-teal-600 dark:text-teal-400 bg-teal-500/10',
    },
    {
        title: 'Invoice Saya',
        description: 'Terbitkan & pantau tagihan ke buyer',
        href: '/invoice',
        icon: Receipt,
        tone: 'text-emerald-600 dark:text-emerald-400 bg-emerald-500/10',
    },
    {
        title: 'Pengiriman Logistik',
        description: 'Laporkan status pengiriman fisik barang',
        href: '/pengiriman',
        icon: Truck,
        tone: 'text-amber-600 dark:text-amber-400 bg-amber-500/10',
    },
];

export default function VendorDashboard({ userName, dashboard }) {
    const summary = dashboard?.summary ?? {};

    const ordersStats = summary.orders ?? {
        total: 0,
        pending_response: 0,
        in_execution: 0,
        completed_paid: 0,
        disputed: 0,
    };
    const invoicesStats = summary.invoices ?? {
        total: 0,
        pending: 0,
        matched: 0,
        total_revenue: 0,
    };
    const partnershipsStats = summary.partnerships ?? {
        active: 0,
        pending: 0,
    };

    const recentOrders = Array.isArray(dashboard?.recent_orders) ? dashboard.recent_orders : [];
    const partnerships = Array.isArray(dashboard?.partnerships) ? dashboard.partnerships : [];
    const recentInvoices = Array.isArray(dashboard?.recent_invoices) ? dashboard.recent_invoices : [];

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
                            Vendor / Pemasok
                        </Badge>
                        {ordersStats.pending_response > 0 && (
                            <Badge className="text-xs font-medium px-2.5 py-0.5 bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30">
                                {ordersStats.pending_response} PO Menunggu Respons
                            </Badge>
                        )}
                    </div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        Selamat Datang, {userName || 'Vendor'}!
                    </h1>
                    <p className="text-sm text-muted-foreground max-w-3xl">
                        Pantau pesanan dari mitra buyer, kelola pengiriman, terbitkan tagihan,
                        dan kelola kemitraan B2B Anda di platform PAGORA.
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Button asChild variant="outline" size="sm" className="h-9 gap-1.5">
                        <Link href="/kerja-sama/calon-mitra">
                            <Handshake className="size-4" />
                            <span>Ajukan Kerja Sama</span>
                        </Link>
                    </Button>
                    <Button asChild size="sm" className="h-9 gap-1.5">
                        <Link href="/purchase-order">
                            <ShoppingBag className="size-4" />
                            <span>Lihat Pesanan</span>
                        </Link>
                    </Button>
                </div>
            </div>

            {/* Banner PO menunggu respons */}
            {ordersStats.pending_response > 0 && (
                <div className="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-start gap-3">
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-500/20 text-amber-700 dark:text-amber-300 mt-0.5 sm:mt-0">
                            <ShoppingBag className="size-5" />
                        </div>
                        <div className="space-y-0.5">
                            <p className="text-sm font-semibold text-amber-950 dark:text-amber-200">
                                Terdapat Pesanan Menunggu Respons Anda
                            </p>
                            <p className="text-xs text-amber-800/90 dark:text-amber-300/90">
                                <strong>{ordersStats.pending_response} Purchase Order</strong> telah masuk
                                dan menunggu persetujuan atau negosiasi harga dari Anda.
                            </p>
                        </div>
                    </div>
                    <Button
                        asChild
                        size="sm"
                        className="h-8 text-xs bg-amber-600 hover:bg-amber-700 text-white shrink-0"
                    >
                        <Link href="/purchase-order">
                            Tanggapi Pesanan ({ordersStats.pending_response})
                        </Link>
                    </Button>
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
                            <ShoppingBag className="size-4" />
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="flex items-baseline justify-between">
                            <div>
                                <div className="text-2xl font-bold tracking-tight">
                                    {ordersStats.total}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    {ordersStats.in_execution} sedang berjalan
                                </p>
                            </div>
                            {ordersStats.pending_response > 0 && (
                                <Badge className="bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30 text-[11px]">
                                    {ordersStats.pending_response} Menunggu
                                </Badge>
                            )}
                        </div>

                        <div className="grid grid-cols-3 gap-1.5 pt-2 border-t text-[11px]">
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Aktif</span>
                                <span className="font-semibold text-sky-600 dark:text-sky-400">
                                    {ordersStats.in_execution}
                                </span>
                            </div>
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Lunas</span>
                                <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                                    {ordersStats.completed_paid}
                                </span>
                            </div>
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Sengketa</span>
                                <span className="font-semibold text-rose-600 dark:text-rose-400">
                                    {ordersStats.disputed}
                                </span>
                            </div>
                        </div>

                        <Link
                            href="/purchase-order"
                            className="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline pt-0.5"
                        >
                            <span>Kelola Pesanan</span>
                            <ArrowUpRight className="size-3.5" />
                        </Link>
                    </CardContent>
                </Card>

                {/* KPI 2: Tagihan & Pendapatan */}
                <Card className="border-border/80 shadow-xs">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Tagihan & Pendapatan
                        </CardTitle>
                        <div className="flex size-8 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                            <Receipt className="size-4" />
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="flex items-baseline justify-between">
                            <div>
                                <div className="text-2xl font-bold tracking-tight">
                                    {formatCompactCurrency(invoicesStats.total_revenue)}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Total tagihan disetujui (matched)
                                </p>
                            </div>
                        </div>

                        <div className="grid grid-cols-3 gap-1.5 pt-2 border-t text-[11px]">
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Total</span>
                                <span className="font-semibold text-foreground">
                                    {invoicesStats.total}
                                </span>
                            </div>
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Pending</span>
                                <span className="font-semibold text-amber-600 dark:text-amber-400">
                                    {invoicesStats.pending}
                                </span>
                            </div>
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Disetujui</span>
                                <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                                    {invoicesStats.matched}
                                </span>
                            </div>
                        </div>

                        <Link
                            href="/invoice"
                            className="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline pt-0.5"
                        >
                            <span>Kelola Invoice</span>
                            <ArrowUpRight className="size-3.5" />
                        </Link>
                    </CardContent>
                </Card>

                {/* KPI 3: Kemitraan Aktif */}
                <Card className="border-border/80 shadow-xs">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Kemitraan Aktif
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
                                    perusahaan buyer sebagai mitra
                                </p>
                            </div>
                            {partnershipsStats.pending > 0 && (
                                <Badge className="bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30 text-[11px]">
                                    {partnershipsStats.pending} Pending
                                </Badge>
                            )}
                        </div>

                        <div className="grid grid-cols-2 gap-1.5 pt-2 border-t text-[11px]">
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Aktif</span>
                                <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                                    {partnershipsStats.active}
                                </span>
                            </div>
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Menunggu</span>
                                <span className="font-semibold text-amber-600 dark:text-amber-400">
                                    {partnershipsStats.pending}
                                </span>
                            </div>
                        </div>

                        <Link
                            href="/kerja-sama/calon-mitra"
                            className="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline pt-0.5"
                        >
                            <span>Kelola Kerja Sama</span>
                            <ArrowUpRight className="size-3.5" />
                        </Link>
                    </CardContent>
                </Card>

                {/* KPI 4: Tren Kinerja */}
                <Card className="border-border/80 shadow-xs">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Ringkasan Kinerja
                        </CardTitle>
                        <div className="flex size-8 items-center justify-center rounded-lg bg-violet-500/10 text-violet-600 dark:text-violet-400">
                            <TrendingUp className="size-4" />
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="flex items-baseline justify-between">
                            <div>
                                <div className="text-2xl font-bold tracking-tight">
                                    {ordersStats.completed_paid}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    pesanan selesai & lunas
                                </p>
                            </div>
                        </div>

                        <div className="pt-2 border-t space-y-1.5 text-[11px]">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Tingkat Penyelesaian</span>
                                <span className="font-semibold text-foreground">
                                    {ordersStats.total > 0
                                        ? Math.round((ordersStats.completed_paid / ordersStats.total) * 100)
                                        : 0}%
                                </span>
                            </div>
                            <div className="h-1.5 w-full rounded-full bg-muted overflow-hidden">
                                <div
                                    className="h-full rounded-full bg-emerald-500"
                                    style={{
                                        width: `${ordersStats.total > 0 ? Math.min(100, Math.round((ordersStats.completed_paid / ordersStats.total) * 100)) : 0}%`,
                                    }}
                                />
                            </div>
                        </div>

                        <Link
                            href="/purchase-order"
                            className="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline pt-0.5"
                        >
                            <span>Lihat Riwayat Pesanan</span>
                            <ArrowUpRight className="size-3.5" />
                        </Link>
                    </CardContent>
                </Card>
            </div>

            {/* Pintasan Modul */}
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
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

            {/* Tiga Tabel: PO Terbaru + Kemitraan + Invoice */}
            <div className="grid gap-4 lg:grid-cols-2">
                {/* Purchase Order Terbaru */}
                <Card className="border-border/80 shadow-xs flex flex-col">
                    <CardHeader className="flex flex-row items-start justify-between gap-3 pb-3">
                        <div className="space-y-1">
                            <div className="flex items-center gap-2">
                                <CardTitle className="text-base">Pesanan Terbaru</CardTitle>
                                <Badge variant="secondary" className="text-xs">
                                    {ordersStats.total} Total
                                </Badge>
                            </div>
                            <CardDescription className="text-xs">
                                Purchase Order terbaru yang masuk dari mitra buyer.
                            </CardDescription>
                        </div>
                        <Button asChild variant="outline" size="sm" className="h-8 text-xs shrink-0">
                            <Link href="/purchase-order">Lihat Semua</Link>
                        </Button>
                    </CardHeader>
                    <CardContent className="flex-1">
                        {recentOrders.length === 0 ? (
                            <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-8 px-4 text-center">
                                <ShoppingBag className="size-8 text-muted-foreground/40 mb-2" />
                                <p className="text-sm font-medium text-foreground">
                                    Belum Ada Purchase Order
                                </p>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    Pesanan dari mitra buyer akan muncul di sini.
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-2.5">
                                {recentOrders.map((po) => (
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
                                            <p className="text-xs text-muted-foreground">
                                                {po.buyer_name}
                                            </p>
                                            <div className="flex items-center gap-3 text-[11px] text-muted-foreground">
                                                <span>{po.items_count} item</span>
                                                <span className="font-medium text-foreground">
                                                    {formatCompactCurrency(po.total_amount)}
                                                </span>
                                                <span>{formatDate(po.created_at)}</span>
                                            </div>
                                        </div>
                                        <Button asChild size="sm" variant="outline" className="h-8 text-xs shrink-0">
                                            <Link href={`/purchase-order/${po.uuid}`}>Detail</Link>
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Invoice Terbaru */}
                <Card className="border-border/80 shadow-xs flex flex-col">
                    <CardHeader className="flex flex-row items-start justify-between gap-3 pb-3">
                        <div className="space-y-1">
                            <div className="flex items-center gap-2">
                                <CardTitle className="text-base">Invoice Terbaru</CardTitle>
                                <Badge variant="secondary" className="text-xs">
                                    {invoicesStats.total} Total
                                </Badge>
                            </div>
                            <CardDescription className="text-xs">
                                Tagihan yang telah diterbitkan — prioritas: pending & mismatched.
                            </CardDescription>
                        </div>
                        <Button asChild variant="outline" size="sm" className="h-8 text-xs shrink-0">
                            <Link href="/invoice">Lihat Semua</Link>
                        </Button>
                    </CardHeader>
                    <CardContent className="flex-1">
                        {recentInvoices.length === 0 ? (
                            <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-8 px-4 text-center">
                                <FileText className="size-8 text-muted-foreground/40 mb-2" />
                                <p className="text-sm font-medium text-foreground">
                                    Belum Ada Invoice
                                </p>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    Tagihan dapat diterbitkan setelah barang diterima oleh buyer.
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-2.5">
                                {recentInvoices.map((invoice) => (
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
                                                {invoice.buyer_name} · PO: {invoice.po_number}
                                            </p>
                                            <div className="flex items-center gap-3 text-[11px] text-muted-foreground">
                                                <span className="font-medium text-foreground">
                                                    {formatCompactCurrency(invoice.amount)}
                                                </span>
                                                <span>{formatDate(invoice.invoice_date || invoice.created_at)}</span>
                                            </div>
                                        </div>
                                        <Button asChild size="sm" variant="outline" className="h-8 text-xs shrink-0">
                                            <Link href={`/invoice/${invoice.uuid}`}>Detail</Link>
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Kemitraan Aktif & Pending */}
            <Card className="border-border/80 shadow-xs">
                <CardHeader className="flex flex-row items-start justify-between gap-3 pb-3">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2">
                            <CardTitle className="text-base">Kerja Sama Mitra Buyer</CardTitle>
                            <Badge variant="secondary" className="text-xs">
                                {partnershipsStats.active} Aktif
                            </Badge>
                            {partnershipsStats.pending > 0 && (
                                <Badge className="text-xs bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30">
                                    {partnershipsStats.pending} Pending
                                </Badge>
                            )}
                        </div>
                        <CardDescription className="text-xs">
                            Status kemitraan dengan perusahaan buyer — hanya mitra aktif yang dapat
                            membuat PO.
                        </CardDescription>
                    </div>
                    <Button asChild variant="outline" size="sm" className="h-8 text-xs shrink-0">
                        <Link href="/kerja-sama/calon-mitra">Kelola Semua</Link>
                    </Button>
                </CardHeader>
                <CardContent>
                    {partnerships.length === 0 ? (
                        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-8 px-4 text-center">
                            <Handshake className="size-8 text-muted-foreground/40 mb-2" />
                            <p className="text-sm font-medium text-foreground">
                                Belum Ada Kerja Sama
                            </p>
                            <p className="text-xs text-muted-foreground mt-0.5">
                                Ajukan kerja sama ke perusahaan buyer untuk mulai menerima pesanan.
                            </p>
                            <Button asChild size="sm" className="mt-3 h-8 text-xs">
                                <Link href="/kerja-sama/calon-mitra">Ajukan Kerja Sama</Link>
                            </Button>
                        </div>
                    ) : (
                        <div className="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
                            {partnerships.map((ks) => (
                                <div
                                    key={ks.uuid}
                                    className="flex flex-col gap-1.5 rounded-xl border bg-muted/20 p-3 hover:bg-muted/40 transition-colors"
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <p className="text-sm font-semibold text-foreground truncate">
                                            {ks.buyer_name}
                                        </p>
                                        <Badge
                                            className={cn(
                                                'text-[10px] shrink-0 border',
                                                getPartnershipStatusClass(ks.status),
                                            )}
                                        >
                                            {ks.status_label}
                                        </Badge>
                                    </div>
                                    {ks.buyer_city && (
                                        <div className="flex items-center gap-1 text-[11px] text-muted-foreground">
                                            <MapPin className="size-3" />
                                            <span>{ks.buyer_city}</span>
                                        </div>
                                    )}
                                    <p className="text-[11px] text-muted-foreground">
                                        {ks.status === 'agreed' || ks.status === 'Aktif'
                                            ? `Aktif sejak ${formatDate(ks.agreed_at || ks.created_at)}`
                                            : `Diajukan ${formatDate(ks.created_at)}`}
                                    </p>
                                </div>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
