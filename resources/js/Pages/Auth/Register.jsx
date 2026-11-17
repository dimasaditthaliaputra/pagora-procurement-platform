import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { PasswordInput } from '@/Components/password-input';
import { LoadingButton } from '@/Components/loading-button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { useDropzone } from 'react-dropzone';
import { describeRejection, formatBytes } from '@/lib/file';
import { useUploadFeedback } from '@/hooks/use-upload-feedback';
import { FileDropzone } from '@/Components/file-dropzone';
import { OtpField, OTP_LENGTH } from '@/Components/otp-field';
import { useCountdown } from '@/hooks/use-countdown';
import { FlashHandler } from '@/Components/flash-handler';
import { notify } from '@/lib/notify';
import { cn } from '@/lib/utils';
import { isEmail, isNpwp, isPhoneNumber, normalizeNpwp, normalizePhoneNumber } from '@/lib/validators';
import {
    Building2,
    ArrowRight,
    ArrowLeft,
    CheckCircle2,
    ShieldCheck,
    AlertCircle,
    ChevronLeft,
    ChevronRight,
    UploadCloud,
    FileText,
    Trash2,
} from 'lucide-react';

const STEPS = [
    { id: 1, label: 'Akun' },
    { id: 2, label: 'Verifikasi' },
    { id: 3, label: 'Perusahaan' },
    { id: 4, label: 'Legalitas' },
];

const DOCUMENT_TYPES = [
    { value: 'npwp', label: 'NPWP Perusahaan' },
    { value: 'siup', label: 'SIUP (Surat Izin Usaha Perdagangan)' },
    { value: 'nib', label: 'NIB (Nomor Induk Berusaha)' },
];

const MAX_DOCUMENT_SIZE = 5 * 1024 * 1024;
const MAX_LEGAL_DOCUMENTS = 10;
const PDF_ACCEPT = { 'application/pdf': ['.pdf'] };

