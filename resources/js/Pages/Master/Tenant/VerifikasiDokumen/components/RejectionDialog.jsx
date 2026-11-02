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

export function RejectionDialog({ tenant, open, onOpenChange }) {
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

    if (!tenant) return null;

    const companyName = tenant.company_name ?? 'Tenant';

    const handleReject = (e) => {
        e.preventDefault();

        const trimmed = reason.trim();
        if (!trimmed) {
            setError('Alasan penolakan wajib diisi agar pendaftar mengetahui poin yang perlu diperbaiki.');
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
            `/master/tenant/verifikasi-dokumen/${tenant.uuid}/verifikasi`,
            {
                aksi: 'reject',
                rejection_reason: trimmed,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setLoading(false);
                    onOpenChange(false);
                    notify.success(`Pendaftaran tenant ${companyName} berhasil ditolak.`);
                },
                onError: (errors) => {
                    setLoading(false);
                    if (errors.rejection_reason) {
                        setError(errors.rejection_reason);
                        notify.validationFailed();
                    } else {
                        notify.error(
                            errors.aksi ||
                            errors.verification_status ||
                            'Gagal memproses penolakan pendaftaran tenant.'
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
                        <DialogTitle>Tolak Pendaftaran Tenant</DialogTitle>
                    </div>
                    <DialogDescription className="pt-2 text-sm text-muted-foreground leading-relaxed">
                        Tolak pendaftaran tenant untuk{' '}
                        <strong className="text-foreground">{companyName}</strong>. Berikan
                        alasan yang jelas dan objektif mengenai ketidaksesuaian dokumen atau data legalitas.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleReject} noValidate className="space-y-4 pt-2">
                    <div className="space-y-2">
                        <div className="flex items-center justify-between">
                            <Label htmlFor="rejection_reason" className="text-xs font-medium">
                                Alasan Penolakan <span className="text-destructive">*</span>
                            </Label>
                            <span className="text-[11px] text-muted-foreground">
                                {reason.length}/1000
                            </span>
                        </div>
                        <Textarea
                            id="rejection_reason"
                            value={reason}
                            onChange={(e) => {
                                setReason(e.target.value);
                                if (error) setError('');
                            }}
                            placeholder="Contoh: Dokumen legalitas tidak valid atau nomor NPWP tidak terdaftar pada basis data DJP."
                            rows={4}
                            aria-invalid={!!error}
                            className={cn(error && 'border-destructive focus-visible:ring-destructive')}
                        />
                        {error && (
                            <p className="text-xs text-destructive">{error}</p>
                        )}
                    </div>

                    <DialogFooter className="mt-4 gap-2">
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
                            Ya, Tolak Pendaftaran
                        </LoadingButton>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

