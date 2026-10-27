import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Label } from '@/Components/ui/label';
import { LoadingButton } from '@/Components/loading-button';
import { PasswordInput } from '@/Components/password-input';
import { FlashHandler } from '@/Components/flash-handler';
import { Toaster } from '@/Components/ui/sonner';
import { notify } from '@/lib/notify';
import { cn } from '@/lib/utils';

export default function ResetPassword({ token, email }) {
    // The email comes from the reset link itself, not from a default value.
    const { data, setData, post, processing, errors, setError, clearErrors } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const validate = () => {
        clearErrors();
        let firstError = null;

        if (!data.password) {
            setError('password', 'Kata sandi baru wajib diisi.');
            firstError ??= 'password';
        } else if (data.password.length < 8) {
            setError('password', 'Kata sandi minimal harus 8 karakter.');
            firstError ??= 'password';
        }

        if (!data.password_confirmation) {
            setError('password_confirmation', 'Konfirmasi kata sandi wajib diisi.');
            firstError ??= 'password_confirmation';
        } else if (data.password_confirmation !== data.password) {
            setError('password_confirmation', 'Konfirmasi kata sandi tidak cocok.');
            firstError ??= 'password_confirmation';
        }

        if (firstError) {
            document.getElementById(firstError)?.focus();
            notify.validationFailed();
            return false;
        }

        return true;
    };

    const submit = (e) => {
        e.preventDefault();
        if (!validate()) {
            return;
        }

        post('/reset-password', {
            preserveScroll: true,
            onError: () => notify.validationFailed(),
        });
    };

    return (
        <div className="min-h-screen bg-background text-foreground flex flex-col justify-between p-6 md:p-10">
            <Head title="Atur Ulang Kata Sandi — PAGORA" />
            <FlashHandler />
            <Toaster />

            <div className="flex flex-1 items-center justify-center py-8">
                <div className="w-full max-w-sm">
                    <form onSubmit={submit} className="flex flex-col gap-6" noValidate>
                        <div className="flex flex-col gap-2 text-center">
                            <h1 className="text-2xl font-bold tracking-tight">Atur Ulang Kata Sandi</h1>
                            <p className="text-muted-foreground text-sm text-balance">
                                Buat kata sandi baru untuk akun <span className="font-medium text-foreground">{email}</span>
                            </p>
                        </div>

                        {(errors.email || errors.token || errors.general) && (
                            <div className="text-xs font-medium text-destructive bg-destructive/5 border border-destructive/30 p-3 rounded-lg text-center">
                                {errors.email || errors.token || errors.general}{' '}
                                <Link href="/forgot-password" className="underline underline-offset-4">
                                    Minta tautan baru
                                </Link>
                            </div>
                        )}

                        <div className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="password" className={cn(errors.password && 'text-destructive')}>
                                    Kata Sandi Baru <span className="text-destructive">*</span>
                                </Label>
                                <PasswordInput
                                    id="password"
                                    value={data.password}
                                    onChange={(e) => {
                                        setData('password', e.target.value);
                                        clearErrors('password');
                                    }}
                                    error={errors.password}
                                    disabled={processing}
                                />
                                {errors.password && (
                                    <p id="password-error" className="text-xs font-medium text-destructive">
                                        {errors.password}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label
                                    htmlFor="password_confirmation"
                                    className={cn(errors.password_confirmation && 'text-destructive')}
                                >
                                    Konfirmasi Kata Sandi <span className="text-destructive">*</span>
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    value={data.password_confirmation}
                                    onChange={(e) => {
                                        setData('password_confirmation', e.target.value);
                                        clearErrors('password_confirmation');
                                    }}
                                    error={errors.password_confirmation}
                                    disabled={processing}
                                />
                                {errors.password_confirmation && (
                                    <p id="password_confirmation-error" className="text-xs font-medium text-destructive">
                                        {errors.password_confirmation}
                                    </p>
                                )}
                            </div>

                            <LoadingButton
                                type="submit"
                                className="w-full mt-2 cursor-pointer"
                                loading={processing}
                                loadingText="Menyimpan..."
                            >
                                Simpan Kata Sandi Baru
                            </LoadingButton>
                        </div>

                        <Link
                            href="/login"
                            className="inline-flex items-center justify-center gap-2 text-sm font-medium text-muted-foreground hover:text-foreground transition-colors"
                        >
                            <ArrowLeft className="size-4" />
                            Kembali ke halaman masuk
                        </Link>
                    </form>
                </div>
            </div>

            <div className="text-center text-xs text-muted-foreground">
                © {new Date().getFullYear()} PAGORA Enterprise E-Procurement
            </div>
        </div>
    );
}
