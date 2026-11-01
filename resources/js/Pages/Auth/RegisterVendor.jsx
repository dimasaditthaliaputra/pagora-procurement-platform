import { Head, Link, useForm, useHttp } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Combobox } from '@/Components/combobox';
import { FileDropzone } from '@/Components/file-dropzone';
import { uploadingLabel } from '@/lib/file';
import { useUploadFeedback } from '@/hooks/use-upload-feedback';
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
import { isEmail, isPhoneNumber, normalizePhoneNumber } from '@/lib/validators';

export default function RegisterVendor({ kategori_options = [], status, invitation = null }) {
    const form = useForm({
        company_name: invitation?.company_name ?? '',
        contact_email: invitation?.contact_email ?? '',
        phone: '',
        kategori_komoditas: invitation?.kategori_komoditas ?? '',
        address: '',
        otp: '',
        legal_document: null,
        invitation_token: invitation?.token ?? '',
    });
    const { data, setData, post, processing, errors, setError, clearErrors, reset } = form;
    const legalDocumentFeedback = useUploadFeedback(form, 'legal_document');

    const [otpSent, setOtpSent] = useState(false);
    const resend = useCountdown();
    const otpRequest = useHttp({ contact_email: '' });

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
        const email = data.contact_email.trim();

        if (!email) {
            setError('contact_email', 'Email bisnis wajib diisi.');
            notify.validationFailed();
            return;
        }
        if (!isEmail(email)) {
            setError('contact_email', 'Format email bisnis tidak valid.');
            notify.validationFailed();
            return;
        }

        clearErrors('contact_email', 'otp');
        otpRequest.transform(() => ({ contact_email: email }));
        otpRequest.post('/register/vendor/otp', {
            onSuccess: () => {
                setOtpSent(true);
                resend.start(60);
                notify.success('Kode OTP telah dikirim ke email bisnis Anda.');
            },
            onError: (errs) => {
                setError('contact_email', errs.contact_email ?? 'Gagal mengirim kode OTP.');
                notify.validationFailed();
            },
        });
    };

    const validate = () => {
        let hasError = false;
        clearErrors();

        const namaTrimmed = data.company_name.trim();
        if (!namaTrimmed) {
            setError('company_name', 'Nama perusahaan wajib diisi.');
            hasError = true;
        }

        const emailTrimmed = data.contact_email.trim();
        if (!emailTrimmed) {
            setError('contact_email', 'Email bisnis wajib diisi.');
            hasError = true;
        } else if (!isEmail(emailTrimmed)) {
            setError('contact_email', 'Format email bisnis tidak valid.');
            hasError = true;
        }

        const telpTrimmed = normalizePhoneNumber(data.phone);
        if (!telpTrimmed) {
            setError('phone', 'Nomor telepon wajib diisi.');
            hasError = true;
        } else if (!isPhoneNumber(telpTrimmed)) {
            setError('phone', 'Format nomor telepon tidak valid. Gunakan 8–15 digit angka, contoh: 081234567890.');
            hasError = true;
        }

        if (!data.kategori_komoditas) {
            setError('kategori_komoditas', 'Kategori komoditas wajib dipilih.');
            hasError = true;
        }

        const alamatTrimmed = data.address.trim();
        if (!alamatTrimmed) {
            setError('address', 'Alamat lengkap wajib diisi.');
            hasError = true;
        }

        if (!otpSent) {
            setError('otp', 'Verifikasi email terlebih dahulu dengan menekan "Kirim Kode OTP".');
            hasError = true;
        } else if (data.otp.length !== OTP_LENGTH) {
            setError('otp', `Kode OTP harus terdiri dari ${OTP_LENGTH} digit angka.`);
            hasError = true;
        }

        if (!data.legal_document) {
            setError('legal_document', 'Dokumen legalitas (NPWP/SIUP) wajib diunggah.');
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

                    {/* Invitation Banner */}
                    {invitation && (
                        <div className="mb-8 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-start gap-3 text-emerald-800 dark:text-emerald-300">
                            <ShieldCheck className="size-5 shrink-0 mt-0.5 text-emerald-600 dark:text-emerald-400" />
                            <div className="text-sm">
                                <p className="font-semibold">Pendaftaran melalui Undangan Resmi</p>
                                <p className="text-xs text-emerald-700/80 dark:text-emerald-400/80 mt-1 leading-relaxed">
                                    Anda mendaftar menggunakan tautan undangan resmi untuk rekanan <strong>{invitation.company_name}</strong>. Silakan lengkapi kontak, alamat, kode OTP email, dan dokumen legalitas perusahaan.
                                </p>
                            </div>
                        </div>
                    )}

                    {/* Form Input */}
                    <form noValidate onSubmit={handleSubmit} className="space-y-6">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-5 items-start">
                            {/* Nama Perusahaan */}
                            <div className="space-y-1.5">
                                <Label htmlFor="company_name" className={cn('text-sm font-medium', errors.company_name && 'text-destructive')}>
                                    Nama Perusahaan <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="company_name"
                                    type="text"
                                    placeholder="Contoh: PT Sumber Pangan Makmur"
                                    value={data.company_name}
                                    onChange={(e) => {
                                        setData('company_name', e.target.value);
                                        clearErrors('company_name');
                                    }}
                                    aria-invalid={!!errors.company_name}
                                    className={cn('h-10 w-full', errors.company_name && 'border-destructive focus-visible:ring-destructive')}
                                />
                                {errors.company_name && (
                                    <p className="text-xs font-medium text-destructive flex items-center gap-1.5 mt-1">
                                        <AlertCircle className="size-3.5 shrink-0" />
                                        <span>{errors.company_name}</span>
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
                                <Label htmlFor="contact_email" className={cn('text-sm font-medium', errors.contact_email && 'text-destructive')}>
                                    Email Bisnis <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="contact_email"
                                    type="email"
                                    placeholder="Contoh: procurement@sumberpangan.co.id"
                                    value={data.contact_email}
                                    onChange={(e) => {
                                        setData('contact_email', e.target.value);
                                        clearErrors('contact_email');
                                        if (otpSent) {
                                            // The OTP belongs to the previous address.
                                            setOtpSent(false);
                                            setData('otp', '');
                                            resend.start(0);
                                        }
                                    }}
                                    aria-invalid={!!errors.contact_email}
                                    className={cn('h-10 w-full', errors.contact_email && 'border-destructive focus-visible:ring-destructive')}
                                />
                                {errors.contact_email && (
                                    <p className="text-xs font-medium text-destructive flex items-center gap-1.5 mt-1">
                                        <AlertCircle className="size-3.5 shrink-0" />
                                        <span>{errors.contact_email}</span>
                                    </p>
                                )}
                            </div>

                            {/* Nomor Telepon */}
                            <div className="space-y-1.5">
                                <Label htmlFor="phone" className={cn('text-sm font-medium', errors.phone && 'text-destructive')}>
                                    Nomor Telepon <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="phone"
                                    type="tel"
                                    placeholder="Contoh: 0812-3456-7890"
                                    value={data.phone}
                                    onChange={(e) => {
                                        setData('phone', e.target.value);
                                        clearErrors('phone');
                                    }}
                                    onBlur={() => setData('phone', normalizePhoneNumber(data.phone))}
                                    aria-invalid={!!errors.phone}
                                    className={cn('h-10 w-full', errors.phone && 'border-destructive focus-visible:ring-destructive')}
                                />
                                {errors.phone && (
                                    <p className="text-xs font-medium text-destructive flex items-center gap-1.5 mt-1">
                                        <AlertCircle className="size-3.5 shrink-0" />
                                        <span>{errors.phone}</span>
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
                            <Label htmlFor="address" className={cn('text-sm font-medium', errors.address && 'text-destructive')}>
                                Alamat Lengkap <span className="text-destructive">*</span>
                            </Label>
                            <Textarea
                                id="address"
                                rows={3}
                                placeholder="Contoh: Kawasan Industri MM2100 Blok C-3 No. 8, Cikarang Barat, Kab. Bekasi, Jawa Barat 17530"
                                value={data.address}
                                onChange={(e) => {
                                    setData('address', e.target.value);
                                    clearErrors('address');
                                }}
                                aria-invalid={!!errors.address}
                                className={cn('w-full resize-none min-h-[80px]', errors.address && 'border-destructive focus-visible:ring-destructive')}
                            />
                            {errors.address && (
                                <p className="text-xs font-medium text-destructive flex items-center gap-1.5 mt-1">
                                    <AlertCircle className="size-3.5 shrink-0" />
                                    <span>{errors.address}</span>
                                </p>
                            )}
                        </div>

                        {/* Dokumen Legalitas (NPWP/SIUP) */}
                        <div className="space-y-1.5">
                            <Label className={cn('text-sm font-medium', errors.legal_document && 'text-destructive')}>
                                Dokumen Legalitas (NPWP/SIUP) <span className="text-destructive">*</span>
                            </Label>
                            <FileDropzone
                                value={data.legal_document}
                                onChange={(file) => {
                                    setData('legal_document', file);
                                    clearErrors('legal_document');
                                }}
                                onError={(errMsg) => {
                                    setError('legal_document', errMsg);
                                    notify.validationFailed();
                                }}
                                error={errors.legal_document}
                                {...legalDocumentFeedback}
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
                                loadingText={uploadingLabel(legalDocumentFeedback.progress, 'Mengirim Pendaftaran...')}
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
