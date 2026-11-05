import { Link } from '@inertiajs/react';
import { Calendar, ClipboardCheck, Eye, MoreHorizontal, RefreshCw, Truck } from 'lucide-react';
import { DataTableColumnHeader } from '@/Components/data-table/data-table-column-header';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { formatDate } from '@/lib/format';

export const SHIPMENT_STATUS_VARIANT = {
    in_transit: 'sky',
    arrived: 'green',
};

export function ShipmentStatusBadge({ status, label }) {
    const variant = SHIPMENT_STATUS_VARIANT[status] ?? 'secondary';
    return (
        <Badge variant={variant} className="gap-1 whitespace-nowrap">
            <Truck className="size-3" />
            {label || status || '-'}
        </Badge>
    );
}

export function getColumns({ onSortChange, isVendor = false }) {
    return [
        {
            id: 'po_number',
            header: 'Nomor PO',
            cell: ({ row }) => {
                const shipment = row.original;
                const po = shipment.purchase_order;
                return (
                    <div className="flex flex-col gap-0.5">
                        <Link
                            href={`/pengiriman/${shipment.uuid}`}
                            className="font-mono text-xs font-semibold text-primary hover:underline"
                        >
                            {po?.po_number ?? '-'}
                        </Link>
                        {shipment.is_reshipment && (
                            <span className="inline-flex items-center gap-1 text-[10px] font-medium text-amber-600 dark:text-amber-400">
                                <RefreshCw className="size-2.5" />
                                Pengiriman Ulang
                            </span>
                        )}
                    </div>
                );
            },
        },
        {
            id: 'partner',
            header: isVendor ? 'Tujuan Buyer' : 'Vendor Pengirim',
            cell: ({ row }) => {
                const po = row.original.purchase_order;
                const companyName = isVendor
                    ? po?.tenant?.company_name ?? 'Perusahaan Buyer'
                    : po?.vendor?.company_name ?? 'Vendor';
                return <span className="font-medium text-foreground text-sm">{companyName}</span>;
            },
        },
        {
            accessorKey: 'route',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Jalur" onSortChange={onSortChange} />
            ),
            cell: ({ row }) => (
                <Badge variant="outline" className="font-medium">
                    {row.original.route}
                </Badge>
            ),
        },
        {
            accessorKey: 'carrier_name',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Armada & Plat Nomor" onSortChange={onSortChange} />
            ),
            cell: ({ row }) => (
                <div className="flex flex-col">
                    <span className="text-sm font-medium text-foreground">{row.original.carrier_name}</span>
                    <span className="font-mono text-xs text-muted-foreground">
                        {row.original.vehicle_plate_number}
                    </span>
                </div>
            ),
        },
        {
            accessorKey: 'estimated_arrival_date',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Perkiraan Tiba (ETA)" onSortChange={onSortChange} />
            ),
            cell: ({ row }) => (
                <span className="inline-flex items-center gap-1.5 text-sm tabular-nums">
                    <Calendar className="size-3.5 text-muted-foreground" />
                    {formatDate(row.original.estimated_arrival_date)}
                </span>
            ),
        },
        {
            accessorKey: 'status',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Status Logistik" onSortChange={onSortChange} />
            ),
            cell: ({ row }) => (
                <ShipmentStatusBadge
                    status={row.original.status}
                    label={row.original.status_label}
                />
            ),
        },
        {
            accessorKey: 'created_at',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Dilaporkan" onSortChange={onSortChange} />
            ),
            cell: ({ row }) => (
                <span className="text-xs text-muted-foreground tabular-nums">
                    {formatDate(row.original.created_at)}
                </span>
            ),
        },
        {
            id: 'actions',
            header: () => <span className="sr-only">Aksi</span>,
            cell: ({ row }) => {
                const shipment = row.original;
                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" className="size-8 p-0 cursor-pointer">
                                <span className="sr-only">Buka menu</span>
                                <MoreHorizontal className="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuLabel>Aksi Pengiriman</DropdownMenuLabel>
                            <DropdownMenuItem asChild>
                                <Link href={`/pengiriman/${shipment.uuid}`}>
                                    <Eye className="mr-2 size-4" />
                                    Lihat Detail Pengiriman
                                </Link>
                            </DropdownMenuItem>
                            {shipment.can?.inspect && shipment.purchase_order?.uuid && (
                                <DropdownMenuItem asChild>
                                    <Link href={`/inspeksi-qc/create?po=${shipment.purchase_order.uuid}`}>
                                        <ClipboardCheck className="mr-2 size-4" />
                                        Lakukan Inspeksi Barang
                                    </Link>
                                </DropdownMenuItem>
                            )}
                            {shipment.can?.view_purchase_order && shipment.purchase_order?.uuid && (
                                <DropdownMenuItem asChild>
                                    <Link href={`/purchase-order/${shipment.purchase_order.uuid}`}>
                                        <Eye className="mr-2 size-4" />
                                        Lihat Dokumen PO
                                    </Link>
                                </DropdownMenuItem>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                );
            },
        },
    ];
}

