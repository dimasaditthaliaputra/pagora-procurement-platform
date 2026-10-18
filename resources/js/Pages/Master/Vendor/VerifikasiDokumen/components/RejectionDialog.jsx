import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';
import { Textarea } from '@/Components/ui/textarea';
import { Label } from '@/Components/ui/label';
import { LoadingButton } from '@/Components/loading-button';
import { ShieldAlert } from 'lucide-react';
import { notify } from '@/lib/notify';
import { cn } from '@/lib/utils';

export function RejectionDialog({ document, open, onOpenChange }) {
    const [reason, setReason] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (open) {
            setReason('');
            setError('');
            setLoading(false);
        }
    }, [open]);

    if (!document) return null;

    const vendorName = document.vendor?.nama_perusahaan ?? 'Vendor';

    const handleReject = (e) => {
        e.preventDefault();

        const trimmed = reason.trim();
        if (!trimmed) {
            setError('Alasan penolakan wajib diisi agar vendor mengetahui poin perbaikan.');
            notify.validationFailed();
            return;
        }

        if (trimmed.length > 1000) {
            setError('Alasan penolakan maksimal 1000 karakter.');
            notify.validationFailed();
            return;
        }

        setError('');
        setLoading(true);

        router.post(
            `/master/vendor/verifikasi-dokumen/${document.uuid}/verifikasi`,
            {
                aksi: 'reject',
                alasan_penolakan: trimmed,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setLoading(false);
                    onOpenChange(false);
                },
                onError: (errors) => {
                    setLoading(false);
                    if (errors.alasan_penolakan) {
                        setError(errors.alasan_penolakan);
                        notify.validationFailed();
                    } else {
                        notify.error(
                            errors.aksi ||
                            errors.status_verifikasi ||
                            'Gagal memproses penolakan dokumen.'
                        );
                    }
                },
            }
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <div className="flex items-center gap-2 text-destructive">
                        <div className="flex size-10 items-center justify-center rounded-full bg-destructive/10">
                            <ShieldAlert className="size-5" />
                        </div>
                        <DialogTitle>Tolak Dokumen Legalitas</DialogTitle>
                    </div>
                    <DialogDescription className="pt-2 text-sm text-muted-foreground leading-relaxed">
                        Tolak pengajuan dokumen legalitas untuk{' '}
                        <strong className="text-foreground">{vendorName}</strong>. Berikan
                        alasan yang jelas dan objektif.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleReject} noValidate className="space-y-4 pt-2">
                    <div className="space-y-2">
                        <div className="flex items-center justify-between">
                            <Label htmlFor="alasan_penolakan" className="text-xs font-medium">
                                Alasan Penolakan <span className="text-destructive">*</span>
                            </Label>
                            <span className="text-[11px] text-muted-foreground">
                                {reason.length}/1000
                            </span>
                        </div>
                        <Textarea
                            id="alasan_penolakan"
                            value={reason}
                            onChange={(e) => {
                                setReason(e.target.value);
                                if (error) setError('');
                            }}
                            placeholder="Contoh: Dokumen scan SIUP/NIB buram dan nomor izin operasional telah kedaluwarsa."
                            rows={4}
                            aria-invalid={!!error}
                            className={cn(
                                'resize-none text-sm leading-relaxed',
                                error && 'border-destructive focus-visible:ring-destructive'
                            )}
                            disabled={loading}
                        />
                        {error && (
                            <p className="text-xs text-destructive font-medium">
                                {error}
                            </p>
                        )}
                    </div>

                    <DialogFooter className="gap-2 sm:gap-0 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={loading}
                        >
                            Batal
                        </Button>
                        <LoadingButton
                            type="submit"
                            variant="destructive"
                            loading={loading}
                            loadingText="Menolak..."
                        >
                            Tolak Dokumen
                        </LoadingButton>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
