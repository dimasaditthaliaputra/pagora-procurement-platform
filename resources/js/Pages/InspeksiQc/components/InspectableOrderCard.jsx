import { Link } from '@inertiajs/react';
import {
    Calendar,
    ClipboardCheck,
    Package,
    RefreshCw,
    Truck,
} from 'lucide-react';
import { formatDate } from '@/lib/format';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';

export const INSPECTABLE_TABULAR_GRID =
    'lg:grid lg:grid-cols-[minmax(180px,1.25fr)_minmax(200px,1.45fr)_minmax(140px,1fr)_200px] lg:items-center lg:gap-4';

export function InspectableOrderTableHeader() {
    return (
        <div
            aria-hidden="true"
            className={`hidden ${INSPECTABLE_TABULAR_GRID} rounded-lg border border-border/50 bg-muted/40 px-4 py-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground`}
        >
            <div>Nomor PO &amp; Vendor</div>
            <div>Jalur &amp; Armada Pengiriman</div>
            <div>Muatan &amp; Estimasi Tiba</div>
            <div className="text-right">Aksi Inspeksi QC</div>
        </div>
    );
}

export function InspectableOrderCard({ order }) {
    return (
        <div
            className={`flex flex-col gap-3 rounded-xl border border-border/70 bg-card px-4 py-3.5 shadow-2xs transition-colors hover:border-sky-500/45 ${INSPECTABLE_TABULAR_GRID}`}
        >
            {/* Kolom 1: Nomor PO, Status, & Vendor */}
            <div className="min-w-0 space-y-1">
                <div className="flex items-center gap-2 flex-wrap">
                    <span className="font-mono text-sm font-bold text-foreground">
                        {order.po_number}
                    </span>
                    <Badge variant="sky" className="text-[10px] px-1.5 py-0">
                        In Transit
                    </Badge>
                    {order.shipment?.is_reshipment && (
                        <Badge variant="warning" className="gap-1 text-[10px] px-1.5 py-0">
                            <RefreshCw className="size-2.5" />
                            Pengiriman Ulang
                        </Badge>
                    )}
                </div>
                <p
                    className="text-xs font-medium text-muted-foreground truncate"
                    title={order.vendor_name}
                >
                    {order.vendor_name}
                </p>
                {order.po_date && (
                    <p className="text-[11px] text-muted-foreground/80 tabular-nums">
                        Tgl. PO: {formatDate(order.po_date)}
                    </p>
                )}
            </div>

            {/* Kolom 2: Jalur, Armada, & Nomor Polisi */}
            <div className="min-w-0">
                {order.shipment ? (
                    <div className="space-y-1 rounded-lg border bg-muted/25 px-3 py-2 lg:rounded-none lg:border-0 lg:bg-transparent lg:p-0">
                        <div className="flex items-center gap-1.5 flex-wrap text-xs">
                            <Truck className="size-3.5 text-sky-600 dark:text-sky-400 shrink-0" />
                            <span
                                className="font-semibold text-foreground truncate"
                                title={order.shipment.carrier_name}
                            >
                                {order.shipment.carrier_name}
                            </span>
                            {order.shipment.vehicle_plate_number && (
                                <span className="rounded bg-muted px-1.5 py-0.5 font-mono text-[11px] font-semibold text-foreground">
                                    {order.shipment.vehicle_plate_number}
                                </span>
                            )}
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Jalur Pengiriman:{' '}
                            <span className="font-medium text-foreground">
                                {order.shipment.route}
                            </span>
                        </p>
                    </div>
                ) : (
                    <span className="text-xs text-muted-foreground">
                        Data pengiriman belum tersedia
                    </span>
                )}
            </div>

            {/* Kolom 3: Jumlah Item & Estimasi Tanggal Tiba (ETA) */}
            <div className="flex flex-row items-center justify-between gap-2 lg:flex-col lg:items-start lg:justify-center lg:gap-1 text-xs">
                <span className="inline-flex items-center gap-1.5 font-semibold text-foreground tabular-nums">
                    <Package className="size-3.5 text-muted-foreground shrink-0" />
                    {order.items_count} Item Pesanan
                </span>
                {order.shipment?.estimated_arrival_date && (
                    <span className="inline-flex items-center gap-1 text-muted-foreground tabular-nums">
                        <Calendar className="size-3 shrink-0" />
                        ETA {formatDate(order.shipment.estimated_arrival_date)}
                    </span>
                )}
            </div>

            {/* Kolom 4: Tombol Aksi Inspeksi */}
            <div className="flex items-center justify-end border-t border-border/60 pt-3 lg:border-t-0 lg:pt-0">
                <Button
                    asChild
                    size="sm"
                    className="h-9 w-full sm:w-auto px-3.5 text-xs font-semibold cursor-pointer shadow-2xs"
                >
                    <Link href={`/inspeksi-qc/create?po=${order.uuid}`}>
                        <ClipboardCheck className="size-3.5" />
                        Buat Form Pengecekan
                    </Link>
                </Button>
            </div>
        </div>
    );
}

export function InspectableOrderList({ orders = [] }) {
    return (
        <div className="space-y-2.5">
            <InspectableOrderTableHeader />
            <div className="space-y-2.5">
                {orders.map((order) => (
                    <InspectableOrderCard key={order.uuid} order={order} />
                ))}
            </div>
        </div>
    );
}

export default InspectableOrderCard;
