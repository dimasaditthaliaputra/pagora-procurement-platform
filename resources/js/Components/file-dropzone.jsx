import { useState, useEffect, useCallback } from 'react';
import { useDropzone } from 'react-dropzone';
import { UploadCloud, FileText, Image as ImageIcon, X, AlertCircle } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from '@/Components/ui/button';

/**
 * Komponen Reusable FileDropzone berbasis react-dropzone
 * Sesuai aturan RULES.md Bagian 8.7
 */
export function FileDropzone({
    value,
    onChange,
    onError,
    error,
    accept = {
        'application/pdf': ['.pdf'],
        'image/jpeg': ['.jpg', '.jpeg'],
        'image/png': ['.png'],
    },
    maxSize = 2 * 1024 * 1024, // 2MB
    maxFiles = 1,
    disabled = false,
    label = 'Unggah Dokumen',
    description = 'Format PDF, JPG, JPEG, atau PNG (Maksimal 2 MB)',
    className,
}) {
    const [previewUrl, setPreviewUrl] = useState(null);

    // Format ukuran file agar mudah dibaca
    const formatBytes = (bytes) => {
        if (!bytes || bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`;
    };

    // Buat URL preview saat ada file gambar dan kelola revokeObjectURL
    useEffect(() => {
        if (value instanceof File && value.type.startsWith('image/')) {
            const objectUrl = URL.createObjectURL(value);
            setPreviewUrl(objectUrl);
            return () => {
                URL.revokeObjectURL(objectUrl);
            };
        } else if (typeof value === 'string' && value.length > 0) {
            setPreviewUrl(value);
        } else {
            setPreviewUrl(null);
        }
    }, [value]);

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
                const rejection = fileRejections[0];
                let errorMsg = 'File tidak dapat diterima.';
                if (rejection.errors.some((e) => e.code === 'file-too-large')) {
                    errorMsg = `Ukuran file terlalu besar. Maksimal ${formatBytes(maxSize)}.`;
                } else if (rejection.errors.some((e) => e.code === 'file-invalid-type')) {
                    errorMsg = 'Format file tidak didukung. Harap unggah PDF, JPG, atau PNG.';
                }
                onError?.(errorMsg);
            }
        },
        [maxSize, onError]
    );

    const handleRemove = (e) => {
        e.stopPropagation();
        if (previewUrl && value instanceof File) {
            URL.revokeObjectURL(previewUrl);
        }
        setPreviewUrl(null);
        onChange?.(null);
    };

    const { getRootProps, getInputProps, isDragActive, isDragReject } = useDropzone({
        accept,
        maxSize,
        maxFiles,
        multiple: false,
        disabled,
        onDropAccepted,
        onDropRejected,
    });

    const isImage = value instanceof File
        ? value.type.startsWith('image/')
        : typeof value === 'string' && /\.(jpg|jpeg|png|webp)$/i.test(value);

    return (
        <div className={cn('space-y-2', className)}>
            {/* Area Dropzone / Preview */}
            {value ? (
                <div
                    className={cn(
                        'flex items-center justify-between p-3.5 rounded-xl border bg-card/70 transition-colors',
                        error ? 'border-destructive bg-destructive/5' : 'border-border'
                    )}
                >
                    <div className="flex items-center gap-3 min-w-0">
                        {isImage && previewUrl ? (
                            <img
                                src={previewUrl}
                                alt="Preview"
                                className="size-11 rounded-lg object-cover border border-border/70 shrink-0"
                            />
                        ) : (
                            <div className="size-11 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 flex items-center justify-center shrink-0">
                                <FileText className="size-5" />
                            </div>
                        )}
                        <div className="min-w-0">
                            <p className="text-sm font-medium text-foreground truncate">
                                {value instanceof File ? value.name : 'Dokumen Terunggah'}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {value instanceof File ? formatBytes(value.size) : 'File tersimpan'}
                            </p>
                        </div>
                    </div>
                    {!disabled && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            onClick={handleRemove}
                            className="text-muted-foreground hover:text-destructive shrink-0"
                            title="Hapus file"
                        >
                            <X className="size-4" />
                            <span className="sr-only">Hapus file</span>
                        </Button>
                    )}
                </div>
            ) : (
                <div
                    {...getRootProps()}
                    aria-invalid={!!error}
                    className={cn(
                        'flex flex-col items-center justify-center p-6 border-2 border-dashed rounded-xl cursor-pointer text-center transition-all duration-150',
                        isDragActive && !isDragReject && 'border-primary bg-primary/5 scale-[0.99]',
                        isDragReject && 'border-destructive bg-destructive/5',
                        error && 'border-destructive focus-visible:ring-destructive bg-destructive/5',
                        !error && !isDragActive && 'border-border hover:border-primary/60 hover:bg-muted/30',
                        disabled && 'opacity-60 cursor-not-allowed pointer-events-none'
                    )}
                >
                    <input {...getInputProps()} />
                    <div className="p-3 rounded-full bg-primary/10 text-primary mb-3">
                        <UploadCloud className="size-6" />
                    </div>
                    <p className="text-sm font-medium text-foreground">
                        {isDragActive
                            ? 'Lepaskan file dokumen di sini...'
                            : 'Klik untuk memilih atau seret & lepas file di sini'}
                    </p>
                    <p className="text-xs text-muted-foreground mt-1 max-w-xs">{description}</p>
                </div>
            )}

            {/* Pesan Error */}
            {error && (
                <p className="text-xs text-destructive flex items-center gap-1.5 mt-1">
                    <AlertCircle className="size-3.5 shrink-0" />
                    <span>{error}</span>
                </p>
            )}
        </div>
    );
}

export default FileDropzone;
