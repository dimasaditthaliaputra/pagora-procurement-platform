import { useState } from 'react';
import { router } from '@inertiajs/react';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { LoadingButton } from '@/Components/loading-button';
import { ShieldCheck } from 'lucide-react';
import { notify } from '@/lib/notify';

export function ApprovalDialog({ document, open, onOpenChange }) {
    const [loading, setLoading] = useState(false);

    if (!document) return null;

    const vendorName = document.vendor?.nama_perusahaan ?? 'Vendor';

    const handleApprove = () => {
        setLoading(true);
        router.post(
            `/master/vendor/verifikasi-dokumen/${document.uuid}/verifikasi`,
            {
                aksi: 'approve',
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setLoading(false);
                    onOpenChange(false);
                },
                onError: (errors) => {
                    setLoading(false);
                    notify.error(
                        errors.aksi ||
                        errors.status_verifikasi ||
                        'Gagal memproses persetujuan dokumen.'
                    );
                },
            }
        );
    };

    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent className="max-w-md">
                <AlertDialogHeader>
                    <div className="flex items-center gap-2 text-emerald-600">
                        <div className="flex size-10 items-center justify-center rounded-full bg-emerald-50 dark:bg-emerald-950/40">
                            <ShieldCheck className="size-5" />
                        </div>
                        <AlertDialogTitle>Setujui Dokumen Legalitas</AlertDialogTitle>
                    </div>
                    <AlertDialogDescription className="pt-2 text-sm text-muted-foreground leading-relaxed">
                        Apakah Anda yakin ingin menyetujui dokumen legalitas{' '}
                        <strong className="text-foreground">{document.jenis_dokumen}</strong>{' '}
                        milik <strong className="text-foreground">{vendorName}</strong>?
                        <br />
                        <span className="block mt-2 text-xs">
                            Status verifikasi dokumen dan vendor akan diubah menjadi{' '}
                            <span className="font-semibold text-emerald-600">Disetujui</span>.
                        </span>
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter className="mt-4 gap-2">
                    <AlertDialogCancel disabled={loading}>
                        Batal
                    </AlertDialogCancel>
                    <LoadingButton
                        onClick={handleApprove}
                        loading={loading}
                        loadingText="Menyetujui..."
                        className="bg-emerald-600 hover:bg-emerald-700 text-white"
                    >
                        Ya, Setujui Dokumen
                    </LoadingButton>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
