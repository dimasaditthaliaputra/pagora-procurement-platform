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
import { Textarea } from '@/Components/ui/textarea';
import { Label } from '@/Components/ui/label';
import { LoadingButton } from '@/Components/loading-button';
import { ShieldAlert, AlertTriangle } from 'lucide-react';
import { notify } from '@/lib/notify';
import { cn } from '@/lib/utils';

export function BlokirDialog({ vendor, open, onOpenChange }) {
    const [alasan, setAlasan] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    if (!vendor) return null;

    const handleClose = (nextOpen) => {
        if (loading) return; // cegah tutup saat processing
        if (!nextOpen) {
            setAlasan('');
            setError('');
        }
        onOpenChange(nextOpen);
    };

    const handleSubmit = (e) => {
        e.preventDefault();

        const trimmed = alasan.trim();
        if (!trimmed) {
            setError('Alasan pemblokiran wajib diisi.');
            notify.validationFailed();
            return;
        }

        if (trimmed.length < 5) {
            setError('Alasan pemblokiran minimal 5 karakter.');
            notify.validationFailed();
            return;
        }

        setError('');
        setLoading(true);

        router.post(
            `/master/vendor/${vendor.uuid}/blokir`,
            {
                alasan_pemblokiran: trimmed,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setLoading(false);
                    setAlasan('');
                    setError('');
                    onOpenChange(false);
                },
                onError: (errors) => {
                    setLoading(false);
                    if (errors.alasan_pemblokiran) {
                        setError(errors.alasan_pemblokiran);
                        notify.validationFailed();
                    } else {
                        notify.error(errors.message || errors.error || 'Gagal memproses pemblokiran vendor.');
                    }
                },
            }
        );
    };

    return (
        <AlertDialog open={open} onOpenChange={handleClose}>
            <AlertDialogContent className="max-w-lg">
                <form onSubmit={handleSubmit} noValidate>
                    <AlertDialogHeader>
                        <div className="flex items-center gap-2 text-destructive">
                            <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-destructive/10">
                                <ShieldAlert className="size-5" />
                            </div>
                            <AlertDialogTitle>Blokir Rekanan Vendor</AlertDialogTitle>
                        </div>
                        <AlertDialogDescription className="pt-2 text-sm text-muted-foreground leading-relaxed">
                            Apakah Anda yakin ingin memblokir rekanan{' '}
                            <strong className="text-foreground">{vendor.nama_perusahaan}</strong>?
                            Vendor yang diblokir tidak dapat login ke sistem, tidak dapat ditunjuk dalam pengadaan,
                            dan seluruh akun anggotanya akan dinonaktifkan sementara.
                        </AlertDialogDescription>
                    </AlertDialogHeader>

                    <div className="mt-4 space-y-2">
                        <Label htmlFor="alasan_pemblokiran" className="flex items-center gap-1 font-medium">
                            Alasan Pemblokiran <span className="text-destructive">*</span>
                        </Label>
                        <Textarea
                            id="alasan_pemblokiran"
                            value={alasan}
                            onChange={(e) => {
                                setAlasan(e.target.value);
                                if (error) setError('');
                            }}
                            placeholder="Tuliskan alasan pemblokiran secara spesifik (contoh: Wanprestasi pengerjaan PO-xxx atau pelanggaran regulasi)..."
                            rows={3}
                            disabled={loading}
                            aria-invalid={!!error}
                            className={cn(
                                error && 'border-destructive focus-visible:ring-destructive'
                            )}
                        />
                        {error && (
                            <p className="text-xs font-medium text-destructive">
                                {error}
                            </p>
                        )}
                    </div>

                    <div className="mt-3 flex items-start gap-2 rounded-md bg-amber-500/10 p-2.5 text-xs text-amber-700 dark:text-amber-400">
                        <AlertTriangle className="size-4 shrink-0 mt-0.5" />
                        <span>
                            Tindakan ini akan tercatat dalam audit log sistem beserta identitas Anda sebagai pengambil keputusan.
                        </span>
                    </div>

                    <AlertDialogFooter className="mt-6">
                        <AlertDialogCancel
                            type="button"
                            disabled={loading}
                            onClick={() => handleClose(false)}
                        >
                            Batal
                        </AlertDialogCancel>
                        <LoadingButton
                            type="submit"
                            variant="destructive"
                            loading={loading}
                            loadingText="Memblokir..."
                        >
                            Konfirmasi Blokir
                        </LoadingButton>
                    </AlertDialogFooter>
                </form>
            </AlertDialogContent>
        </AlertDialog>
    );
}
