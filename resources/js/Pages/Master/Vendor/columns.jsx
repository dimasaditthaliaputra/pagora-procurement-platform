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
import {
    Building2,
    CheckCircle2,
    Eye,
    MoreHorizontal,
    Phone,
    ShieldAlert,
    ShieldCheck,
    Tag,
} from 'lucide-react';

export function getColumns({ onBlock, onUnblock, onViewDetail, canManage }) {
    return [
        {
            accessorKey: 'nama_perusahaan',
            header: 'Vendor & Kontak',
            cell: ({ row }) => {
                const vendor = row.original;

                return (
                    <div className="flex flex-col gap-1 py-1">
                        <div className="flex items-center gap-2">
                            <Building2 className="size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                            <span className="font-semibold text-foreground">
                                {vendor.nama_perusahaan}
                            </span>
                        </div>
                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                            <span>{vendor.email_kontak || '-'}</span>
                            {vendor.no_telepon && (
                                <span className="flex items-center gap-1">
                                    <Phone className="size-3 text-muted-foreground" />
                                    {vendor.no_telepon}
                                </span>
                            )}
                        </div>
                    </div>
                );
            },
        },
        {
            accessorKey: 'kategori_komoditas',
            header: 'Kategori Komoditas',
            cell: ({ row }) => {
                const kategori = row.original.kategori_komoditas;

                return (
                    <div className="flex items-center gap-1.5 text-sm">
                        <Tag className="size-3.5 text-muted-foreground" />
                        <span className="font-medium text-foreground">
                            {kategori || 'Umum'}
                        </span>
                    </div>
                );
            },
        },
        {
            accessorKey: 'status_verifikasi',
            header: 'Verifikasi Legalitas',
            cell: ({ row }) => {
                const status = row.original.status_verifikasi;

                if (status === 'terverifikasi') {
                    return (
                        <Badge className="bg-emerald-600 text-white hover:bg-emerald-700">
                            Terverifikasi
                        </Badge>
                    );
                }

                if (status === 'ditolak') {
                    return (
                        <Badge variant="destructive">
                            Ditolak
                        </Badge>
                    );
                }

                return (
                    <Badge variant="outline" className="text-amber-600 border-amber-300 bg-amber-50 dark:bg-amber-950/40">
                        Pending
                    </Badge>
                );
            },
        },
        {
            accessorKey: 'status_aktif',
            header: 'Status Rekanan',
            cell: ({ row }) => {
                const vendor = row.original;
                const isBlocked = !vendor.status_aktif;

                if (isBlocked) {
                    return (
                        <div className="flex flex-col gap-0.5">
                            <Badge variant="destructive" className="w-fit flex items-center gap-1 font-semibold">
                                <ShieldAlert className="size-3" />
                                DIBLOKIR
                            </Badge>
                            {vendor.alasan_pemblokiran && (
                                <span className="text-[11px] text-muted-foreground truncate max-w-[180px] italic">
                                    {vendor.alasan_pemblokiran}
                                </span>
                            )}
                        </div>
                    );
                }

                return (
                    <Badge className="bg-emerald-500 hover:bg-emerald-600 text-white flex items-center gap-1 w-fit">
                        <CheckCircle2 className="size-3" />
                        Aktif
                    </Badge>
                );
            },
        },
        {
            accessorKey: 'created_at',
            header: 'Terdaftar',
            cell: ({ row }) => {
                const date = row.original.created_at;
                if (!date) return <span className="text-xs text-muted-foreground">-</span>;

                return (
                    <span className="text-xs text-muted-foreground">
                        {new Date(date).toLocaleDateString('id-ID', {
                            day: 'numeric',
                            month: 'short',
                            year: 'numeric',
                        })}
                    </span>
                );
            },
        },
        {
            id: 'actions',
            header: () => <div className="text-right">Aksi</div>,
            cell: ({ row }) => {
                const vendor = row.original;
                const isBlocked = !vendor.status_aktif;

                return (
                    <div className="flex items-center justify-end gap-1">
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-8 px-2 text-xs"
                            onClick={() => onViewDetail(vendor)}
                        >
                            <Eye className="size-3.5 mr-1" />
                            Detail
                        </Button>

                        {canManage && (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="ghost" size="icon" className="size-8">
                                        <span className="sr-only">Menu aksi</span>
                                        <MoreHorizontal className="size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" className="w-44">
                                    <DropdownMenuLabel>Aksi Rekanan</DropdownMenuLabel>
                                    <DropdownMenuItem onClick={() => onViewDetail(vendor)}>
                                        <Eye className="size-4 mr-2" />
                                        Lihat Profil
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    {isBlocked ? (
                                        <DropdownMenuItem
                                            className="text-emerald-600 dark:text-emerald-400 focus:text-emerald-700 font-medium"
                                            onClick={() => onUnblock(vendor)}
                                        >
                                            <ShieldCheck className="size-4 mr-2" />
                                            Buka Blokir
                                        </DropdownMenuItem>
                                    ) : (
                                        <DropdownMenuItem
                                            className="text-destructive focus:text-destructive font-medium"
                                            onClick={() => onBlock(vendor)}
                                        >
                                            <ShieldAlert className="size-4 mr-2" />
                                            Blokir Vendor
                                        </DropdownMenuItem>
                                    )}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}
                    </div>
                );
            },
        },
    ];
}
