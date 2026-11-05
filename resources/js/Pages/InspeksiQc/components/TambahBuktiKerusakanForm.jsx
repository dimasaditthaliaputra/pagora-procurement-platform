import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Camera, FilePlus2, ShieldAlert } from 'lucide-react';
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

const PHOTO_ACCEPT = {
    'image/jpeg': ['.jpg', '.jpeg'],
    'image/png': ['.png'],
    'image/webp': ['.webp'],
};

const MAX_PHOTO_SIZE = 5 * 1024 * 1024; // 5 MB (PRD BR-10, RULES §7 & §8.7)
const MAX_PHOTOS = 5;

export function TambahBuktiKerusakanForm({ itemUuid, commodityName, poNumber }) {
    const [confirmOpen, setConfirmOpen] = useState(false);

    const { data, setData, post, processing, progress, errors, setError, clearErrors, reset } =
        useForm({
            message: '',
            photos: [],
        });

    const handleAddPhoto = (file) => {
        if (!file) return;
        setData('photos', [...data.photos, file].slice(0, MAX_PHOTOS));
        clearErrors('photos');
    };

    const handleReplaceOrRemovePhoto = (photoIndex, nextFile) => {
        const nextPhotos = [...data.photos];
        if (nextFile instanceof File) {
            nextPhotos[photoIndex] = nextFile;
        } else {
            nextPhotos.splice(photoIndex, 1);
        }
        setData('photos', nextPhotos);
        clearErrors('photos', `photos.${photoIndex}`);
    };

    const getPhotoError = () => {
        if (errors.photos) return errors.photos;
        const nestedKey = Object.keys(errors).find((key) => key.startsWith('photos.'));
        return nestedKey ? errors[nestedKey] : undefined;
    };

    const validateClientSide = () => {
        const trimmedMessage = (data.message || '').trim();
        if (trimmedMessage.length < 5) {
            clearErrors();
            setError(
                'message',
                trimmedMessage.length === 0
                    ? 'Catatan rincian tambahan kerusakan barang wajib diisi.'
                    : 'Catatan tambahan kerusakan minimal 5 karakter.'
            );
            notify.validationFailed();
            return false;
        }

        return true;
    };

    const handleOpenConfirm = (e) => {
        e.preventDefault();
        if (!validateClientSide()) {
            return;
        }
        setConfirmOpen(true);
    };

    const handleConfirmSubmit = () => {
        post(`/inspeksi-qc/laporan-kerusakan/${itemUuid}/bukti`, {
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

    const photoError = getPhotoError();

    return (
        <>
            <Card className="shadow-sm border-primary/20">
                <CardHeader className="border-b">
                    <CardTitle className="text-base font-semibold flex items-center gap-2">
                        <FilePlus2 className="size-4 text-primary" />
                        Tambahkan Catatan / Foto Bukti Kerusakan Lanjutan
                    </CardTitle>
                    <CardDescription>
                        Tambahkan temuan lanjutan atau dokumentasi foto tambahan ke Log Sengketa secara{' '}
                        <strong>append-only (immutable)</strong> tanpa mengubah rekaman inspeksi awal.
                    </CardDescription>
                </CardHeader>

                <CardContent className="p-5">
                    <form onSubmit={handleOpenConfirm} noValidate className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="additional_damage_message">
                                Rincian Temuan / Catatan Tambahan Kerusakan *
                            </Label>
                            <Textarea
                                id="additional_damage_message"
                                rows={3}
                                placeholder="Contoh: Hasil penimbangan ulang di gudang menunjukkan 4 karung bagian bawah juga mengalami kebocoran dan kontaminasi air..."
                                value={data.message}
                                disabled={processing}
                                onChange={(e) => {
                                    setData('message', e.target.value);
                                    clearErrors('message');
                                }}
                                aria-invalid={Boolean(errors.message)}
                                className={cn(
                                    errors.message &&
                                        'border-destructive focus-visible:ring-destructive'
                                )}
                            />
                            {errors.message && (
                                <p className="text-sm text-destructive">{errors.message}</p>
                            )}
                        </div>

                        <div className="space-y-3">
                            <div className="flex items-center justify-between gap-2">
                                <Label className="flex items-center gap-1.5">
                                    <Camera className="size-4 text-muted-foreground" />
                                    Foto Bukti Tambahan (Opsional, maks. {MAX_PHOTOS} foto)
                                </Label>
                                <span className="text-xs text-muted-foreground">
                                    {data.photos.length}/{MAX_PHOTOS} Foto
                                </span>
                            </div>

                            {data.photos.length > 0 && (
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    {data.photos.map((photoFile, idx) => (
                                        <FileDropzone
                                            key={`extra-damage-photo-${idx}`}
                                            value={photoFile}
                                            accept={PHOTO_ACCEPT}
                                            maxSize={MAX_PHOTO_SIZE}
                                            disabled={processing}
                                            uploading={processing}
                                            progress={progress?.percentage ?? null}
                                            error={errors[`photos.${idx}`]}
                                            onChange={(nextFile) =>
                                                handleReplaceOrRemovePhoto(idx, nextFile)
                                            }
                                            onError={(msg) => {
                                                setError('photos', msg);
                                                notify.validationFailed();
                                            }}
                                        />
                                    ))}
                                </div>
                            )}

                            {data.photos.length < MAX_PHOTOS && (
                                <FileDropzone
                                    value={null}
                                    accept={PHOTO_ACCEPT}
                                    maxSize={MAX_PHOTO_SIZE}
                                    disabled={processing}
                                    uploading={processing}
                                    progress={progress?.percentage ?? null}
                                    label={
                                        data.photos.length === 0
                                            ? 'Unggah Foto Bukti Kerusakan Tambahan'
                                            : 'Tambah Foto Bukti Lainnya'
                                    }
                                    description="Format JPG, JPEG, PNG, atau WEBP (Maksimal 5 MB per foto)"
                                    error={data.photos.length === 0 ? photoError : undefined}
                                    onChange={handleAddPhoto}
                                    onError={(msg) => {
                                        setError('photos', msg);
                                        notify.validationFailed();
                                    }}
                                />
                            )}

                            {data.photos.length > 0 && photoError && (
                                <p className="text-sm text-destructive">{photoError}</p>
                            )}
                        </div>

                        <div className="flex justify-end pt-2">
                            <LoadingButton
                                type="submit"
                                loading={processing}
                                loadingText="Menyimpan ke Log Sengketa..."
                                className="min-h-10 gap-2 cursor-pointer"
                            >
                                <ShieldAlert className="size-4" />
                                Simpan Bukti Tambahan
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
                            <ShieldAlert className="size-5 shrink-0 mt-0.5 text-primary" />
                            <span>Konfirmasi Tambah Bukti Kerusakan ke Log Sengketa</span>
                        </AlertDialogTitle>
                        <AlertDialogDescription asChild>
                            <div className="space-y-2 text-sm text-muted-foreground">
                                <p>
                                    Catatan dan lampiran foto untuk komoditas{' '}
                                    <strong className="text-foreground">{commodityName}</strong> (
                                    <span className="font-mono text-foreground">{poNumber}</span>)
                                    akan dicatat sebagai entri baru yang{' '}
                                    <strong className="text-foreground">
                                        permanen (immutable)
                                    </strong>{' '}
                                    pada Log Sengketa dan diberitahukan ke pihak terkait.
                                </p>
                            </div>
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={processing} className="cursor-pointer">
                            Batal
                        </AlertDialogCancel>
                        <LoadingButton
                            type="button"
                            loading={processing}
                            loadingText="Menyimpan..."
                            onClick={handleConfirmSubmit}
                            className="cursor-pointer"
                        >
                            Ya, Catat ke Log Sengketa
                        </LoadingButton>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}

export default TambahBuktiKerusakanForm;

