import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo, useRef, useState } from 'react';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
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
import { useDropzone } from 'react-dropzone';
import { describeRejection, formatBytes, uploadingLabel } from '@/lib/file';
import { useUploadFeedback } from '@/hooks/use-upload-feedback';
import { FileDropzone } from '@/Components/file-dropzone';
import { LoadingButton } from '@/Components/loading-button';
import { FlashHandler } from '@/Components/flash-handler';
import { OtpField, OTP_LENGTH } from '@/Components/otp-field';
import { useCountdown } from '@/hooks/use-countdown';
import { notify } from '@/lib/notify';
import { cn } from '@/lib/utils';
import {
    CheckCircle2,
    ArrowLeft,
    ChevronLeft,
    ChevronRight,
    AlertCircle,
    UploadCloud,
    FileText,
    Trash2,
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

export default function RegisterVendor({
    commodity_categories = [],
    status,
    invitation = null,
    invitation_error = null,
    google_user = null,
    acceptance = null,
}) {
    const isGoogle = Boolean(google_user?.email);
    const [step, setStep] = useState(() => (isGoogle ? 3 : 1));
    const [termsAccepted, setTermsAccepted] = useState(false);
    const bodyScrollRef = useRef(null);

    const checkboxLabel =
        acceptance?.checkbox_label ??
        'Saya telah membaca dan menyetujui Syarat dan Ketentuan PAGORA.';

    const renderTermsLabel = (text) => {
        const target = 'Syarat dan Ketentuan';
        if (!text.includes(target)) {
            return text;
        }
        const [before, after] = text.split(target);
        return (
            <span>
                {before}
                <a
                    href="/syarat-dan-ketentuan"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="font-semibold text-primary underline underline-offset-4 hover:text-primary/80 transition-colors"
                    onClick={(e) => e.stopPropagation()}
                >
                    {target}
                </a>
                {after}
            </span>
        );
    };

    const initialCategoryUuids = invitation?.commodity_category_uuid
        ? [invitation.commodity_category_uuid]
        : [];
    const initialEmail = google_user?.email ?? (invitation?.contact_email ?? '');

    const sortedCommodityCategories = useMemo(() => {
        if (!Array.isArray(commodity_categories)) {
            return [];
        }
        const regular = [];
        const others = [];
        for (const cat of commodity_categories) {
            if (/^(lainnya|lain-lain|other)$/i.test(cat.name?.trim() ?? '')) {
                others.push(cat);
            } else {
                regular.push(cat);
            }
        }
        return [...regular, ...others];
    }, [commodity_categories]);

    const form = useForm({
        name: google_user?.name ?? '',
        email: initialEmail,
        password: '',
        password_confirmation: '',
        company_name: invitation?.company_name ?? '',
        tax_number: '',
        contact_email: initialEmail,
        phone: '',
        address: '',
        otp: isGoogle ? '000000' : '',
        commodity_category_uuids: initialCategoryUuids,
        dokumen_npwp: null,
        dokumen_nib: null,
        dokumen_ktp: null,
        nomor_nib: '',
        nomor_ktp: '',
        pkp_status: 'non_ppn',
        is_pkp: false,
        nomor_sppkp: '',
        dokumen_sppkp: null,
        nomor_akta: '',
        dokumen_akta: null,
        legal_documents: [],
        invitation_token: invitation?.token ?? '',
        google_id: google_user?.google_id ?? '',
        avatar: google_user?.avatar ?? '',
        is_google_registration: isGoogle,
        terms_accepted: false,
    });

    const { data, setData, post, processing, errors, setError, clearErrors, reset } = form;
    const npwpFeedback = useUploadFeedback(form, 'dokumen_npwp');
    const nibFeedback = useUploadFeedback(form, 'dokumen_nib');
    const ktpFeedback = useUploadFeedback(form, 'dokumen_ktp');
    const sppkpFeedback = useUploadFeedback(form, 'dokumen_sppkp');
    const aktaFeedback = useUploadFeedback(form, 'dokumen_akta');
    const legalDocumentFeedback = useUploadFeedback(form, 'legal_documents');

    const [otpSent, setOtpSent] = useState(false);
    const [lastOtpEmail, setLastOtpEmail] = useState('');
    const [verifiedOtpInfo, setVerifiedOtpInfo] = useState(null);
    const [verifyingOtp, setVerifyingOtp] = useState(false);
    const [sendingOtp, setSendingOtp] = useState(false);
    const resend = useCountdown();

    const currentEmail = (data.email || data.contact_email || '').trim().toLowerCase();
    const isOtpVerified = isGoogle || Boolean(
        verifiedOtpInfo &&
        verifiedOtpInfo.email === currentEmail &&
        verifiedOtpInfo.otp === data.otp &&
        data.otp.length === OTP_LENGTH
    );

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

    const sendOtp = async (callback) => {
        const email = (data.email || data.contact_email || '').trim();

        if (!email) {
            setError('email', 'Email bisnis wajib diisi.');
            notify.validationFailed();
            scrollToFirstError();
            return false;
        }
        if (!isEmail(email)) {
            setError('email', 'Format email bisnis tidak valid.');
            notify.validationFailed();
            scrollToFirstError();
            return false;
        }

        clearErrors('email', 'contact_email', 'otp');
        setVerifiedOtpInfo(null);
        setData('otp', '');
        setSendingOtp(true);

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const response = await fetch('/register/vendor/otp', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                },
                body: JSON.stringify({
                    contact_email: email,
                    email: email,
                }),
            });

            const result = await response.json();

            if (response.ok && result.success) {
                setOtpSent(true);
                setLastOtpEmail(email.toLowerCase());
                resend.start(60);
                notify.success(result.message || 'Kode OTP telah dikirim ke email bisnis Anda.');
                if (typeof callback === 'function') {
                    callback();
                } else {
                    goToStep(2);
                }
                return true;
            }

            const errorMsg =
                result.errors?.contact_email?.[0] ||
                result.errors?.contact_email ||
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
            scrollToFirstError();
            return false;
        } catch {
            const errorMsg = 'Terjadi kesalahan saat mengirim kode OTP. Silakan periksa koneksi Anda.';
            if (step === 2) {
                setError('otp', errorMsg);
            } else {
                setError('email', errorMsg);
            }
            notify.error(errorMsg);
            scrollToFirstError();
            return false;
        } finally {
            setSendingOtp(false);
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
            setError('name', 'Nama lengkap penanggung jawab wajib diisi.');
            hasError = true;
        } else if (trimmedName.length < 3) {
            setError('name', 'Nama lengkap minimal 3 karakter.');
            hasError = true;
        }

        const trimmedEmail = data.email.trim();
        if (!trimmedEmail) {
            setError('email', 'Email bisnis wajib diisi.');
            hasError = true;
        } else if (!isEmail(trimmedEmail)) {
            setError('email', 'Format email bisnis tidak valid.');
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

    const verifyOtpWithServer = async (email, otp) => {
        if (!email || otp.length !== OTP_LENGTH) {
            return false;
        }

        setVerifyingOtp(true);
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const response = await fetch('/register/vendor/otp/verify', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                },
                body: JSON.stringify({
                    contact_email: email,
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
                result.errors?.contact_email?.[0] ||
                result.errors?.email?.[0] ||
                result.message ||
                'Kode OTP salah. Pastikan kode sesuai dengan yang dikirim ke email bisnis Anda.';

            setError('otp', errorMsg);
            setVerifiedOtpInfo(null);
            notify.validationFailed();
            scrollToFirstError();
            return false;
        } catch {
            const errorMsg = 'Terjadi kesalahan saat memverifikasi kode OTP. Silakan coba lagi.';
            setError('otp', errorMsg);
            setVerifiedOtpInfo(null);
            notify.validationFailed();
            scrollToFirstError();
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

        const email = (data.email || data.contact_email || '').trim();
        if (value.length === OTP_LENGTH && email && isEmail(email)) {
            verifyOtpWithServer(email, value).then((valid) => {
                if (valid) {
                    goToStep(3);
                }
            });
        }
    };

    const validateStep2 = async () => {
        if (isGoogle) {
            return true;
        }

        clearErrors('otp');
        const email = (data.email || data.contact_email || '').trim();
        const otp = data.otp;

        if (!otp || otp.length !== OTP_LENGTH) {
            setError('otp', `Kode OTP harus terdiri dari ${OTP_LENGTH} digit angka.`);
            notify.validationFailed();
            scrollToFirstError();
            return false;
        }

        if (
            verifiedOtpInfo &&
            verifiedOtpInfo.email === email.toLowerCase() &&
            verifiedOtpInfo.otp === otp
        ) {
            return true;
        }

        return await verifyOtpWithServer(email, otp);
    };

    const validateStep3 = () => {
        let hasError = false;
        clearErrors('company_name', 'phone', 'address', 'commodity_category_uuids');

        const namaTrimmed = data.company_name.trim();
        if (!namaTrimmed) {
            setError('company_name', 'Nama perusahaan wajib diisi.');
            hasError = true;
        } else if (namaTrimmed.length < 3) {
            setError('company_name', 'Nama perusahaan minimal 3 karakter.');
            hasError = true;
        }

        const telpTrimmed = normalizePhoneNumber(data.phone);
        if (!telpTrimmed) {
            setError('phone', 'Nomor telepon wajib diisi.');
            hasError = true;
        } else if (!isPhoneNumber(telpTrimmed)) {
            setError(
                'phone',
                'Format nomor telepon tidak valid (8-15 digit angka, contoh: 081234567890).'
            );
            hasError = true;
        } else if (telpTrimmed !== data.phone) {
            setData('phone', telpTrimmed);
        }

        const alamatTrimmed = data.address.trim();
        if (!alamatTrimmed) {
            setError('address', 'Alamat lengkap perusahaan wajib diisi.');
            hasError = true;
        }

        if (!Array.isArray(data.commodity_category_uuids) || data.commodity_category_uuids.length === 0) {
            setError('commodity_category_uuids', 'Pilih minimal satu kategori komoditas.');
            hasError = true;
        }

        if (hasError) {
            notify.validationFailed();
            scrollToFirstError();
            return false;
        }

        return true;
    };

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
                key === 'tax_number' ||
                key === 'nomor_nib' ||
                key === 'nomor_ktp' ||
                key === 'pkp_status' ||
                key === 'is_pkp' ||
                key === 'nomor_sppkp' ||
                key === 'dokumen_sppkp' ||
                key === 'file_sppkp' ||
                key === 'nomor_akta' ||
                key === 'dokumen_akta' ||
                key === 'file_akta'
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

    const validateStep4 = () => {
        let hasError = false;
        clearErrors('tax_number', 'nomor_nib', 'nomor_ktp');
        clearLegalDocumentErrors();

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

        if (data.pkp_status === 'ppn' || data.is_pkp === true) {
            if (!validateLegalFile(data.dokumen_sppkp, 'dokumen_sppkp', 'Dokumen SPPKP Perusahaan')) {
                hasError = true;
            }
        }

        if (data.dokumen_akta) {
            if (!validateLegalFile(data.dokumen_akta, 'dokumen_akta', 'Dokumen Akta Pendirian')) {
                hasError = true;
            }
        }

        if (hasError) {
            notify.validationFailed();
            scrollToFirstError();
            return false;
        }

        return true;
    };

    const handleStepClick = async (targetStep) => {
        if (processing || verifyingOtp || sendingOtp || targetStep === step) {
            return;
        }

        if (targetStep === 2 && isGoogle) {
            goToStep(3);
            return;
        }

        if (targetStep < step) {
            goToStep(targetStep);
            return;
        }

        if (step === 1 && !validateStep1()) {
            return;
        }

        if (targetStep >= 3 && !isGoogle) {
            const valid2 = await validateStep2();
            if (!valid2) {
                goToStep(2);
                scrollToFirstError();
                return;
            }
        }

        if (targetStep === 4) {
            const valid3 = validateStep3();
            if (!valid3) {
                goToStep(3);
                scrollToFirstError();
                return;
            }
        }

        goToStep(targetStep);
    };

    const handleNextStep = async () => {
        if (processing || verifyingOtp || sendingOtp) {
            return;
        }

        if (step === 1) {
            if (!validateStep1()) {
                return;
            }

            if (isGoogle) {
                goToStep(3);
                return;
            }

            const email = (data.email || data.contact_email || '').trim().toLowerCase();
            const emailNeedsOtp = !otpSent || lastOtpEmail !== email;

            if (emailNeedsOtp) {
                sendOtp(() => goToStep(2));
            } else if (isOtpVerified) {
                goToStep(3);
            } else {
                goToStep(2);
            }
        } else if (step === 2) {
            const valid = await validateStep2();
            if (valid) {
                goToStep(3);
            }
        } else if (step === 3) {
            if (validateStep3()) {
                goToStep(4);
            }
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

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (processing || verifyingOtp || sendingOtp) {
            return;
        }

        if (step < 4) {
            handleNextStep();
            return;
        }

        if (!isGoogle && !validateStep1()) {
            goToStep(1);
            scrollToFirstError();
            return;
        }

        if (!isGoogle) {
            const validStep2 = await validateStep2();
            if (!validStep2) {
                goToStep(2);
                scrollToFirstError();
                return;
            }
        }

        const validStep3 = validateStep3();
        if (!validStep3) {
            goToStep(3);
            scrollToFirstError();
            return;
        }

        if (!validateStep4()) {
            goToStep(4);
            scrollToFirstError();
            return;
        }

        if (!termsAccepted) {
            notify.error('Anda harus menyetujui Syarat dan Ketentuan terlebih dahulu.');
            return;
        }

        post('/register/vendor', {
            forceFormData: true,
            onError: (errs) => {
                notify.validationFailed();
                if (errs.name || errs.email || errs.password || errs.password_confirmation) {
                    goToStep(1);
                } else if (errs.otp) {
                    goToStep(2);
                } else if (
                    errs.company_name ||
                    errs.contact_email ||
                    errs.phone ||
                    errs.address ||
                    errs.commodity_category_uuids
                ) {
                    goToStep(3);
                } else {
                    goToStep(4);
                }
                scrollToFirstError();
            },
            onSuccess: () => {
                reset();
                setOtpSent(false);
                setVerifiedOtpInfo(null);
                resend.start(0);
            },
        });
    };

    const hasLegalDocumentsError = Object.keys(errors).some(
        (key) =>
            key === 'legal_documents' ||
            key.startsWith('legal_documents.') ||
            key === 'dokumen_npwp' ||
            key === 'dokumen_nib' ||
            key === 'dokumen_ktp' ||
            key === 'dokumen_sppkp' ||
            key === 'file_sppkp' ||
            key === 'nomor_sppkp' ||
            key === 'dokumen_akta' ||
            key === 'file_akta' ||
            key === 'pkp_status' ||
            key === 'is_pkp' ||
            key === 'file_npwp' ||
            key === 'file_nib' ||
            key === 'file_ktp'
    );

    const stepHasError = {
        1: !!(errors.name || errors.email || errors.password || errors.password_confirmation),
        2: !!errors.otp,
        3: !!(
            errors.company_name ||
            errors.tax_number ||
            errors.contact_email ||
            errors.phone ||
            errors.address
        ),
        4: !!(errors.commodity_category_uuids || hasLegalDocumentsError),
    };

    return (
        <div className="h-dvh w-full overflow-hidden bg-muted/30 text-foreground flex flex-col p-3 sm:p-5 lg:p-6 font-inter selection:bg-brand-primary selection:text-text-primary">
            <Head title="Registrasi Calon Vendor Mitra - PAGORA" />
            <FlashHandler />

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
                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 p-1 rounded-xl bg-muted/60 border border-border/70 text-xs select-none">
                            {STEPS.map((item) => {
                                const isActive = step === item.id;
                                const isCompleted =
                                    (step > item.id && !stepHasError[item.id]) || (isGoogle && item.id < 3);
                                const hasErr = stepHasError[item.id];

                                return (
                                    <button
                                        key={item.id}
                                        type="button"
                                        onClick={() => handleStepClick(item.id)}
                                        disabled={processing || verifyingOtp}
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
                                        <span className="truncate hidden sm:inline">
                                            {isGoogle && item.id === 2 ? 'Verifikasi (Auto)' : item.label}
                                        </span>
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

                            {/* Banner Akun Google */}
                            {isGoogle && (
                                <div className="p-3.5 rounded-xl border border-blue-500/25 bg-blue-500/5 dark:bg-blue-950/20 flex items-center justify-between gap-3 text-xs">
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
                                        className="text-xs font-medium text-destructive hover:underline shrink-0 cursor-pointer"
                                    >
                                        Batalkan
                                    </Link>
                                </div>
                            )}

                            {/* STEP HEADER */}
                            <div className="flex flex-col gap-1">
                                <h1 className="text-xl sm:text-2xl font-bold tracking-tight text-foreground">
                                    {step === 1 && 'Registrasi Akun Vendor'}
                                    {step === 2 && 'Verifikasi Email Bisnis'}
                                    {step === 3 && 'Profil & Komoditas Perusahaan'}
                                    {step === 4 && 'Dokumen Legalitas Vendor'}
                                </h1>
                                <p className="text-xs sm:text-sm text-muted-foreground">
                                    {step === 1 && 'Langkah 1 dari 4 - Profil akun penanggung jawab'}
                                    {step === 2 && 'Langkah 2 dari 4 - Masukkan kode verifikasi OTP email bisnis'}
                                    {step === 3 && 'Langkah 3 dari 4 - Data perusahaan & kategori komoditas yang dipasok'}
                                    {step === 4 && 'Langkah 4 dari 4 - Nomor identitas & unggah berkas legalitas resmi'}
                                </p>
                            </div>

                            {/* STEP 1: Profil Akun */}
                            {step === 1 && (
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
                                    {/* Opsi Cepat: Daftar dengan Akun Google */}
                                    {!isGoogle && (
                                        <div className="col-span-1 md:col-span-2 space-y-2.5 pb-2">
                                            <a
                                                href="/auth/google/redirect?flow=vendor"
                                                className="inline-flex items-center justify-center gap-2.5 whitespace-nowrap rounded-xl text-xs sm:text-sm font-medium transition-all outline-none select-none border border-border bg-card hover:bg-muted/70 hover:text-foreground h-10 px-4 w-full cursor-pointer shadow-2xs"
                                            >
                                                <svg className="size-4 shrink-0" viewBox="0 0 24 24">
                                                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                                                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                                                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" />
                                                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" />
                                                </svg>
                                                <span>Daftar Cepat dengan Google</span>
                                            </a>

                                            <p className="text-center text-[11px] text-muted-foreground">
                                                Dengan melanjutkan, Anda menyetujui{' '}
                                                <a
                                                    href="/syarat-dan-ketentuan"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="font-semibold text-primary underline underline-offset-4 hover:text-primary/80 transition-colors"
                                                >
                                                    Syarat dan Ketentuan
                                                </a>
                                            </p>

                                            <div className="relative text-center text-xs after:absolute after:inset-0 after:top-1/2 after:z-0 after:flex after:items-center after:border-t after:border-border my-1">
                                                <span className="relative z-10 px-2.5 bg-card text-muted-foreground font-medium text-[11px]">
                                                    atau lengkapi formulir manual di bawah
                                                </span>
                                            </div>
                                        </div>
                                    )}

                                    {/* Nama Lengkap */}
                                    <div className="space-y-1.5">
                                        <div className="flex items-center justify-between">
                                            <Label
                                                htmlFor="name"
                                                className={cn('text-sm font-medium', errors.name && 'text-destructive')}
                                            >
                                                Nama Lengkap<span className="text-destructive">*</span>
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
                                            placeholder="Contoh: Budi Pratama"
                                            value={data.name}
                                            onChange={(e) => {
                                                setData('name', e.target.value);
                                                clearErrors('name');
                                            }}
                                            aria-invalid={!!errors.name}
                                            disabled={isGoogle}
                                            readOnly={isGoogle}
                                            className={cn(
                                                'h-10 w-full',
                                                errors.name && 'border-destructive focus-visible:ring-destructive',
                                                isGoogle && 'bg-muted/50 cursor-not-allowed'
                                            )}
                                            autoFocus={!isGoogle}
                                        />
                                        {errors.name && (
                                            <p className="text-xs font-medium text-destructive flex items-center gap-1.5">
                                                <AlertCircle className="size-3.5 shrink-0" />
                                                <span>{errors.name}</span>
                                            </p>
                                        )}
                                    </div>

                                    {/* Email Bisnis */}
                                    <div className="space-y-1.5">
                                        <div className="flex items-center justify-between">
                                            <Label
                                                htmlFor="email"
                                                className={cn('text-sm font-medium', errors.email && 'text-destructive')}
                                            >
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
                                            placeholder="Contoh: procurement@perusahaan.co.id"
                                            value={data.email}
                                            onChange={(e) => {
                                                const val = e.target.value;
                                                setData((prev) => ({
                                                    ...prev,
                                                    email: val,
                                                    contact_email: val,
                                                    otp: '',
                                                }));
                                                clearErrors('email', 'contact_email', 'otp');
                                                setVerifiedOtpInfo(null);
                                                if (otpSent) {
                                                    setOtpSent(false);
                                                    resend.start(0);
                                                }
                                            }}
                                            aria-invalid={!!errors.email}
                                            disabled={isGoogle}
                                            readOnly={isGoogle}
                                            className={cn(
                                                'h-10 w-full',
                                                errors.email && 'border-destructive focus-visible:ring-destructive',
                                                isGoogle && 'bg-muted/50 cursor-not-allowed'
                                            )}
                                        />
                                        {errors.email && (
                                            <p className="text-xs font-medium text-destructive flex items-center gap-1.5">
                                                <AlertCircle className="size-3.5 shrink-0" />
                                                <span>{errors.email}</span>
                                            </p>
                                        )}
                                    </div>

                                    {/* Kata Sandi & Konfirmasi: Sembunyikan bila daftar via Google */}
                                    {isGoogle ? (
                                        <div className="col-span-1 md:col-span-2 p-3.5 rounded-xl border border-blue-500/25 bg-blue-500/5 dark:bg-blue-950/20 text-xs text-muted-foreground flex items-start gap-2.5">
                                            <CheckCircle2 className="size-4 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" />
                                            <div className="flex-1">
                                                <p className="font-semibold text-foreground">
                                                    Kata Sandi Default &amp; OTP Otomatis
                                                </p>
                                                <p className="text-[11px] mt-0.5 leading-relaxed">
                                                    Identitas Google Anda sudah tervalidasi. Kata sandi unik telah dibuatkan otomatis dan akan dikirimkan ke Gmail Anda setelah pendaftaran selesai. Anda tidak perlu memasukkan kata sandi manual atau verifikasi OTP.
                                                </p>
                                            </div>
                                        </div>
                                    ) : (
                                        <>
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
                                        </>
                                    )}
                                </div>
                            )}

                            {/* STEP 2: Verifikasi OTP Email Bisnis */}
                            {step === 2 && (
                                <div className="space-y-6 max-w-lg mx-auto py-4">
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
                                </div>
                            )}

                            {/* STEP 3: Profil Perusahaan & Komoditas */}
                            {step === 3 && (
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

                                        {/* Nomor Telepon Perusahaan */}
                                        <div className="space-y-1.5">
                                            <Label
                                                htmlFor="phone"
                                                className={cn('text-sm font-medium', errors.phone && 'text-destructive')}
                                            >
                                                Nomor Telepon Perusahaan <span className="text-destructive">*</span>
                                            </Label>
                                            <Input
                                                id="phone"
                                                type="tel"
                                                placeholder="Contoh: 081234567890 atau (021) 5551234"
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
                                            placeholder="Contoh: Jl. Industri Raya No. 45, Kawasan MM2100, Cikarang Barat, Bekasi"
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

                                    {/* Pilih >= 1 Kategori Komoditas (Dipindahkan ke Step 3 Profil Perusahaan) */}
                                    <div className="space-y-1.5 pt-1">
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

                                        {sortedCommodityCategories.length > 0 ? (
                                            <div
                                                data-invalid={!!errors.commodity_category_uuids}
                                                className={cn(
                                                    'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5 p-3 rounded-xl border bg-muted/20',
                                                    errors.commodity_category_uuids
                                                        ? 'border-destructive'
                                                        : 'border-border'
                                                )}
                                            >
                                                {sortedCommodityCategories.map((cat) => {
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
                                </div>
                            )}

                            {/* STEP 4: Dokumen Legalitas */}
                            {step === 4 && (
                                <div className="space-y-4">
                                    <div className="space-y-4 pt-1" data-invalid={hasLegalDocumentsError}>
                                        <div>
                                            <Label
                                                className={cn(
                                                    'text-sm font-semibold',
                                                    hasLegalDocumentsError && 'text-destructive'
                                                )}
                                            >
                                                Dokumen Legalitas Vendor{' '}
                                                <span className="text-destructive">*</span>
                                            </Label>
                                            <p className="text-xs text-muted-foreground mt-0.5">
                                                Lengkapi berkas legalitas resmi perusahaan, status PKP, dan dokumen perizinan berformat PDF maksimal 5 MB per berkas.
                                            </p>
                                        </div>

                                        {errors.legal_documents && (
                                            <div className="rounded-xl border border-destructive/30 bg-destructive/10 p-3 text-xs font-medium text-destructive flex items-start gap-2.5">
                                                <AlertCircle className="size-4 shrink-0 mt-0.5 text-destructive" />
                                                <p className="leading-relaxed">{errors.legal_documents}</p>
                                            </div>
                                        )}

                                        {/* 1. Dokumen NPWP */}
                                        <div className="space-y-3 p-3.5 sm:p-4 rounded-xl border border-border bg-card/40">
                                            <div className="flex items-center justify-between gap-2">
                                                <Label
                                                    htmlFor="tax_number"
                                                    className={cn(
                                                        'text-xs font-semibold uppercase tracking-wider',
                                                        (errors.tax_number || errors.dokumen_npwp || errors.file_npwp)
                                                            ? 'text-destructive'
                                                            : 'text-muted-foreground'
                                                    )}
                                                >
                                                    1. Dokumen NPWP Perusahaan <span className="text-destructive">*</span>
                                                </Label>
                                            </div>

                                            {/* Nomor NPWP diatas field upload */}
                                            <div className="space-y-1">
                                                <Label
                                                    htmlFor="tax_number"
                                                    className={cn(
                                                        'text-xs text-muted-foreground font-normal',
                                                        errors.tax_number && 'text-destructive'
                                                    )}
                                                >
                                                    Nomor NPWP Perusahaan <span className="text-destructive">*</span>
                                                </Label>
                                                <Input
                                                    id="tax_number"
                                                    type="text"
                                                    placeholder="Contoh: 01.234.567.8-901.000 atau 16 digit"
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
                                                        'h-9 text-xs sm:text-sm',
                                                        errors.tax_number && 'border-destructive focus-visible:ring-destructive'
                                                    )}
                                                />
                                                {errors.tax_number && (
                                                    <p className="text-xs font-medium text-destructive flex items-center gap-1.5">
                                                        <AlertCircle className="size-3.5 shrink-0" />
                                                        <span>{errors.tax_number}</span>
                                                    </p>
                                                )}
                                            </div>

                                            {/* Upload Dokumen NPWP */}
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

                                        {/* 2. Dokumen NIB */}
                                        <div className="space-y-3 p-3.5 sm:p-4 rounded-xl border border-border bg-card/40">
                                            <div className="flex items-center justify-between gap-2">
                                                <Label
                                                    htmlFor="vendor_nomor_nib"
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

                                            {/* Nomor NIB diatas field upload */}
                                            <div className="space-y-1">
                                                <Label
                                                    htmlFor="vendor_nomor_nib"
                                                    className={cn(
                                                        'text-xs text-muted-foreground font-normal',
                                                        errors.nomor_nib && 'text-destructive'
                                                    )}
                                                >
                                                    Nomor NIB Perusahaan <span className="text-destructive">*</span>
                                                </Label>
                                                <Input
                                                    id="vendor_nomor_nib"
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

                                            {/* Upload Dokumen NIB */}
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

                                        {/* 3. Dokumen KTP Penanggung Jawab */}
                                        <div className="space-y-3 p-3.5 sm:p-4 rounded-xl border border-border bg-card/40">
                                            <div className="flex items-center justify-between gap-2">
                                                <Label
                                                    htmlFor="vendor_nomor_ktp"
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

                                            {/* Nomor KTP diatas field upload */}
                                            <div className="space-y-1">
                                                <Label
                                                    htmlFor="vendor_nomor_ktp"
                                                    className={cn(
                                                        'text-xs text-muted-foreground font-normal',
                                                        errors.nomor_ktp && 'text-destructive'
                                                    )}
                                                >
                                                    Nomor KTP / NIK Penanggung Jawab <span className="text-destructive">*</span>
                                                </Label>
                                                <Input
                                                    id="vendor_nomor_ktp"
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

                                            {/* Upload Dokumen KTP */}
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

                                        {/* 4. Status PKP Perusahaan (PPN vs Non PPN) */}
                                        <div className="space-y-3 p-3.5 sm:p-4 rounded-xl border border-border bg-card/40">
                                            <div className="flex items-center justify-between gap-2">
                                                <Label
                                                    className={cn(
                                                        'text-xs font-semibold uppercase tracking-wider',
                                                        (errors.pkp_status || errors.is_pkp)
                                                            ? 'text-destructive'
                                                            : 'text-muted-foreground'
                                                    )}
                                                >
                                                    4. Status PKP Perusahaan (Pengusaha Kena Pajak) <span className="text-destructive">*</span>
                                                </Label>
                                                <Badge
                                                    variant={data.is_pkp ? 'default' : 'secondary'}
                                                    className="text-[11px] font-medium"
                                                >
                                                    {data.is_pkp ? 'PPN' : 'Non PPN'}
                                                </Badge>
                                            </div>

                                            <p className="text-xs text-muted-foreground">
                                                Apakah perusahaan Anda berstatus Pengusaha Kena Pajak (PKP)? Pilih salah satu opsi di bawah ini:
                                            </p>

                                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5 pt-1">
                                                {/* Opsi 1: PPN / PKP */}
                                                <label
                                                    className={cn(
                                                        'flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-all select-none',
                                                        data.pkp_status === 'ppn' || data.is_pkp === true
                                                            ? 'border-primary bg-primary/5 shadow-2xs'
                                                            : 'border-border/80 bg-background hover:bg-muted/30 hover:border-border'
                                                    )}
                                                >
                                                    <input
                                                        type="radio"
                                                        name="vendor_pkp_status"
                                                        value="ppn"
                                                        checked={data.pkp_status === 'ppn' || data.is_pkp === true}
                                                        onChange={() => {
                                                            setData((prev) => ({
                                                                ...prev,
                                                                pkp_status: 'ppn',
                                                                is_pkp: true,
                                                            }));
                                                            clearErrors('pkp_status', 'is_pkp');
                                                        }}
                                                        disabled={processing}
                                                        className="mt-0.5 text-primary focus:ring-primary size-4 shrink-0"
                                                    />
                                                    <div className="space-y-1">
                                                        <div className="flex items-center gap-1.5">
                                                            <span className="text-xs sm:text-sm font-semibold text-foreground">
                                                                PPN (Pengusaha Kena Pajak)
                                                            </span>
                                                            <Badge variant="outline" className="text-[10px] font-medium border-primary/40 text-primary">
                                                                PKP
                                                            </Badge>
                                                        </div>
                                                        <p className="text-[11px] leading-relaxed text-muted-foreground">
                                                            Perusahaan berhak menagih PPN dan menerbitkan Faktur Pajak. Wajib melampirkan berkas SPPKP resmi.
                                                        </p>
                                                    </div>
                                                </label>

                                                {/* Opsi 2: Non PPN / Non-PKP */}
                                                <label
                                                    className={cn(
                                                        'flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-all select-none',
                                                        data.pkp_status === 'non_ppn' && data.is_pkp === false
                                                            ? 'border-primary bg-primary/5 shadow-2xs'
                                                            : 'border-border/80 bg-background hover:bg-muted/30 hover:border-border'
                                                    )}
                                                >
                                                    <input
                                                        type="radio"
                                                        name="vendor_pkp_status"
                                                        value="non_ppn"
                                                        checked={data.pkp_status === 'non_ppn' && data.is_pkp === false}
                                                        onChange={() => {
                                                            setData((prev) => ({
                                                                ...prev,
                                                                pkp_status: 'non_ppn',
                                                                is_pkp: false,
                                                                dokumen_sppkp: null,
                                                                nomor_sppkp: '',
                                                            }));
                                                            clearErrors('pkp_status', 'is_pkp', 'dokumen_sppkp', 'file_sppkp');
                                                        }}
                                                        disabled={processing}
                                                        className="mt-0.5 text-primary focus:ring-primary size-4 shrink-0"
                                                    />
                                                    <div className="space-y-1">
                                                        <div className="flex items-center gap-1.5">
                                                            <span className="text-xs sm:text-sm font-semibold text-foreground">
                                                                Non PPN (Bukan PKP)
                                                            </span>
                                                            <Badge variant="outline" className="text-[10px] font-medium text-muted-foreground">
                                                                Non-PKP
                                                            </Badge>
                                                        </div>
                                                        <p className="text-[11px] leading-relaxed text-muted-foreground">
                                                            Perusahaan UMKM/Non-PKP dan tidak memungut komponen PPN pada invoice atau penawaran harga.
                                                        </p>
                                                    </div>
                                                </label>
                                            </div>

                                            {(errors.pkp_status || errors.is_pkp) && (
                                                <p className="text-xs font-medium text-destructive flex items-center gap-1.5">
                                                    <AlertCircle className="size-3.5 shrink-0" />
                                                    <span>{errors.pkp_status || errors.is_pkp}</span>
                                                </p>
                                            )}
                                        </div>

                                        {/* Dokumen Kondisional: SPPKP (Wajib jika PKP / PPN) */}
                                        {(data.pkp_status === 'ppn' || data.is_pkp === true) && (
                                            <div className="space-y-3 p-3.5 sm:p-4 rounded-xl border border-primary/40 bg-primary/5">
                                                <div className="flex items-center justify-between gap-2">
                                                    <Label
                                                        htmlFor="vendor_nomor_sppkp"
                                                        className={cn(
                                                            'text-xs font-semibold uppercase tracking-wider',
                                                            (errors.nomor_sppkp || errors.dokumen_sppkp || errors.file_sppkp)
                                                                ? 'text-destructive'
                                                                : 'text-primary'
                                                        )}
                                                    >
                                                        Dokumen SPPKP (Surat Pengukuhan Pengusaha Kena Pajak) <span className="text-destructive">*</span>
                                                    </Label>
                                                    <Badge variant="default" className="text-[10px] font-medium">
                                                        Wajib untuk PKP
                                                    </Badge>
                                                </div>

                                                <p className="text-xs text-muted-foreground">
                                                    Unggah berkas SPPKP resmi dari Direktorat Jenderal Pajak (DJP) untuk memverifikasi hak penagihan PPN 11% perusahaan Anda.
                                                </p>

                                                {/* Nomor SPPKP */}
                                                <div className="space-y-1">
                                                    <Label
                                                        htmlFor="vendor_nomor_sppkp"
                                                        className={cn(
                                                            'text-xs text-muted-foreground font-normal',
                                                            errors.nomor_sppkp && 'text-destructive'
                                                        )}
                                                    >
                                                        Nomor SPPKP Perusahaan <span className="text-muted-foreground">(Opsional)</span>
                                                    </Label>
                                                    <Input
                                                        id="vendor_nomor_sppkp"
                                                        type="text"
                                                        placeholder="Contoh: S-123PKP/WPJ.07/KP.0303/2023 atau Nomor Surat Pengukuhan"
                                                        value={data.nomor_sppkp}
                                                        onChange={(e) => {
                                                            setData('nomor_sppkp', e.target.value);
                                                            if (errors.nomor_sppkp) clearErrors('nomor_sppkp');
                                                        }}
                                                        disabled={processing}
                                                        aria-invalid={!!errors.nomor_sppkp}
                                                        className={cn(
                                                            'h-9 text-xs sm:text-sm bg-background',
                                                            errors.nomor_sppkp && 'border-destructive focus-visible:ring-destructive'
                                                        )}
                                                    />
                                                    {errors.nomor_sppkp && (
                                                        <p className="text-xs font-medium text-destructive">
                                                            {errors.nomor_sppkp}
                                                        </p>
                                                    )}
                                                </div>

                                                {/* Upload Dokumen SPPKP */}
                                                <div className="space-y-1">
                                                    <FileDropzone
                                                        value={data.dokumen_sppkp}
                                                        onChange={(file) => {
                                                            setData('dokumen_sppkp', file);
                                                            if (errors.dokumen_sppkp || errors.file_sppkp) {
                                                                clearErrors('dokumen_sppkp', 'file_sppkp', 'legal_documents');
                                                            }
                                                        }}
                                                        onError={(msg) => {
                                                            setError('dokumen_sppkp', msg);
                                                            notify.error(msg);
                                                        }}
                                                        error={errors.dokumen_sppkp || errors.file_sppkp}
                                                        accept={PDF_ACCEPT}
                                                        maxSize={MAX_DOCUMENT_SIZE}
                                                        disabled={processing}
                                                        uploading={sppkpFeedback.uploading}
                                                        progress={sppkpFeedback.progress}
                                                        status={sppkpFeedback.status}
                                                        label="Unggah Berkas SPPKP"
                                                        description="Hanya format PDF (.pdf) dengan ukuran maksimal 5 MB"
                                                    />
                                                    {(errors.dokumen_sppkp || errors.file_sppkp) && (
                                                        <p className="text-xs font-medium text-destructive flex items-center gap-1.5 mt-1">
                                                            <AlertCircle className="size-3.5 shrink-0" />
                                                            <span>{errors.dokumen_sppkp || errors.file_sppkp}</span>
                                                        </p>
                                                    )}
                                                </div>
                                            </div>
                                        )}

                                        {/* Dokumen Tambahan: Akta Pendirian & SK Kemenkumham (Opsional) */}
                                        <div className="space-y-3 p-3.5 sm:p-4 rounded-xl border border-dashed border-border bg-card/30">
                                            <div className="flex items-center justify-between gap-2">
                                                <Label
                                                    htmlFor="vendor_nomor_akta"
                                                    className={cn(
                                                        'text-xs font-semibold uppercase tracking-wider',
                                                        (errors.nomor_akta || errors.dokumen_akta || errors.file_akta)
                                                            ? 'text-destructive'
                                                            : 'text-muted-foreground'
                                                    )}
                                                >
                                                    5. Dokumen Akta Pendirian & SK Kemenkumham
                                                </Label>
                                                <Badge variant="outline" className="text-[10px] font-medium text-muted-foreground">
                                                    Opsional
                                                </Badge>
                                            </div>

                                            <p className="text-xs text-muted-foreground">
                                                Lampirkan berkas akta dan pengesahan Kemenkumham bila perusahaan Anda berbentuk badan hukum resmi (PT/CV) untuk memenuhi syarat tender yang mensyaratkan badan hukum.
                                            </p>

                                            {/* Nomor Akta */}
                                            <div className="space-y-1">
                                                <Label
                                                    htmlFor="vendor_nomor_akta"
                                                    className={cn(
                                                        'text-xs text-muted-foreground font-normal',
                                                        errors.nomor_akta && 'text-destructive'
                                                    )}
                                                >
                                                    Nomor Akta Pendirian / SK Kemenkumham <span className="text-muted-foreground">(Opsional)</span>
                                                </Label>
                                                <Input
                                                    id="vendor_nomor_akta"
                                                    type="text"
                                                    placeholder="Contoh: AHU-0012345.AH.01.01.TAHUN 2023 atau Nomor Akta Notaris"
                                                    value={data.nomor_akta}
                                                    onChange={(e) => {
                                                        setData('nomor_akta', e.target.value);
                                                        if (errors.nomor_akta) clearErrors('nomor_akta');
                                                    }}
                                                    disabled={processing}
                                                    aria-invalid={!!errors.nomor_akta}
                                                    className={cn(
                                                        'h-9 text-xs sm:text-sm',
                                                        errors.nomor_akta && 'border-destructive focus-visible:ring-destructive'
                                                    )}
                                                />
                                                {errors.nomor_akta && (
                                                    <p className="text-xs font-medium text-destructive">
                                                        {errors.nomor_akta}
                                                    </p>
                                                )}
                                            </div>

                                            {/* Upload Dokumen Akta */}
                                            <div className="space-y-1">
                                                <FileDropzone
                                                    value={data.dokumen_akta}
                                                    onChange={(file) => {
                                                        setData('dokumen_akta', file);
                                                        if (errors.dokumen_akta || errors.file_akta) {
                                                            clearErrors('dokumen_akta', 'file_akta');
                                                        }
                                                    }}
                                                    onError={(msg) => {
                                                        setError('dokumen_akta', msg);
                                                        notify.error(msg);
                                                    }}
                                                    error={errors.dokumen_akta || errors.file_akta}
                                                    accept={PDF_ACCEPT}
                                                    maxSize={MAX_DOCUMENT_SIZE}
                                                    disabled={processing}
                                                    uploading={aktaFeedback.uploading}
                                                    progress={aktaFeedback.progress}
                                                    status={aktaFeedback.status}
                                                    label="Unggah Berkas Akta Pendirian / SK Kemenkumham"
                                                    description="Hanya format PDF (.pdf) dengan ukuran maksimal 5 MB (Opsional)"
                                                />
                                                {(errors.dokumen_akta || errors.file_akta) && (
                                                    <p className="text-xs font-medium text-destructive flex items-center gap-1.5 mt-1">
                                                        <AlertCircle className="size-3.5 shrink-0" />
                                                        <span>{errors.dokumen_akta || errors.file_akta}</span>
                                                    </p>
                                                )}
                                            </div>
                                        </div>

                                        {/* Checkbox Persetujuan Syarat dan Ketentuan */}
                                        <div className="flex items-start gap-3 rounded-xl border border-border/70 bg-card p-3.5 transition-colors hover:bg-muted/30">
                                            <Checkbox
                                                id="terms-vendor"
                                                checked={termsAccepted}
                                                onCheckedChange={(checked) => {
                                                    const val = Boolean(checked);
                                                    setTermsAccepted(val);
                                                    setData('terms_accepted', val);
                                                }}
                                                disabled={processing}
                                                className="mt-0.5 shrink-0"
                                            />
                                            <Label
                                                htmlFor="terms-vendor"
                                                className="text-xs font-normal leading-relaxed text-muted-foreground cursor-pointer select-none"
                                            >
                                                {renderTermsLabel(checkboxLabel)}
                                            </Label>
                                        </div>
                                    </div>
                                </div>
                            )}                        </div>
                    </div>

                    {/* Pinned Bottom Card Footer: Login Link & Compact Step Navigation */}
                    <div className="shrink-0 border-t border-border/80 bg-card px-5 py-3.5 sm:px-8 flex items-center justify-between gap-3">
                        <div className="text-xs text-muted-foreground space-y-0.5">
                            <p>
                                Sudah memiliki akun?{' '}
                                <Link href="/login" className="font-semibold text-foreground hover:underline">
                                    Masuk
                                </Link>
                            </p>
                            <p className="text-[11px]">
                                Dengan mendaftar, Anda menyetujui{' '}
                                <Link
                                    href="/syarat-dan-ketentuan"
                                    className="font-semibold text-foreground underline underline-offset-4 hover:text-primary"
                                >
                                    Syarat &amp; Ketentuan PAGORA
                                </Link>
                                .
                            </p>
                        </div>

                        <div className="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                onClick={() => goToStep(step === 3 && isGoogle ? 1 : step - 1)}
                                disabled={processing || verifyingOtp || sendingOtp || step === 1}
                                aria-label={step === 3 && isGoogle ? 'Kembali ke profil akun' : 'Langkah sebelumnya'}
                                title={step === 3 && isGoogle ? 'Kembali ke profil akun' : 'Langkah sebelumnya'}
                                className="size-10 rounded-xl cursor-pointer"
                            >
                                <ChevronLeft className="size-4" />
                            </Button>

                            {step < 4 ? (
                                <Button
                                    type="button"
                                    size="icon"
                                    onClick={handleNextStep}
                                    disabled={
                                        processing ||
                                        verifyingOtp ||
                                        sendingOtp ||
                                        (step === 2 && data.otp.length !== OTP_LENGTH)
                                    }
                                    aria-label="Lanjut ke langkah berikutnya"
                                    title="Lanjut ke langkah berikutnya"
                                    className="size-10 rounded-xl cursor-pointer"
                                >
                                    {verifyingOtp || ((step === 1 || step === 2) && sendingOtp) ? (
                                        <span className="size-4 border-2 border-primary-foreground border-t-transparent rounded-full animate-spin" />
                                    ) : (
                                        <ChevronRight className="size-4" />
                                    )}
                                </Button>
                            ) : (
                                <LoadingButton
                                    type="submit"
                                    loading={processing}
                                    disabled={processing || !termsAccepted}
                                    loadingText={uploadingLabel(
                                        legalDocumentFeedback.progress,
                                        'Mengirim...'
                                    )}
                                    className="h-10 px-5 rounded-xl cursor-pointer font-medium disabled:cursor-not-allowed disabled:opacity-50"
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
