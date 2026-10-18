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
    Clock,
    Download,
    Eye,
    FileText,
    MoreHorizontal,
    Phone,
    ShieldAlert,
    ShieldCheck,
    XCircle,
} from 'lucide-react';

export function getColumns({ onApprove, onReject, onViewDetail }) {
    return [
        {
            accessorKey: 'vendor',
            header: 'Vendor & Kontak',
            cell: ({ row }) => {
                const doc = row.original;
                const vendor = doc.vendor;

                if (!vendor) {
                    return (
                        <span className="text-sm italic text-muted-foreground">
                            Vendor tidak ditemukan
                        </span>
                    );
                }

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
                        {vendor.kategori_komoditas && (
                            <div className="mt-0.5">
                                <span className="inline-block rounded-md bg-muted px-2 py-0.5 text-[11px] font-medium text-muted-foreground">
                                    {vendor.kategori_komoditas}
                                </span>
                            </div>
                        )}
                    </div>
                );
            },
        },
        {
            accessorKey: 'jenis_dokumen',
            header: 'Jenis Dokumen',
            cell: ({ row }) => {
                const doc = row.original;
                return (
                    <div className="flex items-center gap-2 font-medium">
                        <FileText className="size-4 text-muted-foreground shrink-0" />
                        <span>{doc.jenis_dokumen}</span>
                    </div>
                );
            },
        },
        {
            accessorKey: 'nomor_dokumen',
            header: 'Nomor Dokumen',
            cell: ({ row }) => {
                const nomor = row.original.nomor_dokumen;
                return nomor ? (
                    <span className="font-mono text-xs font-medium text-foreground bg-muted/60 px-2 py-1 rounded">
                        {nomor}
                    </span>
                ) : (
                    <span className="text-xs italic text-muted-foreground">-</span>
                );
            },
        },
        {
            accessorKey: 'status_verifikasi',
            header: 'Status',
            cell: ({ row }) => {
                const status = row.original.status_verifikasi;
                const alasan = row.original.alasan_penolakan;

                switch (status) {
                    case 'approved':
                        return (
                            <Badge
                                variant="outline"
                                className="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20 font-medium inline-flex items-center gap-1.5"
                            >
                                <CheckCircle2 className="size-3.5" />
                                Disetujui
                            </Badge>
                        );
                    case 'rejected':
                        return (
                            <div className="flex flex-col gap-1">
                                <Badge
                                    variant="outline"
                                    className="bg-destructive/10 text-destructive border-destructive/20 font-medium inline-flex items-center gap-1.5 w-fit"
                                >
                                    <XCircle className="size-3.5" />
                                    Ditolak
                                </Badge>
                                {alasan && (
                                    <span
                                        className="text-xs text-muted-foreground line-clamp-1 max-w-[200px]"
                                        title={alasan}
                                    >
                                        Alasan: {alasan}
                                    </span>
                                )}
                            </div>
                        );
                    case 'pending':
                    default:
                        return (
                            <Badge
                                variant="outline"
                                className="bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20 font-medium inline-flex items-center gap-1.5"
                            >
                                <Clock className="size-3.5" />
                                Menunggu Verifikasi
                            </Badge>
                        );
                }
            },
        },
        {
            accessorKey: 'diverifikasi_pada',
            header: 'Waktu Verifikasi',
            cell: ({ row }) => {
                const doc = row.original;
                const verifDate = doc.diverifikasi_pada;
                const verifier = doc.verifier?.nama_lengkap;

                if (!verifDate) {
                    return (
                        <span className="text-xs text-muted-foreground italic">
                            Belum diproses
                        </span>
                    );
                }

                return (
                    <div className="flex flex-col text-xs">
                        <span className="font-medium text-foreground">
                            {new Date(verifDate).toLocaleDateString('id-ID', {
                                day: 'numeric',
                                month: 'short',
                                year: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit',
                            })}
                        </span>
                        {verifier && (
                            <span className="text-muted-foreground">
                                oleh {verifier}
                            </span>
                        )}
                    </div>
                );
            },
        },
        {
            id: 'actions',
            header: () => <div className="text-right">Aksi</div>,
            cell: ({ row }) => {
                const doc = row.original;
                const isPending = doc.status_verifikasi === 'pending';

                return (
                    <div className="flex items-center justify-end gap-2">
                        {doc.file_url && (
                            <Button
                                variant="outline"
                                size="sm"
                                asChild
                                className="h-8 gap-1.5 text-xs font-normal"
                            >
                                <a
                                    href={doc.file_url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Download className="size-3.5" />
                                    <span>Unduh File</span>
                                </a>
                            </Button>
                        )}

                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="h-8 w-8 p-0"
                                >
                                    <span className="sr-only">Buka menu</span>
                                    <MoreHorizontal className="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-48">
                                <DropdownMenuLabel>Tindakan</DropdownMenuLabel>
                                <DropdownMenuItem
                                    onClick={() => onViewDetail(doc)}
                                    className="cursor-pointer gap-2"
                                >
                                    <Eye className="size-4 text-muted-foreground" />
                                    Detail Dokumen
                                </DropdownMenuItem>

                                {isPending && (
                                    <>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            onClick={() => onApprove(doc)}
                                            className="cursor-pointer gap-2 text-emerald-600 focus:text-emerald-600 focus:bg-emerald-50 dark:focus:bg-emerald-950/30"
                                        >
                                            <ShieldCheck className="size-4" />
                                            Setujui Dokumen
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            onClick={() => onReject(doc)}
                                            className="cursor-pointer gap-2 text-destructive focus:text-destructive focus:bg-destructive/10"
                                        >
                                            <ShieldAlert className="size-4" />
                                            Tolak Dokumen
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
