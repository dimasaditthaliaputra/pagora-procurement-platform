import { Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowUpRight,
    CheckCircle2,
    ClipboardCheck,
    PackageCheck,
    PackageX,
    Truck,
    XCircle,
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

function formatDate(isoString) {
    if (!isoString) return '-';
    try {
        return new Intl.DateTimeFormat('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(isoString));
    } catch {
        return '-';
    }
}

function getPoStatusClass(status) {
    switch (status) {
        case 'dalam_pengiriman':
            return 'bg-sky-500/15 text-sky-700 dark:text-sky-300 border-sky-500/30';
        case 'partially_received':
            return 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30';
        case 'diterima_gudang':
            return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30';
        default:
            return 'bg-muted/50 text-muted-foreground border-border';
    }
}

const QUICK_MODULES = [
    {
        title: 'Penerimaan Barang',
        description: 'Catat & inspeksi barang masuk dari vendor',
        href: '/penerimaan-barang',
        icon: ClipboardCheck,
        tone: 'text-sky-600 dark:text-sky-400 bg-sky-500/10',
    },
    {
        title: 'Purchase Order',
        description: 'Pantau status PO & jadwal pengiriman',
        href: '/purchase-orders',
        icon: Truck,
        tone: 'text-amber-600 dark:text-amber-400 bg-amber-500/10',
    },
];

export default function QcDashboard({ userName, dashboard }) {
    const company = dashboard?.company ?? null;
    const summary = dashboard?.summary ?? {};

    const inspectionsStats = summary.inspections ?? {
        total_receipts: 0,
        pending_receipts: 0,
        completed_receipts: 0,
        in_transit_orders: 0,
        accepted_items: 0,
        rejected_items: 0,
        total_items_inspected: 0,
        acceptance_rate: 0,
    };

    const pendingDeliveries = Array.isArray(dashboard?.pending_deliveries)
        ? dashboard.pending_deliveries
        : [];
    const recentInspections = Array.isArray(dashboard?.recent_inspections)
        ? dashboard.recent_inspections
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
                            <ClipboardCheck className="size-3.5 mr-1" />
                            QC Gudang
                        </Badge>
                        {company && (
                            <Badge
                                variant="outline"
                                className="text-xs font-medium px-2.5 py-0.5 border-orange-500/30 bg-orange-500/10 text-orange-700 dark:text-orange-400"
                            >
                                {company.company_name}
                            </Badge>
                        )}
                    </div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        Selamat Datang, {userName || 'QC Gudang'}!
                    </h1>
                    <p className="text-sm text-muted-foreground max-w-3xl">
                        Pantau pengiriman barang yang masuk, lakukan inspeksi kualitas per item,
                        dan catat penerimaan gudang sesuai Purchase Order.
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Button asChild size="sm" className="h-9 gap-1.5">
                        <Link href="/penerimaan-barang">
                            <ClipboardCheck className="size-4" />
                            <span>Inspeksi Barang</span>
                        </Link>
                    </Button>
                </div>
            </div>

            {/* Banner pengiriman menunggu inspeksi */}
            {inspectionsStats.pending_receipts > 0 && (
                <div className="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-start gap-3">
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-500/20 text-amber-700 dark:text-amber-300 mt-0.5 sm:mt-0">
                            <AlertTriangle className="size-5" />
                        </div>
                        <div className="space-y-0.5">
                            <p className="text-sm font-semibold text-amber-950 dark:text-amber-200">
                                Terdapat Pengiriman Belum Diinspeksi
                            </p>
                            <p className="text-xs text-amber-800/90 dark:text-amber-300/90">
                                <strong>{inspectionsStats.pending_receipts} pengiriman</strong> telah
                                tiba di gudang dan menunggu inspeksi kualitas barang.
                            </p>
                        </div>
                    </div>
                    <Button
                        asChild
                        size="sm"
                        className="h-8 text-xs bg-amber-600 hover:bg-amber-700 text-white shrink-0"
                    >
                        <Link href="/penerimaan-barang">
                            Mulai Inspeksi ({inspectionsStats.pending_receipts})
                        </Link>
                    </Button>
                </div>
            )}

            {/* 4 KPI Utama */}
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {/* KPI 1: PO Dalam Pengiriman */}
                <Card className="border-border/80 shadow-xs">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Dalam Pengiriman
                        </CardTitle>
                        <div className="flex size-8 items-center justify-center rounded-lg bg-sky-500/10 text-sky-600 dark:text-sky-400">
                            <Truck className="size-4" />
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="flex items-baseline justify-between">
                            <div>
                                <div className="text-2xl font-bold tracking-tight">
                                    {inspectionsStats.in_transit_orders}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    PO menunggu/sedang pengiriman
                                </p>
                            </div>
                            {inspectionsStats.pending_receipts > 0 && (
                                <Badge className="bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30 text-[11px]">
                                    {inspectionsStats.pending_receipts} Belum Diinspeksi
                                </Badge>
                            )}
                        </div>

                        <div className="pt-2 border-t text-[11px] text-muted-foreground">
                            {inspectionsStats.in_transit_orders === 0
                                ? 'Tidak ada pengiriman aktif saat ini'
                                : `${inspectionsStats.completed_receipts} pengiriman telah diinspeksi`}
                        </div>

                        <Link
                            href="/purchase-orders"
                            className="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline pt-0.5"
                        >
                            <span>Lihat Semua PO</span>
                            <ArrowUpRight className="size-3.5" />
                        </Link>
                    </CardContent>
                </Card>

                {/* KPI 2: Total Penerimaan */}
                <Card className="border-border/80 shadow-xs">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Total Penerimaan
                        </CardTitle>
                        <div className="flex size-8 items-center justify-center rounded-lg bg-teal-500/10 text-teal-600 dark:text-teal-400">
                            <ClipboardCheck className="size-4" />
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="flex items-baseline justify-between">
                            <div>
                                <div className="text-2xl font-bold tracking-tight">
                                    {inspectionsStats.total_receipts}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    {inspectionsStats.completed_receipts} selesai diinspeksi
                                </p>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-1.5 pt-2 border-t text-[11px]">
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Selesai</span>
                                <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                                    {inspectionsStats.completed_receipts}
                                </span>
                            </div>
                            <div className="rounded-md bg-muted/50 px-2 py-1">
                                <span className="text-muted-foreground block">Pending</span>
                                <span className="font-semibold text-amber-600 dark:text-amber-400">
                                    {inspectionsStats.pending_receipts}
                                </span>
                            </div>
                        </div>

                        <Link
                            href="/penerimaan-barang"
                            className="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline pt-0.5"
                        >
                            <span>Riwayat Penerimaan</span>
                            <ArrowUpRight className="size-3.5" />
                        </Link>
                    </CardContent>
                </Card>

                {/* KPI 3: Item Diterima */}
                <Card className="border-border/80 shadow-xs">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Item Diterima
                        </CardTitle>
                        <div className="flex size-8 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                            <PackageCheck className="size-4" />
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="flex items-baseline justify-between">
                            <div>
                                <div className="text-2xl font-bold tracking-tight">
                                    {inspectionsStats.accepted_items}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    dari {inspectionsStats.total_items_inspected} item diinspeksi
                                </p>
                            </div>
                            <Badge
                                className={cn(
                                    'text-[11px]',
                                    inspectionsStats.acceptance_rate >= 90
                                        ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30'
                                        : inspectionsStats.acceptance_rate >= 70
                                          ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30'
                                          : 'bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-500/30',
                                )}
                            >
                                {inspectionsStats.acceptance_rate}% Lulus
                            </Badge>
                        </div>

                        <div className="pt-2 border-t">
                            <div className="h-2 w-full rounded-full bg-muted overflow-hidden">
                                <div
                                    className="h-full rounded-full bg-emerald-500 transition-all"
                                    style={{
                                        width: `${Math.min(100, inspectionsStats.acceptance_rate)}%`,
                                    }}
                                />
                            </div>
                            <p className="text-[11px] text-muted-foreground mt-1">
                                Tingkat kelulusan inspeksi keseluruhan
                            </p>
                        </div>

                        <Link
                            href="/penerimaan-barang"
                            className="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline pt-0.5"
                        >
                            <span>Detail Inspeksi</span>
                            <ArrowUpRight className="size-3.5" />
                        </Link>
                    </CardContent>
                </Card>

                {/* KPI 4: Item Ditolak */}
                <Card className="border-border/80 shadow-xs">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Item Ditolak
                        </CardTitle>
                        <div className="flex size-8 items-center justify-center rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-400">
                            <PackageX className="size-4" />
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="flex items-baseline justify-between">
                            <div>
                                <div className="text-2xl font-bold tracking-tight">
                                    {inspectionsStats.rejected_items}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    item gagal lulus inspeksi QC
                                </p>
                            </div>
                        </div>

                        <div className="pt-2 border-t">
                            {inspectionsStats.rejected_items === 0 ? (
                                <div className="flex items-center gap-2 text-[11px] text-emerald-600 dark:text-emerald-400">
                                    <CheckCircle2 className="size-3.5" />
                                    <span>Semua item lolos inspeksi</span>
                                </div>
                            ) : (
                                <div className="flex items-center gap-2 text-[11px] text-rose-600 dark:text-rose-400">
                                    <XCircle className="size-3.5" />
                                    <span>Item ditolak dikirimkan ke sengketa</span>
                                </div>
                            )}
                        </div>

                        <Link
                            href="/penerimaan-barang"
                            className="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline pt-0.5"
                        >
                            <span>Lihat Laporan Tolak</span>
                            <ArrowUpRight className="size-3.5" />
                        </Link>
                    </CardContent>
                </Card>
            </div>

            {/* Pintasan Modul */}
            <div className="grid gap-3 sm:grid-cols-2">
                {QUICK_MODULES.map((mod) => (
                    <Link
                        key={mod.href + mod.title}
                        href={mod.href}
                        className="group flex items-center gap-4 rounded-xl border border-border/80 bg-card p-4 shadow-2xs transition-all hover:border-primary/40 hover:shadow-sm"
                    >
                        <div
                            className={cn(
                                'flex size-10 shrink-0 items-center justify-center rounded-lg',
                                mod.tone,
                            )}
                        >
                            <mod.icon className="size-5" />
                        </div>
                        <div className="flex-1 min-w-0">
                            <p className="text-sm font-semibold text-foreground">{mod.title}</p>
                            <p className="text-xs text-muted-foreground mt-0.5">{mod.description}</p>
                        </div>
                        <ArrowUpRight className="size-4 text-muted-foreground group-hover:text-foreground transition-colors shrink-0" />
                    </Link>
                ))}
            </div>

            {/* Dua Tabel: Pengiriman Menunggu + Riwayat Inspeksi */}
            <div className="grid gap-4 lg:grid-cols-2">
                {/* Pengiriman Menunggu Inspeksi */}
                <Card className="border-border/80 shadow-xs flex flex-col">
                    <CardHeader className="flex flex-row items-start justify-between gap-3 pb-3">
                        <div className="space-y-1">
                            <div className="flex items-center gap-2">
                                <CardTitle className="text-base">Menunggu Inspeksi</CardTitle>
                                <Badge
                                    variant={pendingDeliveries.length > 0 ? 'default' : 'secondary'}
                                    className="text-xs"
                                >
                                    {pendingDeliveries.length} PO
                                </Badge>
                            </div>
                            <CardDescription className="text-xs">
                                Purchase Order dengan barang dalam pengiriman atau sebagian diterima.
                            </CardDescription>
                        </div>
                        <Button asChild variant="outline" size="sm" className="h-8 text-xs shrink-0">
                            <Link href="/penerimaan-barang">Semua Penerimaan</Link>
                        </Button>
                    </CardHeader>
                    <CardContent className="flex-1">
                        {pendingDeliveries.length === 0 ? (
                            <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-8 px-4 text-center">
                                <CheckCircle2 className="size-8 text-emerald-500/70 mb-2" />
                                <p className="text-sm font-medium text-foreground">
                                    Tidak Ada Pengiriman Aktif
                                </p>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    Semua pengiriman telah diinspeksi atau belum ada pengiriman masuk.
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-2.5">
                                {pendingDeliveries.map((po) => (
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
                                                {po.vendor_name}
                                            </p>
                                            <div className="flex flex-wrap gap-1 mt-0.5">
                                                {po.commodity_names?.slice(0, 3).map((name, idx) => (
                                                    <Badge
                                                        key={idx}
                                                        variant="outline"
                                                        className="text-[10px] px-1.5"
                                                    >
                                                        {name}
                                                    </Badge>
                                                ))}
                                                {(po.commodity_names?.length ?? 0) > 3 && (
                                                    <Badge
                                                        variant="outline"
                                                        className="text-[10px] px-1.5"
                                                    >
                                                        +{(po.commodity_names?.length ?? 0) - 3} lainnya
                                                    </Badge>
                                                )}
                                            </div>
                                        </div>
                                        <Button asChild size="sm" className="h-8 text-xs shrink-0">
                                            <Link href={`/penerimaan-barang?po=${po.uuid}`}>
                                                Inspeksi
                                            </Link>
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Riwayat Inspeksi Terbaru */}
                <Card className="border-border/80 shadow-xs flex flex-col">
                    <CardHeader className="flex flex-row items-start justify-between gap-3 pb-3">
                        <div className="space-y-1">
                            <CardTitle className="text-base">Riwayat Inspeksi Terbaru</CardTitle>
                            <CardDescription className="text-xs">
                                Hasil inspeksi QC yang telah diselesaikan beserta ringkasan penerimaan.
                            </CardDescription>
                        </div>
                        <Button asChild variant="outline" size="sm" className="h-8 text-xs shrink-0">
                            <Link href="/penerimaan-barang">Lihat Semua</Link>
                        </Button>
                    </CardHeader>
                    <CardContent className="flex-1">
                        {recentInspections.length === 0 ? (
                            <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-8 px-4 text-center">
                                <ClipboardCheck className="size-8 text-muted-foreground/40 mb-2" />
                                <p className="text-sm font-medium text-foreground">
                                    Belum Ada Riwayat Inspeksi
                                </p>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    Riwayat inspeksi akan muncul setelah proses penerimaan barang dilakukan.
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-2.5">
                                {recentInspections.map((receipt) => (
                                    <div
                                        key={receipt.uuid}
                                        className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-xl border bg-muted/20 p-3 transition-colors hover:bg-muted/40"
                                    >
                                        <div className="min-w-0 space-y-1">
                                            <div className="flex items-center gap-2">
                                                <p className="text-sm font-semibold text-foreground">
                                                    {receipt.po_number}
                                                </p>
                                                {receipt.rejected_count > 0 ? (
                                                    <Badge className="text-[10px] bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-500/30">
                                                        {receipt.rejected_count} Ditolak
                                                    </Badge>
                                                ) : (
                                                    <Badge className="text-[10px] bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30">
                                                        Semua Diterima
                                                    </Badge>
                                                )}
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                {receipt.vendor_name} · Inspektor: {receipt.inspector_name}
                                            </p>
                                            <div className="flex items-center gap-3 text-[11px] text-muted-foreground">
                                                <span className="text-emerald-600 dark:text-emerald-400">
                                                    ✓ {receipt.accepted_count} diterima
                                                </span>
                                                {receipt.rejected_count > 0 && (
                                                    <span className="text-rose-600 dark:text-rose-400">
                                                        ✗ {receipt.rejected_count} ditolak
                                                    </span>
                                                )}
                                                <span>{formatDate(receipt.inspected_at)}</span>
                                            </div>
                                        </div>
                                        <Button asChild size="sm" variant="outline" className="h-8 text-xs shrink-0">
                                            <Link href={`/penerimaan-barang/${receipt.uuid}`}>
                                                Detail
                                            </Link>
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
