import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Building2,
    Calendar,
    Check,
    ClipboardCheck,
    FileText,
    Lock,
    MapPin,
    RefreshCw,
    Truck,
    UserCheck,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { formatDate, formatNumber, formatRupiah } from '@/lib/format';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { ShipmentStatusBadge } from '@/Pages/Pengiriman/columns';

const LOGISTICS_STEPS = [
    { key: 'disepakati', label: 'PO Disepakati' },
    { key: 'in_transit', label: 'Dalam Pengiriman (In Transit)' },
    { key: 'arrived', label: 'Tiba di Gudang Tujuan' },
];

function LogisticsStepper({ status }) {
    const activeIndex = status === 'arrived' ? 2 : 1;

    return (
        <ol className="flex items-center gap-2 overflow-x-auto pb-1">
            {LOGISTICS_STEPS.map((step, index) => {
                const done = index < activeIndex || (status === 'arrived' && index === 2);
                const active = status !== 'arrived' && index === activeIndex;

                return (
                    <li key={step.key} className="flex items-center gap-2 shrink-0">
                        <span
                            className={cn(
                                'flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium',
                                done && 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
                                active && 'border-primary bg-primary text-primary-foreground',
                                !done && !active && 'text-muted-foreground'
                            )}
                        >
                            {done && <Check className="size-3" />}
                            {step.label}
                        </span>
                        {index < LOGISTICS_STEPS.length - 1 && <span className="h-px w-6 bg-border" />}
                    </li>
                );
            })}
        </ol>
    );
}

function DetailRow({ label, children }) {
    return (
        <div className="grid gap-0.5">
            <span className="text-xs text-muted-foreground">{label}</span>
            <div className="text-sm font-medium text-foreground">{children}</div>
        </div>
    );
}

