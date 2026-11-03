import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Building2, MapPin, ShieldCheck, Eye } from 'lucide-react';
import { router } from '@inertiajs/react';

export function getColumns() {
    return [
        {
            accessorKey: 'company_name',
            header: 'Perusahaan',
            cell: ({ row }) => {
                const tenant = row.original;

                return (
                    <div className="flex items-center gap-2 py-1">
                        <Building2 className="size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                        <span className="font-semibold text-foreground">
                            {tenant.company_name}
                        </span>
                    </div>
                );
            },
        },
        {
            accessorKey: 'address',
            header: 'Lokasi',
            cell: ({ row }) => {
                const address = row.original.address;

                if (!address) {
                    return <span className="text-xs text-muted-foreground">-</span>;
                }

                return (
                    <div className="flex items-start gap-1.5 text-sm max-w-xs">
                        <MapPin className="size-3.5 text-muted-foreground mt-0.5 shrink-0" />
                        <span className="text-muted-foreground line-clamp-2">
                            {address}
                        </span>
                    </div>
                );
            },
        },
        {
            accessorKey: 'verification_status',
            header: 'Status Verifikasi',
            cell: ({ row }) => {
                const status = row.original.verification_status;

                if (status === 'approved') {
                    return (
                        <Badge className="bg-emerald-600 text-white hover:bg-emerald-700 flex items-center gap-1 w-fit">
                            <ShieldCheck className="size-3" />
                            Terverifikasi
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
            accessorKey: 'verified_at',
            header: 'Tergabung Sejak',
            cell: ({ row }) => {
                const date = row.original.verified_at ?? row.original.created_at;

                if (!date) {
                    return <span className="text-xs text-muted-foreground">-</span>;
                }

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
            header: 'Aksi',
            cell: ({ row }) => (
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() =>
                        router.get(`/kerja-sama/calon-mitra/${row.original.uuid}`)
                    }
                >
                    <Eye className="mr-1.5 size-4" />
                    Lihat Detail
                </Button>
            ),
        },
    ];
}
