import { useState } from 'react';
import { router } from '@inertiajs/react';
import { notify } from '@/lib/notify';
import { cn } from '@/lib/utils';
import { LoadingButton } from '@/Components/loading-button';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';

/**
 * Confirmation for a Vendor decision on a settlement (UC-18 step 5, 5a). Refusing needs a reason.
 * `decision` is `accept` or `reject`; `url` is the decision endpoint.
 */
export function SettlementDecisionDialog({ open, onOpenChange, url, decision, title, description }) {
    const [reason, setReason] = useState('');
    const [error, setError] = useState(null);
    const [processing, setProcessing] = useState(false);
    const rejecting = decision === 'reject';

    const close = (value) => {
        if (processing) return;
        onOpenChange(value);
        if (!value) {
            setReason('');
            setError(null);
        }
    };

    const submit = () => {
        if (rejecting && reason.trim().length < 5) {
            setError(reason.trim() === '' ? 'Alasan penolakan wajib diisi.' : 'Alasan penolakan minimal 5 karakter.');
            notify.validationFailed();
            return;
        }

        router.post(
            url,
            { decision, reason: rejecting ? reason.trim() : null },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => {
                    onOpenChange(false);
                    setReason('');
                    setError(null);
                },
                onError: (errors) => {
                    setError(errors.reason ?? null);
                    notify.error(errors.resolution ?? errors.reason ?? 'Keputusan belum dapat diproses.');
                },
            }
        );
    };

    return (
        <AlertDialog open={open} onOpenChange={close}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    <AlertDialogDescription>{description}</AlertDialogDescription>
                </AlertDialogHeader>

                {rejecting && (
                    <div className="space-y-2">
                        <Label htmlFor="settlement_reject_reason">Alasan Penolakan *</Label>
                        <Textarea
                            id="settlement_reject_reason"
                            rows={3}
                            value={reason}
                            disabled={processing}
                            placeholder="Jelaskan alasan Anda menolak penyelesaian ini..."
                            onChange={(e) => {
                                setReason(e.target.value);
                                setError(null);
                            }}
                            aria-invalid={Boolean(error)}
                            className={cn(error && 'border-destructive focus-visible:ring-destructive')}
                        />
                        {error && <p className="text-sm text-destructive">{error}</p>}
                    </div>
                )}

                <AlertDialogFooter>
                    <AlertDialogCancel disabled={processing} className="cursor-pointer">
                        Batal
                    </AlertDialogCancel>
                    <LoadingButton
                        type="button"
                        variant={rejecting ? 'destructive' : 'default'}
                        loading={processing}
                        loadingText="Memproses..."
                        onClick={submit}
                        className="cursor-pointer"
                    >
                        {rejecting ? 'Ya, Tolak' : 'Ya, Setujui'}
                    </LoadingButton>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

export default SettlementDecisionDialog;
