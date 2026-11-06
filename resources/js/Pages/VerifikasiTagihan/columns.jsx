import { Link } from '@inertiajs/react';
import { Building2, Eye, FileCheck, FileText, Receipt, ShieldAlert, ShieldCheck } from 'lucide-react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { formatDate, formatRupiah } from '@/lib/format';

export function getColumns() {
    return [
        {
            accessorKey: 'nomor_invoice_vendor',
            header: 'Invoice & Tanggal',
            cell: ({ row }) => {
                const invoice = row.original;

                return (
                    <div className="flex flex-col gap-1 py-1">
                        <div className="flex items-center gap-2">
                            <Receipt className="size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                            <Link
                                href={`/verifikasi-tagihan/${invoice.uuid}`}
                                className="font-semibold text-foreground hover:underline"
                            >
                                {invoice.nomor_invoice_vendor}
                            </Link>
                        </div>
                        <span className="text-xs text-muted-foreground">
                            {formatDate(invoice.tanggal_invoice)}
                        </span>
                    </div>
                );
            },
        },
        {
            accessorKey: 'vendor',
            header: 'Vendor Rekanan',
            cell: ({ row }) => {
                const vendor = row.original.vendor;

                return (
                    <div className="flex flex-col gap-0.5">
                        <div className="flex items-center gap-1.5 font-medium text-foreground">
                            <Building2 className="size-3.5 shrink-0 text-muted-foreground" />
                            <span>{vendor?.company_name || '-'}</span>
                        </div>
                        {vendor?.contact_email && (
                            <span className="text-xs text-muted-foreground">
                                {vendor.contact_email}
                            </span>
                        )}
                    </div>
                );
            },
        },
        {
            accessorKey: 'purchase_order',
            header: 'Purchase Order',
            cell: ({ row }) => {
                const po = row.original.purchase_order;

                if (!po) {
                    return <span className="text-xs text-muted-foreground">-</span>;
                }

                return (
                    <div className="flex flex-col gap-1">
                        <div className="flex items-center gap-1.5 font-mono text-xs font-semibold text-foreground">
                            <FileText className="size-3.5 shrink-0 text-muted-foreground" />
                            <Link
                                href={`/purchase-order/${po.uuid}`}
                                className="hover:underline"
                            >
                                {po.nomor_po}
                            </Link>
                        </div>
                        {po.status && (
                            <span className="text-[11px] text-muted-foreground">
                                Status: {po.status.label || po.status}
                            </span>
                        )}
                    </div>
                );
            },
        },
        {
            accessorKey: 'jumlah_tagihan',
            header: () => <div className="text-right">Nominal Tagihan</div>,
            cell: ({ row }) => {
                return (
                    <div className="text-right font-semibold tabular-nums text-foreground">
                        {formatRupiah(row.original.jumlah_tagihan)}
                    </div>
                );
            },
        },
        {
            accessorKey: 'status_matching',
            header: 'Hasil 3-Way Matching',
            cell: ({ row }) => {
                const status = row.original.status_matching;

                if (status === 'matched') {
                    return (
                        <Badge className="bg-emerald-600 text-white hover:bg-emerald-700 flex items-center gap-1 w-fit">
                            <ShieldCheck className="size-3" />
                            Sesuai (Cocok)
                        </Badge>
                    );
                }

                if (status === 'mismatched') {
                    return (
                        <Badge variant="destructive" className="flex items-center gap-1 w-fit">
                            <ShieldAlert className="size-3" />
                            Tidak Sesuai
                        </Badge>
                    );
                }

                return (
                    <Badge variant="outline" className="text-amber-600 border-amber-300 bg-amber-50 dark:bg-amber-950/40 flex items-center gap-1 w-fit">
                        <FileCheck className="size-3" />
                        Menunggu Verifikasi
                    </Badge>
                );
            },
        },
        {
            id: 'actions',
            header: () => <div className="text-right">Aksi</div>,
            cell: ({ row }) => {
                const invoice = row.original;
                const isPending = invoice.status_matching === 'pending';

                return (
                    <div className="flex items-center justify-end gap-1">
                        <Button
                            variant={isPending ? 'default' : 'outline'}
                            size="sm"
                            className="h-8 text-xs gap-1.5"
                            asChild
                        >
                            <Link href={`/verifikasi-tagihan/${invoice.uuid}`}>
                                <Eye className="size-3.5" />
                                {isPending ? 'Verifikasi' : 'Detail'}
                            </Link>
                        </Button>
                    </div>
                );
            },
        },
    ];
}

