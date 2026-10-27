import { useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { cn } from '@/lib/utils';
import { notify } from '@/lib/notify';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { FileDropzone } from '@/Components/file-dropzone';
import { LoadingButton } from '@/Components/loading-button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';
import { PengaturanLayout } from '@/Pages/Pengaturan/components/PengaturanLayout';

function Field({ id, label, required = false, error, className, children }) {
    return (
        <div className={cn('grid gap-1.5', className)}>
            <Label htmlFor={id} className={cn('text-sm', error && 'text-destructive')}>
                {label} {required && <span className="text-destructive">*</span>}
            </Label>
            {children}
            {error && <p className="text-xs font-medium text-destructive">{error}</p>}
        </div>
    );
}

export default function PengaturanProfil({ pengaturan = {} }) {
    const { data, setData, post, processing, errors, setError, clearErrors } = useForm({
        nama_aplikasi: pengaturan.nama_aplikasi ?? '',
        tagline: pengaturan.tagline ?? '',
        logo: null,
        hapus_logo: false,
    });

    const logoPreview = data.logo ?? (data.hapus_logo ? null : pengaturan.logo_url);

    const bind = (field) => ({
        id: field,
        value: data[field],
        onChange: (e) => {
            setData(field, e.target.value);
            if (errors[field]) clearErrors(field);
        },
        'aria-invalid': Boolean(errors[field]),
        className: cn('h-9', errors[field] && 'border-destructive focus-visible:ring-destructive'),
    });

    const handleSubmit = (e) => {
        e.preventDefault();

        if (!data.nama_aplikasi.trim()) {
            setError('nama_aplikasi', 'Nama aplikasi wajib diisi.');
            notify.validationFailed();
            return;
        }

        post('/pengaturan/profil', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => setData((prev) => ({ ...prev, logo: null, hapus_logo: false })),
            onError: () => notify.validationFailed(),
        });
    };

    return (
        <PengaturanLayout title="Profil Aplikasi">
            <form noValidate onSubmit={handleSubmit} className="w-full">
                <Card className="w-full shadow-sm flex flex-col">
                    <CardHeader className="py-3.5 px-5 border-b">
                        <CardTitle className="text-base font-semibold">Identitas Aplikasi</CardTitle>
                        <CardDescription className="text-xs">
                            Nama, tagline, dan logo tampil di sidebar serta halaman aplikasi.
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="p-5 space-y-4">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <Field id="nama_aplikasi" label="Nama Aplikasi" required error={errors.nama_aplikasi}>
                                <Input placeholder="Contoh: PAGORA" {...bind('nama_aplikasi')} />
                            </Field>
                            <Field id="tagline" label="Tagline" error={errors.tagline}>
                                <Input placeholder="Contoh: E-Procurement" {...bind('tagline')} />
                            </Field>
                        </div>

                        <Field id="logo" label="Logo Aplikasi" error={errors.logo}>
                            <FileDropzone
                                value={logoPreview}
                                onChange={(file) => {
                                    setData((prev) => ({
                                        ...prev,
                                        logo: file,
                                        hapus_logo: file === null && Boolean(pengaturan.logo_url),
                                    }));
                                    if (errors.logo) clearErrors('logo');
                                }}
                                onError={(message) => setError('logo', message)}
                                error={errors.logo}
                                accept={{
                                    'image/png': ['.png'],
                                    'image/jpeg': ['.jpg', '.jpeg'],
                                    'image/webp': ['.webp'],
                                }}
                                label="Unggah Logo"
                                description="Format PNG, JPG, JPEG, atau WEBP (Maksimal 2 MB). Disarankan rasio persegi."
                                disabled={processing}
                            />
                        </Field>
                    </CardContent>

                    <CardFooter className="py-3 px-5 border-t bg-muted/20 flex justify-end">
                        <LoadingButton
                            type="submit"
                            loading={processing}
                            loadingText="Menyimpan Perubahan..."
                            className="gap-2 cursor-pointer shadow-sm h-9"
                        >
                            <Save className="size-4" />
                            Simpan Perubahan
                        </LoadingButton>
                    </CardFooter>
                </Card>
            </form>
        </PengaturanLayout>
    );
}
