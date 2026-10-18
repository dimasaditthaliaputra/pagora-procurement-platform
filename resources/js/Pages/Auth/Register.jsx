import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { cn } from '@/lib/utils';
import { Building2, ArrowRight } from 'lucide-react';

export default function Register() {
    const { data, setData, post, processing, errors, setError, clearErrors } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const validate = () => {
        let hasError = false;
        clearErrors();

        const trimmedName = data.name.trim();
        if (!trimmedName) {
            setError('name', 'Nama lengkap wajib diisi.');
            hasError = true;
        }

        const trimmedEmail = data.email.trim();
        if (!trimmedEmail) {
            setError('email', 'Alamat email wajib diisi.');
            hasError = true;
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(trimmedEmail)) {
            setError('email', 'Format alamat email tidak valid.');
            hasError = true;
        }

        if (!data.password) {
            setError('password', 'Kata sandi wajib diisi.');
            hasError = true;
        } else if (data.password.length < 8) {
            setError('password', 'Kata sandi minimal harus 8 karakter.');
            hasError = true;
        }

        if (!data.password_confirmation) {
            setError('password_confirmation', 'Konfirmasi kata sandi wajib diisi.');
            hasError = true;
        } else if (data.password !== data.password_confirmation) {
            setError('password_confirmation', 'Konfirmasi kata sandi tidak cocok.');
            hasError = true;
        }

        return !hasError;
    };

    const submit = (e) => {
        e.preventDefault();
        if (!validate()) {
            return;
        }
        post('/register');
    };

    return (
        <div className="grid min-h-screen lg:grid-cols-2 bg-background text-foreground">
            <Head title="Pendaftaran Akun — PAGORA" />

            {/* Left — Form */}
            <div className="flex flex-col justify-between p-6 md:p-10">
                <div className="flex justify-center md:justify-start">
                    <Link href="/" className="inline-flex items-center gap-2 font-bold text-2xl tracking-tight text-foreground hover:opacity-80 transition-opacity">
                        <span className="flex size-9 items-center justify-center rounded-xl bg-primary text-primary-foreground font-extrabold text-base">
                            P
                        </span>
                        PAGORA
                    </Link>
                </div>

                <div className="flex flex-1 items-center justify-center py-8">
                    <div className="w-full max-w-sm">
                        <form onSubmit={submit} className="flex flex-col gap-5" noValidate>
                            <div className="flex flex-col gap-2 text-center">
                                <h1 className="text-2xl font-bold tracking-tight">
                                    Daftar Akun Baru
                                </h1>
                                <p className="text-muted-foreground text-sm text-balance">
                                    Lengkapi data untuk bergabung ke ekosistem pengadaan PAGORA
                                </p>
                            </div>

                            {/* Callout Khusus Calon Vendor Bahan Baku */}
                            <div className="p-3.5 rounded-xl border border-emerald-500/25 bg-emerald-500/5 dark:bg-emerald-950/20 flex items-start gap-3 text-xs leading-relaxed">
                                <div className="p-1 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5">
                                    <Building2 className="size-4" />
                                </div>
                                <div className="flex-1 text-left">
                                    <p className="font-semibold text-foreground">
                                        Perusahaan Pemasok Bahan Baku?
                                    </p>
                                    <p className="text-muted-foreground mt-0.5">
                                        Pendaftaran calon vendor memerlukan pengunggahan berkas NIB/legalitas usaha.
                                    </p>
                                    <Link
                                        href="/register/vendor"
                                        className="inline-flex items-center gap-1 font-semibold text-emerald-600 dark:text-emerald-400 hover:underline mt-1.5"
                                    >
                                        <span>Daftar sebagai Calon Vendor</span>
                                        <ArrowRight className="size-3" />
                                    </Link>
                                </div>
                            </div>

                            <div className="grid gap-3.5">
                                <div className="grid gap-1.5">
                                    <Label htmlFor="name" className={errors.name ? 'text-destructive' : ''}>
                                        Nama Lengkap
                                    </Label>
                                    <Input
                                        id="name"
                                        type="text"
                                        name="name"
                                        value={data.name}
                                        onChange={(e) => {
                                            setData('name', e.target.value);
                                            if (errors.name) clearErrors('name');
                                        }}
                                        placeholder="Nama lengkap Anda"
                                        aria-invalid={errors.name ? 'true' : 'false'}
                                        aria-describedby={errors.name ? 'name-error' : undefined}
                                        className={cn(
                                            errors.name && 'border-destructive focus-visible:ring-destructive'
                                        )}
                                        autoFocus
                                    />
                                    {errors.name && (
                                        <p id="name-error" className="text-xs font-medium text-destructive">
                                            {errors.name}
                                        </p>
                                    )}
                                </div>

                                <div className="grid gap-1.5">
                                    <Label htmlFor="email" className={errors.email ? 'text-destructive' : ''}>
                                        Email Bisnis
                                    </Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        name="email"
                                        value={data.email}
                                        onChange={(e) => {
                                            setData('email', e.target.value);
                                            if (errors.email) clearErrors('email');
                                        }}
                                        placeholder="nama@perusahaan.co.id"
                                        aria-invalid={errors.email ? 'true' : 'false'}
                                        aria-describedby={errors.email ? 'email-error' : undefined}
                                        className={cn(
                                            errors.email && 'border-destructive focus-visible:ring-destructive'
                                        )}
                                    />
                                    {errors.email && (
                                        <p id="email-error" className="text-xs font-medium text-destructive">
                                            {errors.email}
                                        </p>
                                    )}
                                </div>

                                <div className="grid gap-1.5">
                                    <Label htmlFor="password" className={errors.password ? 'text-destructive' : ''}>
                                        Kata Sandi
                                    </Label>
                                    <Input
                                        id="password"
                                        type="password"
                                        name="password"
                                        value={data.password}
                                        onChange={(e) => {
                                            setData('password', e.target.value);
                                            if (errors.password) clearErrors('password');
                                        }}
                                        placeholder="Minimal 8 karakter"
                                        aria-invalid={errors.password ? 'true' : 'false'}
                                        aria-describedby={errors.password ? 'password-error' : undefined}
                                        className={cn(
                                            errors.password && 'border-destructive focus-visible:ring-destructive'
                                        )}
                                    />
                                    {errors.password && (
                                        <p id="password-error" className="text-xs font-medium text-destructive">
                                            {errors.password}
                                        </p>
                                    )}
                                </div>

                                <div className="grid gap-1.5">
                                    <Label htmlFor="password_confirmation" className={errors.password_confirmation ? 'text-destructive' : ''}>
                                        Konfirmasi Kata Sandi
                                    </Label>
                                    <Input
                                        id="password_confirmation"
                                        type="password"
                                        name="password_confirmation"
                                        value={data.password_confirmation}
                                        onChange={(e) => {
                                            setData('password_confirmation', e.target.value);
                                            if (errors.password_confirmation) clearErrors('password_confirmation');
                                        }}
                                        placeholder="Ulangi kata sandi"
                                        aria-invalid={errors.password_confirmation ? 'true' : 'false'}
                                        aria-describedby={errors.password_confirmation ? 'password_confirmation-error' : undefined}
                                        className={cn(
                                            errors.password_confirmation && 'border-destructive focus-visible:ring-destructive'
                                        )}
                                    />
                                    {errors.password_confirmation && (
                                        <p id="password_confirmation-error" className="text-xs font-medium text-destructive">
                                            {errors.password_confirmation}
                                        </p>
                                    )}
                                </div>

                                <Button type="submit" size="default" className="w-full mt-2 cursor-pointer" disabled={processing}>
                                    {processing ? 'Mendaftarkan akun...' : 'Daftar Sekarang'}
                                </Button>

                                <div className="relative text-center text-xs after:absolute after:inset-0 after:top-1/2 after:z-0 after:flex after:items-center after:border-t after:border-border my-1">
                                    <span className="relative z-10 px-2 bg-background text-muted-foreground font-medium">
                                        Atau daftar dengan
                                    </span>
                                </div>

                                <div>
                                    <a
                                        href="/auth/google/redirect"
                                        className="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-lg text-sm font-medium transition-all outline-none select-none border border-border bg-background hover:bg-muted hover:text-foreground h-10 px-4 w-full cursor-pointer"
                                    >
                                        <svg className="size-4 shrink-0" viewBox="0 0 24 24">
                                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" />
                                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" />
                                        </svg>
                                        Daftar dengan Google
                                    </a>
                                </div>
                            </div>

                            <div className="text-center text-sm text-muted-foreground">
                                Sudah memiliki akun terdaftar?{' '}
                                <Link href="/login" className="text-foreground font-semibold underline underline-offset-4 hover:text-primary transition-colors">
                                    Masuk di sini
                                </Link>
                            </div>
                        </form>
                    </div>
                </div>

                <div className="text-center text-xs text-muted-foreground">
                    © {new Date().getFullYear()} PAGORA Enterprise E-Procurement
                </div>
            </div>

            {/* Right — Cover Image */}
            <div className="relative hidden lg:block bg-muted">
                <img
                    src="/images/login-cover.jpg"
                    alt="PAGORA Procurement Platform"
                    className="absolute inset-0 h-full w-full object-cover dark:brightness-[0.2] dark:grayscale"
                />
                <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent" />
                <div className="absolute bottom-0 left-0 right-0 p-10">
                    <blockquote className="space-y-2 text-white">
                        <p className="text-lg font-medium leading-relaxed">
                            "Bergabunglah bersama ratusan vendor dan buyer terverifikasi dalam rantai pasok industri modern Indonesia."
                        </p>
                        <footer className="text-sm text-white/70">
                            — Ekosistem Pengadaan Digital PAGORA
                        </footer>
                    </blockquote>
                </div>
            </div>
        </div>
    );
}
