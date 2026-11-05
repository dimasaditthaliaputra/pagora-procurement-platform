import { Link } from '@inertiajs/react';
import {
    AlertTriangle,
    Download,
    Eye,
    FileWarning,
    MessageSquareWarning,
    MoreHorizontal,
    PackageX,
    Receipt,
} from 'lucide-react';
import { DataTableColumnHeader } from '@/Components/data-table/data-table-column-header';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { formatDate, formatTime } from '@/lib/format';

const TRIGGER_STYLES = {
    goods_mismatch: { variant: 'destructive', icon: PackageX },
    qc_rejected: { variant: 'warning', icon: AlertTriangle },
    quantity_mismatch: { variant: 'sky', icon: Receipt },
    amount_mismatch: { variant: 'sky', icon: Receipt },
    settlement_disagreement: { variant: 'gray', icon: MessageSquareWarning },
};

export function SengketaTriggerBadge({ triggerType, label }) {
    const style = TRIGGER_STYLES[triggerType] ?? { variant: 'gray', icon: FileWarning };
    const Icon = style.icon;

    return (
        <Badge variant={style.variant} className="gap-1 whitespace-nowrap">
            <Icon className="size-3" />
            {label}
        </Badge>
    );
}

export function SengketaStatusBadge({ status, label }) {
    if (status === 'selesai' || status === 'resolved') {
        return <Badge variant="green">{label}</Badge>;
    }

    if (status === 'dalam_mediasi' || status === 'in_mediation') {
        return <Badge variant="sky">{label}</Badge>;
    }

    return <Badge variant="warning">{label}</Badge>;
}

export function getSengketaColumns({ onSortChange }) {
    return [
        {
            id: 'dispute',
            header: 'Sengketa & Purchase Order',
            cell: ({ row }) => {
                const dispute = row.original;

                return (
                    <div className="min-w-[200px] space-y-0.5">
                        <Link
                            href={`/log-sengketa/${dispute.uuid}`}
                            className="font-mono text-sm font-semibold text-foreground hover:underline"
                        >
                            {dispute.po_number}
                        </Link>
                        <p className="text-xs text-muted-foreground">
                            Kode {dispute.short_code} · {dispute.vendor_name}
                        </p>
                        <p className="text-xs text-muted-foreground/80 truncate max-w-[260px]" title={dispute.object_summary}>
                            {dispute.object_summary}
                        </p>
                    </div>
                );
            },
        },
        {
            id: 'trigger_type',
            accessorKey: 'trigger_type',
            enableSorting: true,
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Kategori Pemicu" onSortChange={onSortChange} />
            ),
            cell: ({ row }) => (
                <SengketaTriggerBadge
                    triggerType={row.original.trigger_type}
                    label={row.original.trigger_type_label}
                />
            ),
        },
        {
            id: 'reason',
            header: 'Alasan Kendala',
            cell: ({ row }) => (
                <p
                    className="max-w-[280px] text-sm text-muted-foreground line-clamp-2"
                    title={row.original.reason ?? ''}
                >
                    {row.original.reason || '-'}
                </p>
            ),
        },
        {
            id: 'tanggal_dibuka',
            accessorKey: 'opened_at',
            enableSorting: true,
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Tanggal Dibuka" onSortChange={onSortChange} />
            ),
            cell: ({ row }) => (
                <div className="whitespace-nowrap text-sm tabular-nums">
                    <div className="text-foreground">{formatDate(row.original.opened_at)}</div>
                    <div className="text-xs text-muted-foreground">
                        {formatTime(row.original.opened_at)} WIB · {row.original.opened_by_name}
                    </div>
                </div>
            ),
        },
        {
            id: 'status',
            accessorKey: 'status',
            enableSorting: true,
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Status" onSortChange={onSortChange} />
            ),
            cell: ({ row }) => (
                <div className="space-y-1">
                    <SengketaStatusBadge status={row.original.status} label={row.original.status_label} />
                    <p className="text-[11px] text-muted-foreground tabular-nums">
                        {row.original.logs_count} entri log
                    </p>
                </div>
            ),
        },
        {
            id: 'actions',
            header: () => <span className="sr-only">Aksi</span>,
            cell: ({ row }) => {
                const dispute = row.original;

                return (
                    <div className="flex justify-end">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="ghost" size="icon-sm" className="cursor-pointer">
                                    <MoreHorizontal className="size-4" />
                                    <span className="sr-only">Buka menu aksi</span>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-52">
                                <DropdownMenuLabel>Aksi Sengketa</DropdownMenuLabel>
                                {dispute.can?.view && (
                                    <DropdownMenuItem asChild className="cursor-pointer">
                                        <Link href={`/log-sengketa/${dispute.uuid}`}>
                                            <Eye className="size-4" />
                                            Tinjau Detail Sengketa
                                        </Link>
                                    </DropdownMenuItem>
                                )}
                                {dispute.can?.export && (
                                    <>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem asChild className="cursor-pointer">
                                            <a href={dispute.export_url}>
                                                <Download className="size-4" />
                                                Unduh Dokumen (PDF)
                                            </a>
                                        </DropdownMenuItem>
                                    </>
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                );
            },
        },
    ];
}

