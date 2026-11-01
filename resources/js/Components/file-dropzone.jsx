import { useCallback } from 'react';
import { useDropzone } from 'react-dropzone';
import {
    AlertCircle,
    CheckCircle2,
    FileText,
    Loader2,
    Replace,
    Trash2,
    UploadCloud,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { describeRejection, formatBytes, getFileExtension, isImageSource } from '@/lib/file';
import { useObjectUrl } from '@/hooks/use-object-url';
import { Button } from '@/Components/ui/button';
import { Progress } from '@/Components/ui/progress';

const DEFAULT_ACCEPT = {
    'application/pdf': ['.pdf'],
    'image/jpeg': ['.jpg', '.jpeg'],
    'image/png': ['.png'],
};

/**
 * Komponen Reusable FileDropzone berbasis react-dropzone (RULES.md Bagian 8.7).
 *
 * `value` bisa `File` (pilihan baru), `string` (URL file yang sudah tersimpan), atau `null`.
 * Feedback upload datang dari luar; untuk form Inertia pakai `useUploadFeedback(form, field)`:
 *
 * @param {boolean}  uploading  File sedang dikirim; tampilkan loading dan kunci aksi ganti/hapus.
 * @param {?number}  progress   Persentase 0-100; tanpa nilai tampil indeterminate.
 * @param {'idle'|'uploading'|'success'|'error'} status  Status upload terakhir.
 */
export function FileDropzone({
    value,
    onChange,
    onError,
    error,
    accept = DEFAULT_ACCEPT,
    maxSize = 2 * 1024 * 1024, // 2MB
    disabled = false,
    uploading = false,
    progress = null,
    status = 'idle',
    label = 'Unggah Dokumen',
    description = 'Format PDF, JPG, JPEG, atau PNG (Maksimal 2 MB)',
    className,
}) {
    const objectUrl = useObjectUrl(value instanceof File ? value : null);
    const previewUrl = value instanceof File ? objectUrl : value;
    const isImage = isImageSource(value);
    const isNewFile = value instanceof File;
    const locked = disabled || uploading;
    const hasError = Boolean(error) || status === 'error';

    const onDropAccepted = useCallback(
        (acceptedFiles) => {
            if (acceptedFiles.length > 0) {
                onChange?.(acceptedFiles[0]);
            }
        },
        [onChange]
    );

    const onDropRejected = useCallback(
        (fileRejections) => {
            if (fileRejections.length > 0) {
                onError?.(describeRejection(fileRejections[0], { accept, maxSize }));
            }
        },
        [accept, maxSize, onError]
    );

    const { getRootProps, getInputProps, isDragActive, isDragReject, open } = useDropzone({
        accept,
        maxSize,
        maxFiles: 1,
        multiple: false,
        disabled: locked,
        noClick: Boolean(value),
        noDrag: Boolean(value),
        noKeyboard: Boolean(value),
        onDropAccepted,
        onDropRejected,
    });

    const handleRemove = (e) => {
        e.stopPropagation();
        onChange?.(null);
    };

    const fileName = isNewFile ? value.name : 'Dokumen Terunggah';
    const extension = isNewFile ? getFileExtension(value.name) : getFileExtension(String(value ?? ''));
    const fileMeta = [extension, isNewFile ? formatBytes(value.size) : 'File tersimpan'].filter(Boolean).join(' • ');

    return (
        <div className={cn('w-full min-w-0 space-y-2', className)}>
            <input {...getInputProps()} />

            {value ? (
                <div
                    className={cn(
                        'rounded-xl border bg-card/70 overflow-hidden transition-colors',
                        hasError ? 'border-destructive bg-destructive/5' : 'border-border'
                    )}
                >
                    {isImage && previewUrl && (
                        <div className="relative flex items-center justify-center bg-muted/40 p-2">
                            <img
                                src={previewUrl}
                                alt={`Pratinjau ${fileName}`}
                                className={cn(
                                    'max-h-64 w-full rounded-lg object-contain transition-opacity',
                                    uploading && 'opacity-60'
                                )}
                            />
                            {uploading && (
                                <div className="absolute inset-0 flex items-center justify-center">
                                    <Loader2 className="size-7 animate-spin text-primary" />
                                </div>
                            )}
                        </div>
                    )}

                    <div className="flex items-center justify-between gap-3 p-3.5">
                        <div className="flex flex-1 items-center gap-3 min-w-0">
                            {!isImage && (
                                <div className="size-11 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 flex items-center justify-center shrink-0">
                                    {uploading ? <Loader2 className="size-5 animate-spin" /> : <FileText className="size-5" />}
                                </div>
                            )}
                            <div className="min-w-0 flex-1">
                                <p className="text-sm font-medium text-foreground truncate" title={fileName}>
                                    {fileName}
                                </p>
                                <p className="text-xs text-muted-foreground">{fileMeta}</p>
                            </div>
                        </div>

                        {!locked && (
                            <div className="flex items-center gap-1 shrink-0">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    onClick={open}
                                    className="text-muted-foreground hover:text-foreground"
                                    title="Ganti file"
                                >
                                    <Replace className="size-4" />
                                    <span className="sr-only">Ganti file</span>
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    onClick={handleRemove}
                                    className="text-muted-foreground hover:text-destructive"
                                    title="Hapus file"
                                >
                                    <Trash2 className="size-4" />
                                    <span className="sr-only">Hapus file</span>
                                </Button>
                            </div>
                        )}
                    </div>

                    {(uploading || status === 'success') && (
                        <div className="px-3.5 pb-3.5 space-y-1.5" role="status" aria-live="polite">
                            {uploading ? (
                                <>
                                    <Progress value={typeof progress === 'number' ? progress : undefined} />
                                    <p className="text-xs text-muted-foreground flex items-center justify-between gap-2">
                                        <span>Sedang mengunggah file...</span>
                                        {typeof progress === 'number' && (
                                            <span className="font-medium tabular-nums">{progress}%</span>
                                        )}
                                    </p>
                                </>
                            ) : (
                                <p className="text-xs font-medium text-emerald-600 flex items-center gap-1.5">
                                    <CheckCircle2 className="size-3.5" />
                                    File berhasil diunggah.
                                </p>
                            )}
                        </div>
                    )}
                </div>
            ) : (
                <div
                    {...getRootProps()}
                    aria-invalid={hasError}
                    className={cn(
                        'flex flex-col items-center justify-center p-6 border-2 border-dashed rounded-xl cursor-pointer text-center transition-all duration-150',
                        isDragActive && !isDragReject && 'border-primary bg-primary/5 scale-[0.99]',
                        isDragReject && 'border-destructive bg-destructive/5',
                        hasError && 'border-destructive focus-visible:ring-destructive bg-destructive/5',
                        !hasError && !isDragActive && 'border-border hover:border-primary/60 hover:bg-muted/30',
                        locked && 'opacity-60 cursor-not-allowed pointer-events-none'
                    )}
                >
                    <div className="p-3 rounded-full bg-primary/10 text-primary mb-3">
                        <UploadCloud className="size-6" />
                    </div>
                    <p className="text-sm font-medium text-foreground">
                        {isDragActive ? 'Lepaskan file di sini...' : label}
                    </p>
                    <p className="text-xs text-muted-foreground mt-1">
                        Klik untuk memilih atau seret & lepas file di sini
                    </p>
                    <p className="text-xs text-muted-foreground mt-1 max-w-xs">{description}</p>
                </div>
            )}

            {error && (
                <p className="text-xs text-destructive flex items-center gap-1.5 mt-1" role="alert">
                    <AlertCircle className="size-3.5 shrink-0" />
                    <span>{error}</span>
                </p>
            )}
        </div>
    );
}

export default FileDropzone;
