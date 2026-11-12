import { Head, Link, useForm, useHttp } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Checkbox } from '@/Components/ui/checkbox';
import { PasswordInput } from '@/Components/password-input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
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
    CheckCircle2,
    ArrowLeft,
    ChevronLeft,
    ChevronRight,
    AlertCircle,
} from 'lucide-react';
import Logo from '../../../assets/logo/logo.webp';
import {
    isEmail,
    isNpwp,
    isPhoneNumber,
    normalizeNpwp,
    normalizePhoneNumber,
} from '@/lib/validators';

const STEPS = [
    { id: 1, label: 'Profil Akun' },
    { id: 2, label: 'Data Perusahaan' },
    { id: 3, label: 'Komoditas & Legalitas' },
];

const DOCUMENT_TYPES = [
    { value: 'npwp', label: 'NPWP Perusahaan' },
    { value: 'siup', label: 'SIUP (Surat Izin Usaha Perdagangan)' },
    { value: 'nib', label: 'NIB (Nomor Induk Berusaha)' },
];

export default function RegisterVendor({
    commodity_categories = [],
    status,
    invitation = null,
    invitation_error = null,
}) {
    const [step, setStep] = useState(1);
    const bodyScrollRef = useRef(null);

    const initialCategoryUuids = invitation?.commodity_category_uuid
        ? [invitation.commodity_category_uuid]
        : [];

    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        company_name: invitation?.company_name ?? '',
        tax_number: '',
        contact_email: invitation?.contact_email ?? '',
        phone: '',
        address: '',
        otp: '',
        commodity_category_uuids: initialCategoryUuids,
        document_type: 'npwp',
        document_number: '',
        legal_document: null,
        invitation_token: invitation?.token ?? '',
    });

    const { data, setData, post, processing, errors, setError, clearErrors, reset } = form;
    const legalDocumentFeedback = useUploadFeedback(form, 'legal_document');

    const [otpSent, setOtpSent] = useState(false);
    const resend = useCountdown();
    const otpRequest = useHttp({ contact_email: '' });

    const scrollBodyToTop = () => {
        if (bodyScrollRef.current) {
            bodyScrollRef.current.scrollTo({ top: 0, behavior: 'smooth' });
        }
    };

    const scrollToFirstError = () => {
        requestAnimationFrame(() => {
            const firstInvalid = bodyScrollRef.current?.querySelector(
                '[aria-invalid="true"], [data-invalid="true"]'
            );
            firstInvalid?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    };

    const goToStep = (nextStep) => {
        setStep(nextStep);
        scrollBodyToTop();
    };

    const sendOtp = () => {
        const email = data.contact_email.trim();

        if (!email) {
            setError('contact_email', 'Email bisnis perusahaan wajib diisi.');
            notify.validationFailed();
            scrollToFirstError();
            return;
        }
        if (!isEmail(email)) {
            setError('contact_email', 'Format email bisnis perusahaan tidak valid.');
            notify.validationFailed();
            scrollToFirstError();
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
                scrollToFirstError();
            },
        });
    };

    const validateStep1 = () => {
        let hasError = false;
        clearErrors('name', 'email', 'password', 'password_confirmation');

        const trimmedName = data.name.trim();
        if (!trimmedName) {
            setError('name', 'Nama lengkap penanggung jawab wajib diisi.');
            hasError = true;
        } else if (trimmedName.length < 3) {
            setError('name', 'Nama lengkap minimal 3 karakter.');
            hasError = true;
        }

        const trimmedEmail = data.email.trim();
        if (!trimmedEmail) {
            setError('email', 'Email akun wajib diisi.');
            hasError = true;
        } else if (!isEmail(trimmedEmail)) {
            setError('email', 'Format email akun tidak valid.');
            hasError = true;
        }

        if (!data.password) {
            setError('password', 'Kata sandi wajib diisi.');
            hasError = true;
        } else if (data.password.length < 8) {
            setError('password', 'Kata sandi minimal 8 karakter.');
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
            scrollToFirstError();
            return false;
        }

        return true;
    };

    const validateStep2 = () => {
        let hasError = false;
        clearErrors('company_name', 'tax_number', 'contact_email', 'phone', 'address', 'otp');

        const namaTrimmed = data.company_name.trim();
        if (!namaTrimmed) {
            setError('company_name', 'Nama perusahaan wajib diisi.');
            hasError = true;
        } else if (namaTrimmed.length < 3) {
            setError('company_name', 'Nama perusahaan minimal 3 karakter.');
            hasError = true;
        }

        const normalizedNpwp = normalizeNpwp(data.tax_number);
        if (!normalizedNpwp) {
            setError('tax_number', 'Nomor NPWP perusahaan wajib diisi.');
            hasError = true;
        } else if (!isNpwp(normalizedNpwp)) {
            setError(
                'tax_number',
                'Format NPWP tidak valid (15 digit contoh: 01.234.567.8-901.000 atau 16 digit angka).'
            );
            hasError = true;
        } else if (normalizedNpwp !== data.tax_number) {
            setData('tax_number', normalizedNpwp);
        }

        const emailTrimmed = data.contact_email.trim();
        if (!emailTrimmed) {
            setError('contact_email', 'Email bisnis perusahaan wajib diisi.');
            hasError = true;
        } else if (!isEmail(emailTrimmed)) {
            setError('contact_email', 'Format email bisnis perusahaan tidak valid.');
            hasError = true;
        }

        const telpTrimmed = normalizePhoneNumber(data.phone);
        if (!telpTrimmed) {
            setError('phone', 'Nomor telepon wajib diisi.');
            hasError = true;
        } else if (!isPhoneNumber(telpTrimmed)) {
            setError(
                'phone',
                'Format nomor telepon tidak valid (8–15 digit angka, contoh: 081234567890).'
            );
            hasError = true;
        }

        if (!otpSent) {
            setError('otp', 'Verifikasi email bisnis terlebih dahulu dengan menekan "Kirim Kode OTP".');
            hasError = true;
        } else if (data.otp.length !== OTP_LENGTH) {
            setError('otp', `Kode OTP harus terdiri dari ${OTP_LENGTH} digit angka.`);
            hasError = true;
        }

        const alamatTrimmed = data.address.trim();
        if (!alamatTrimmed) {
            setError('address', 'Alamat lengkap perusahaan wajib diisi.');
            hasError = true;
        }

        if (hasError) {
            notify.validationFailed();
            scrollToFirstError();
            return false;
        }

        return true;
    };

    const validateStep3 = () => {
        let hasError = false;
        clearErrors('commodity_category_uuids', 'document_type', 'document_number', 'legal_document');

        if (!Array.isArray(data.commodity_category_uuids) || data.commodity_category_uuids.length === 0) {
            setError('commodity_category_uuids', 'Pilih minimal satu kategori komoditas.');
            hasError = true;
        }

        if (!data.legal_document) {
            setError('legal_document', 'Dokumen legalitas (NPWP/SIUP/NIB) wajib diunggah.');
            hasError = true;
        }

        if (hasError) {
            notify.validationFailed();
            scrollToFirstError();
            return false;
        }

        return true;
    };

    const handleStepClick = (targetStep) => {
        if (processing || targetStep === step) {
            return;
        }

        if (targetStep < step) {
            goToStep(targetStep);
            return;
        }

        if (step === 1 && !validateStep1()) {
            return;
        }

        if (targetStep === 3 && !validateStep2()) {
            goToStep(2);
            scrollToFirstError();
            return;
        }

        goToStep(targetStep);
    };

    const handleNextStep = () => {
        if (step === 1 && validateStep1()) {
            goToStep(2);
        } else if (step === 2 && validateStep2()) {
            goToStep(3);
        }
    };

    const toggleCategory = (uuid) => {
        const current = Array.isArray(data.commodity_category_uuids)
            ? data.commodity_category_uuids
            : [];
        const exists = current.includes(uuid);
        const updated = exists
            ? current.filter((item) => item !== uuid)
            : [...current, uuid];

        setData('commodity_category_uuids', updated);
        clearErrors('commodity_category_uuids');
    };

    const handleSubmit = (e) => {
        e.preventDefault();

        if (!validateStep1()) {
            goToStep(1);
            scrollToFirstError();
            return;
        }

        if (!validateStep2()) {
            goToStep(2);
            scrollToFirstError();
            return;
        }

        if (!validateStep3()) {
            goToStep(3);
            scrollToFirstError();
            return;
        }

        post('/register/vendor', {
            forceFormData: true,
            onError: (errs) => {
                notify.validationFailed();
                if (errs.name || errs.email || errs.password || errs.password_confirmation) {
                    goToStep(1);
                } else if (
                    errs.company_name ||
                    errs.tax_number ||
                    errs.contact_email ||
                    errs.phone ||
                    errs.address ||
                    errs.otp
                ) {
                    goToStep(2);
                }
                scrollToFirstError();
            },
            onSuccess: () => {
                reset();
                setOtpSent(false);
                resend.start(0);
                goToStep(1);
            },
        });
    };

    const stepHasError = {
        1: !!(errors.name || errors.email || errors.password || errors.password_confirmation),
        2: !!(
            errors.company_name ||
            errors.tax_number ||
            errors.contact_email ||
            errors.phone ||
            errors.otp ||
            errors.address
        ),
        3: !!(
            errors.commodity_category_uuids ||
            errors.document_type ||
            errors.document_number ||
            errors.legal_document
        ),
    };

    return (
        <div className="h-dvh w-full overflow-hidden bg-muted/30 text-foreground flex flex-col p-3 sm:p-5 lg:p-6 font-inter selection:bg-brand-primary selection:text-text-primary">
            <Head title="Registrasi Calon Vendor Mitra — PAGORA" />
            <FlashHandler />
            <Toaster />

            {/* Full-Width Card Container with Internal Scroll */}
            <div className="w-full flex-1 min-h-0 flex flex-col bg-card rounded-2xl border border-border shadow-sm overflow-hidden">
                {/* Pinned Top Card Header: Logo & Tombol Kembali */}
                <div className="shrink-0 border-b border-border/80 bg-card px-5 py-3.5 sm:px-8 flex items-center justify-between gap-3">
                    <Link href="/" className="inline-flex items-center gap-3 group">
                        <div className="size-9 rounded-xl bg-background border border-border flex items-center justify-center p-1.5 shadow-2xs group-hover:scale-105 transition-transform">
                            <img src={Logo} alt="Logo PAGORA" className="size-full object-contain" />
                        </div>
                        <div className="flex flex-col">
                            <span className="font-bold tracking-tight text-lg text-foreground leading-none">
                                PAGORA
                            </span>
                            <span className="text-[11px] text-muted-foreground font-medium">
                                E-Procurement Platform
                            </span>
                        </div>
                    </Link>

                    <Button asChild variant="outline" size="sm" className="rounded-full gap-1.5">
                        <Link href="/register">
                            <ArrowLeft className="size-4" />
                            <span>Kembali</span>
                        </Link>
                    </Button>
                </div>

                {/* Pinned Wizard Stepper Bar */}
                <div className="shrink-0 border-b border-border/70 bg-muted/25 px-5 py-3 sm:px-8">
                    <div className="max-w-4xl mx-auto">
                        <div className="grid grid-cols-3 gap-2 p-1 rounded-xl bg-muted/60 border border-border/70 text-xs select-none">
                            {STEPS.map((item) => {
                                const isActive = step === item.id;
                                const isCompleted = step > item.id && !stepHasError[item.id];
                                const hasErr = stepHasError[item.id];

                                return (
                                    <button
                                        key={item.id}
                                        type="button"
                                        onClick={() => handleStepClick(item.id)}
                                        className={cn(
                                            'flex items-center justify-center sm:justify-start gap-2 px-3 py-2 rounded-lg font-medium transition-all text-left cursor-pointer',
                                            isActive
                                                ? 'bg-background text-foreground shadow-xs'
                                                : 'text-muted-foreground hover:text-foreground',
                                            hasErr && 'text-destructive'
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                'flex items-center justify-center size-5 rounded-full text-[11px] font-semibold shrink-0',
                                                hasErr
                                                    ? 'bg-destructive text-destructive-foreground'
                                                    : isActive
                                                        ? 'bg-primary text-primary-foreground'
                                                        : isCompleted
                                                            ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                                                            : 'bg-muted-foreground/20 text-muted-foreground'
                                            )}
                                        >
                                            {hasErr ? (
                                                '!'
                                            ) : isCompleted ? (
                                                <CheckCircle2 className="size-3.5" />
                                            ) : (
                                                item.id
                                            )}
                                        </span>
                                        <span className="truncate hidden sm:inline">{item.label}</span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                </div>

                {/* Form Wrapping Scrollable Body & Pinned Footer */}
                <form noValidate onSubmit={handleSubmit} className="flex-1 min-h-0 flex flex-col">
                    {/* Scrollable Card Body */}
                    <div
                        ref={bodyScrollRef}
                        className="flex-1 min-h-0 overflow-y-auto px-5 py-5 sm:px-8 sm:py-6"
                    >
                        <div className="max-w-4xl mx-auto space-y-5">
                            {/* Status Sukses Banner */}
                            {status && (
                                <div className="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-start gap-3 text-emerald-800 dark:text-emerald-300">
                                    <CheckCircle2 className="size-5 shrink-0 mt-0.5 text-emerald-600 dark:text-emerald-400" />
                                    <p className="text-sm font-medium">{status}</p>
                                </div>
                            )}

                            {/* Invalid / Expired Invitation Banner */}
                            {invitation_error && (
                                <div className="p-4 rounded-xl bg-destructive/10 border border-destructive/25 flex items-start gap-3 text-destructive">
                                    <AlertCircle className="size-5 shrink-0 mt-0.5" />
                                    <div className="text-sm">
                                        <p className="font-semibold">Tautan Undangan Tidak Valid</p>
                                        <p className="text-xs mt-0.5 opacity-90">{invitation_error}</p>
                                    </div>
                                </div>
                            )}

                            {/* Valid Invitation Banner */}
                            {invitation && (
                                <div className="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center gap-3 text-emerald-800 dark:text-emerald-300 text-sm">
                                    <CheckCircle2 className="size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                    <span>
                                        Pendaftaran melalui undangan untuk <strong>{invitation.company_name}</strong>.
                                    </span>
                                </div>
                            )}

                            {/* STEP HEADER */}
                            <div className="flex flex-col gap-1">
                                <h1 className="text-xl sm:text-2xl font-bold tracking-tight text-foreground">
                                    {step === 1 && 'Registrasi Akun Vendor'}
                                    {step === 2 && 'Data Perusahaan Vendor'}
                                    {step === 3 && 'Komoditas & Legalitas'}
                                </h1>
                                <p className="text-xs sm:text-sm text-muted-foreground">
                                    {step === 1 && 'Langkah 1 dari 3 — Profil akun penanggung jawab'}
                                    {step === 2 && 'Langkah 2 dari 3 — Data legal & kontak perusahaan'}
                                    {step === 3 && 'Langkah 3 dari 3 — Kategori komoditas & dokumen legalitas'}
                                </p>
                            </div>

                            {/* STEP 1: Profil Akun */}
                            {step === 1 && (
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
                                    {/* Nama Lengkap */}
                                    <div className="space-y-1.5">
                                        <Label
                                            htmlFor="name"
                                            className={cn('text-sm font-medium', errors.name && 'text-destructive')}
                                        >
                                            Nama Lengkap Penanggung Jawab <span className="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            id="name"
                                            type="text"
                                            placeholder="Contoh: Andi Wijaya"
                                            value={data.name}
                                            onChange={(e) => {
                                                setData('name', e.target.value);
                                                clearErrors('name');
                                            }}
                                            aria-invalid={!!errors.name}
                                            className={cn(
                                                'h-10 w-full',
                                                errors.name && 'border-destructive focus-visible:ring-destructive'
                                            )}
                                            autoFocus
                                        />
                                        {errors.name && (
                                            <p className="text-xs font-medium text-destructive flex items-center gap-1.5">
                                                <AlertCircle className="size-3.5 shrink-0" />
                                                <span>{errors.name}</span>
                                            </p>
                                        )}
                                    </div>

                                    {/* Email Akun */}
                                    <div className="space-y-1.5">
                                        <Label
                                            htmlFor="email"
                                            className={cn('text-sm font-medium', errors.email && 'text-destructive')}
                                        >
                                            Email Akun Login <span className="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            placeholder="Contoh: andi@sumberpangan.co.id"
                                            value={data.email}
                                            onChange={(e) => {
                                                setData('email', e.target.value);
                                                clearErrors('email');
                                            }}
                                            aria-invalid={!!errors.email}
                                            className={cn(
                                                'h-10 w-full',
                                                errors.email && 'border-destructive focus-visible:ring-destructive'
                                            )}
                                        />
                                        {errors.email && (
                                            <p className="text-xs font-medium text-destructive flex items-center gap-1.5">
                                                <AlertCircle className="size-3.5 shrink-0" />
                                                <span>{errors.email}</span>
                                            </p>
                                        )}
                                    </div>

                                    {/* Kata Sandi */}
                                    <div className="space-y-1.5">
                                        <Label
                                            htmlFor="password"
                                            className={cn('text-sm font-medium', errors.password && 'text-destructive')}
                                        >
                                            Kata Sandi <span className="text-destructive">*</span>
                                        </Label>
                                        <PasswordInput
                                            id="password"
                                            placeholder="Minimal 8 karakter"
                                            value={data.password}
                                            onChange={(e) => {
                                                setData('password', e.target.value);
                                                clearErrors('password');
                                            }}
                                            error={errors.password}
                                            disabled={processing}
                                            className="h-10 w-full"
                                        />
                                        {errors.password && (
                                            <p className="text-xs font-medium text-destructive flex items-center gap-1.5">
                                                <AlertCircle className="size-3.5 shrink-0" />
                                                <span>{errors.password}</span>
                                            </p>
                                        )}
                                    </div>

                                    {/* Konfirmasi Kata Sandi */}
                                    <div className="space-y-1.5">
                                        <Label
                                            htmlFor="password_confirmation"
                                            className={cn(
                                                'text-sm font-medium',
                                                errors.password_confirmation && 'text-destructive'
                                            )}
                                        >
                                            Konfirmasi Kata Sandi <span className="text-destructive">*</span>
                                        </Label>
                                        <PasswordInput
                                            id="password_confirmation"
                                            placeholder="Ulangi kata sandi"
                                            value={data.password_confirmation}
                                            onChange={(e) => {
                                                setData('password_confirmation', e.target.value);
                                                clearErrors('password_confirmation');
                                            }}
                                            error={errors.password_confirmation}
                                            disabled={processing}
                                            className="h-10 w-full"
                                        />
                                        {errors.password_confirmation && (
                                            <p className="text-xs font-medium text-destructive flex items-center gap-1.5">
                                                <AlertCircle className="size-3.5 shrink-0" />
                                                <span>{errors.password_confirmation}</span>
                                            </p>
                                        )}
                                    </div>
                                </div>
                            )}

                            {/* STEP 2: Data Perusahaan */}
                            {step === 2 && (
                                <div className="space-y-4">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
                                        {/* Nama Perusahaan */}
                                        <div className="space-y-1.5">
                                            <Label
                                                htmlFor="company_name"
                                                className={cn(
                                                    'text-sm font-medium',
                                                    errors.company_name && 'text-destructive'
                                                )}
                                            >
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
                                                className={cn(
                                                    'h-10 w-full',
                                                    errors.company_name &&
                                                        'border-destructive focus-visible:ring-destructive'
                                                )}
                                                autoFocus
                                            />
                                            {errors.company_name && (
                                                <p className="text-xs font-medium text-destructive flex items-center gap-1.5">
                                                    <AlertCircle className="size-3.5 shrink-0" />
                                                    <span>{errors.company_name}</span>
                                                </p>
                                            )}
                                        </div>

                                        {/* Nomor NPWP */}
                                        <div className="space-y-1.5">
                                            <Label
                                                htmlFor="tax_number"
                                                className={cn(
                                                    'text-sm font-medium',
                                                    errors.tax_number && 'text-destructive'
                                                )}
                                            >
                                                Nomor NPWP Perusahaan <span className="text-destructive">*</span>
                                            </Label>
                                            <Input
                                                id="tax_number"
                                                type="text"
                                                placeholder="Contoh: 01.234.567.8-901.000"
                                                value={data.tax_number}
                                                onChange={(e) => {
                                                    setData('tax_number', e.target.value);
                                                    clearErrors('tax_number');
                                                }}
                                                onBlur={(e) => {
                                                    const formatted = normalizeNpwp(e.target.value);
                                                    if (formatted && formatted !== e.target.value) {
                                                        setData('tax_number', formatted);
                                                    }
                                                }}
                                                aria-invalid={!!errors.tax_number}
                                                className={cn(
                                                    'h-10 w-full',
                                                    errors.tax_number &&
                                                        'border-destructive focus-visible:ring-destructive'
                                                )}
                                            />
                                            {errors.tax_number && (
                                                <p className="text-xs font-medium text-destructive flex items-center gap-1.5">
                                                    <AlertCircle className="size-3.5 shrink-0" />
                                                    <span>{errors.tax_number}</span>
                                                </p>
                                            )}
                                        </div>

                                        {/* Email Bisnis */}
                                        <div className="space-y-1.5">
                                            <Label
                                                htmlFor="contact_email"
                                                className={cn(
                                                    'text-sm font-medium',
                                                    errors.contact_email && 'text-destructive'
                                                )}
                                            >
                                                Email Bisnis Perusahaan <span className="text-destructive">*</span>
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
                                                        setOtpSent(false);
                                                        setData('otp', '');
                                                        resend.start(0);
                                                    }
                                                }}
                                                aria-invalid={!!errors.contact_email}
                                                className={cn(
                                                    'h-10 w-full',
                                                    errors.contact_email &&
                                                        'border-destructive focus-visible:ring-destructive'
                                                )}
                                            />
                                            {errors.contact_email && (
                                                <p className="text-xs font-medium text-destructive flex items-center gap-1.5">
                                                    <AlertCircle className="size-3.5 shrink-0" />
                                                    <span>{errors.contact_email}</span>
                                                </p>
                                            )}
                                        </div>

                                        {/* Nomor Telepon */}
                                        <div className="space-y-1.5">
                                            <Label
                                                htmlFor="phone"
                                                className={cn('text-sm font-medium', errors.phone && 'text-destructive')}
                                            >
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
                                                className={cn(
                                                    'h-10 w-full',
                                                    errors.phone && 'border-destructive focus-visible:ring-destructive'
                                                )}
                                            />
                                            {errors.phone && (
                                                <p className="text-xs font-medium text-destructive flex items-center gap-1.5">
                                                    <AlertCircle className="size-3.5 shrink-0" />
                                                    <span>{errors.phone}</span>
                                                </p>
                                            )}
                                        </div>
                                    </div>

                                    {/* Verifikasi Email (OTP) */}
                                    <div
                                        data-invalid={!!errors.otp}
                                        className={cn(
                                            'rounded-xl border bg-muted/30 p-3.5 sm:p-4 space-y-3',
                                            errors.otp ? 'border-destructive' : 'border-border'
                                        )}
                                    >
                                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                            <div className="text-sm">
                                                <p
                                                    className={cn(
                                                        'font-semibold',
                                                        errors.otp ? 'text-destructive' : 'text-foreground'
                                                    )}
                                                >
                                                    Verifikasi Email Bisnis <span className="text-destructive">*</span>
                                                </p>
                                                <p className="text-xs text-muted-foreground mt-0.5">
                                                    Kirim kode 6 digit ke email bisnis di atas.
                                                </p>
                                            </div>
                                            <LoadingButton
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={sendOtp}
                                                loading={otpRequest.processing}
                                                loadingText="Mengirim..."
                                                disabled={processing || resend.seconds > 0}
                                                className="shrink-0"
                                            >
                                                {resend.seconds > 0
                                                    ? `Kirim ulang (${resend.seconds}d)`
                                                    : otpSent
                                                        ? 'Kirim Ulang Kode'
                                                        : 'Kirim Kode OTP'}
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
                                        <Label
                                            htmlFor="address"
                                            className={cn('text-sm font-medium', errors.address && 'text-destructive')}
                                        >
                                            Alamat Lengkap Perusahaan <span className="text-destructive">*</span>
                                        </Label>
                                        <Textarea
                                            id="address"
                                            rows={2}
                                            placeholder="Contoh: Kawasan Industri MM2100 Blok C-3 No. 8, Cikarang Barat, Kab. Bekasi"
                                            value={data.address}
                                            onChange={(e) => {
                                                setData('address', e.target.value);
                                                clearErrors('address');
                                            }}
                                            aria-invalid={!!errors.address}
                                            className={cn(
                                                'w-full resize-none min-h-[72px]',
                                                errors.address && 'border-destructive focus-visible:ring-destructive'
                                            )}
                                        />
                                        {errors.address && (
                                            <p className="text-xs font-medium text-destructive flex items-center gap-1.5">
                                                <AlertCircle className="size-3.5 shrink-0" />
                                                <span>{errors.address}</span>
                                            </p>
                                        )}
                                    </div>
                                </div>
                            )}

                            {/* STEP 3: Komoditas & Legalitas */}
                            {step === 3 && (
                                <div className="space-y-4">
                                    {/* Pilih >= 1 Kategori Komoditas */}
                                    <div className="space-y-1.5">
                                        <div className="flex items-center justify-between gap-2">
                                            <Label
                                                className={cn(
                                                    'text-sm font-medium',
                                                    errors.commodity_category_uuids && 'text-destructive'
                                                )}
                                            >
                                                Kategori Komoditas yang Dipasok{' '}
                                                <span className="text-destructive">*</span>
                                            </Label>
                                            <span className="text-xs text-muted-foreground">
                                                Dipilih: {data.commodity_category_uuids.length}
                                            </span>
                                        </div>

                                        {commodity_categories.length > 0 ? (
                                            <div
                                                data-invalid={!!errors.commodity_category_uuids}
                                                className={cn(
                                                    'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5 p-3 rounded-xl border bg-muted/20',
                                                    errors.commodity_category_uuids
                                                        ? 'border-destructive'
                                                        : 'border-border'
                                                )}
                                            >
                                                {commodity_categories.map((cat) => {
                                                    const checked = data.commodity_category_uuids.includes(
                                                        cat.uuid
                                                    );
                                                    return (
                                                        <label
                                                            key={cat.uuid}
                                                            className={cn(
                                                                'flex items-center gap-2.5 px-3 py-2 rounded-lg border text-xs sm:text-sm font-medium transition-all cursor-pointer select-none',
                                                                checked
                                                                    ? 'bg-primary/5 border-primary text-foreground shadow-2xs'
                                                                    : 'bg-background border-border/80 text-muted-foreground hover:text-foreground hover:border-border'
                                                            )}
                                                        >
                                                            <Checkbox
                                                                checked={checked}
                                                                onCheckedChange={() =>
                                                                    toggleCategory(cat.uuid)
                                                                }
                                                                disabled={processing}
                                                            />
                                                            <span className="truncate">{cat.name}</span>
                                                        </label>
                                                    );
                                                })}
                                            </div>
                                        ) : (
                                            <div
                                                data-invalid={!!errors.commodity_category_uuids}
                                                className="p-4 rounded-xl border border-dashed border-border text-xs text-muted-foreground text-center"
                                            >
                                                Belum ada kategori komoditas aktif di sistem.
                                            </div>
                                        )}

                                        {errors.commodity_category_uuids && (
                                            <p className="text-xs font-medium text-destructive flex items-center gap-1.5">
                                                <AlertCircle className="size-3.5 shrink-0" />
                                                <span>{errors.commodity_category_uuids}</span>
                                            </p>
                                        )}
                                    </div>

                                    {/* Jenis & Nomor Dokumen Legalitas */}
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
                                        <div className="space-y-1.5">
                                            <Label
                                                htmlFor="document_type"
                                                className={cn(
                                                    'text-sm font-medium',
                                                    errors.document_type && 'text-destructive'
                                                )}
                                            >
                                                Jenis Dokumen Legalitas
                                            </Label>
                                            <Select
                                                value={data.document_type}
                                                onValueChange={(value) => {
                                                    setData('document_type', value);
                                                    clearErrors('document_type');
                                                }}
                                                disabled={processing}
                                            >
                                                <SelectTrigger
                                                    id="document_type"
                                                    aria-invalid={!!errors.document_type}
                                                    className={cn(
                                                        'h-10 w-full',
                                                        errors.document_type &&
                                                            'border-destructive focus-visible:ring-destructive'
                                                    )}
                                                >
                                                    <SelectValue placeholder="Pilih jenis dokumen" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {DOCUMENT_TYPES.map((doc) => (
                                                        <SelectItem key={doc.value} value={doc.value}>
                                                            {doc.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            {errors.document_type && (
                                                <p className="text-xs font-medium text-destructive flex items-center gap-1.5">
                                                    <AlertCircle className="size-3.5 shrink-0" />
                                                    <span>{errors.document_type}</span>
                                                </p>
                                            )}
                                        </div>

                                        <div className="space-y-1.5">
                                            <Label
                                                htmlFor="document_number"
                                                className={cn(
                                                    'text-sm font-medium',
                                                    errors.document_number && 'text-destructive'
                                                )}
                                            >
                                                Nomor Dokumen (Opsional)
                                            </Label>
                                            <Input
                                                id="document_number"
                                                type="text"
                                                placeholder="Contoh: 1234567890123"
                                                value={data.document_number}
                                                onChange={(e) => {
                                                    setData('document_number', e.target.value);
                                                    clearErrors('document_number');
                                                }}
                                                aria-invalid={!!errors.document_number}
                                                className={cn(
                                                    'h-10 w-full',
                                                    errors.document_number &&
                                                        'border-destructive focus-visible:ring-destructive'
                                                )}
                                            />
                                            {errors.document_number && (
                                                <p className="text-xs font-medium text-destructive flex items-center gap-1.5">
                                                    <AlertCircle className="size-3.5 shrink-0" />
                                                    <span>{errors.document_number}</span>
                                                </p>
                                            )}
                                        </div>
                                    </div>

                                    {/* Unggah Dokumen Legalitas (Max 5 MB) */}
                                    <div className="space-y-1.5" data-invalid={!!errors.legal_document}>
                                        <Label
                                            className={cn(
                                                'text-sm font-medium',
                                                errors.legal_document && 'text-destructive'
                                            )}
                                        >
                                            Unggah Dokumen Legalitas (NPWP / SIUP / NIB){' '}
                                            <span className="text-destructive">*</span>
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
                                            maxSize={5 * 1024 * 1024}
                                            label="Seret & letakkan berkas atau klik untuk memilih"
                                            description="Format PDF, JPG, JPEG, atau PNG (Maksimal 5 MB)"
                                        />
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Pinned Bottom Card Footer: Login Link & Compact Step Navigation */}
                    <div className="shrink-0 border-t border-border/80 bg-card px-5 py-3.5 sm:px-8 flex items-center justify-between gap-3">
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
                                onClick={() => goToStep(step - 1)}
                                disabled={processing || step === 1}
                                aria-label="Langkah sebelumnya"
                                title="Langkah sebelumnya"
                                className="cursor-pointer"
                            >
                                <ChevronLeft className="size-4" />
                            </Button>

                            {step < 3 ? (
                                <Button
                                    type="button"
                                    size="icon"
                                    onClick={handleNextStep}
                                    disabled={processing}
                                    aria-label="Langkah berikutnya"
                                    title="Langkah berikutnya"
                                    className="cursor-pointer"
                                >
                                    <ChevronRight className="size-4" />
                                </Button>
                            ) : (
                                <LoadingButton
                                    type="submit"
                                    loading={processing}
                                    loadingText={uploadingLabel(
                                        legalDocumentFeedback.progress,
                                        'Mengirim...'
                                    )}
                                    className="cursor-pointer"
                                >
                                    <span>Kirim Pendaftaran</span>
                                </LoadingButton>
                            )}
                        </div>
                    </div>
                </form>
            </div>
        </div>
    );
}
