import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Checkbox } from '@/Components/ui/checkbox';
import { cn } from '@/lib/utils';
import { useState } from 'react';
import { Eye, EyeOff } from 'lucide-react';

export default function Login({ status }) {
    const [isView, setIsView] = useState(false);
    const { data, setData, post, processing, errors, setError, clearErrors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const validate = () => {
        let hasError = false;
        clearErrors();

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
        }

        return !hasError;
    };

    const submit = (e) => {
        e.preventDefault();
        if (!validate()) {
            return;
        }
        post('/login');
    };

    return (
        <div className="grid min-h-screen lg:grid-cols-2 bg-background text-foreground">
            <Head title="Masuk Portal Pengadaan — PAGORA" />

            {/* Left — Form */}
            <div className="flex flex-col justify-between p-6 md:p-10">
                <div className="flex flex-1 items-center justify-center py-8">
                    <div className="w-full max-w-sm">
                        <form onSubmit={submit} className="flex flex-col gap-6" noValidate>
                            <div className="flex flex-col gap-2 text-center">
                                <h1 className="text-2xl font-bold tracking-tight">
                                    Masuk ke Portal
                                </h1>
                                <p className="text-muted-foreground text-sm text-balance">
                                    Masukkan kredensial untuk mengakses sistem pengadaan PAGORA
                                </p>
                            </div>

                            {status && (
                                <div className="text-xs font-medium text-emerald-700 bg-emerald-50 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 p-3 rounded-lg text-center">
                                    {status}
                                </div>
                            )}

                            <div className="grid gap-4">
                                <div className="grid gap-2">
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
                                        autoFocus
                                    />
                                    {errors.email && (
                                        <p id="email-error" className="text-xs font-medium text-destructive">
                                            {errors.email}
                                        </p>
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    <div className="flex items-center justify-between">
                                        <Label htmlFor="password" className={errors.password ? 'text-destructive' : ''}>
                                            Kata Sandi
                                        </Label>
                                        <Link href="/forgot-password" className="text-sm font-medium text-primary hover:underline">
                                            Lupa Kata Sandi?
                                        </Link>
                                    </div>
                                    <div className="relative">
                                        <Input
                                            id="password"
                                            type={isView ? 'text' : 'password'}
                                            name="password"
                                            value={data.password}
                                            onChange={(e) => {
                                                setData('password', e.target.value);
                                                if (errors.password) clearErrors('password');
                                            }}
                                            placeholder="••••••••••••"
                                            aria-invalid={errors.password ? 'true' : 'false'}
                                            aria-describedby={errors.password ? 'password-error' : undefined}
                                            className={cn(
                                                'pr-10',
                                                errors.password && 'border-destructive focus-visible:ring-destructive'
                                            )}
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setIsView(!isView)}
                                            className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground focus:outline-none cursor-pointer"
                                            tabIndex={-1}
                                            aria-label={isView ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'}
                                        >
                                            {isView ? (
                                                <Eye className="size-4" />
                                            ) : (
                                                <EyeOff className="size-4" />
                                            )}
                                        </button>
                                    </div>
                                    {errors.password && (
                                        <p id="password-error" className="text-xs font-medium text-destructive">
                                            {errors.password}
                                        </p>
                                    )}
                                </div>

                                <div className="flex items-center justify-between">
                                    <div className="flex items-center space-x-2">
                                        <Checkbox
                                            id="remember"
                                            checked={data.remember}
                                            onCheckedChange={(checked) => setData('remember', Boolean(checked))}
                                        />
                                        <Label
                                            htmlFor="remember"
                                            className="text-sm font-medium leading-none cursor-pointer select-none text-muted-foreground"
                                        >
                                            Ingat saya
                                        </Label>
                                    </div>
                                </div>

                                <Button type="submit" size="default" className="w-full mt-2 cursor-pointer" disabled={processing}>
                                    {processing ? 'Memverifikasi...' : 'Masuk ke Dashboard'}
                                </Button>

                                <div className="relative text-center text-xs after:absolute after:inset-0 after:top-1/2 after:z-0 after:flex after:items-center after:border-t after:border-border my-1">
                                    <span className="relative z-10 px-2 bg-background text-muted-foreground font-medium">
                                        Atau lanjutkan dengan
                                    </span>
                                </div>

                                <div>
                                    <a
                                        href="/auth/google/redirect"
                                        aria-invalid={errors.google ? 'true' : 'false'}
                                        aria-describedby={errors.google ? 'google-error' : undefined}
                                        className={cn(
                                            'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-lg text-sm font-medium transition-all outline-none select-none border border-border bg-background hover:bg-muted hover:text-foreground h-10 px-4 w-full cursor-pointer',
                                            errors.google && 'border-destructive text-destructive ring-1 ring-destructive/40 bg-destructive/5 hover:bg-destructive/10'
                                        )}
                                    >
                                        <svg className="size-4 shrink-0" viewBox="0 0 24 24">
                                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" />
                                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" />
                                        </svg>
                                        Masuk dengan Google
                                    </a>
                                    {errors.google && (
                                        <p id="google-error" className="text-xs font-medium text-destructive text-center mt-2">
                                            {errors.google}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="text-center text-sm text-muted-foreground">
                                Belum memiliki akun?{' '}
                                <Link href="/register" className="text-foreground font-semibold underline underline-offset-4 hover:text-primary transition-colors">
                                    Daftar di sini
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
                            "PAGORA menyederhanakan seluruh proses pengadaan bahan baku kami. Dari pencarian vendor hingga manajemen kontrak, semuanya dalam satu platform."
                        </p>
                        <footer className="text-sm text-white/70">
                            — Tim Procurement, Perusahaan Manufaktur
                        </footer>
                    </blockquote>
                </div>
            </div>
        </div>
    );
}
