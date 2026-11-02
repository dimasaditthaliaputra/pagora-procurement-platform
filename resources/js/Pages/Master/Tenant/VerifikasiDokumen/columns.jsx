import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import {
    Building2,
    CheckCircle2,
    Clock,
    Download,
    Eye,
    FileText,
    Mail,
    MoreHorizontal,
    User,
    XCircle,
} from 'lucide-react';

export function VerificationStatusBadge({ status }) {
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
                <Badge
                    variant="outline"
                    className="bg-destructive/10 text-destructive border-destructive/20 font-medium inline-flex items-center gap-1.5"
                >
                    <XCircle className="size-3.5" />
                    Ditolak
                </Badge>
            );
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
}

export function getColumns({ onViewDetail, onApprove, onReject }) {
    return [
        {
            accessorKey: 'company_name',
            header: 'Tenant & Legalitas',
            cell: ({ row }) => {
                const tenant = row.original;

                return (
                    <div className="flex flex-col gap-1 py-1">
                        <div className="flex items-center gap-2">
                            <Building2 className="size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                            <span className="font-semibold text-foreground">
                                {tenant.company_name}
                            </span>
                        </div>
                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                            {tenant.tax_number && (
                                <span className="font-mono">NPWP: {tenant.tax_number}</span>
                            )}
                            {tenant.address && (
                                <span className="truncate max-w-xs">{tenant.address}</span>
                            )}
                        </div>
                    </div>
                );
            },
        },
        {
            id: 'owner',
            header: 'Akun Pemilik / Kontak',
            cell: ({ row }) => {
                const owner = row.original.owner;

                if (!owner) {
                    return <span className="text-xs text-muted-foreground">-</span>;
                }

                return (
                    <div className="flex flex-col gap-0.5 text-xs">
                        <div className="flex items-center gap-1.5 font-medium text-foreground">
                            <User className="size-3.5 text-muted-foreground shrink-0" />
                            <span>{owner.name}</span>
                        </div>
                        <div className="flex items-center gap-1.5 text-muted-foreground">
                            <Mail className="size-3 text-muted-foreground shrink-0" />
                            <span>{owner.email}</span>
                        </div>
                    </div>
                );
            },
        },
        {
            id: 'document',
            header: 'Dokumen',
            cell: ({ row }) => {
                const tenant = row.original;

                if (!tenant.has_legal_document) {
                    return (
                        <span className="text-xs text-muted-foreground italic">
                            Tidak Ada Berkas
                        </span>
                    );
                }

                return (
                    <div className="flex items-center gap-2">
                        {tenant.legal_document_url ? (
                            <Button
                                variant="outline"
                                size="sm"
                                asChild
                                className="h-7 text-xs gap-1.5 px-2.5"
                            >
                                <a
                                    href={tenant.legal_document_url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Download className="size-3" />
                                    <span>Unduh SIUP/NIB</span>
                                </a>
                            </Button>
                        ) : (
                            <Badge variant="outline" className="text-xs font-normal gap-1">
                                <FileText className="size-3 text-muted-foreground" />
                                Tersedia
                            </Badge>
                        )}
                    </div>
                );
            },
        },
        {
            accessorKey: 'verification_status',
            header: 'Status',
            cell: ({ row }) => (
                <VerificationStatusBadge status={row.original.verification_status} />
            ),
        },
        {
            id: 'actions',
            header: () => <div className="text-right">Aksi</div>,
            cell: ({ row }) => {
                const tenant = row.original;
                const isPending = tenant.verification_status === 'pending';

                return (
                    <div className="flex items-center justify-end gap-1.5">
                        <Button
                            variant="outline"
                            size="sm"
                            className="h-8 gap-1.5 text-xs font-normal"
                            onClick={() => onViewDetail(tenant)}
                        >
                            <Eye className="size-3.5" />
                            <span>Tinjau</span>
                        </Button>

                        {isPending && (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="h-8 w-8 text-muted-foreground hover:text-foreground"
                                    >
                                        <MoreHorizontal className="size-4" />
                                        <span className="sr-only">Aksi lainnya</span>
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" className="w-36">
                                    <DropdownMenuItem
                                        onClick={() => onApprove(tenant)}
                                        className="gap-2 text-emerald-600 focus:text-emerald-600"
                                    >
                                        <CheckCircle2 className="size-4" />
                                        <span>Setujui</span>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        onClick={() => onReject(tenant)}
                                        className="gap-2 text-destructive focus:text-destructive"
                                    >
                                        <XCircle className="size-4" />
                                        <span>Tolak</span>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}
                    </div>
                );
            },
        },
    ];
}

