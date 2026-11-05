import { Link } from '@inertiajs/react';
import {
    AlertTriangle,
    Camera,
    ClipboardCheck,
    Eye,
    FileText,
    MoreHorizontal,
    PackageX,
    ShieldAlert,
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
import { formatDate, formatNumber } from '@/lib/format';

export function DamageTriggerBadge({ triggerType, label }) {
    if (triggerType === 'goods_mismatch') {
        return (
            <Badge variant="destructive" className="gap-1 whitespace-nowrap">
                <PackageX className="size-3" />
                {label || 'Barang Rusak / Tidak Sesuai'}
            </Badge>
        );
    }

    return (
        <Badge variant="warning" className="gap-1 whitespace-nowrap">
            <AlertTriangle className="size-3" />
            {label || 'Hasil QC Ditolak'}
        </Badge>
    );
}

export function DisputeStatusBadge({ status, label }) {
    if (status === 'selesai' || status === 'resolved') {
        return <Badge variant="green">{label || 'Selesai'}</Badge>;
    }

    if (status === 'dalam_mediasi' || status === 'in_mediation') {
        return <Badge variant="sky">{label || 'Dalam Mediasi'}</Badge>;
    }

    return <Badge variant="warning">{label || 'Terbuka'}</Badge>;
}

export function getDamageColumns({ onSortChange, isVendor = false }) {
    return [
        {
            id: 'commodity',
            header: 'Item & Nomor PO',
            cell: ({ row }) => {
                const report = row.original;
                const po = report.purchase_order;

                return (
                    <div className="flex flex-col gap-0.5">
                        <Link
                            href={`/inspeksi-qc/laporan-kerusakan/${report.uuid}`}
                            className="font-semibold text-sm text-foreground hover:text-primary hover:underline"
                        >
                            {report.commodity_name}
                        </Link>
                        <span className="text-xs text-muted-foreground font-mono">
                            {po?.po_number ?? '-'}
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
                const name = isVendor
                    ? po?.tenant?.company_name ?? 'Perusahaan Buyer'
                    : po?.vendor?.company_name ?? 'Vendor';
                return <span className="text-sm font-medium text-foreground">{name}</span>;
            },
        },
        {
            id: 'trigger_type',
            header: 'Kategori Kerusakan',
            cell: ({ row }) => {
                const dispute = row.original.dispute;
                return (
                    <DamageTriggerBadge
                        triggerType={dispute?.trigger_type}
                        label={dispute?.trigger_type_label}
                    />
                );
            },
        },
        {
            accessorKey: 'received_quantity',
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title="Kuantitas & Susut"
                    onSort={(desc) => onSortChange('received_quantity', desc)}
                />
            ),
            cell: ({ row }) => {
                const r = row.original;
                return (
                    <div className="flex flex-col gap-0.5 text-xs tabular-nums">
                        <span className="font-semibold text-foreground">
                            Diterima: {formatNumber(r.received_quantity)} /{' '}
                            {formatNumber(r.ordered_quantity)} {r.unit}
                        </span>
                        <span className="text-muted-foreground">
                            Selisih: {formatNumber(r.shortage_quantity)} {r.unit} (
                            {formatNumber(r.shrinkage_percent)}%)
                        </span>
                    </div>
                );
            },
        },
        {
            id: 'evidence_and_dispute',
            header: 'Bukti & Status Sengketa',
            cell: ({ row }) => {
                const r = row.original;
                const dispute = r.dispute;

                return (
                    <div className="flex flex-wrap items-center gap-1.5">
                        <Badge variant="outline" className="gap-1 text-xs">
                            <Camera className="size-3 text-muted-foreground" />
                            {r.photos_count ?? 0} Foto
                        </Badge>
                        {dispute && (
                            <DisputeStatusBadge
                                status={dispute.status}
                                label={dispute.status_label}
                            />
                        )}
                    </div>
                );
            },
        },
        {
            accessorKey: 'created_at',
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title="Waktu Laporan"
                    onSort={(desc) => onSortChange('created_at', desc)}
                />
            ),
            cell: ({ row }) => {
                const r = row.original;
                return (
                    <div className="flex flex-col gap-0.5 text-xs">
                        <span className="tabular-nums text-foreground">
                            {formatDate(r.inspected_at || r.created_at)}
                        </span>
                        <span className="text-muted-foreground">
                            Oleh {r.inspector?.name ?? 'QC Gudang'}
                        </span>
                    </div>
                );
            },
        },
        {
            id: 'actions',
            header: () => <span className="sr-only">Aksi</span>,
            cell: ({ row }) => {
                const r = row.original;
                const po = r.purchase_order;

                return (
                    <div className="flex justify-end">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-8 cursor-pointer"
                                    aria-label={`Aksi laporan kerusakan ${r.commodity_name}`}
                                >
                                    <MoreHorizontal className="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-56">
                                <DropdownMenuLabel>Aksi Laporan Kerusakan</DropdownMenuLabel>
                                {r.can?.view && (
                                    <DropdownMenuItem asChild className="cursor-pointer">
                                        <Link href={`/inspeksi-qc/laporan-kerusakan/${r.uuid}`}>
                                            <Eye className="mr-2 size-4" />
                                            Lihat Detail Laporan Kerusakan
                                        </Link>
                                    </DropdownMenuItem>
                                )}
                                {r.can?.report_additional_evidence && (
                                    <DropdownMenuItem asChild className="cursor-pointer">
                                        <Link href={`/inspeksi-qc/laporan-kerusakan/${r.uuid}#tambah-bukti`}>
                                            <ShieldAlert className="mr-2 size-4 text-destructive" />
                                            Tambah Bukti Kerusakan
                                        </Link>
                                    </DropdownMenuItem>
                                )}
                                {r.can?.view_receipt && r.goods_receipt_uuid && (
                                    <DropdownMenuItem asChild className="cursor-pointer">
                                        <Link href={`/inspeksi-qc/${r.goods_receipt_uuid}`}>
                                            <ClipboardCheck className="mr-2 size-4" />
                                            Buka Dokumen Inspeksi QC
                                        </Link>
                                    </DropdownMenuItem>
                                )}
                                {r.can?.view_purchase_order && po?.uuid && (
                                    <DropdownMenuItem asChild className="cursor-pointer">
                                        <Link href={`/purchase-order/${po.uuid}`}>
                                            <FileText className="mr-2 size-4" />
                                            Lihat Purchase Order
                                        </Link>
                                    </DropdownMenuItem>
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                );
            },
        },
    ];
}

