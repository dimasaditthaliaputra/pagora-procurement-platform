import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { LoadingButton } from '@/Components/loading-button';
import { FlashHandler } from '@/Components/flash-handler';
import { Toaster } from '@/Components/ui/sonner';
import { notify } from '@/lib/notify';
import { cn } from '@/lib/utils';

export default function ForgotPassword() {
    const { data, setData, post, processing, errors, setError, clearErrors } = useForm({
        email: '',
    });

    const validate = () => {
        clearErrors();

        const trimmedEmail = data.email.trim();
        if (!trimmedEmail) {
            setError('email', 'Alamat email wajib diisi.');
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(trimmedEmail)) {
            setError('email', 'Format alamat email tidak valid.');
        } else {
            return true;
        }

        document.getElementById('email')?.focus();
        notify.validationFailed();
        return false;
    };

    const submit = (e) => {
        e.preventDefault();
        if (!validate()) {
            return;
        }

        post('/forgot-password', {
            preserveScroll: true,
            onError: () => notify.validationFailed(),
        });
    };

    return (
        <div className="min-h-screen bg-background text-foreground flex flex-col justify-between p-6 md:p-10">
            <Head title="Lupa Kata Sandi — PAGORA" />
            <FlashHandler />
            <Toaster />

            <div className="flex flex-1 items-center justify-center py-8">
                <div className="w-full max-w-sm">
                    <form onSubmit={submit} className="flex flex-col gap-6" noValidate>
                        <div className="flex flex-col gap-2 text-center">
                            <h1 className="text-2xl font-bold tracking-tight">Lupa Kata Sandi</h1>
                            <p className="text-muted-foreground text-sm text-balance">
                                Masukkan email akun Anda yang sudah terverifikasi. Kami akan mengirimkan tautan untuk mengatur ulang kata sandi.
                            </p>
                        </div>

                        <div className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="email" className={cn(errors.email && 'text-destructive')}>
                                    Email <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    value={data.email}
                                    onChange={(e) => {
                                        setData('email', e.target.value);
                                        clearErrors('email');
                                    }}
                                    aria-invalid={!!errors.email}
                                    aria-describedby={errors.email ? 'email-error' : undefined}
                                    className={cn(errors.email && 'border-destructive focus-visible:ring-destructive')}
                                    disabled={processing}
                                    autoFocus
                                />
                                {errors.email && (
                                    <p id="email-error" className="text-xs font-medium text-destructive">
                                        {errors.email}
                                    </p>
                                )}
                                {errors.general && (
                                    <p className="text-xs font-medium text-destructive">{errors.general}</p>
                                )}
                            </div>

                            <LoadingButton
                                type="submit"
                                className="w-full mt-2 cursor-pointer"
                                loading={processing}
                                loadingText="Mengirim tautan..."
                            >
                                Kirim Tautan Atur Ulang
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