export default function PengirimanShow({ shipment: rawShipment }) {
    const shipment = rawShipment?.data ?? rawShipment;
    const po = shipment?.purchase_order;
    const items = po?.items ?? [];

    const breadcrumbs = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Pengiriman', href: '/pengiriman' },
        { label: po?.po_number ?? 'Detail Pengiriman', href: '#' },
    ];

    return (
        <DashboardLayout breadcrumbs={breadcrumbs}>
            <Head title={`Pengiriman ${po?.po_number ?? ''} - PAGORA`} />

            <div className="flex flex-col gap-4 w-full">
                <div className="flex flex-col md:flex-row md:items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-bold tracking-tight flex flex-wrap items-center gap-2">
                            <Truck className="size-5 text-primary" />
                            <span>Pengiriman Logistik</span>
                            {po?.po_number && (
                                <span className="font-mono text-muted-foreground">· {po.po_number}</span>
                            )}
                            <ShipmentStatusBadge
                                status={shipment?.status}
                                label={shipment?.status_label}
                            />
                            {shipment?.is_reshipment && (
                                <Badge variant="warning" className="gap-1">
                                    <RefreshCw className="size-3" />
                                    Pengiriman Ulang
                                </Badge>
                            )}
                        </h1>
                        <p className="text-xs text-muted-foreground mt-1">
                            Dilaporkan oleh {shipment?.reporter?.name ?? 'Vendor'} pada{' '}
                            {formatDate(shipment?.created_at)}
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" size="sm" asChild className="h-8">
                            <Link href="/pengiriman">
                                <ArrowLeft className="mr-1.5 size-3.5" />
                                Kembali
                            </Link>
                        </Button>
                        {shipment?.can?.inspect && po?.uuid && (
                            <Button size="sm" asChild className="h-8 gap-1.5 shadow-sm">
                                <Link href={`/inspeksi-qc/create?po=${po.uuid}`}>
                                    <ClipboardCheck className="size-3.5" />
                                    Lakukan Inspeksi Barang
                                </Link>
                            </Button>
                        )}
                        {shipment?.can?.view_purchase_order && po?.uuid && (
                            <Button variant="outline" size="sm" asChild className="h-8">
                                <Link href={`/purchase-order/${po.uuid}`}>
                                    <FileText className="mr-1.5 size-3.5" />
                                    Buka Dokumen PO
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <Card className="shadow-sm">
                    <CardContent className="p-4">
                        <LogisticsStepper status={shipment?.status} />
                    </CardContent>
                </Card>

                {po?.is_locked && (
                    <Alert>
                        <Lock className="size-4" />
                        <AlertTitle>Dokumen &amp; Kuantitas Purchase Order Terkunci Mutlak</AlertTitle>
                        <AlertDescription>
                            Status logistik fisik telah dilaporkan sehingga rincian harga maupun kuantitas pada{' '}
                            <strong className="font-mono">{po.po_number}</strong> terkunci permanen sebagai acuan inspeksi QC gudang dan pencocokan tagihan.
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <Card className="shadow-sm lg:col-span-2">
                        <CardHeader className="py-3.5 px-5 border-b">
                            <CardTitle className="text-base font-semibold">
                                Informasi Operasional Armada
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <DetailRow label="Jalur Pengiriman">
                                <Badge variant="outline">{shipment?.route ?? '-'}</Badge>
                            </DetailRow>
                            <DetailRow label="Perkiraan Tanggal Tiba (ETA)">
                                <span className="inline-flex items-center gap-1.5 tabular-nums">
                                    <Calendar className="size-4 text-muted-foreground" />
                                    {formatDate(shipment?.estimated_arrival_date)}
                                </span>
                            </DetailRow>
                            <DetailRow label="Nama Armada / Ekspedisi">
                                {shipment?.carrier_name ?? '-'}
                            </DetailRow>
                            <DetailRow label="Nomor Plat Armada">
                                <span className="inline-flex items-center rounded-md border bg-muted/40 px-2.5 py-0.5 font-mono text-sm font-bold tracking-wider">
                                    {shipment?.vehicle_plate_number ?? '-'}
                                </span>
                            </DetailRow>
                            <DetailRow label="Dilaporkan Oleh">
                                <span className="inline-flex items-center gap-1.5">
                                    <UserCheck className="size-4 text-muted-foreground" />
                                    {shipment?.reporter?.name ?? 'Vendor'}
                                </span>
                            </DetailRow>
                            <DetailRow label="Status Purchase Order">
                                <span className="font-medium">{po?.status_label ?? '-'}</span>
                            </DetailRow>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm lg:col-span-1">
                        <CardHeader className="py-3.5 px-5 border-b">
                            <CardTitle className="text-base font-semibold">
                                Pihak Pengirim &amp; Tujuan
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-5 grid gap-4">
                            <DetailRow label="Vendor Pengirim">
                                <span className="inline-flex items-center gap-1.5">
                                    <Building2 className="size-4 text-muted-foreground shrink-0" />
                                    {po?.vendor?.company_name ?? 'Vendor'}
                                </span>
                            </DetailRow>
                            <DetailRow label="Perusahaan Buyer (Tujuan)">
                                <span className="inline-flex items-center gap-1.5">
                                    <Building2 className="size-4 text-primary shrink-0" />
                                    {po?.tenant?.company_name ?? 'Perusahaan Buyer'}
                                </span>
                            </DetailRow>
                            <DetailRow label="Alamat Gudang Tujuan">
                                {po?.tenant?.address ? (
                                    <span className="inline-flex items-start gap-1.5 text-xs font-normal text-muted-foreground">
                                        <MapPin className="size-3.5 shrink-0 mt-0.5" />
                                        {po.tenant.address}
                                    </span>
                                ) : (
                                    <span className="text-xs italic font-normal text-muted-foreground">
                                        Alamat gudang belum dicantumkan
                                    </span>
                                )}
                            </DetailRow>
                        </CardContent>
                    </Card>
                </div>

                <Card className="shadow-sm">
                    <CardHeader className="py-3.5 px-5 border-b">
                        <CardTitle className="text-base font-semibold">
                            Rincian Barang dalam Pengiriman ({po?.po_number ?? '-'})
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="pl-5">Komoditas</TableHead>
                                    <TableHead className="text-right">Kuantitas PO (Terkunci)</TableHead>
                                    <TableHead className="text-right">Harga Satuan</TableHead>
                                    <TableHead className="text-right pr-5">Subtotal</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {items.map((item) => (
                                    <TableRow key={item.uuid}>
                                        <TableCell className="pl-5 font-medium">
                                            {item.commodity_name}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatNumber(item.quantity)} {item.unit}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatRupiah(item.unit_price)}
                                        </TableCell>
                                        <TableCell className="text-right pr-5 font-semibold tabular-nums">
                                            {formatRupiah(item.subtotal)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                            <TableFooter>
                                <TableRow className="font-semibold text-base">
                                    <TableCell colSpan={2} className="pl-5">
                                        Total Nilai Purchase Order
                                    </TableCell>
                                    <TableCell colSpan={2} className="text-right pr-5 tabular-nums">
                                        {formatRupiah(po?.total_amount ?? 0)}
                                    </TableCell>
                                </TableRow>
                            </TableFooter>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </DashboardLayout>
    );
}

