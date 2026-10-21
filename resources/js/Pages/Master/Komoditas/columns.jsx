import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { Edit, MoreHorizontal, Trash2 } from 'lucide-react';
import { DataTableColumnHeader } from '@/Components/data-table/data-table-column-header';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { LoadingButton } from '@/Components/loading-button';
import { notify } from '@/lib/notify';

export function getColumns({ onSortChange }) {
    return [
        {
            accessorKey: 'sku',
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title="SKU"
                    onSortChange={onSortChange}
                />
            ),
            cell: ({ row }) => {
                const sku = row.getValue('sku');
                return sku ? (
                    <span className="font-mono text-xs font-semibold px-2 py-0.5 rounded bg-muted">
                        {sku}
                    </span>
                ) : (
                    <span className="text-xs text-muted-foreground italic">-</span>
                );
            },
        },
        {
            accessorKey: 'nama_komoditas',
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title="Nama Komoditas"
                    onSortChange={onSortChange}
                />
            ),
            cell: ({ row }) => {
                const komoditas = row.original;
                return (
                    <span className="font-medium text-foreground">{komoditas.nama_komoditas}</span>
                );
            },
        },
        {
            accessorKey: 'kategori',
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title="Kategori"
                    onSortChange={onSortChange}
                />
            ),
            cell: ({ row }) => {
                const kategori = row.getValue('kategori');
                return kategori ? (
                    <Badge variant="outline" className="font-normal">
                        {kategori}
                    </Badge>
                ) : (
                    <span className="text-xs text-muted-foreground italic">-</span>
                );
            },
        },
        {
            accessorKey: 'satuan',
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title="Satuan"
                    onSortChange={onSortChange}
                />
            ),
            cell: ({ row }) => (
                <span className="text-sm uppercase font-semibold text-muted-foreground">
                    {row.getValue('satuan')}
                </span>
            ),
        },
        {
            accessorKey: 'kode_referensi_api',
            header: 'Ref. API Pangan',
            cell: ({ row }) => {
                const ref = row.getValue('kode_referensi_api');
                return ref ? (
                    <Badge variant="secondary" className="font-mono text-xs">
                        ID: {ref}
                    </Badge>
                ) : (
                    <span className="text-xs text-muted-foreground italic">Manual (Tanpa API)</span>
                );
            },
        },
        {
            accessorKey: 'status',
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title="Status"
                    onSortChange={onSortChange}
                />
            ),
            cell: ({ row }) => {
                const isActive = row.getValue('status');
                return isActive ? (
                    <Badge variant='green'>
                        Aktif
                    </Badge>
                ) : (
                    <Badge variant="secondary" className="text-muted-foreground">
                        Non-aktif
                    </Badge>
                );
            },
        },
        {
            id: 'actions',
            header: () => <span className="sr-only">Aksi</span>,
            cell: ({ row }) => <ActionCell komoditas={row.original} />,
        },
    ];
}

function ActionCell({ komoditas }) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);

    const handleDelete = () => {
        setDeleting(true);
        router.delete(`/master/komoditas/${komoditas.uuid}`, {
            preserveScroll: true,
            onSuccess: () => {
                setDialogOpen(false);
            },
            onError: () => {
                notify.validationFailed();
            },
            onFinish: () => {
                setDeleting(false);
            },
        });
    };

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="ghost" className="size-8 p-0">
                        <span className="sr-only">Buka menu</span>
                        <MoreHorizontal className="size-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuLabel>Aksi Komoditas</DropdownMenuLabel>
                    <DropdownMenuItem asChild>
                        <Link href={`/master/komoditas/${komoditas.uuid}/edit`}>
                            <Edit className="mr-2 size-4" />
                            Ubah Data
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        onClick={() => setDialogOpen(true)}
                        className="text-destructive focus:text-destructive cursor-pointer"
                    >
                        <Trash2 className="mr-2 size-4" />
                        Hapus Komoditas
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <AlertDialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus Komoditas</AlertDialogTitle>
                        <AlertDialogDescription>
                            Apakah Anda yakin ingin menghapus komoditas{' '}
                            <strong>{komoditas.nama_komoditas}</strong>? Tindakan ini dapat
                            dibatalkan kembali melalui arsip data.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={deleting}>Batal</AlertDialogCancel>
                        <LoadingButton
                            variant="destructive"
                            loading={deleting}
                            loadingText="Menghapus..."
                            onClick={handleDelete}
                        >
                            Ya, Hapus
                        </LoadingButton>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
