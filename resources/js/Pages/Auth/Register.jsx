import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { PasswordInput } from '@/Components/password-input';
import { LoadingButton } from '@/Components/loading-button';
import { FileDropzone } from '@/Components/file-dropzone';
import { notify } from '@/lib/notify';
import { cn } from '@/lib/utils';
import { Building2, ArrowRight, ArrowLeft, CheckCircle2, ShieldCheck } from 'lucide-react';

export default function Register() {
    const [step, setStep] = useState(1);

    const { data, setData, post, processing, errors, setError, clearErrors } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        nama_perusahaan: '',
        nomor_npwp: '',
        alamat: '',
        dokumen_npwp: null,
    });

    const validateStep1 = () => {
        let hasError = false;
        clearErrors('name', 'email', 'password', 'password_confirmation');

        const trimmedName = data.name.trim();
        if (!trimmedName) {
            setError('name', 'Nama lengkap wajib diisi.');
            hasError = true;
        } else if (trimmedName.length < 3) {
            setError('name', 'Nama lengkap minimal 3 karakter.');
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

        if (hasError) {
            notify.validationFailed();
            return false;
        }

        return true;
    };

    const handleNextStep = (e) => {
        e.preventDefault();
        if (validateStep1()) {
            setStep(2);
        }
    };

    const validateStep2 = () => {
        let hasError = false;
        clearErrors('nama_perusahaan', 'nomor_npwp', 'alamat', 'dokumen_npwp');

        const trimmedPerusahaan = data.nama_perusahaan.trim();
        if (!trimmedPerusahaan) {
            setError('nama_perusahaan', 'Nama perusahaan wajib diisi.');
            hasError = true;
        } else if (trimmedPerusahaan.length < 3) {
            setError('nama_perusahaan', 'Nama perusahaan minimal 3 karakter.');
            hasError = true;
        }

        const trimmedNpwp = data.nomor_npwp.trim();
        if (!trimmedNpwp) {
            setError('nomor_npwp', 'Nomor NPWP wajib diisi.');
            hasError = true;
        }

        const trimmedAlamat = data.alamat.trim();
        if (!trimmedAlamat) {
            setError('alamat', 'Alamat lengkap perusahaan wajib diisi.');
            hasError = true;
        }

        if (hasError) {
            notify.validationFailed();
            return false;
        }

        return true;
    };

    const submit = (e) => {
        e.preventDefault();

        if (!validateStep1()) {
            setStep(1);
            return;
        }

        if (!validateStep2()) {
            return;
        }

        post('/register', {
            forceFormData: true,
            onError: (errs) => {
                notify.validationFailed();
                if (errs.name || errs.email || errs.password || errs.password_confirmation) {
                    setStep(1);
                }
            },
        });
    };

    return (
        <div className="grid min-h-screen lg:grid-cols-2 bg-background text-foreground">
            <Head title="Pendaftaran Akun Tenant — PAGORA" />

            {/* Left Column — Form */}
            <div className="flex flex-col justify-between p-6 md:p-10">
                <div className="flex flex-1 items-center justify-center py-8">
                    <div className="w-full max-w-md">
                        {/* Stepper Progress */}
                        <div className="flex items-center justify-between gap-2 mb-6 p-1.5 rounded-xl bg-muted/50 border border-border/70 text-xs select-none">
                            <button
                                type="button"
                                onClick={() => setStep(1)}
                                className={cn(
                                    'flex items-center gap-2 flex-1 px-3 py-2 rounded-lg font-medium transition-all text-left cursor-pointer',
                                    step === 1
                                        ? 'bg-background text-foreground shadow-xs'
                                        : 'text-muted-foreground hover:text-foreground'
                                )}
                            >
                                <span
                                    className={cn(
                                        'flex items-center justify-center size-5 rounded-full text-[11px] font-semibold shrink-0',
                                        step === 1
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                                    )}
                                >
                                    {step > 1 ? <CheckCircle2 className="size-3.5" /> : '1'}
                                </span>
                                <span className="truncate">1. Profil Akun</span>
                            </button>

                            <div className="h-4 w-px bg-border/70 shrink-0" />

                            <button
                                type="button"
                                onClick={() => {
                                    if (validateStep1()) {
                                        setStep(2);
                                    }
                                }}
                                className={cn(
                                    'flex items-center gap-2 flex-1 px-3 py-2 rounded-lg font-medium transition-all text-left cursor-pointer',
                                    step === 2
                                        ? 'bg-background text-foreground shadow-xs'
                                        : 'text-muted-foreground hover:text-foreground'
                                )}
                            >
                                <span
                                    className={cn(
                                        'flex items-center justify-center size-5 rounded-full text-[11px] font-semibold shrink-0',
                                        step === 2
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted-foreground/20 text-muted-foreground'
                                    )}
                                >
                                    2
                                </span>
                                <span className="truncate">2. Data Perusahaan</span>
                            </button>
                        </div>

                        <form onSubmit={submit} className="flex flex-col gap-5" noValidate>
                            {/* Header Section */}
                            <div className="flex flex-col gap-1.5 text-center">
                                <h1 className="text-2xl font-bold tracking-tight">
                                    {step === 1 ? 'Daftar Akun Tenant' : 'Data Perusahaan Tenant'}
                                </h1>
                                <p className="text-muted-foreground text-sm text-balance">
                                    {step === 1
                                        ? 'Langkah 1 dari 2: Masukkan profil perwakilan penanggung jawab akun'
                                        : 'Langkah 2 dari 2: Lengkapi profil perusahaan buyer untuk aktivasi tenant'}
                                </p>
                            </div>

                            {/* Callout Khusus Calon Vendor Bahan Baku (Hanya di Step 1) */}
                            {step === 1 && (
                                <div className="p-3.5 rounded-xl border border-emerald-500/25 bg-emerald-500/5 dark:bg-emerald-950/20 flex items-start gap-3 text-xs leading-relaxed">
                                    <div className="p-1 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5">
                                        <Building2 className="size-4" />
                                    </div>
                                    <div className="flex-1 text-left">
                                        <p className="font-semibold text-foreground">
                                            Perusahaan Pemasok Bahan Baku?
                                        </p>
                                        <p className="text-muted-foreground mt-0.5">
                                            Pendaftaran calon vendor memerlukan alur pengunggahan legalitas khusus pemasok.
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
                            )}

                            {/* STEP 1: Profil Akun User */}
                            {step === 1 && (
                                <div className="grid gap-3.5">
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="name" className={errors.name ? 'text-destructive' : ''}>
                                            Nama Lengkap <span className="text-destructive">*</span>
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
                                            placeholder="Contoh: Budi Pratama"
                                            aria-invalid={!!errors.name}
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
                                            Email Bisnis <span className="text-destructive">*</span>
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
                                            placeholder="Contoh: procurement@perusahaan.co.id"
                                            aria-invalid={!!errors.email}
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
                                            Kata Sandi <span className="text-destructive">*</span>
                                        </Label>
                                        <PasswordInput
                                            id="password"
                                            value={data.password}
                                            onChange={(e) => {
                                                setData('password', e.target.value);
                                                if (errors.password) clearErrors('password');
                                            }}
                                            placeholder="Minimal 8 karakter"
                                            error={errors.password}
                                            disabled={processing}
                                        />
                                        {errors.password && (
                                            <p id="password-error" className="text-xs font-medium text-destructive">
                                                {errors.password}
                                            </p>
                                        )}
                                    </div>

                                    <div className="grid gap-1.5">
                                        <Label
                                            htmlFor="password_confirmation"
                                            className={errors.password_confirmation ? 'text-destructive' : ''}
                                        >
                                            Konfirmasi Kata Sandi <span className="text-destructive">*</span>
                                        </Label>
                                        <PasswordInput
                                            id="password_confirmation"
                                            value={data.password_confirmation}
                                            onChange={(e) => {
                                                setData('password_confirmation', e.target.value);
                                                if (errors.password_confirmation) clearErrors('password_confirmation');
                                            }}
                                            placeholder="Ulangi kata sandi"
                                            error={errors.password_confirmation}
                                            disabled={processing}
                                        />
                                        {errors.password_confirmation && (
                                            <p id="password_confirmation-error" className="text-xs font-medium text-destructive">
                                                {errors.password_confirmation}
                                            </p>
                                        )}
                                    </div>

                                    <Button
                                        type="button"
                                        size="default"
                                        onClick={handleNextStep}
                                        className="w-full mt-2 cursor-pointer flex items-center justify-center gap-2"
                                    >
                                        <span>Lanjut: Data Perusahaan</span>
                                        <ArrowRight className="size-4" />
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
                            )}

                            {/* STEP 2: Data Perusahaan Tenant */}
                            {step === 2 && (
                                <div className="grid gap-3.5">
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="nama_perusahaan" className={errors.nama_perusahaan ? 'text-destructive' : ''}>
                                            Nama Perusahaan / Entitas Bisnis <span className="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            id="nama_perusahaan"
                                            type="text"
                                            name="nama_perusahaan"
                                            value={data.nama_perusahaan}
                                            onChange={(e) => {
                                                setData('nama_perusahaan', e.target.value);
                                                if (errors.nama_perusahaan) clearErrors('nama_perusahaan');
                                            }}
                                            placeholder="Contoh: PT Sumber Pangan Makmur"
                                            aria-invalid={!!errors.nama_perusahaan}
                                            aria-describedby={errors.nama_perusahaan ? 'nama_perusahaan-error' : undefined}
                                            className={cn(
                                                errors.nama_perusahaan && 'border-destructive focus-visible:ring-destructive'
                                            )}
                                            autoFocus
                                        />
                                        {errors.nama_perusahaan && (
                                            <p id="nama_perusahaan-error" className="text-xs font-medium text-destructive">
                                                {errors.nama_perusahaan}
                                            </p>
                                        )}
                                    </div>

                                    <div className="grid gap-1.5">
                                        <Label htmlFor="nomor_npwp" className={errors.nomor_npwp ? 'text-destructive' : ''}>
                                            Nomor NPWP Perusahaan <span className="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            id="nomor_npwp"
                                            type="text"
                                            name="nomor_npwp"
                                            value={data.nomor_npwp}
                                            onChange={(e) => {
                                                setData('nomor_npwp', e.target.value);
                                                if (errors.nomor_npwp) clearErrors('nomor_npwp');
                                            }}
                                            placeholder="Contoh: 01.234.567.8-901.000"
                                            aria-invalid={!!errors.nomor_npwp}
                                            aria-describedby={errors.nomor_npwp ? 'nomor_npwp-error' : undefined}
                                            className={cn(
                                                errors.nomor_npwp && 'border-destructive focus-visible:ring-destructive'
                                            )}
                                        />
                                        {errors.nomor_npwp && (
                                            <p id="nomor_npwp-error" className="text-xs font-medium text-destructive">
                                                {errors.nomor_npwp}
                                            </p>
                                        )}
                                    </div>

                                    <div className="grid gap-1.5">
                                        <Label htmlFor="alamat" className={errors.alamat ? 'text-destructive' : ''}>
                                            Alamat Lengkap Perusahaan <span className="text-destructive">*</span>
                                        </Label>
                                        <Textarea
                                            id="alamat"
                                            name="alamat"
                                            rows={3}
                                            value={data.alamat}
                                            onChange={(e) => {
                                                setData('alamat', e.target.value);
                                                if (errors.alamat) clearErrors('alamat');
                                            }}
                                            placeholder="Contoh: Jl. Industri Raya No. 45, Kawasan MM2100, Cikarang Barat, Bekasi, Jawa Barat 17530"
                                            aria-invalid={!!errors.alamat}
                                            aria-describedby={errors.alamat ? 'alamat-error' : undefined}
                                            className={cn(
                                                errors.alamat && 'border-destructive focus-visible:ring-destructive'
                                            )}
                                        />
                                        {errors.alamat && (
                                            <p id="alamat-error" className="text-xs font-medium text-destructive">
                                                {errors.alamat}
                                            </p>
                                        )}
                                    </div>

                                    <div className="grid gap-1.5">
                                        <Label htmlFor="dokumen_npwp" className={errors.dokumen_npwp ? 'text-destructive' : ''}>
                                            Unggah Dokumen NPWP (Opsional)
                                        </Label>
                                        <FileDropzone
                                            value={data.dokumen_npwp}
                                            onChange={(file) => {
                                                setData('dokumen_npwp', file);
                                                if (errors.dokumen_npwp) clearErrors('dokumen_npwp');
                                            }}
                                            onError={(msg) => {
                                                setError('dokumen_npwp', msg);
                                                notify.validationFailed();
                                            }}
                                            error={errors.dokumen_npwp}
                                            disabled={processing}
                                            label="Dokumen NPWP"
                                            description="Format PDF, JPG, JPEG, atau PNG (Maksimal 2 MB)"
                                        />
                                    </div>

                                    {/* Action Buttons: Kembali & Submit */}
                                    <div className="flex items-center gap-3 mt-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="default"
                                            onClick={() => setStep(1)}
                                            disabled={processing}
                                            className="cursor-pointer flex items-center justify-center gap-1.5 shrink-0"
                                        >
                                            <ArrowLeft className="size-4" />
                                            <span>Kembali</span>
                                        </Button>

                                        <LoadingButton
                                            type="submit"
                                            size="default"
                                            loading={processing}
                                            loadingText="Mendaftarkan tenant..."
                                            className="flex-1 cursor-pointer flex items-center justify-center gap-2"
                                        >
                                            <ShieldCheck className="size-4" />
                                            <span>Daftarkan Tenant</span>
                                        </LoadingButton>
                                    </div>
                                </div>
                            )}

                            <div className="text-center text-sm text-muted-foreground mt-1">
                                Sudah memiliki akun terdaftar?{' '}
                                <Link
                                    href="/login"
                                    className="text-foreground font-semibold underline underline-offset-4 hover:text-primary transition-colors"
                                >
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

            {/* Right Column — Cover Image & Branding */}
            <div className="relative hidden lg:block bg-muted">
                <img
                    src="/images/login-cover.jpg"
                    alt="PAGORA Procurement Platform"
                    className="absolute inset-0 h-full w-full object-cover dark:brightness-[0.2] dark:grayscale"
                />
                <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/30 to-transparent" />
                <div className="absolute bottom-0 left-0 right-0 p-10">
                    <blockquote className="space-y-3 text-white">
                        <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 backdrop-blur-xs text-xs font-medium border border-white/20">
                            <ShieldCheck className="size-3.5 text-emerald-400" />
                            <span>Ekosistem Pengadaan B2B Terpercaya</span>
                        </div>
                        <p className="text-xl font-medium leading-relaxed">
                            "Integrasikan rantai pasok manufaktur Anda dengan efisiensi PO, transparansi harga acuan komoditas, dan tata kelola vendor modern."
                        </p>
                        <footer className="text-sm text-white/70">
                            — PAGORA Tenant Enterprise Portal
                        </footer>
                    </blockquote>
                </div>
            </div>
        </div>
    );
}
