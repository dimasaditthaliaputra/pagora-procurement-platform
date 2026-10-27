import { Link } from '@inertiajs/react';
import { Eye, Edit, MessagesSquare, MoreHorizontal, Send, Trash2 } from 'lucide-react';
import { DataTableColumnHeader } from '@/Components/data-table/data-table-column-header';
import { Button } from '@/Components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { formatDate, formatRupiah } from '@/lib/format';
import { StatusBadge } from '@/Pages/PurchaseOrder/components/StatusBadge';
import { hasNegotiation, isEditable, totalPo } from '@/Pages/PurchaseOrder/constants';

export function getColumns({ onSortChange, onKonfirmasi, onHapus }) {
    return [
        {
            accessorKey: 'nomor_po',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Nomor PO" onSortChange={onSortChange} />
            ),
            cell: ({ row }) => (
                <Link
                    href={`/purchase-order/${row.original.uuid}`}
                    className="font-mono text-xs font-semibold hover:underline"
                >
                    {row.original.nomor_po}
                </Link>
            ),
        },
        {
            id: 'vendor',
            header: 'Vendor',
            cell: ({ row }) => (
                <span className="font-medium text-foreground">{row.original.vendor.nama_perusahaan}</span>
            ),
        },
        {
            accessorKey: 'tanggal_po',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Tanggal PO" onSortChange={onSortChange} />
            ),
            cell: ({ row }) => <span className="text-sm">{formatDate(row.original.tanggal_po)}</span>,
        },
        {
            id: 'items',
            header: 'Komoditas',
            cell: ({ row }) => {
                const items = row.original.items;
                const first = items[0]?.nama_komoditas ?? '-';
                return (
                    <span className="text-sm text-muted-foreground">
                        {first}
                        {items.length > 1 && <span className="ml-1 text-xs">+{items.length - 1} lainnya</span>}
                    </span>
                );
            },
        },
        {
            accessorKey: 'total',
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Total" onSortChange={onSortChange} />
            ),
            cell: ({ row }) => (
                <span className="text-sm font-semibold tabular-nums">{formatRupiah(totalPo(row.original.items))}</span>
            ),
        },
        {
            accessorKey: 'status',
            header: 'Status',
            cell: ({ row }) => <StatusBadge po={row.original} showLock />,
        },
        {
            id: 'actions',
            header: () => <span className="sr-only">Aksi</span>,
            cell: ({ row }) => <ActionCell po={row.original} onKonfirmasi={onKonfirmasi} onHapus={onHapus} />,
        },
    ];
}

function ActionCell({ po, onKonfirmasi, onHapus }) {
    const draft = isEditable(po);

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" className="size-8 p-0">
                    <span className="sr-only">Buka menu</span>
                    <MoreHorizontal className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuLabel>Aksi Purchase Order</DropdownMenuLabel>
                <DropdownMenuItem asChild>
                    <Link href={`/purchase-order/${po.uuid}`}>
                        <Eye className="mr-2 size-4" />
                        Lihat Detail
                    </Link>
                </DropdownMenuItem>
                {(po.status === 'negosiasi' || hasNegotiation(po)) && (
                    <DropdownMenuItem asChild>
                        <Link href={`/purchase-order/${po.uuid}/negosiasi`}>
                            <MessagesSquare className="mr-2 size-4" />
                            {po.status === 'negosiasi' ? 'Buka Negosiasi' : 'Riwayat Negosiasi'}
                        </Link>
                    </DropdownMenuItem>
                )}
                {draft && (
                    <>
                        <DropdownMenuItem asChild>
                            <Link href={`/purchase-order/${po.uuid}/edit`}>
                                <Edit className="mr-2 size-4" />
                                Ubah Draf
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => onKonfirmasi(po)} className="cursor-pointer">
                            <Send className="mr-2 size-4" />
                            Konfirmasi &amp; Negosiasi
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            onClick={() => onHapus(po)}
                            className="text-destructive focus:text-destructive cursor-pointer"
                        >
                            <Trash2 className="mr-2 size-4" />
                            Hapus Draf
                        </DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