export default function Register({ google_user = null, status = null }) {
    const isGoogle = Boolean(google_user?.email);
    const [step, setStep] = useState(() => (isGoogle ? 3 : 1));

    const form = useForm({
        name: google_user?.name ?? '',
        email: google_user?.email ?? '',
        otp: isGoogle ? '000000' : '',
        password: '',
        password_confirmation: '',
        nama_perusahaan: '',
        nomor_npwp: '',
        telepon: '',
        alamat: '',
        dokumen_npwp: null,
        dokumen_nib: null,
        dokumen_ktp: null,
        nomor_nib: '',
        nomor_ktp: '',
        legal_documents: [],
        google_id: google_user?.google_id ?? '',
        avatar: google_user?.avatar ?? '',
        is_google_registration: isGoogle,
    });

    const { data, setData, post, processing, errors, setError, clearErrors, progress } = form;
    const npwpFeedback = useUploadFeedback(form, 'dokumen_npwp');
    const nibFeedback = useUploadFeedback(form, 'dokumen_nib');
    const ktpFeedback = useUploadFeedback(form, 'dokumen_ktp');
    const legalDocumentFeedback = useUploadFeedback(form, 'legal_documents');

    const [otpSent, setOtpSent] = useState(false);
    const [lastOtpEmail, setLastOtpEmail] = useState('');
    const [verifiedOtpInfo, setVerifiedOtpInfo] = useState(null);
    const [verifyingOtp, setVerifyingOtp] = useState(false);
    const [sendingOtp, setSendingOtp] = useState(false);
    const resend = useCountdown();

    const clearLegalDocumentErrors = () => {
        const docErrorKeys = Object.keys(errors).filter(
            (key) =>
                key === 'legal_documents' ||
                key.startsWith('legal_documents.') ||
                key === 'dokumen_npwp' ||
                key === 'dokumen_nib' ||
                key === 'dokumen_ktp' ||
                key === 'file_npwp' ||
                key === 'file_nib' ||
                key === 'file_ktp' ||
                key === 'nomor_nib' ||
                key === 'nomor_ktp'
        );
        if (docErrorKeys.length > 0) {
            clearErrors(...docErrorKeys);
        }
    };

    const validateLegalFile = (file, fieldKey, fieldLabel) => {
        if (!file) {
            setError(fieldKey, `${fieldLabel} wajib diunggah.`);
            return false;
        }

        if (!(file instanceof File)) {
            setError(fieldKey, `Berkas ${fieldLabel.toLowerCase()} tidak valid.`);
            return false;
        }

        const isPdf =
            file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
        if (!isPdf) {
            setError(
                fieldKey,
                `Format berkas ${fieldLabel.toLowerCase()} harus berupa PDF (.pdf).`
            );
            return false;
        }

        if (file.size <= 0) {
            setError(
                fieldKey,
                `Berkas ${fieldLabel.toLowerCase()} kosong (0 byte).`
            );
            return false;
        }

        if (file.size > MAX_DOCUMENT_SIZE) {
            setError(
                fieldKey,
                `Ukuran berkas ${fieldLabel.toLowerCase()} (${formatBytes(file.size)}) melebihi batas maksimal 5 MB.`
            );
            return false;
        }

        return true;
    };

    const hasLegalDocumentsError = Object.keys(errors).some(
        (key) =>
            key === 'legal_documents' ||
            key.startsWith('legal_documents.') ||
            key === 'dokumen_npwp' ||
            key === 'dokumen_nib' ||
            key === 'dokumen_ktp' ||
            key === 'file_npwp' ||
            key === 'file_nib' ||
            key === 'file_ktp'
    );

    const isOtpVerified = isGoogle || Boolean(
        verifiedOtpInfo &&
        verifiedOtpInfo.email === data.email.trim().toLowerCase() &&
        verifiedOtpInfo.otp === data.otp &&
        data.otp.length === OTP_LENGTH
    );

    const sendOtp = async (onSuccessCallback) => {
        const trimmedEmail = data.email.trim();

        if (!trimmedEmail) {
            setError('email', 'Alamat email bisnis wajib diisi.');
            notify.validationFailed();
            return false;
        }
        if (!isEmail(trimmedEmail)) {
            setError('email', 'Format alamat email tidak valid.');
            notify.validationFailed();
            return false;
        }

        clearErrors('email', 'otp');
        setVerifiedOtpInfo(null);
        setData('otp', '');
        setSendingOtp(true);

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const response = await fetch('/register/otp/kirim', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                },
                body: JSON.stringify({
                    email: trimmedEmail,
                }),
            });

            const result = await response.json();

            if (response.ok && result.success) {
                setOtpSent(true);
                setLastOtpEmail(trimmedEmail.toLowerCase());
                resend.start(60);
                notify.success(result.message || 'Kode OTP telah dikirim ke email bisnis Anda.');
                if (typeof onSuccessCallback === 'function') {
                    onSuccessCallback();
                } else {
                    setStep(2);
                }
                return true;
            }

            const errorMsg =
                result.errors?.email?.[0] ||
                result.errors?.email ||
                result.errors?.otp?.[0] ||
                result.errors?.otp ||
                result.message ||
                'Gagal mengirim kode OTP.';

            if (step === 2) {
                setError('otp', errorMsg);
            } else {
                setError('email', errorMsg);
            }
            notify.error(errorMsg);
            return false;
        } catch {
            const errorMsg = 'Terjadi kesalahan saat mengirim kode OTP. Silakan periksa koneksi Anda.';
            if (step === 2) {
                setError('otp', errorMsg);
            } else {
                setError('email', errorMsg);
            }
            notify.error(errorMsg);
            return false;
        } finally {
            setSendingOtp(false);
        }
    };

    const verifyOtpWithServer = async (email, otp) => {
        if (!email || otp.length !== OTP_LENGTH) {
            return false;
        }

        setVerifyingOtp(true);
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const response = await fetch('/register/otp/verifikasi', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                },
                body: JSON.stringify({
                    email,
                    otp,
                }),
            });

            const result = await response.json();

            if (response.ok && result.success) {
                setVerifiedOtpInfo({ email: email.toLowerCase(), otp });
                clearErrors('otp');
                notify.success('Kode OTP berhasil diverifikasi.');
                return true;
            }

            const errorMsg =
                result.errors?.otp?.[0] ||
                result.errors?.otp ||
                result.errors?.email?.[0] ||
                result.message ||
                'Kode OTP salah. Pastikan kode sesuai dengan yang dikirim ke email bisnis Anda.';

            setError('otp', errorMsg);
            setVerifiedOtpInfo(null);
            notify.validationFailed();
            return false;
        } catch {
            const errorMsg = 'Terjadi kesalahan saat memverifikasi kode OTP. Silakan coba lagi.';
            setError('otp', errorMsg);
            setVerifiedOtpInfo(null);
            notify.validationFailed();
            return false;
        } finally {
            setVerifyingOtp(false);
        }
    };

    const handleOtpChange = (value) => {
        setData('otp', value);
        clearErrors('otp');

        if (verifiedOtpInfo && value !== verifiedOtpInfo.otp) {
            setVerifiedOtpInfo(null);
        }

        if (value.length === OTP_LENGTH && data.email.trim() && isEmail(data.email.trim())) {
            verifyOtpWithServer(data.email.trim(), value).then((valid) => {
                if (valid) {
                    setStep(3);
                }
            });
        }
    };

    const validateStep1 = () => {
        if (isGoogle) {
            return true;
        }

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
        } else if (!isEmail(trimmedEmail)) {
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
            requestAnimationFrame(() => {
                const firstInvalid = document.querySelector('[aria-invalid="true"]');
                firstInvalid?.focus();
            });
            return false;
        }

        return true;
    };

    const handleNextStep1 = () => {
        if (!validateStep1()) {
            return;
        }

        if (isGoogle) {
            setStep(3);
            return;
        }

        const currentEmail = data.email.trim().toLowerCase();
        const emailNeedsOtp = !otpSent || lastOtpEmail !== currentEmail;

        if (emailNeedsOtp) {
            sendOtp(() => {
                setStep(2);
            });
        } else if (isOtpVerified) {
            setStep(3);
        } else {
            setStep(2);
        }
    };

    const handleVerifyOtpAndNext = async () => {
        if (!data.otp) {
            setError('otp', 'Kode OTP wajib diisi.');
            notify.validationFailed();
            return;
        }
        if (data.otp.length !== OTP_LENGTH) {
            setError('otp', `Kode OTP harus terdiri dari ${OTP_LENGTH} digit angka.`);
            notify.validationFailed();
            return;
        }

        if (isOtpVerified) {
            setStep(3);
            return;
        }

        const valid = await verifyOtpWithServer(data.email.trim(), data.otp);
        if (valid) {
            setStep(3);
        }
    };

    const validateStep3 = () => {
        let hasError = false;
        clearErrors('nama_perusahaan', 'telepon', 'alamat');

        const trimmedPerusahaan = data.nama_perusahaan.trim();
        if (!trimmedPerusahaan) {
            setError('nama_perusahaan', 'Nama perusahaan wajib diisi.');
            hasError = true;
        } else if (trimmedPerusahaan.length < 3) {
            setError('nama_perusahaan', 'Nama perusahaan minimal 3 karakter.');
            hasError = true;
        }

        const normalizedTelepon = normalizePhoneNumber(data.telepon);
        if (!normalizedTelepon) {
            setError('telepon', 'Nomor telepon perusahaan wajib diisi.');
            hasError = true;
        } else if (!isPhoneNumber(normalizedTelepon)) {
            setError(
                'telepon',
                'Format nomor telepon tidak valid. Gunakan 8–15 digit angka, contoh: 081234567890.'
            );
            hasError = true;
        } else if (normalizedTelepon !== data.telepon) {
            setData('telepon', normalizedTelepon);
        }

        const trimmedAlamat = data.alamat.trim();
        if (!trimmedAlamat) {
            setError('alamat', 'Alamat lengkap perusahaan wajib diisi.');
            hasError = true;
        }

        if (hasError) {
            notify.validationFailed();
            requestAnimationFrame(() => {
                const firstInvalid = document.querySelector('[aria-invalid="true"]');
                firstInvalid?.focus();
            });
            return false;
        }

        return true;
    };

    const handleNextStep3 = () => {
        if (validateStep3()) {
            setStep(4);
        }
    };

    const validateStep4 = () => {
        let hasError = false;
        clearErrors('nomor_npwp', 'nomor_nib', 'nomor_ktp');
        clearLegalDocumentErrors();

        const normalizedNpwp = normalizeNpwp(data.nomor_npwp);
        if (!normalizedNpwp) {
            setError('nomor_npwp', 'Nomor NPWP wajib diisi.');
            hasError = true;
        } else if (!isNpwp(normalizedNpwp)) {
            setError(
                'nomor_npwp',
                'Format nomor NPWP tidak valid. Gunakan format 15 digit (contoh: 01.234.567.8-901.000) atau 16 digit angka.'
            );
            hasError = true;
        } else if (normalizedNpwp !== data.nomor_npwp) {
            setData('nomor_npwp', normalizedNpwp);
        }

        if (!validateLegalFile(data.dokumen_npwp, 'dokumen_npwp', 'Dokumen NPWP Perusahaan')) {
            hasError = true;
        }

        const trimmedNib = (data.nomor_nib || '').trim();
        if (!trimmedNib) {
            setError('nomor_nib', 'Nomor NIB perusahaan wajib diisi.');
            hasError = true;
        } else if (trimmedNib.length < 3) {
            setError('nomor_nib', 'Nomor NIB minimal 3 karakter.');
            hasError = true;
        }

        if (!validateLegalFile(data.dokumen_nib, 'dokumen_nib', 'Dokumen NIB Perusahaan')) {
            hasError = true;
        }

        const trimmedKtp = (data.nomor_ktp || '').trim();
        if (!trimmedKtp) {
            setError('nomor_ktp', 'Nomor KTP / NIK penanggung jawab wajib diisi.');
            hasError = true;
        } else if (trimmedKtp.length < 8) {
            setError('nomor_ktp', 'Nomor KTP / NIK minimal 8 karakter.');
            hasError = true;
        }

        if (!validateLegalFile(data.dokumen_ktp, 'dokumen_ktp', 'Dokumen KTP Penanggung Jawab')) {
            hasError = true;
        }

        if (hasError) {
            notify.validationFailed();
            requestAnimationFrame(() => {
                const firstInvalid = document.querySelector('[aria-invalid="true"]');
                firstInvalid?.focus();
            });
            return false;
        }

        return true;
    };

    const handleStepClick = (targetStep) => {
        if (processing || verifyingOtp || sendingOtp || targetStep === step) {
            return;
        }

        if (targetStep === 2 && isGoogle) {
            setStep(3);
            return;
        }

        if (targetStep < step) {
            setStep(targetStep);
            return;
        }

        if (step === 1 && !validateStep1()) {
            return;
        }

        if (targetStep >= 3 && !isGoogle && !isOtpVerified) {
            setStep(2);
            return;
        }

        if (targetStep === 4) {
            if (!validateStep3()) {
                setStep(3);
                return;
            }
        }

        setStep(targetStep);
    };

    const submit = (e) => {
        e.preventDefault();

        if (!validateStep1()) {
            setStep(1);
            return;
        }

        if (!isOtpVerified) {
            setStep(2);
            setError('otp', 'Silakan verifikasi email bisnis Anda dengan kode OTP terlebih dahulu.');
            return;
        }

        if (!validateStep3()) {
            setStep(3);
            return;
        }

        if (!validateStep4()) {
            setStep(4);
            return;
        }

        post('/register', {
            forceFormData: true,
            onError: (errs) => {
                notify.validationFailed();
                if (errs.name || errs.email || errs.password || errs.password_confirmation) {
                    setStep(1);
                } else if (errs.otp && !isGoogle) {
                    setVerifiedOtpInfo(null);
                    setStep(2);
                } else if (errs.nama_perusahaan || errs.telepon || errs.alamat) {
                    setStep(3);
                } else {
                    setStep(4);
                }
                requestAnimationFrame(() => {
                    const firstInvalid = document.querySelector('[aria-invalid="true"]');
                    firstInvalid?.focus();
                });
            },
        });
    };

    return (
        <div className="grid min-h-screen lg:h-screen lg:overflow-hidden lg:grid-cols-2 bg-background text-foreground">
            <Head title="Pendaftaran Akun Tenant - PAGORA" />
            <FlashHandler />

            {/* Left Column - Form */}
            <div className="flex flex-col justify-between h-full p-6 md:p-8 lg:p-10 overflow-y-auto">
                {/* Top Nav: Kembali ke Beranda */}
                <div className="flex items-center justify-between shrink-0 mb-2">
                    <Link
                        href="/"
                        className="inline-flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground transition-colors group cursor-pointer"
                    >
                        <ArrowLeft className="size-3.5 transition-transform group-hover:-translate-x-0.5" />
                        <span>Kembali ke Beranda</span>
                    </Link>

                    <Link
                        href="/login"
                        className="text-xs text-muted-foreground hover:text-foreground transition-colors cursor-pointer sm:hidden"
                    >
                        Sudah punya akun? Masuk
                    </Link>
                </div>

                {/* Form Body Area */}
                <div className="flex flex-1 items-center justify-center py-2 md:py-4">
                    <div className="w-full max-w-md">
                        {/* Stepper Progress (4 Langkah: Akun, Verifikasi, Perusahaan, Legalitas) */}
                        <div className="grid grid-cols-4 gap-1 sm:gap-1.5 mb-5 p-1 rounded-xl bg-muted/50 border border-border/70 text-xs select-none">
                            {STEPS.map((item) => {
                                const isActive = step === item.id;
                                const isCompleted = step > item.id || (isGoogle && item.id < 3);

                                return (
                                    <button
                                        key={item.id}
                                        type="button"
                                        onClick={() => handleStepClick(item.id)}
                                        disabled={
                                            processing ||
                                            verifyingOtp ||
                                            sendingOtp ||
                                            (item.id === 2 && (isGoogle || !otpSent)) ||
                                            (item.id >= 3 && !isOtpVerified)
                                        }
                                        className={cn(
                                            'flex items-center justify-center gap-1 sm:gap-1.5 px-1.5 sm:px-2.5 py-1.5 rounded-lg font-medium transition-all text-center cursor-pointer disabled:cursor-not-allowed disabled:opacity-50 text-[11px] sm:text-xs',
                                            isActive
                                                ? 'bg-background text-foreground shadow-xs'
                                                : 'text-muted-foreground hover:text-foreground'
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                'flex items-center justify-center size-5 rounded-full text-[11px] font-semibold shrink-0',
                                                isActive
                                                    ? 'bg-primary text-primary-foreground'
                                                    : isCompleted
                                                        ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                                                        : 'bg-muted-foreground/20 text-muted-foreground'
                                            )}
                                        >
                                            {isCompleted ? <CheckCircle2 className="size-3.5" /> : item.id}
                                        </span>
                                        <span className="truncate">
                                            {isGoogle && item.id === 2 ? 'Verifikasi (Auto)' : item.label}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>

                        {/* Banner Akun Google */}
                        {isGoogle && (
                            <div className="mb-4 p-3 rounded-xl border border-blue-500/25 bg-blue-500/5 dark:bg-blue-950/20 flex items-center justify-between gap-3 text-xs">
                                <div className="flex items-center gap-2.5 min-w-0">
                                    {google_user?.avatar ? (
                                        <img
                                            src={google_user.avatar}
                                            alt={google_user.name || 'Google User'}
                                            className="size-8 rounded-full object-cover border border-border shrink-0"
                                        />
                                    ) : (
                                        <div className="size-8 rounded-full bg-blue-600/10 text-blue-600 font-bold flex items-center justify-center shrink-0">
                                            G
                                        </div>
                                    )}
                                    <div className="min-w-0">
                                        <p className="font-semibold text-foreground truncate">{google_user?.name || data.name}</p>
                                        <p className="text-muted-foreground text-[11px] truncate">
                                            {google_user?.email || data.email} • Terverifikasi Google
                                        </p>
                                    </div>
                                </div>
                                <Link
                                    href="/auth/google/cancel"
                                    method="post"
                                    as="button"
                                    className="text-[11px] font-medium text-destructive hover:underline shrink-0 cursor-pointer"
                                >
                                    Batalkan
                                </Link>
                            </div>
                        )}

                        {/* STEP 1: Profil Akun */}
                        {step === 1 && (
                            <form
                                noValidate
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    handleNextStep1();
                                }}
                                className="flex flex-col gap-4"
                            >
                                <div className="flex flex-col gap-1 text-center">
                                    <h1 className="text-2xl font-bold tracking-tight">Daftar Akun Tenant</h1>
                                    <p className="text-muted-foreground text-xs text-balance">
                                        Langkah 1 dari 4 - Masukkan profil perwakilan penanggung jawab akun
                                    </p>
                                </div>

                                {/* Callout Calon Vendor */}
                                <div className="p-3 rounded-xl border border-emerald-500/25 bg-emerald-500/5 dark:bg-emerald-950/20 flex items-start gap-2.5 text-xs">
                                    <div className="p-1 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5">
                                        <Building2 className="size-3.5" />
                                    </div>
                                    <div className="flex-1 text-left">
                                        <p className="font-semibold text-foreground">
                                            Perusahaan Pemasok Bahan Baku?
                                        </p>
                                        <p className="text-muted-foreground text-[11px] mt-0.5">
                                            Pendaftaran vendor memerlukan alur verifikasi khusus pemasok.
                                        </p>
                                        <Link
                                            href="/register/vendor"
                                            className="inline-flex items-center gap-1 font-semibold text-emerald-600 dark:text-emerald-400 hover:underline mt-1 text-[11px]"
                                        >
                                            <span>Daftar sebagai Calon Vendor</span>
                                            <ArrowRight className="size-3" />
                                        </Link>
                                    </div>
                                </div>

                                {/* Opsi Cepat: Daftar dengan Akun Google */}
                                {!isGoogle && (
                                    <div className="space-y-2.5">
                                        <a
                                            href="/auth/google/redirect?flow=tenant"
                                            className="inline-flex items-center justify-center gap-2.5 whitespace-nowrap rounded-xl text-xs sm:text-sm font-medium transition-all outline-none select-none border border-border bg-background hover:bg-muted/70 hover:text-foreground h-10 px-4 w-full cursor-pointer shadow-2xs"
                                        >
                                            <svg className="size-4 shrink-0" viewBox="0 0 24 24">
                                                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                                                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                                                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" />
                                                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" />
                                            </svg>
                                            <span>Daftar Cepat dengan Google</span>
                                        </a>

                                        <div className="relative text-center text-xs after:absolute after:inset-0 after:top-1/2 after:z-0 after:flex after:items-center after:border-t after:border-border my-1">
                                            <span className="relative z-10 px-2.5 bg-background text-muted-foreground font-medium text-[11px]">
                                                atau lengkapi formulir manual di bawah
                                            </span>
                                        </div>
                                    </div>
                                )}

                                <div className="grid gap-3">
                                    {/* Nama Lengkap */}
                                    <div className="grid gap-1">
                                        <div className="flex items-center justify-between">
                                            <Label htmlFor="name" className={errors.name ? 'text-destructive text-xs' : 'text-xs'}>
                                                Nama Lengkap <span className="text-destructive">*</span>
                                            </Label>
                                            {isGoogle && (
                                                <span className="text-[10px] text-muted-foreground bg-muted px-1.5 py-0.5 rounded">
                                                    Dari Akun Google
                                                </span>
                                            )}
                                        </div>
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
                                            disabled={sendingOtp || isGoogle}
                                            readOnly={isGoogle}
                                            className={cn(
                                                'h-9 text-sm',
                                                errors.name && 'border-destructive focus-visible:ring-destructive',
                                                isGoogle && 'bg-muted/50 cursor-not-allowed'
                                            )}
                                            autoFocus={!isGoogle}
                                        />
                                        {errors.name && (
                                            <p className="text-xs font-medium text-destructive">{errors.name}</p>
                                        )}
                                    </div>

                                    {/* Email Bisnis */}
                                    <div className="grid gap-1">
                                        <div className="flex items-center justify-between">
                                            <Label htmlFor="email" className={errors.email ? 'text-destructive text-xs' : 'text-xs'}>
                                                Email Bisnis <span className="text-destructive">*</span>
                                            </Label>
                                            {isGoogle && (
                                                <span className="text-[10px] text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 px-1.5 py-0.5 rounded font-medium">
                                                    Email Terverifikasi
                                                </span>
                                            )}
                                        </div>
                                        <Input
                                            id="email"
                                            type="email"
                                            name="email"
                                            value={data.email}
                                            onChange={(e) => {
                                                setData('email', e.target.value);
                                                if (errors.email) clearErrors('email');
                                                if (verifiedOtpInfo) setVerifiedOtpInfo(null);
                                            }}
                                            placeholder="Contoh: procurement@perusahaan.co.id"
                                            aria-invalid={!!errors.email}
                                            disabled={sendingOtp || isGoogle}
                                            readOnly={isGoogle}
                                            className={cn(
                                                'h-9 text-sm',
                                                errors.email && 'border-destructive focus-visible:ring-destructive',
                                                isGoogle && 'bg-muted/50 cursor-not-allowed'
                                            )}
                                        />
                                        {errors.email && (
                                            <p className="text-xs font-medium text-destructive">{errors.email}</p>
                                        )}
                                    </div>

                                    {/* Kata Sandi & Konfirmasi: Sembunyikan bila daftar via Google */}
                                    {isGoogle ? (
                                        <div className="p-3 rounded-xl border border-blue-500/25 bg-blue-500/5 dark:bg-blue-950/20 text-xs text-muted-foreground flex items-start gap-2.5">
                                            <ShieldCheck className="size-4 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" />
                                            <div className="flex-1">
                                                <p className="font-semibold text-foreground">
                                                    Kata Sandi Default &amp; OTP Otomatis
                                                </p>
                                                <p className="text-[11px] mt-0.5 leading-relaxed">
                                                    Identitas Google Anda sudah tervalidasi. Kata sandi default unik telah dibuatkan otomatis dan akan dikirimkan ke Gmail Anda setelah pendaftaran selesai.
                                                </p>
                                            </div>
                                        </div>
                                    ) : (
                                        <>
                                            {/* Kata Sandi */}
                                            <div className="grid gap-1">
                                                <Label htmlFor="password" className={errors.password ? 'text-destructive text-xs' : 'text-xs'}>
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
                                                    disabled={sendingOtp}
                                                />
                                                {errors.password && (
                                                    <p className="text-xs font-medium text-destructive">{errors.password}</p>
                                                )}
                                            </div>

                                            {/* Konfirmasi Kata Sandi */}
                                            <div className="grid gap-1">
                                                <Label
                                                    htmlFor="password_confirmation"
                                                    className={errors.password_confirmation ? 'text-destructive text-xs' : 'text-xs'}
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
                                                    disabled={sendingOtp}
                                                />
                                                {errors.password_confirmation && (
                                                    <p className="text-xs font-medium text-destructive">
                                                        {errors.password_confirmation}
                                                    </p>
                                                )}
                                            </div>

                                        </>
                                    )}

                                    {/* Navigation Controls: [ < ] (disabled) and [ Lanjut > ] */}
                                    <div className="flex items-center justify-between gap-3 pt-2">
                                        <p className="text-xs text-muted-foreground">
                                            Sudah memiliki akun?{' '}
                                            <Link href="/login" className="font-semibold text-foreground hover:underline">
                                                Masuk
                                            </Link>
                                        </p>

                                        <div className="flex items-center gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                disabled={true}
                                                aria-label="Langkah sebelumnya"
                                                className="size-10 rounded-xl opacity-40 cursor-not-allowed"
                                            >
                                                <ChevronLeft className="size-4" />
                                            </Button>

                                            <Button
                                                type="submit"
                                                size="icon"
                                                disabled={sendingOtp}
                                                aria-label="Lanjut ke langkah berikutnya"
                                                title="Lanjut ke langkah berikutnya"
                                                className="size-10 rounded-xl cursor-pointer"
                                            >
                                                {sendingOtp ? (
                                                    <span className="size-4 border-2 border-primary-foreground border-t-transparent rounded-full animate-spin" />
                                                ) : (
                                                    <ChevronRight className="size-4" />
                                                )}
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        )}

                        {/* STEP 2: Verifikasi OTP Email Bisnis (Memakai Halaman/Step OTP) */}
                        {step === 2 && (
                            <form
                                noValidate
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    handleVerifyOtpAndNext();
                                }}
                                className="flex flex-col gap-5"
                            >
                                <div className="flex flex-col gap-1.5 text-center">
                                    <h1 className="text-2xl font-bold tracking-tight">Verifikasi Email Bisnis</h1>
                                    <p className="text-muted-foreground text-sm text-balance">
                                        Langkah 2 dari 4 - Masukkan kode 6 digit yang kami kirimkan ke{' '}
                                        <span className="font-semibold text-foreground">{data.email}</span> untuk memverifikasi pendaftaran Anda.
                                    </p>
                                </div>

                                <div className="space-y-4 pt-1">
                                    <OtpField
                                        value={data.otp}
                                        onChange={handleOtpChange}
                                        error={errors.otp}
                                        disabled={processing || verifyingOtp}
                                        autoFocus
                                    />

                                    {verifyingOtp && (
                                        <p className="text-xs text-muted-foreground flex items-center justify-center gap-1.5">
                                            <span className="size-3.5 border-2 border-primary border-t-transparent rounded-full animate-spin" />
                                            <span>Memverifikasi kode OTP...</span>
                                        </p>
                                    )}

                                    <div className="flex flex-col items-center gap-2 pt-1">
                                        <LoadingButton
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => sendOtp()}
                                            loading={sendingOtp}
                                            loadingText="Mengajukan ulang..."
                                            disabled={processing || verifyingOtp || resend.seconds > 0}
                                            className="text-xs text-muted-foreground hover:text-foreground cursor-pointer"
                                        >
                                            {resend.seconds > 0
                                                ? `Ajukan ulang kode dalam ${resend.seconds} detik`
                                                : 'Tidak menerima kode? Ajukan kode baru'}
                                        </LoadingButton>
                                    </div>
                                </div>

                                {/* Navigation Controls: [ < ] and [ > ] */}
                                <div className="flex items-center justify-between gap-3 pt-3 border-t border-border/60">
                                    <p className="text-xs text-muted-foreground">
                                        Sudah memiliki akun?{' '}
                                        <Link href="/login" className="font-semibold text-foreground hover:underline">
                                            Masuk
                                        </Link>
                                    </p>

                                    <div className="flex items-center gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            onClick={() => setStep(1)}
                                            disabled={processing || verifyingOtp}
                                            aria-label="Kembali ke profil akun"
                                            title="Kembali ke profil akun"
                                            className="size-10 rounded-xl cursor-pointer"
                                        >
                                            <ChevronLeft className="size-4" />
                                        </Button>

                                        <Button
                                            type="submit"
                                            size="icon"
                                            disabled={processing || verifyingOtp || data.otp.length !== OTP_LENGTH}
                                            aria-label="Lanjut ke langkah berikutnya"
                                            title="Lanjut ke langkah berikutnya"
                                            className="size-10 rounded-xl cursor-pointer"
                                        >
                                            {verifyingOtp ? (
                                                <span className="size-4 border-2 border-primary-foreground border-t-transparent rounded-full animate-spin" />
                                            ) : (
                                                <ChevronRight className="size-4" />
                                            )}
                                        </Button>
                                    </div>
                                </div>
                            </form>
                        )}

                        {/* STEP 3: Data Profil Perusahaan Tenant */}
                        {step === 3 && (
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    handleNextStep3();
                                }}
                                className="flex flex-col gap-4"
                                noValidate
                            >
                                <div className="flex flex-col gap-1 text-center">
                                    <h1 className="text-2xl font-bold tracking-tight">Profil Perusahaan</h1>
                                    <p className="text-muted-foreground text-xs text-balance">
                                        Langkah 3 dari 4 - Lengkapi profil perusahaan buyer yang akan didaftarkan
                                    </p>
                                </div>

                                <div className="grid gap-3">
                                    {/* Nama Perusahaan */}
                                    <div className="grid gap-1">
                                        <Label htmlFor="nama_perusahaan" className={errors.nama_perusahaan ? 'text-destructive text-xs' : 'text-xs'}>
                                            Nama Perusahaan / Instansi <span className="text-destructive">*</span>
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
                                            disabled={processing}
                                            className={cn(
                                                'h-9 text-sm',
                                                errors.nama_perusahaan && 'border-destructive focus-visible:ring-destructive'
                                            )}
                                            autoFocus
                                        />
                                        {errors.nama_perusahaan && (
                                            <p className="text-xs font-medium text-destructive">{errors.nama_perusahaan}</p>
                                        )}
                                    </div>

                                    {/* Nomor Telepon Perusahaan */}
                                    <div className="grid gap-1">
                                        <Label htmlFor="telepon" className={errors.telepon ? 'text-destructive text-xs' : 'text-xs'}>
                                            Nomor Telepon Perusahaan <span className="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            id="telepon"
                                            type="tel"
                                            name="telepon"
                                            value={data.telepon}
                                            onChange={(e) => {
                                                setData('telepon', e.target.value);
                                                if (errors.telepon) clearErrors('telepon');
                                            }}
                                            onBlur={(e) => {
                                                const formatted = normalizePhoneNumber(e.target.value);
                                                if (formatted && formatted !== e.target.value) {
                                                    setData('telepon', formatted);
                                                }
                                            }}
                                            placeholder="Contoh: 081234567890 atau (021) 5551234"
                                            aria-invalid={!!errors.telepon}
                                            disabled={processing}
                                            className={cn(
                                                'h-9 text-sm',
                                                errors.telepon && 'border-destructive focus-visible:ring-destructive'
                                            )}
                                        />
                                        {errors.telepon && (
                                            <p className="text-xs font-medium text-destructive">{errors.telepon}</p>
                                        )}
                                    </div>

                                    {/* Alamat Lengkap */}
                                    <div className="grid gap-1">
                                        <Label htmlFor="alamat" className={errors.alamat ? 'text-destructive text-xs' : 'text-xs'}>
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
                                            placeholder="Contoh: Jl. Industri Raya No. 45, Kawasan MM2100, Cikarang Barat, Bekasi"
                                            aria-invalid={!!errors.alamat}
                                            disabled={processing}
                                            className={cn(
                                                'text-sm resize-none',
                                                errors.alamat && 'border-destructive focus-visible:ring-destructive'
                                            )}
                                        />
                                        {errors.alamat && (
                                            <p className="text-xs font-medium text-destructive">{errors.alamat}</p>
                                        )}
                                    </div>

                                    {/* Navigation Controls on Step 3: [ < ] and [ Lanjut > ] */}
                                    <div className="flex items-center justify-between gap-3 pt-2 border-t border-border/60">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            onClick={() => setStep(isGoogle ? 1 : 2)}
                                            disabled={processing}
                                            aria-label={isGoogle ? 'Kembali ke profil akun' : 'Kembali ke Verifikasi OTP'}
                                            title={isGoogle ? 'Kembali ke profil akun' : 'Kembali ke Verifikasi OTP'}
                                            className="size-10 rounded-xl cursor-pointer shrink-0"
                                        >
                                            <ChevronLeft className="size-4" />
                                        </Button>

                                        <Button
                                            type="submit"
                                            size="icon"
                                            disabled={processing}
                                            aria-label="Lanjut ke langkah berikutnya"
                                            title="Lanjut ke langkah berikutnya"
                                            className="size-10 rounded-xl cursor-pointer"
                                        >
                                            <ChevronRight className="size-4" />
                                        </Button>
                                    </div>
                                </div>
                            </form>
                        )}

                        {/* STEP 4: Dokumen Legalitas Tenant */}
                        {step === 4 && (
                            <form onSubmit={submit} className="flex flex-col gap-4" noValidate>
                                <div className="flex flex-col gap-1 text-center">
                                    <h1 className="text-2xl font-bold tracking-tight">Dokumen Legalitas Perusahaan</h1>
                                    <p className="text-muted-foreground text-xs text-balance">
                                        Langkah 4 dari 4 - Lengkapi nomor identitas dan unggah berkas legalitas resmi perusahaan berformat PDF maksimal 5 MB per berkas.
                                    </p>
                                </div>

                                <div className="space-y-4 pt-1" data-invalid={hasLegalDocumentsError}>
                                    {errors.legal_documents && (
                                        <div className="rounded-xl border border-destructive/30 bg-destructive/10 p-3 text-xs font-medium text-destructive flex items-start gap-2.5">
                                            <AlertCircle className="size-4 shrink-0 mt-0.5 text-destructive" />
                                            <p className="leading-relaxed">{errors.legal_documents}</p>
                                        </div>
                                    )}

                                    {/* 1. Card Dokumen NPWP Perusahaan */}
                                    <div className="space-y-3 p-3.5 sm:p-4 rounded-xl border border-border bg-card/40">
                                        <div className="flex items-center justify-between gap-2">
                                            <Label
                                                htmlFor="nomor_npwp"
                                                className={cn(
                                                    'text-xs font-semibold uppercase tracking-wider',
                                                    (errors.nomor_npwp || errors.dokumen_npwp || errors.file_npwp)
                                                        ? 'text-destructive'
                                                        : 'text-muted-foreground'
                                                )}
                                            >
                                                1. Dokumen NPWP Perusahaan <span className="text-destructive">*</span>
                                            </Label>
                                        </div>

                                        {/* Input Nomor NPWP diletakkan diatas dropzone upload */}
                                        <div className="space-y-1">
                                            <Label
                                                htmlFor="nomor_npwp"
                                                className={cn(
                                                    'text-xs font-normal text-muted-foreground',
                                                    errors.nomor_npwp && 'text-destructive'
                                                )}
                                            >
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
                                                onBlur={(e) => {
                                                    const formatted = normalizeNpwp(e.target.value);
                                                    if (formatted && formatted !== e.target.value) {
                                                        setData('nomor_npwp', formatted);
                                                    }
                                                }}
                                                placeholder="Contoh: 01.234.567.8-901.000 atau 16 digit"
                                                aria-invalid={!!errors.nomor_npwp}
                                                disabled={processing}
                                                className={cn(
                                                    'h-9 text-xs sm:text-sm',
                                                    errors.nomor_npwp && 'border-destructive focus-visible:ring-destructive'
                                                )}
                                            />
                                            {errors.nomor_npwp && (
                                                <p className="text-xs font-medium text-destructive">{errors.nomor_npwp}</p>
                                            )}
                                        </div>

                                        {/* Dropzone Upload Dokumen NPWP */}
                                        <div className="space-y-1">
                                            <FileDropzone
                                                value={data.dokumen_npwp}
                                                onChange={(file) => {
                                                    setData('dokumen_npwp', file);
                                                    if (errors.dokumen_npwp || errors.file_npwp) {
                                                        clearErrors('dokumen_npwp', 'file_npwp', 'legal_documents');
                                                    }
                                                }}
                                                onError={(msg) => {
                                                    setError('dokumen_npwp', msg);
                                                    notify.error(msg);
                                                }}
                                                error={errors.dokumen_npwp || errors.file_npwp}
                                                accept={PDF_ACCEPT}
                                                maxSize={MAX_DOCUMENT_SIZE}
                                                disabled={processing}
                                                uploading={npwpFeedback.uploading}
                                                progress={npwpFeedback.progress}
                                                status={npwpFeedback.status}
                                                label="Unggah Berkas NPWP"
                                                description="Hanya format PDF (.pdf) dengan ukuran maksimal 5 MB"
                                            />
                                            {(errors.dokumen_npwp || errors.file_npwp) && (
                                                <p className="text-xs font-medium text-destructive flex items-center gap-1.5 mt-1">
                                                    <AlertCircle className="size-3.5 shrink-0" />
                                                    <span>{errors.dokumen_npwp || errors.file_npwp}</span>
                                                </p>
                                            )}
                                        </div>
                                    </div>

                                    {/* 2. Card Dokumen NIB Perusahaan */}
                                    <div className="space-y-3 p-3.5 sm:p-4 rounded-xl border border-border bg-card/40">
                                        <div className="flex items-center justify-between gap-2">
                                            <Label
                                                htmlFor="tenant_nomor_nib"
                                                className={cn(
                                                    'text-xs font-semibold uppercase tracking-wider',
                                                    (errors.nomor_nib || errors.dokumen_nib || errors.file_nib)
                                                        ? 'text-destructive'
                                                        : 'text-muted-foreground'
                                                )}
                                            >
                                                2. Dokumen NIB (Nomor Induk Berusaha) <span className="text-destructive">*</span>
                                            </Label>
                                        </div>

                                        {/* Input Nomor NIB diletakkan diatas dropzone upload */}
                                        <div className="space-y-1">
                                            <Label
                                                htmlFor="tenant_nomor_nib"
                                                className={cn(
                                                    'text-xs font-normal text-muted-foreground',
                                                    errors.nomor_nib && 'text-destructive'
                                                )}
                                            >
                                                Nomor NIB Perusahaan <span className="text-destructive">*</span>
                                            </Label>
                                            <Input
                                                id="tenant_nomor_nib"
                                                type="text"
                                                placeholder="Contoh: 1234567890123"
                                                value={data.nomor_nib}
                                                onChange={(e) => {
                                                    setData('nomor_nib', e.target.value);
                                                    if (errors.nomor_nib) clearErrors('nomor_nib');
                                                }}
                                                disabled={processing}
                                                aria-invalid={!!errors.nomor_nib}
                                                className={cn(
                                                    'h-9 text-xs sm:text-sm',
                                                    errors.nomor_nib && 'border-destructive focus-visible:ring-destructive'
                                                )}
                                            />
                                            {errors.nomor_nib && (
                                                <p className="text-xs font-medium text-destructive">
                                                    {errors.nomor_nib}
                                                </p>
                                            )}
                                        </div>

                                        {/* Dropzone Upload Dokumen NIB */}
                                        <div className="space-y-1">
                                            <FileDropzone
                                                value={data.dokumen_nib}
                                                onChange={(file) => {
                                                    setData('dokumen_nib', file);
                                                    if (errors.dokumen_nib || errors.file_nib) {
                                                        clearErrors('dokumen_nib', 'file_nib', 'legal_documents');
                                                    }
                                                }}
                                                onError={(msg) => {
                                                    setError('dokumen_nib', msg);
                                                    notify.error(msg);
                                                }}
                                                error={errors.dokumen_nib || errors.file_nib}
                                                accept={PDF_ACCEPT}
                                                maxSize={MAX_DOCUMENT_SIZE}
                                                disabled={processing}
                                                uploading={nibFeedback.uploading}
                                                progress={nibFeedback.progress}
                                                status={nibFeedback.status}
                                                label="Unggah Berkas NIB"
                                                description="Hanya format PDF (.pdf) dengan ukuran maksimal 5 MB"
                                            />
                                            {(errors.dokumen_nib || errors.file_nib) && (
                                                <p className="text-xs font-medium text-destructive flex items-center gap-1.5 mt-1">
                                                    <AlertCircle className="size-3.5 shrink-0" />
                                                    <span>{errors.dokumen_nib || errors.file_nib}</span>
                                                </p>
                                            )}
                                        </div>
                                    </div>

                                    {/* 3. Card Dokumen KTP Penanggung Jawab */}
                                    <div className="space-y-3 p-3.5 sm:p-4 rounded-xl border border-border bg-card/40">
                                        <div className="flex items-center justify-between gap-2">
                                            <Label
                                                htmlFor="tenant_nomor_ktp"
                                                className={cn(
                                                    'text-xs font-semibold uppercase tracking-wider',
                                                    (errors.nomor_ktp || errors.dokumen_ktp || errors.file_ktp)
                                                        ? 'text-destructive'
                                                        : 'text-muted-foreground'
                                                )}
                                            >
                                                3. Dokumen KTP Penanggung Jawab <span className="text-destructive">*</span>
                                            </Label>
                                        </div>

                                        {/* Input Nomor KTP diletakkan diatas dropzone upload */}
                                        <div className="space-y-1">
                                            <Label
                                                htmlFor="tenant_nomor_ktp"
                                                className={cn(
                                                    'text-xs font-normal text-muted-foreground',
                                                    errors.nomor_ktp && 'text-destructive'
                                                )}
                                            >
                                                Nomor KTP / NIK Penanggung Jawab <span className="text-destructive">*</span>
                                            </Label>
                                            <Input
                                                id="tenant_nomor_ktp"
                                                type="text"
                                                placeholder="Contoh: 3171012345670001"
                                                value={data.nomor_ktp}
                                                onChange={(e) => {
                                                    setData('nomor_ktp', e.target.value);
                                                    if (errors.nomor_ktp) clearErrors('nomor_ktp');
                                                }}
                                                disabled={processing}
                                                aria-invalid={!!errors.nomor_ktp}
                                                className={cn(
                                                    'h-9 text-xs sm:text-sm',
                                                    errors.nomor_ktp && 'border-destructive focus-visible:ring-destructive'
                                                )}
                                            />
                                            {errors.nomor_ktp && (
                                                <p className="text-xs font-medium text-destructive">
                                                    {errors.nomor_ktp}
                                                </p>
                                            )}
                                        </div>

                                        {/* Dropzone Upload Dokumen KTP */}
                                        <div className="space-y-1">
                                            <FileDropzone
                                                value={data.dokumen_ktp}
                                                onChange={(file) => {
                                                    setData('dokumen_ktp', file);
                                                    if (errors.dokumen_ktp || errors.file_ktp) {
                                                        clearErrors('dokumen_ktp', 'file_ktp', 'legal_documents');
                                                    }
                                                }}
                                                onError={(msg) => {
                                                    setError('dokumen_ktp', msg);
                                                    notify.error(msg);
                                                }}
                                                error={errors.dokumen_ktp || errors.file_ktp}
                                                accept={PDF_ACCEPT}
                                                maxSize={MAX_DOCUMENT_SIZE}
                                                disabled={processing}
                                                uploading={ktpFeedback.uploading}
                                                progress={ktpFeedback.progress}
                                                status={ktpFeedback.status}
                                                label="Unggah Berkas KTP Penanggung Jawab"
                                                description="Hanya format PDF (.pdf) dengan ukuran maksimal 5 MB"
                                            />
                                            {(errors.dokumen_ktp || errors.file_ktp) && (
                                                <p className="text-xs font-medium text-destructive flex items-center gap-1.5 mt-1">
                                                    <AlertCircle className="size-3.5 shrink-0" />
                                                    <span>{errors.dokumen_ktp || errors.file_ktp}</span>
                                                </p>
                                            )}
                                        </div>
                                    </div>

                                    {/* Navigation Controls on Step 4: [ < ] and Submit Button */}
                                    <div className="flex items-center justify-between gap-3 pt-2 border-t border-border/60">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            onClick={() => setStep(3)}
                                            disabled={processing}
                                            aria-label="Kembali ke profil perusahaan"
                                            title="Kembali ke profil perusahaan"
                                            className="size-10 rounded-xl cursor-pointer shrink-0"
                                        >
                                            <ChevronLeft className="size-4" />
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
                            </form>
                        )}
                    </div>
                </div>

                {/* Footer Copyright */}
                <div className="text-center text-xs text-muted-foreground shrink-0 mt-2">
                    © {new Date().getFullYear()} PAGORA Enterprise E-Procurement
                </div>
            </div>

            {/* Right Column - Cover Image & Branding */}
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
                            - PAGORA Tenant Enterprise Portal
                        </footer>
                    </blockquote>
                </div>
            </div>
        </div>
    );
}
