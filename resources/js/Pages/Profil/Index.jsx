import { Head, useForm } from '@inertiajs/react';
import { UserCircle, Save, KeyRound } from 'lucide-react';
import { cn } from '@/lib/utils';
import { notify } from '@/lib/notify';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Badge } from '@/Components/ui/badge';
import { FileDropzone } from '@/Components/file-dropzone';
import { LoadingButton } from '@/Components/loading-button';
import { PasswordInput } from '@/Components/password-input';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';

const PASSWORD_MIN = 8;

function Field({ id, label, required = false, hint, error, className, children }) {
    return (
        <div className={cn('grid gap-1.5', className)}>
            <Label htmlFor={id} className={cn('text-sm', error && 'text-destructive')}>
                {label} {required && <span className="text-destructive">*</span>}
            </Label>
            {children}
            {error ? (
                <p id={`${id}-error`} className="text-xs font-medium text-destructive">{error}</p>
            ) : (
                hint && <p className="text-xs text-muted-foreground">{hint}</p>
            )}
        </div>
    );
}

function ProfilForm({ profil }) {
    const { data, setData, post, processing, errors, setError, clearErrors } = useForm({
        _method: 'put',
        nama_lengkap: profil.nama_lengkap ?? '',
        avatar: null,
        hapus_avatar: false,
    });

    const avatarPreview = data.avatar ?? (data.hapus_avatar ? null : profil.avatar_url);

    const handleSubmit = (e) => {
        e.preventDefault();

        if (!data.nama_lengkap.trim()) {
            setError('nama_lengkap', 'Nama lengkap wajib diisi.');
            document.getElementById('nama_lengkap')?.focus();
            notify.validationFailed();
            return;
        }

        // Multipart PUT is not parsed by PHP, so spoof the method over POST.
        post('/profil', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => setData((prev) => ({ ...prev, avatar: null, hapus_avatar: false })),
            onError: () => notify.validationFailed(),
        });
    };

    return (
        <form noValidate onSubmit={handleSubmit} className="w-full">
            <Card className="w-full shadow-sm flex flex-col">
                <CardHeader className="py-3.5 px-5 border-b">
                    <CardTitle className="text-base font-semibold">Informasi Profil</CardTitle>
                    <CardDescription className="text-xs">
                        Nama dan foto profil tampil di sidebar dan riwayat aktivitas.
                    </CardDescription>
                </CardHeader>

                <CardContent className="p-5 grid grid-cols-1 md:grid-cols-3 gap-5">
                    <Field id="avatar" label="Foto Profil" error={errors.avatar}>
                        <FileDropzone
                            value={avatarPreview}
                            onChange={(file) => {
                                setData((prev) => ({
                                    ...prev,
                                    avatar: file,
                                    hapus_avatar: file === null && Boolean(profil.avatar_url),
                                }));
                                if (errors.avatar) clearErrors('avatar');
                            }}
                            onError={(message) => {
                                setError('avatar', message);
                                notify.validationFailed();
                            }}
                            error={errors.avatar}
                            accept={{
                                'image/png': ['.png'],
                                'image/jpeg': ['.jpg', '.jpeg'],
                                'image/webp': ['.webp'],
                            }}
                            label="Unggah Foto"
                            description="PNG, JPG, JPEG, atau WEBP (Maks. 2 MB)"
                            disabled={processing}
                        />
                    </Field>

                    <div className="md:col-span-2 space-y-4">
                        <Field id="nama_lengkap" label="Nama Lengkap" required error={errors.nama_lengkap}>
                            <Input
                                id="nama_lengkap"
                                value={data.nama_lengkap}
                                onChange={(e) => {
                                    setData('nama_lengkap', e.target.value);
                                    if (errors.nama_lengkap) clearErrors('nama_lengkap');
                                }}
                                aria-invalid={Boolean(errors.nama_lengkap)}
                                disabled={processing}
                                className={cn('h-9', errors.nama_lengkap && 'border-destructive focus-visible:ring-destructive')}
                            />
                        </Field>

                        <Field id="email" label="Email" hint="Email dipakai untuk login. Hubungi superadmin untuk mengubahnya.">
                            <Input id="email" value={profil.email ?? ''} readOnly disabled className="h-9" />
                        </Field>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div className="grid gap-1.5">
                                <span className="text-sm font-medium">Role</span>
                                <div>
                                    <Badge variant="secondary">{profil.role}</Badge>
                                </div>
                            </div>
                            {profil.organisasi && (
                                <div className="grid gap-1.5">
                                    <span className="text-sm font-medium">Perusahaan</span>
                                    <span className="text-sm text-muted-foreground">{profil.organisasi}</span>
                                </div>
                            )}
                        </div>
                    </div>
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
    );
}

