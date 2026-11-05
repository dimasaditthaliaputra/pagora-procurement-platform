import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { MessageSquarePlus } from 'lucide-react';
import { cn } from '@/lib/utils';
import { notify } from '@/lib/notify';
import { FileDropzone } from '@/Components/file-dropzone';
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
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';

const ATTACHMENT_ACCEPT = {
    'image/jpeg': ['.jpg', '.jpeg'],
    'image/png': ['.png'],
    'image/webp': ['.webp'],
    'application/pdf': ['.pdf'],
};

const MAX_ATTACHMENT_SIZE = 5 * 1024 * 1024;

export function TinjauanSengketaForm({ disputeUuid, poNumber, isOpenStatus }) {
    const [confirmOpen, setConfirmOpen] = useState(false);

    const { data, setData, post, processing, progress, errors, setError, clearErrors, reset } = useForm({
        message: '',
        attachment: null,
    });

    const handleOpenConfirm = (e) => {
        e.preventDefault();
        const trimmed = (data.message || '').trim();

        if (trimmed.length < 5) {
            clearErrors();
            setError(
                'message',
                trimmed.length === 0
                    ? 'Catatan tinjauan atau mediasi sengketa wajib diisi.'
                    : 'Catatan tinjauan minimal 5 karakter.'
            );
            notify.validationFailed();
            return;
        }

        setConfirmOpen(true);
    };

    const handleConfirmSubmit = () => {
        post(`/log-sengketa/${disputeUuid}/tinjauan`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setConfirmOpen(false);
                reset();
                clearErrors();
            },
            onError: () => {
                setConfirmOpen(false);
                notify.validationFailed();
            },
        });
    };

    return (
        <>
            <Card className="shadow-sm border-primary/20">
                <CardHeader className="border-b">
                    <CardTitle className="text-base font-semibold flex items-center gap-2">
                        <MessageSquarePlus className="size-4 text-primary" />
                        Catat Tinjauan / Mediasi
                    </CardTitle>
                    <CardDescription>
                        {isOpenStatus
                            ? 'Catatan pertama akan mengubah status sengketa menjadi Dalam Mediasi.'
                            : 'Catatan ditambahkan ke Log Sengketa secara permanen (tidak dapat diubah atau dihapus).'}
                    </CardDescription>
                </CardHeader>
                <CardContent className="p-5">
                    <form onSubmit={handleOpenConfirm} noValidate className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="review_message">Catatan Tinjauan *</Label>
                            <Textarea
                                id="review_message"
                                rows={4}
                                placeholder="Contoh: Telah dikonfirmasi ke vendor via telepon, vendor bersedia mengirim ulang 4 karung beras yang rusak..."
                                value={data.message}
                                disabled={processing}
                                onChange={(e) => {
                                    setData('message', e.target.value);
                                    clearErrors('message');
                                }}
                                aria-invalid={Boolean(errors.message)}
                                className={cn(errors.message && 'border-destructive focus-visible:ring-destructive')}
                            />
                            {errors.message && <p className="text-sm text-destructive">{errors.message}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label>Lampiran Bukti (Opsional)</Label>
                            <FileDropzone
                                value={data.attachment}
                                accept={ATTACHMENT_ACCEPT}
                                maxSize={MAX_ATTACHMENT_SIZE}
                                disabled={processing}
                                uploading={processing}
                                progress={progress?.percentage ?? null}
                                label="Unggah Lampiran Bukti"
                                description="JPG, PNG, WEBP, atau PDF (maksimal 5 MB)"
                                error={errors.attachment}
                                onChange={(file) => {
                                    setData('attachment', file instanceof File ? file : null);
                                    clearErrors('attachment');
                                }}
                                onError={(msg) => {
                                    setError('attachment', msg);
                                    notify.validationFailed();
                                }}
                            />
                        </div>

                        <div className="flex justify-end">
                            <LoadingButton
                                type="submit"
                                loading={processing}
                                loadingText="Menyimpan..."
                                className="gap-2 cursor-pointer"
                            >
                                <MessageSquarePlus className="size-4" />
                                Simpan Catatan
                            </LoadingButton>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <AlertDialog
                open={confirmOpen}
                onOpenChange={(val) => {
                    if (!processing) setConfirmOpen(val);
                }}
            >
                <AlertDialogContent size="lg">
                    <AlertDialogHeader>
                        <AlertDialogTitle className="flex items-start gap-2.5 leading-snug">
                            <MessageSquarePlus className="size-5 shrink-0 mt-0.5 text-primary" />
                            <span>Simpan Catatan Tinjauan Sengketa?</span>
                        </AlertDialogTitle>
                        <AlertDialogDescription asChild>
                            <div className="space-y-2 text-sm text-muted-foreground">
                                <p>
                                    Catatan untuk sengketa{' '}
                                    <strong className="font-mono text-foreground">{poNumber}</strong> akan dicatat
                                    secara <strong className="text-foreground">permanen (immutable)</strong> pada
                                    Log Sengketa dan diberitahukan ke pihak terkait.
                                </p>
                                {isOpenStatus && (
                                    <p>
                                        Status sengketa akan berubah dari <strong className="text-foreground">Terbuka</strong>{' '}
                                        menjadi <strong className="text-foreground">Dalam Mediasi</strong>.
                                    </p>
                                )}
                            </div>
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={processing} className="cursor-pointer">
                            Periksa Kembali
                        </AlertDialogCancel>
                        <LoadingButton
                            type="button"
                            loading={processing}
                            loadingText="Menyimpan..."
                            onClick={handleConfirmSubmit}
                            className="cursor-pointer"
                        >
                            Ya, Simpan Catatan
                        </LoadingButton>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}

export default TinjauanSengketaForm;

