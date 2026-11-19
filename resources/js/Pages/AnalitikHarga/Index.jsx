import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { AlertTriangle, ChartLine, Info, RefreshCw, TableProperties } from 'lucide-react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Combobox } from '@/Components/combobox';
import { LoadingButton } from '@/Components/loading-button';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/Components/ui/empty';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { cn } from '@/lib/utils';
import { formatDate, formatRupiah, formatTime } from '@/lib/format';
import { PriceTrendChart } from './components/PriceTrendChart';
import { RefreshProgress, useRefreshProgress } from './components/RefreshProgress';

export default function AnalitikHargaIndex({
    commodity_options: commodityOptions = [],
    commodity,
    history = [],
    predictions = [],
    ceiling,
    latest_price: latestPrice,
    notice,
    ceiling_margin_percent: marginPercent,
    horizon_days: horizonDays,
    can = {},
}) {
    const [changing, setChanging] = useState(false);
    const [refreshing, setRefreshing] = useState(false);
    const [showTable, setShowTable] = useState(false);
    const progress = useRefreshProgress();

    const hasData = history.length > 0 || predictions.length > 0;
    const lastPrediction = predictions[predictions.length - 1];
    const historyLabel = latestPrice?.source === 'bank_indonesia' ? 'Harga BI (Pedagang Besar)' : 'Harga referensi (data demo)';
    const unit = commodity?.unit ?? 'kg';

    const selectCommodity = (uuid) => {
        if (!uuid || uuid === commodity?.uuid) return;

        router.get('/analitik-harga', { komoditas: uuid }, {
            preserveScroll: true,
            replace: true,
            onStart: () => setChanging(true),
            onFinish: () => setChanging(false),
        });
    };

    const refresh = () => {
        router.post(`/analitik-harga/${commodity.uuid}/perbarui`, {}, {
            preserveScroll: true,
            onStart: () => {
                setRefreshing(true);
                progress.start();
            },
            onFinish: () => {
                setRefreshing(false);
                progress.finish();
            },
        });
    };

    const breadcrumbs = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Dashboard Analitik' },
    ];

    return (
        <DashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard Analitik Harga - PAGORA" />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <ChartLine className="size-6 text-emerald-600 dark:text-emerald-400" />
                            <h1 className="text-2xl font-bold tracking-tight text-foreground">Tren Harga & Harga Pagu</h1>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Prediksi harga dari data PIHPS Bank Indonesia sebagai acuan batas harga wajar saat menyusun dan menegosiasikan PO.
                        </p>
                    </div>

                    {commodityOptions.length > 0 && (
                        <div className="flex w-full flex-col gap-2 sm:flex-row lg:w-auto">
                            <Combobox
                                options={commodityOptions}
                                value={commodity?.uuid ?? ''}
                                onChange={selectCommodity}
                                placeholder="Pilih komoditas"
                                searchPlaceholder="Cari komoditas..."
                                disabled={changing || refreshing}
                                className="sm:w-60"
                            />
                            {can.refresh && commodity?.is_mapped && (
                                <LoadingButton
                                    type="button"
                                    variant="outline"
                                    className="h-9"
                                    loading={refreshing}
                                    loadingText="Memperbarui..."
                                    disabled={changing}
                                    onClick={refresh}
                                >
                                    <RefreshCw className="mr-2 size-4" />
                                    Perbarui data
                                </LoadingButton>
                            )}
                        </div>
                    )}
                </div>

                <RefreshProgress value={progress.value} commodityName={commodity?.name} />

                {commodityOptions.length === 0 ? (
                    <Card className="border-border shadow-xs">
                        <CardContent className="py-10">
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <ChartLine />
                                    </EmptyMedia>
                                    <EmptyTitle>Belum ada komoditas aktif</EmptyTitle>
                                    <EmptyDescription>Aktifkan komoditas untuk perusahaan Anda agar tren harganya dapat dipantau.</EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        </CardContent>
                    </Card>
                ) : (
                    <div className={cn('space-y-6 transition-opacity', changing && 'pointer-events-none opacity-60')}>
                        {notice && (
                            <Alert className={cn(notice.type === 'warning' && 'border-amber-200 bg-amber-50/60 text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-200')}>
                                {notice.type === 'warning' ? <AlertTriangle /> : <Info />}
                                <AlertDescription className="text-current">{notice.message}</AlertDescription>
                            </Alert>
                        )}

                        {!commodity?.is_mapped && (
                            <Alert>
                                <Info />
                                <AlertDescription>
                                    Komoditas ini belum dipetakan ke kode komoditas Bank Indonesia, sehingga data harga tidak diperbarui otomatis.
                                </AlertDescription>
                            </Alert>
                        )}

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <StatCard
                                highlight
                                label="Harga Pagu"
                                value={ceiling ? `${formatRupiah(ceiling.recommended_ceiling_price)}/${unit}` : '-'}
                                hint={ceiling ? `Prediksi tertinggi ${horizonDays} hari + ${marginPercent}%` : 'Belum dapat dihitung'}
                            />
                            <StatCard
                                label="Harga terakhir"
                                value={latestPrice ? `${formatRupiah(latestPrice.price)}/${unit}` : '-'}
                                hint={latestPrice ? `Per ${formatDate(latestPrice.date)}` : 'Belum ada data'}
                            />
                            <StatCard
                                label={`Prediksi ${horizonDays} hari`}
                                value={lastPrediction ? `${formatRupiah(lastPrediction.predicted_price)}/${unit}` : '-'}
                                hint={lastPrediction ? `Untuk ${formatDate(lastPrediction.prediction_date)}` : 'Belum ada prediksi'}
                            />
                            <StatCard
                                label="Diperbarui"
                                value={ceiling ? formatDate(ceiling.calculated_at) : '-'}
                                hint={ceiling ? `Pukul ${formatTime(ceiling.calculated_at)} WIB` : 'Belum pernah'}
                            />
                        </div>

                        <Card className="border-border shadow-xs">
                            <CardHeader className="flex flex-row items-start justify-between gap-4 space-y-0">
                                <div>
                                    <CardTitle className="text-base">{commodity?.name}</CardTitle>
                                    <CardDescription>Riwayat 90 hari dan prediksi {horizonDays} hari ke depan, per {unit}.</CardDescription>
                                </div>
                                {hasData && predictions.length > 0 && (
                                    <Button type="button" variant="ghost" size="sm" onClick={() => setShowTable((value) => !value)}>
                                        <TableProperties className="mr-2 size-4" />
                                        {showTable ? 'Tampilkan grafik' : 'Tampilkan tabel'}
                                    </Button>
                                )}
                            </CardHeader>
                            <CardContent>
                                {!hasData ? (
                                    <Empty className="py-10">
                                        <EmptyHeader>
                                            <EmptyMedia variant="icon">
                                                <ChartLine />
                                            </EmptyMedia>
                                            <EmptyTitle>Belum ada data harga</EmptyTitle>
                                            <EmptyDescription>Data harga untuk komoditas ini belum tersedia.</EmptyDescription>
                                        </EmptyHeader>
                                    </Empty>
                                ) : showTable ? (
                                    <PredictionTable predictions={predictions} ceiling={ceiling} />
                                ) : (
                                    <PriceTrendChart
                                        history={history}
                                        predictions={predictions}
                                        ceiling={ceiling?.recommended_ceiling_price ?? null}
                                        unit={unit}
                                        historyLabel={historyLabel}
                                    />
                                )}
                            </CardContent>
                        </Card>

                        <p className="text-xs text-muted-foreground">
                            Harga Pagu hanya pendukung keputusan; harga akhir tetap ditentukan Purchasing saat negosiasi. Informasi ini tidak
                            ditampilkan kepada Vendor.
                        </p>
                    </div>
                )}
            </div>
        </DashboardLayout>
    );
}