function PasswordForm({ punyaPassword }) {
    const { data, setData, put, processing, errors, setError, clearErrors, reset } = useForm({
        password_saat_ini: '',
        password: '',
        password_confirmation: '',
    });

    const change = (field) => (e) => {
        setData(field, e.target.value);
        if (errors[field]) clearErrors(field);
    };

    const handleSubmit = (e) => {
        e.preventDefault();

        const failed = [];
        if (punyaPassword && !data.password_saat_ini) {
            setError('password_saat_ini', 'Kata sandi saat ini wajib diisi.');
            failed.push('password_saat_ini');
        }
        if (!data.password) {
            setError('password', 'Kata sandi baru wajib diisi.');
            failed.push('password');
        } else if (data.password.length < PASSWORD_MIN) {
            setError('password', `Kata sandi baru minimal ${PASSWORD_MIN} karakter.`);
            failed.push('password');
        }
        if (data.password && data.password !== data.password_confirmation) {
            setError('password_confirmation', 'Konfirmasi kata sandi baru tidak cocok.');
            failed.push('password_confirmation');
        }

        if (failed.length > 0) {
            document.getElementById(failed[0])?.focus();
            notify.validationFailed();
            return;
        }

        put('/profil/password', {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: () => {
                reset('password', 'password_confirmation');
                notify.validationFailed();
            },
        });
    };

    return (
        <form noValidate onSubmit={handleSubmit} className="w-full">
            <Card className="w-full shadow-sm flex flex-col">
                <CardHeader className="py-3.5 px-5 border-b">
                    <CardTitle className="text-base font-semibold flex items-center gap-2">
                        <KeyRound className="size-4 text-primary" />
                        {punyaPassword ? 'Ubah Kata Sandi' : 'Buat Kata Sandi'}
                    </CardTitle>
                    <CardDescription className="text-xs">
                        {punyaPassword
                            ? `Gunakan minimal ${PASSWORD_MIN} karakter yang belum pernah dipakai sebelumnya.`
                            : 'Akun Anda masuk lewat Google. Buat kata sandi agar bisa login dengan email juga.'}
                    </CardDescription>
                </CardHeader>

                <CardContent className="p-5 grid grid-cols-1 md:grid-cols-3 gap-4">
                    {punyaPassword && (
                        <Field id="password_saat_ini" label="Kata Sandi Saat Ini" required error={errors.password_saat_ini}>
                            <PasswordInput
                                id="password_saat_ini"
                                autoComplete="current-password"
                                value={data.password_saat_ini}
                                onChange={change('password_saat_ini')}
                                error={errors.password_saat_ini}
                                disabled={processing}
                                className="h-9"
                            />
                        </Field>
                    )}
                    <Field id="password" label="Kata Sandi Baru" required error={errors.password}>
                        <PasswordInput
                            id="password"
                            autoComplete="new-password"
                            value={data.password}
                            onChange={change('password')}
                            error={errors.password}
                            disabled={processing}
                            className="h-9"
                        />
                    </Field>
                    <Field id="password_confirmation" label="Konfirmasi Kata Sandi Baru" required error={errors.password_confirmation}>
                        <PasswordInput
                            id="password_confirmation"
                            autoComplete="new-password"
                            value={data.password_confirmation}
                            onChange={change('password_confirmation')}
                            error={errors.password_confirmation}
                            disabled={processing}
                            className="h-9"
                        />
                    </Field>
                </CardContent>

                <CardFooter className="py-3 px-5 border-t bg-muted/20 flex justify-end">
                    <LoadingButton
                        type="submit"
                        loading={processing}
                        loadingText="Menyimpan..."
                        className="gap-2 cursor-pointer shadow-sm h-9"
                    >
                        <Save className="size-4" />
                        Simpan Kata Sandi
                    </LoadingButton>
                </CardFooter>
            </Card>
        </form>
    );
}

export default function ProfilIndex({ profil }) {
    const item = profil?.data ?? profil;

    const breadcrumbs = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Profil Saya', href: '#' },
    ];

    return (
        <DashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Profil Saya" />

            <div className="flex flex-col gap-4 w-full">
                <div>
                    <h1 className="text-xl font-bold tracking-tight flex items-center gap-2">
                        <UserCircle className="size-5 text-primary" />
                        Profil Saya
                    </h1>
                    <p className="text-xs text-muted-foreground mt-0.5">
                        Kelola informasi akun dan keamanan login Anda.
                    </p>
                </div>

                <ProfilForm profil={item} />
                <PasswordForm punyaPassword={item.punya_password} />
            </div>
        </DashboardLayout>
    );
}
