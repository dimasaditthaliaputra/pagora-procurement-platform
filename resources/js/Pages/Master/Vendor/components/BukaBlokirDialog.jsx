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
import { ShieldCheck, CheckCircle2 } from 'lucide-react';
import { notify } from '@/lib/notify';

export function BukaBlokirDialog({ vendor, open, onOpenChange }) {
    const [loading, setLoading] = useState(false);

    if (!vendor) return null;

    const handleClose = (nextOpen) => {
        if (loading) return;
        onOpenChange(nextOpen);
    };

    const handleUnblock = () => {
        setLoading(true);

        router.post(
            `/master/vendor/${vendor.uuid}/buka-blokir`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    setLoading(false);
                    onOpenChange(false);
                },
                onError: (errors) => {
                    setLoading(false);
                    notify.error(errors.message || errors.error || 'Gagal membuka pemblokiran vendor.');
                },
            }
        );
    };

    return (
        <AlertDialog open={open} onOpenChange={handleClose}>
            <AlertDialogContent className="max-w-md">
                <AlertDialogHeader>
                    <div className="flex items-center gap-2 text-emerald-600 dark:text-emerald-400">
                        <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-emerald-500/10">
                            <ShieldCheck className="size-5" />
                        </div>
                        <AlertDialogTitle>Buka Pemblokiran Rekanan</AlertDialogTitle>
                    </div>
                    <AlertDialogDescription className="pt-2 text-sm text-muted-foreground leading-relaxed">
                        Apakah Anda yakin ingin memulihkan status rekanan{' '}
                        <strong className="text-foreground">{vendor.nama_perusahaan}</strong>?
                        Vendor ini akan kembali aktif dan akun penggunanya dapat login kembali ke platform Pagora.
                    </AlertDialogDescription>
                </AlertDialogHeader>

                {vendor.alasan_pemblokiran && (
                    <div className="mt-3 rounded-md border border-destructive/20 bg-destructive/5 p-3 text-xs text-muted-foreground">
                        <span className="font-semibold text-destructive block mb-1">
                            Alasan pemblokiran sebelumnya:
                        </span>
                        <p className="italic text-foreground">
                            "{vendor.alasan_pemblokiran}"
                        </p>
                    </div>
                )}

                <AlertDialogFooter className="mt-6">
                    <AlertDialogCancel
                        type="button"
                        disabled={loading}
                        onClick={() => handleClose(false)}
                    >
                        Batal
                    </AlertDialogCancel>
                    <LoadingButton
                        type="button"
                        className="bg-emerald-600 hover:bg-emerald-700 text-white"
                        onClick={handleUnblock}
                        loading={loading}
                        loadingText="Memulihkan..."
                    >
                        <CheckCircle2 className="size-4 mr-1.5" />
                        Pulihkan & Buka Blokir
                    </LoadingButton>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
