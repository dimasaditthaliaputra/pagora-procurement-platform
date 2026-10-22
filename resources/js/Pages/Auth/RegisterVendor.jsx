import { Head, Link, useForm, useHttp } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Combobox } from '@/Components/combobox';
import { FileDropzone } from '@/Components/file-dropzone';
import { LoadingButton } from '@/Components/loading-button';
import { FlashHandler } from '@/Components/flash-handler';
import { OtpField, OTP_LENGTH } from '@/Components/otp-field';
import { useCountdown } from '@/hooks/use-countdown';
import { Toaster } from '@/Components/ui/sonner';
import { notify } from '@/lib/notify';
import { cn } from '@/lib/utils';
import {
    Building2,
    CheckCircle2,
    ArrowLeft,
    ShieldCheck,
    AlertCircle,
} from 'lucide-react';
import Logo from '../../../assets/logo/logo.webp';

export default function RegisterVendor({ kategori_options = [], status }) {
    const { data, setData, post, processing, errors, setError, clearErrors, reset } = useForm({
        nama_perusahaan: '',
        email_kontak: '',
        no_telepon: '',
        kategori_komoditas: '',
        alamat: '',
        otp: '',
        dokumen_legalitas: null,
    });

    const [otpSent, setOtpSent] = useState(false);
    const resend = useCountdown();
    const otpRequest = useHttp({ email_kontak: '' });

    const categories = kategori_options.length > 0 ? kategori_options : [
        'Agroindustri & Pangan',
        'Beras & Biji-bijian',
        'Minyak & Lemak Nabati',
        'Gula, Pemanis & Rempah',
        'Daging, Unggas & Perikanan',
        'Kimia Industri & Polimer',
        'Logam, Baja & Fabrikasi',
        'Tekstil & Kemasan',
        'Lainnya',
    ];

    const sendOtp = () => {
        const email = data.email_kontak.trim();

        if (!email) {
            setError('email_kontak', 'Email bisnis wajib diisi.');
            notify.validationFailed();
            return;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            setError('email_kontak', 'Format email bisnis tidak valid.');
            notify.validationFailed();
            return;
        }

        clearErrors('email_kontak', 'otp');
        otpRequest.transform(() => ({ email_kontak: email }));
        otpRequest.post('/register/vendor/otp', {
            onSuccess: () => {
                setOtpSent(true);
                resend.start(60);
                notify.success('Kode OTP telah dikirim ke email bisnis Anda.');
            },
            onError: (errs) => {
                setError('email_kontak', errs.email_kontak ?? 'Gagal mengirim kode OTP.');
                notify.validationFailed();
            },
        });
    };

    const validate = () => {
        let hasError = false;
        clearErrors();

        const namaTrimmed = data.nama_perusahaan.trim();
        if (!namaTrimmed) {
            setError('nama_perusahaan', 'Nama perusahaan wajib diisi.');
            hasError = true;
        }

        const emailTrimmed = data.email_kontak.trim();
        if (!emailTrimmed) {
            setError('email_kontak', 'Email bisnis wajib diisi.');
            hasError = true;
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailTrimmed)) {
            setError('email_kontak', 'Format email bisnis tidak valid.');
            hasError = true;
        }

        const telpTrimmed = data.no_telepon.trim();
        if (!telpTrimmed) {
            setError('no_telepon', 'Nomor telepon wajib diisi.');
            hasError = true;
        }

        if (!data.kategori_komoditas) {
            setError('kategori_komoditas', 'Kategori komoditas wajib dipilih.');
            hasError = true;
        }

        const alamatTrimmed = data.alamat.trim();
        if (!alamatTrimmed) {
            setError('alamat', 'Alamat lengkap wajib diisi.');
            hasError = true;
        }

        if (!otpSent) {
            setError('otp', 'Verifikasi email terlebih dahulu dengan menekan "Kirim Kode OTP".');
            hasError = true;
        } else if (data.otp.length !== OTP_LENGTH) {
            setError('otp', `Kode OTP harus terdiri dari ${OTP_LENGTH} digit angka.`);
            hasError = true;
        }

        if (!data.dokumen_legalitas) {
            setError('dokumen_legalitas', 'Dokumen legalitas (NPWP/SIUP) wajib diunggah.');
            hasError = true;
        }

        if (hasError) {
            notify.validationFailed();
            return false;
        }

        return true;
    };

    const handleSubmit = (e) => {
        e.preventDefault();

        if (!validate()) {
            return;
        }

        post('/register/vendor', {
            forceFormData: true,
            onError: () => {
                notify.validationFailed();
            },
            onSuccess: () => {
                reset();
                setOtpSent(false);
                resend.start(0);
            },
        });
    };

    return (
        <div className="min-h-screen bg-canvas-bg text-foreground flex flex-col justify-between py-6 px-4 sm:px-6 lg:px-8 font-inter selection:bg-brand-primary selection:text-text-primary">
            <Head title="Registrasi Calon Vendor Mitra — PAGORA" />
            <FlashHandler />
            <Toaster />

            {/* Navigation Header */}
            <header className="max-w-5xl mx-auto w-full flex items-center justify-between pb-6">
                <Link href="/" className="inline-flex items-center gap-3 group">
                    <div className="size-10 rounded-xl bg-card border border-border flex items-center justify-center p-1.5 shadow-2xs group-hover:scale-105 transition-transform">
                        <img src={Logo} alt="Logo PAGORA" className="size-full object-contain" />
                    </div>
                    <div className="flex flex-col">
                        <span className="font-bold tracking-tight text-xl text-foreground leading-none">PAGORA</span>
                        <span className="text-xs text-muted-foreground font-medium">E-Procurement Platform</span>
                    </div>
                </Link>
                <Button asChild variant="outline" size="sm" className="rounded-full gap-2">
                    <Link href="/register">
                        <ArrowLeft className="size-4" />
                        <span>Kembali ke Registrasi</span>
                    </Link>
                </Button>
            </header>

            {/* Form Main Container */}
            <main className="max-w-3xl w-full mx-auto my-4">
                <div className="bg-card rounded-2xl border border-border shadow-xs p-6 sm:p-10">
                    {/* Header info */}
                    <div className="text-center max-w-xl mx-auto mb-8">
                        <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-xs font-semibold text-emerald-700 dark:text-emerald-400 mb-4">
                            <ShieldCheck className="size-3.5" />
                            Kemitraan Vendor Terverifikasi
                        </div>
                        <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
                            Pendaftaran Calon Vendor Mitra
                        </h1>
                        <p className="text-sm text-muted-foreground mt-2 leading-relaxed">
                            Lengkapi data profil usaha untuk diverifikasi oleh tim Purchasing PAGORA.
                            Dapatkan akses pesanan bahan baku berulang dengan transparansi PO dan ketepatan pembayaran.
                        </p>
                    </div>

                    {/* Informasi penting alur pendaftaran */}
                    <div className="mb-8 p-4 rounded-xl bg-muted/40 border border-border/80 flex items-start gap-3 text-xs sm:text-sm text-muted-foreground">
                        <div className="p-1 rounded-md bg-primary/10 text-primary shrink-0 mt-0.5">
                            <Building2 className="size-4" />
                        </div>
                        <p className="leading-relaxed">
                            <strong className="text-foreground font-semibold">Catatan Verifikasi:</strong> Formulir ini mendaftarkan profil usaha Anda ke sistem vendor (status <span className="font-semibold text-foreground">pending</span>). Tim Manajemen akan memvalidasi dokumen legalitas Anda sebelum menerbitkan kredensial login portal vendor.
                        </p>
                    </div>

                    {/* Status Sukses Banner */}
                    {status && (
                        <div className="mb-8 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-start gap-3 text-emerald-800 dark:text-emerald-300">
                            <CheckCircle2 className="size-5 shrink-0 mt-0.5 text-emerald-600 dark:text-emerald-400" />
                            <div className="text-sm">
                                <p className="font-semibold">{status}</p>
                                <p className="text-xs text-emerald-700/80 dark:text-emerald-400/80 mt-1">
                                    Silakan periksa email resmi perusahaan Anda secara berkala untuk pembaruan status pendaftaran dari tim Manajemen PAGORA.
                                </p>
                            </div>
                        </div>
                    )}

                    {/* Form Input */}
                    <form noValidate onSubmit={handleSubmit} className="space-y-6">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-5 items-start">
                            {/* Nama Perusahaan */}
                            <div className="space-y-1.5">
                                <Label htmlFor="nama_perusahaan" className={cn('text-sm font-medium', errors.nama_perusahaan && 'text-destructive')}>
                                    Nama Perusahaan <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="nama_perusahaan"
                                    type="text"
                                    placeholder="Contoh: PT Sumber Pangan Makmur"
                                    value={data.nama_perusahaan}
                                    onChange={(e) => {
                                        setData('nama_perusahaan', e.target.value);
                                        clearErrors('nama_perusahaan');
                                    }}
                                    aria-invalid={!!errors.nama_perusahaan}
                                    className={cn('h-10 w-full', errors.nama_perusahaan && 'border-destructive focus-visible:ring-destructive')}
                                />
                                {errors.nama_perusahaan && (
                                    <p className="text-xs font-medium text-destructive flex items-center gap-1.5 mt-1">
                                        <AlertCircle className="size-3.5 shrink-0" />
                                        <span>{errors.nama_perusahaan}</span>
                                    </p>
                                )}
                            </div>

                            {/* Kategori Komoditas (Searchable Combobox) */}
                            <div className="space-y-1.5">
                                <Label htmlFor="kategori_komoditas" className={cn('text-sm font-medium', errors.kategori_komoditas && 'text-destructive')}>
                                    Kategori Komoditas <span className="text-destructive">*</span>
                                </Label>
                                <Combobox
                                    options={categories}
                                    value={data.kategori_komoditas}
                                    onChange={(val) => {
                                        setData('kategori_komoditas', val);
                                        clearErrors('kategori_komoditas');
                                    }}
                                    placeholder="Pilih kategori komoditas..."
                                    searchPlaceholder="Cari kategori (Beras, Pangan, Logam...)"
                                    emptyMessage="Kategori komoditas tidak ditemukan."
                                    error={errors.kategori_komoditas}
                                    disabled={processing}
                                    className="h-10 w-full"
                                />
                                {errors.kategori_komoditas && (
                                    <p className="text-xs font-medium text-destructive flex items-center gap-1.5 mt-1">
                                        <AlertCircle className="size-3.5 shrink-0" />
                                        <span>{errors.kategori_komoditas}</span>
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-5 items-start">
                            {/* Email Bisnis */}
                            <div className="space-y-1.5">
                                <Label htmlFor="email_kontak" className={cn('text-sm font-medium', errors.email_kontak && 'text-destructive')}>
                                    Email Bisnis <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="email_kontak"
                                    type="email"
                                    placeholder="Contoh: procurement@sumberpangan.co.id"
                                    value={data.email_kontak}
                                    onChange={(e) => {
                                        setData('email_kontak', e.target.value);
                                        clearErrors('email_kontak');
                                        if (otpSent) {
                                            // The OTP belongs to the previous address.
                                            setOtpSent(false);
                                            setData('otp', '');
                                            resend.start(0);
                                        }
                                    }}
                                    aria-invalid={!!errors.email_kontak}
                                    className={cn('h-10 w-full', errors.email_kontak && 'border-destructive focus-visible:ring-destructive')}
                                />
                                {errors.email_kontak && (
                                    <p className="text-xs font-medium text-destructive flex items-center gap-1.5 mt-1">
                                        <AlertCircle className="size-3.5 shrink-0" />
                                        <span>{errors.email_kontak}</span>
                                    </p>
                                )}
                            </div>

                            {/* Nomor Telepon */}
                            <div className="space-y-1.5">
                                <Label htmlFor="no_telepon" className={cn('text-sm font-medium', errors.no_telepon && 'text-destructive')}>
                                    Nomor Telepon <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="no_telepon"
                                    type="tel"
                                    placeholder="Contoh: 0812-3456-7890"
                                    value={data.no_telepon}
                                    onChange={(e) => {
                                        setData('no_telepon', e.target.value);
                                        clearErrors('no_telepon');
                                    }}
                                    aria-invalid={!!errors.no_telepon}
                                    className={cn('h-10 w-full', errors.no_telepon && 'border-destructive focus-visible:ring-destructive')}
                                />
                                {errors.no_telepon && (
                                    <p className="text-xs font-medium text-destructive flex items-center gap-1.5 mt-1">
                                        <AlertCircle className="size-3.5 shrink-0" />
                                        <span>{errors.no_telepon}</span>
                                    </p>
                                )}
                            </div>
                        </div>

                        {/* Verifikasi Email (OTP) */}
                        <div className="rounded-xl border border-border bg-muted/30 p-4 space-y-4">
                            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div className="text-sm">
                                    <p className="font-semibold text-foreground">Verifikasi Email Bisnis</p>
                                    <p className="text-xs text-muted-foreground mt-0.5">
                                        Kami kirim kode 6 digit untuk memastikan email di atas aktif dan milik Anda.
                                    </p>
                                </div>
                                <LoadingButton
                                    type="button"
                                    variant="outline"
                                    onClick={sendOtp}
                                    loading={otpRequest.processing}
                                    loadingText="Mengirim..."
                                    disabled={processing || resend.seconds > 0}
                                    className="shrink-0"
                                >
                                    {resend.seconds > 0
                                        ? `Kirim ulang (${resend.seconds}d)`
                                        : otpSent ? 'Kirim Ulang Kode' : 'Kirim Kode OTP'}
                                </LoadingButton>
                            </div>
                            {otpSent && (
                                <OtpField
                                    value={data.otp}
                                    onChange={(value) => {
                                        setData('otp', value);
                                        clearErrors('otp');
                                    }}
                                    error={errors.otp}
                                    disabled={processing}
                                />
                            )}
                            {!otpSent && errors.otp && (
                                <p className="text-xs font-medium text-destructive flex items-center gap-1.5">
                                    <AlertCircle className="size-3.5 shrink-0" />
                                    <span>{errors.otp}</span>
                                </p>
                            )}
                        </div>

                        {/* Alamat Lengkap */}
                        <div className="space-y-1.5">
                            <Label htmlFor="alamat" className={cn('text-sm font-medium', errors.alamat && 'text-destructive')}>
                                Alamat Lengkap <span className="text-destructive">*</span>
                            </Label>
                            <Textarea
                                id="alamat"
                                rows={3}
                                placeholder="Contoh: Kawasan Industri MM2100 Blok C-3 No. 8, Cikarang Barat, Kab. Bekasi, Jawa Barat 17530"
                                value={data.alamat}
                                onChange={(e) => {
                                    setData('alamat', e.target.value);
                                    clearErrors('alamat');
                                }}
                                aria-invalid={!!errors.alamat}
                                className={cn('w-full resize-none min-h-[80px]', errors.alamat && 'border-destructive focus-visible:ring-destructive')}
                            />
                            {errors.alamat && (
                                <p className="text-xs font-medium text-destructive flex items-center gap-1.5 mt-1">
                                    <AlertCircle className="size-3.5 shrink-0" />
                                    <span>{errors.alamat}</span>
                                </p>
                            )}
                        </div>

                        {/* Dokumen Legalitas (NPWP/SIUP) */}
                        <div className="space-y-1.5">
                            <Label className={cn('text-sm font-medium', errors.dokumen_legalitas && 'text-destructive')}>
                                Dokumen Legalitas (NPWP/SIUP) <span className="text-destructive">*</span>
                            </Label>
                            <FileDropzone
                                value={data.dokumen_legalitas}
                                onChange={(file) => {
                                    setData('dokumen_legalitas', file);
                                    clearErrors('dokumen_legalitas');
                                }}
                                onError={(errMsg) => {
                                    setError('dokumen_legalitas', errMsg);
                                    notify.validationFailed();
                                }}
                                error={errors.dokumen_legalitas}
                                disabled={processing}
                                accept={{
                                    'application/pdf': ['.pdf'],
                                    'image/jpeg': ['.jpg', '.jpeg'],
                                    'image/png': ['.png'],
                                }}
                                maxSize={2 * 1024 * 1024}
                                description="Format scan PDF, JPG, JPEG, atau PNG (Maksimal 2 MB). Contoh: NIB_PT_Sumber_Pangan.pdf"
                            />
                        </div>

                        {/* Submit Button */}
                        <div className="pt-2">
                            <LoadingButton
                                type="submit"
                                loading={processing}
                                loadingText="Mengirim Pendaftaran..."
                                className="w-full h-11 text-sm font-semibold rounded-xl bg-primary text-primary-foreground hover:bg-primary/90 transition-all shadow-sm"
                            >
                                <span>Kirim Pendaftaran Calon Vendor</span>
                            </LoadingButton>
                        </div>
                    </form>

                    {/* Footer note */}
                    <div className="mt-8 pt-6 border-t border-border text-center text-xs text-muted-foreground">
                        Sudah terdaftar dan memiliki akun vendor?{' '}
                        <Link href="/login" className="font-semibold text-foreground hover:underline">
                            Masuk ke Portal di sini
                        </Link>
                    </div>
                </div>
            </main>

            {/* Footer */}
            <footer className="text-center text-xs text-muted-foreground py-4">
                © {new Date().getFullYear()} PAGORA Procurement Platform. Hak Cipta Dilindungi.
            </footer>
        </div>
    );
}
