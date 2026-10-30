import { MoreHorizontal, Eye, Edit, Trash2, UserCheck, UserX, Building2, Shield } from 'lucide-react';
import { DataTableColumnHeader } from '@/Components/data-table/data-table-column-header';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';

function getInitials(name) {
    if (!name) return 'U';
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0])
        .join('')
        .toUpperCase();
}

function getRoleBadge(role) {
    const roleName = role?.name;
    const roleLabel = role?.label ?? 'User';

    switch (roleName) {
        case 'buyer':
            return (
                <Badge variant="outline" className="bg-sky-500/10 text-sky-700 dark:text-sky-400 border-sky-500/30 font-medium">
                    {roleLabel}
                </Badge>
            );
        case 'qc_gudang':
            return (
                <Badge variant="outline" className="bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/30 font-medium">
                    {roleLabel}
                </Badge>
            );
        case 'superadmin':
            return (
                <Badge variant="outline" className="bg-purple-500/10 text-purple-700 dark:text-purple-400 border-purple-500/30 font-medium">
                    {roleLabel}
                </Badge>
            );
        case 'owner':
            return (
                <Badge variant="outline" className="bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-500/30 font-medium">
                    {roleLabel}
                </Badge>
            );
        case 'manajemen':
            return (
                <Badge variant="outline" className="bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 border-indigo-500/30 font-medium">
                    {roleLabel}
                </Badge>
            );
        case 'vendor':
            return (
                <Badge variant="outline" className="bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/30 font-medium">
                    {roleLabel}
                </Badge>
            );
        default:
            return (
                <Badge variant="secondary" className="font-medium">
                    {roleLabel}
                </Badge>
            );
    }
}

function formatDate(isoString) {
    if (!isoString) return '-';
    try {
        const date = new Date(isoString);
        return new Intl.DateTimeFormat('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(date);
    } catch {
        return isoString;
    }
}

export function getColumns({
    isSuperadmin = false,
    canEditPegawai = () => true,
    canDeletePegawai = () => true,
    onSortChange,
    onViewDetail,
    onEdit,
    onDelete,
}) {
    const columns = [
        {
            accessorKey: 'nama_lengkap',
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title="Pengguna"
                    onSortChange={onSortChange}
                />
            ),
            cell: ({ row }) => {
                const pegawai = row.original;
                return (
                    <div className="flex items-center gap-3">
                        <Avatar className="size-9 rounded-full border border-border/60">
                            <AvatarImage src={pegawai.avatar} alt={pegawai.nama_lengkap} />
                            <AvatarFallback className="text-xs font-semibold bg-primary/10 text-primary">
                                {getInitials(pegawai.nama_lengkap)}
                            </AvatarFallback>
                        </Avatar>
                        <div className="flex flex-col">
                            <span className="font-semibold text-foreground text-sm leading-tight">
                                {pegawai.nama_lengkap}
                            </span>
                            <span className="text-xs text-muted-foreground font-normal">
                                {pegawai.email}
                            </span>
                        </div>
                    </div>
                );
            },
        },
    ];

    if (isSuperadmin) {
        columns.push({
            id: 'perusahaan',
            header: () => <span className="text-xs font-semibold">Perusahaan</span>,
            cell: ({ row }) => {
                const perusahaan = row.original.perusahaan;
                if (!perusahaan) {
                    return (
                        <span className="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
                            <Shield className="size-3.5 text-purple-500" />
                            Internal Platform
                        </span>
                    );
                }

                return (
                    <div className="flex flex-col gap-0.5">
                        <span className="text-sm font-medium text-foreground flex items-center gap-1.5">
                            <Building2 className="size-3.5 text-muted-foreground shrink-0" />
                            <span className="truncate max-w-[200px]">{perusahaan.nama}</span>
                        </span>
                        <span className="text-[11px] text-muted-foreground capitalize pl-5">
                            {perusahaan.tipe === 'vendor' ? 'Rekanan Vendor' : 'Perusahaan Buyer'}
                        </span>
                    </div>
                );
            },
        });
    }

    columns.push(
        {
            accessorKey: 'role_id',
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title="Role / Posisi"
                    onSortChange={onSortChange}
                />
            ),
            cell: ({ row }) => {
                const pegawai = row.original;
                return getRoleBadge(pegawai.role);
            },
        },
        {
            accessorKey: 'status_aktif',
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title="Status Akun"
                    onSortChange={onSortChange}
                />
            ),
            cell: ({ row }) => {
                const isActive = row.getValue('status_aktif');
                return isActive ? (
                    <Badge className="bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-500/25 border-emerald-500/20 font-medium inline-flex items-center gap-1.5">
                        <UserCheck className="size-3" />
                        Aktif
                    </Badge>
                ) : (
                    <Badge variant="secondary" className="text-muted-foreground font-medium inline-flex items-center gap-1.5">
                        <UserX className="size-3" />
                        Non-aktif
                    </Badge>
                );
            },
        },
        {
            accessorKey: 'created_at',
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title="Terdaftar Pada"
                    onSortChange={onSortChange}
                />
            ),
            cell: ({ row }) => (
                <span className="text-xs text-muted-foreground">
                    {formatDate(row.getValue('created_at'))}
                </span>
            ),
        },
        {
            id: 'actions',
            header: () => <span className="sr-only">Aksi</span>,
            cell: ({ row }) => {
                const pegawai = row.original;
                const allowEdit = canEditPegawai(pegawai);
                const allowDelete = canDeletePegawai(pegawai);

                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" className="size-8 p-0 cursor-pointer">
                                <span className="sr-only">Buka menu aksi</span>
                                <MoreHorizontal className="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-48">
                            <DropdownMenuLabel>Aksi Pengguna</DropdownMenuLabel>
                            <DropdownMenuItem
                                onClick={() => onViewDetail(pegawai)}
                                className="cursor-pointer"
                            >
                                <Eye className="mr-2 size-4 text-muted-foreground" />
                                Lihat Detail
                            </DropdownMenuItem>
                            {allowEdit && (
                                <DropdownMenuItem
                                    onClick={() => onEdit(pegawai)}
                                    className="cursor-pointer"
                                >
                                    <Edit className="mr-2 size-4 text-muted-foreground" />
                                    Ubah Kredensial
                                </DropdownMenuItem>
                            )}
                            {allowDelete && (
                                <>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        onClick={() => onDelete(pegawai)}
                                        className="text-destructive focus:text-destructive cursor-pointer"
                                    >
                                        <Trash2 className="mr-2 size-4" />
                                        Hapus Akun
                                    </DropdownMenuItem>
                                </>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                );
            },
        }
    );

    return columns;
}