function StatCard({ label, value, hint, highlight = false }) {
    return (
        <Card className={cn('shadow-xs', highlight ? 'border-emerald-200 bg-emerald-50/50 dark:border-emerald-900/50 dark:bg-emerald-950/20' : 'border-border')}>
            <CardContent className="p-4">
                <p className={cn('text-xs font-medium', highlight ? 'text-emerald-800 dark:text-emerald-300' : 'text-muted-foreground')}>{label}</p>
                <p className={cn('mt-1 text-xl font-bold tabular-nums', highlight ? 'text-emerald-900 dark:text-emerald-200' : 'text-foreground')}>{value}</p>
                <p className="mt-1 text-xs text-muted-foreground">{hint}</p>
            </CardContent>
        </Card>
    );
}

function PredictionTable({ predictions, ceiling }) {
    return (
        <div className="max-h-[300px] overflow-y-auto">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Tanggal</TableHead>
                        <TableHead className="text-right">Prediksi</TableHead>
                        <TableHead className="text-right">Prediksi + margin</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {predictions.map((row) => (
                        <TableRow key={row.uuid} className={cn(row.uuid === ceiling?.uuid && 'bg-emerald-50/60 font-medium dark:bg-emerald-950/20')}>
                            <TableCell>{formatDate(row.prediction_date)}</TableCell>
                            <TableCell className="text-right tabular-nums">{formatRupiah(row.predicted_price)}</TableCell>
                            <TableCell className="text-right tabular-nums">
                                {formatRupiah(row.recommended_ceiling_price)}
                                {row.uuid === ceiling?.uuid && <span className="ml-2 text-xs text-emerald-700 dark:text-emerald-400">Harga Pagu</span>}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}
