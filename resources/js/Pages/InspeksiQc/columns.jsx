import { Link } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Eye,
    FileText,
    MoreHorizontal,
    Truck,
    XCircle,
} from 'lucide-react';
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

export function InspectionOverallBadge({ result, label }) {
    if (result === 'rejected') {
        return (
            <Badge variant="destructive" className="gap-1 whitespace-nowrap">
                <AlertTriangle className="size-3" />
                {label || 'Terdapat Penolakan'}
            </Badge>
        );
    }

    return (
        <Badge variant="green" className="gap-1 whitespace-nowrap">
            <CheckCircle2 className="size-3" />
            {label || 'Disetujui Lengkap'}
        </Badge>
    );
}

export function InspectionItemStatusBadge({ status, label }) {
    if (status === 'rejected') {
        return (
            <Badge variant="destructive" className="gap-1 whitespace-nowrap">
                <XCircle className="size-3" />
                {label || 'Ditolak'}
            </Badge>
        );
    }

    return (
        <Badge variant="green" className="gap-1 whitespace-nowrap">
            <CheckCircle2 className="size-3" />
            {label || 'Disetujui'}
        </Badge>
    );
}

export function getColumns({ onSortChange, isVendor = false }) {
    return [
        {
            id: 'po_number',
            header: 'Nomor PO',
            cell: ({ row }) => {
                const receipt = row.original;
                const po = receipt.purchase_order;
                return (
                    <div className="flex flex-col gap-0.5">
                        <Link
                            href={`/inspeksi-qc/${receipt.uuid}`}
                            className="font-mono text-xs font-semibold text-primary hover:underline"
                        >
                            {po?.po_number ?? '-'}
                        </Link>
                        <span className="text-[11px] text-muted-foreground">
                            {po?.status_label ?? '-'}
                        </span>
                    </div>
                );
            },
        },
        {
            id: 'partner',
            header: isVendor ? 'Perusahaan Buyer' : 'Vendor Pengirim',
            cell: ({ row }) => {
                const po = row.original.purchase_order;
                const companyName = isVendor
                    ? po?.tenant?.company_name ?? 'Perusahaan Buyer'
                    : po?.vendor?.company_name ?? 'Vendor';
                return <span className="font-medium text-foreground text-sm">{companyName}</span>;
            },
        },
        {
            accessorKey: 'matched_plate_number',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Armada & Plat Dicocokkan" onSortChange={onSortChange} />
            ),
            cell: ({ row }) => {
                const receipt = row.original;
                return (
                    <div className="flex flex-col">
                        <span className="text-sm font-medium text-foreground">
                            {receipt.shipment?.carrier_name ?? 'Armada Logistik'}
                        </span>
                        <span className="font-mono text-xs text-muted-foreground">
                            {receipt.matched_plate_number || receipt.shipment?.vehicle_plate_number || '-'}
                        </span>
                    </div>
                );
            },
        },
        {
            id: 'summary_items',
            header: 'Rincian Item',
            cell: ({ row }) => {
                const receipt = row.original;
                return (
                    <div className="flex flex-wrap items-center gap-1.5 text-xs">
                        {receipt.accepted_count > 0 && (
                            <span className="inline-flex items-center rounded-md bg-emerald-500/10 px-2 py-0.5 font-medium text-emerald-700 dark:text-emerald-400">
                                {receipt.accepted_count} Disetujui
                            </span>
                        )}
                        {receipt.rejected_count > 0 && (
                            <span className="inline-flex items-center rounded-md bg-destructive/10 px-2 py-0.5 font-medium text-destructive">
                                {receipt.rejected_count} Ditolak
                            </span>
                        )}
                    </div>
                );
            },
        },
        {
            id: 'overall_result',
            header: 'Hasil Inspeksi',
            cell: ({ row }) => (
                <InspectionOverallBadge
                    result={row.original.overall_result}
                    label={row.original.overall_result_label}
                />
            ),
        },
        {
            accessorKey: 'inspected_at',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Diperiksa Pada" onSortChange={onSortChange} />
            ),
            cell: ({ row }) => {
                const receipt = row.original;
                return (
                    <div className="flex flex-col">
                        <span className="text-xs font-medium text-foreground tabular-nums">
                            {formatDate(receipt.inspected_at)}
                        </span>
                        <span className="text-[11px] text-muted-foreground">
                            Oleh {receipt.inspector?.name ?? 'QC Gudang'}
                        </span>
                    </div>
                );
            },
        },
        {
            id: 'actions',
            header: () => <span className="sr-only">Aksi</span>,
            cell: ({ row }) => {
                const receipt = row.original;
                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" className="size-8 p-0 cursor-pointer">
                                <span className="sr-only">Buka menu</span>
                                <MoreHorizontal className="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuLabel>Aksi Inspeksi QC</DropdownMenuLabel>
                            <DropdownMenuItem asChild>
                                <Link href={`/inspeksi-qc/${receipt.uuid}`}>
                                    <Eye className="mr-2 size-4" />
                                    Lihat Hasil Inspeksi
                                </Link>
                            </DropdownMenuItem>
                            {receipt.can?.view_shipment && receipt.shipment?.uuid && (
                                <DropdownMenuItem asChild>
                                    <Link href={`/pengiriman/${receipt.shipment.uuid}`}>
                                        <Truck className="mr-2 size-4" />
                                        Lihat Pengiriman
                                    </Link>
                                </DropdownMenuItem>
                            )}
                            {receipt.can?.view_purchase_order && receipt.purchase_order?.uuid && (
                                <DropdownMenuItem asChild>
                                    <Link href={`/purchase-order/${receipt.purchase_order.uuid}`}>
                                        <FileText className="mr-2 size-4" />
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

