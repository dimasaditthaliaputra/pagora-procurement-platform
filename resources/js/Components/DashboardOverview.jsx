import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { cn } from '@/lib/utils';
import {
    ShoppingCart,
    Package,
    FileText,
    TrendingUp,
    Clock,
    CheckCircle2,
    AlertTriangle,
    ArrowUpRight,
} from 'lucide-react';

const statsCards = [
    {
        title: 'Total Pengadaan',
        value: '24',
        description: '+12% dari bulan lalu',
        icon: ShoppingCart,
        trend: 'up',
    },
    {
        title: 'Produk Terdaftar',
        value: '156',
        description: '8 produk baru minggu ini',
        icon: Package,
        trend: 'up',
    },
    {
        title: 'Purchase Order',
        value: '18',
        description: '5 menunggu persetujuan',
        icon: FileText,
        trend: 'neutral',
    },
    {
        title: 'Nilai Transaksi',
        value: 'Rp 2.4M',
        description: '+8% dari bulan lalu',
        icon: TrendingUp,
        trend: 'up',
    },
];

const recentActivities = [
    { id: 1, action: 'PO-2024-0089 disetujui', time: '5 menit lalu', status: 'success' },
    { id: 2, action: 'Vendor baru mendaftar: PT Maju Jaya', time: '15 menit lalu', status: 'info' },
    { id: 3, action: 'Inspeksi QC batch #A-112 selesai', time: '1 jam lalu', status: 'success' },
    { id: 4, action: 'PO-2024-0087 menunggu persetujuan', time: '2 jam lalu', status: 'warning' },
    { id: 5, action: 'Pengiriman #SHP-445 dalam perjalanan', time: '3 jam lalu', status: 'info' },
];

/**
 * Grid classes per layout. `responsive` follows the viewport (used by the Dashboard page);
 * `wide` and `compact` are fixed so the overview can be rendered inside device previews.
 */
const LAYOUTS = {
    responsive: {
        header: 'sm:flex-row sm:items-center sm:justify-between',
        stats: 'md:grid-cols-2 lg:grid-cols-4',
        content: 'md:grid-cols-2 lg:grid-cols-7',
        chart: 'lg:col-span-4',
        activity: 'lg:col-span-3',
    },
    wide: {
        header: 'flex-row items-center justify-between',
        stats: 'grid-cols-4',
        content: 'grid-cols-7',
        chart: 'col-span-4',
        activity: 'col-span-3',
    },
    compact: {
        header: '',
        stats: 'grid-cols-2',
        content: 'grid-cols-1',
        chart: '',
        activity: '',
    },
};

function StatusIcon({ status }) {
    switch (status) {
        case 'success':
            return <CheckCircle2 className="size-4 text-emerald-500" />;
        case 'warning':
            return <AlertTriangle className="size-4 text-amber-500" />;
        default:
            return <Clock className="size-4 text-blue-500" />;
    }
}

/**
 * Dashboard summary: welcome header, stats cards, procurement trend and recent activity.
 */
export default function DashboardOverview({ userName, layout = 'responsive' }) {
    const classes = LAYOUTS[layout];

    return (
        <div className="flex flex-col gap-4">
            {/* Welcome header */}
            <div className={cn('flex flex-col gap-1', classes.header)}>
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        Selamat Datang, {userName}!
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        Ringkasan aktivitas sistem E-Procurement PAGORA
                    </p>
                </div>
            </div>

            {/* Stats grid */}
            <div className={cn('grid gap-4', classes.stats)}>
                {statsCards.map((stat) => (
                    <Card key={stat.title}>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                {stat.title}
                            </CardTitle>
                            <stat.icon className="size-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stat.value}</div>
                            <p className="text-xs text-muted-foreground flex items-center gap-1">
                                {stat.trend === 'up' && <ArrowUpRight className="size-3 text-emerald-500" />}
                                {stat.description}
                            </p>
                        </CardContent>
                    </Card>
                ))}
            </div>

            {/* Content area */}
            <div className={cn('grid gap-4', classes.content)}>
                {/* Main chart placeholder */}
                <Card className={classes.chart}>
                    <CardHeader>
                        <CardTitle>Tren Pengadaan</CardTitle>
                        <CardDescription>
                            Volume pengadaan 6 bulan terakhir
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex h-[220px] items-center justify-center rounded-lg border border-dashed">
                            <div className="flex flex-col items-center gap-1 text-center">
                                <TrendingUp className="size-8 text-muted-foreground/40" />
                                <p className="text-sm text-muted-foreground">
                                    Grafik tren akan tampil di sini
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Recent activity */}
                <Card className={classes.activity}>
                    <CardHeader>
                        <CardTitle>Aktivitas Terbaru</CardTitle>
                        <CardDescription>
                            Pembaruan terkini di platform
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {recentActivities.map((activity) => (
                                <div key={activity.id} className="flex items-start gap-3">
                                    <StatusIcon status={activity.status} />
                                    <div className="grid gap-0.5 text-sm">
                                        <p className="font-medium leading-none">{activity.action}</p>
                                        <p className="text-xs text-muted-foreground">{activity.time}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
