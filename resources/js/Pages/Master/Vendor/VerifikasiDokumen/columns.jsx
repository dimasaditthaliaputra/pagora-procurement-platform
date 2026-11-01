import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    Building2,
    CheckCircle2,
    Clock,
    Eye,
    FileText,
    Phone,
    XCircle,
} from 'lucide-react';

const DOCUMENT_STATUS_ICON = {
    approved: <CheckCircle2 className="size-3.5 text-emerald-600" />,
    rejected: <XCircle className="size-3.5 text-destructive" />,
    pending: <Clock className="size-3.5 text-amber-600" />,
};

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

export function getColumns({ onViewDetail }) {
    return [
        {
            accessorKey: 'company_name',
            header: 'Vendor & Kontak',
            cell: ({ row }) => {
                const vendor = row.original;

                return (
                    <div className="flex flex-col gap-1 py-1">
                        <div className="flex items-center gap-2">
                            <Building2 className="size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                            <span className="font-semibold text-foreground">
                                {vendor.company_name}
                            </span>
                        </div>
                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                            <span>{vendor.contact_email || '-'}</span>
                            {vendor.phone && (
                                <span className="flex items-center gap-1">
                                    <Phone className="size-3 text-muted-foreground" />
                                    {vendor.phone}
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
            id: 'documents',
            header: 'Dokumen Legalitas',
            cell: ({ row }) => {
                const documents = row.original.documents ?? [];

                return (
                    <div className="flex flex-col gap-1.5">
                        {documents.map((doc) => (
                            <div key={doc.uuid} className="flex items-center gap-2 text-sm">
                                <FileText className="size-4 shrink-0 text-muted-foreground" />
                                <span className="font-medium">{doc.document_type}</span>
                                {doc.document_number && (
                                    <span className="font-mono text-xs text-muted-foreground">
                                        {doc.document_number}
                                    </span>
                                )}
                                {DOCUMENT_STATUS_ICON[doc.verification_status] ?? DOCUMENT_STATUS_ICON.pending}
                            </div>
                        ))}
                    </div>
                );
            },
        },
        {
            id: 'progress',
            header: 'Progres',
            cell: ({ row }) => {
                const documents = row.original.documents ?? [];
                const processed = documents.filter((doc) => doc.verification_status !== 'pending').length;

                return (
                    <span className="text-sm text-muted-foreground">
                        <span className="font-semibold text-foreground">{processed}</span> dari{' '}
                        <span className="font-semibold text-foreground">{documents.length}</span> diverifikasi
                    </span>
                );
            },
        },
        {
            accessorKey: 'verification_status',
            header: 'Status Vendor',
            cell: ({ row }) => <VerificationStatusBadge status={row.original.verification_status} />,
        },
        {
            id: 'actions',
            header: () => <div className="text-right">Aksi</div>,
            cell: ({ row }) => (
                <div className="flex items-center justify-end">
                    <Button
                        variant="outline"
                        size="sm"
                        className="h-8 gap-1.5 text-xs font-normal"
                        onClick={() => onViewDetail(row.original)}
                    >
                        <Eye className="size-3.5" />
                        <span>Tinjau</span>
                    </Button>
                </div>
            ),
        },
    ];
}
