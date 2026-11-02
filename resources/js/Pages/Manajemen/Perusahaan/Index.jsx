import { Head, useForm } from '@inertiajs/react';
import {
    Building2,
    ShieldCheck,
    FileText,
    Mail,
    Phone,
    MapPin,
    Calendar,
    Download,
    AlertCircle,
    CheckCircle2,
    Clock,
    XCircle,
    Save,
    ExternalLink,
    Tag,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { notify } from '@/lib/notify';
import { isNpwp, isEmail, isPhoneNumber, normalizeNpwp } from '@/lib/validators';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Badge } from '@/Components/ui/badge';
import { Textarea } from '@/Components/ui/textarea';
import { LoadingButton } from '@/Components/loading-button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';

function getStatusBadge(status) {
    switch (status) {
        case 'approved':
            return {
                variant: 'success',
                label: 'Terverifikasi',
                icon: CheckCircle2,
            };
        case 'rejected':
            return {
                variant: 'destructive',
                label: 'Ditolak',
                icon: XCircle,
            };
        case 'pending':
        default:
            return {
                variant: 'warning',
                label: 'Menunggu Verifikasi',
                icon: Clock,
            };
    }
}

function formatDate(isoString) {
    if (!isoString) return '-';
    try {
        return new Intl.DateTimeFormat('id-ID', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        }).format(new Date(isoString));
    } catch {
        return isoString;
    }
}

export default function PerusahaanIndex({ perusahaan }) {
    const isVendor = perusahaan?.tipe === 'vendor';

    const { data, setData, put, processing, errors, setError, clearErrors } = useForm({
        company_name: perusahaan?.company_name ?? '',
        tax_number: perusahaan?.tax_number ?? '',
        address: perusahaan?.address ?? '',
        contact_email: perusahaan?.contact_email ?? '',
        phone: perusahaan?.phone ?? '',
    });

    const statusInfo = getStatusBadge(perusahaan?.verification_status);
    const StatusIcon = statusInfo.icon;

    const handleSubmit = (e) => {
        e.preventDefault();

        let hasError = false;

        if (!data.company_name.trim() || data.company_name.trim().length < 3) {
            setError('company_name', 'Nama perusahaan minimal 3 karakter.');
            hasError = true;
        }

        if (!data.tax_number.trim() || !isNpwp(data.tax_number)) {
            setError('tax_number', 'Format nomor NPWP tidak valid (harus 15 atau 16 digit).');
            hasError = true;
        }

        if (!data.address.trim() || data.address.trim().length < 5) {
            setError('address', 'Alamat perusahaan minimal 5 karakter.');
            hasError = true;
        }

        if (isVendor) {
            if (!data.contact_email.trim() || !isEmail(data.contact_email)) {
                setError('contact_email', 'Format email kontak tidak valid.');
                hasError = true;
            }

            if (data.phone && data.phone.trim() !== '' && !isPhoneNumber(data.phone)) {
                setError('phone', 'Nomor telepon tidak valid (8-15 digit angka).');
                hasError = true;
            }
        }

        if (hasError) {
            notify.validationFailed();
            return;
        }

        put('/manajemen/perusahaan', {
            preserveScroll: true,
            onError: () => notify.validationFailed(),
        });
    };

    return (
        <DashboardLayout
            breadcrumbs={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'Manajemen' },
                { label: 'Profil Perusahaan' },
            ]}
        >
            <Head title="Profil Perusahaan" />

            <div className="flex flex-col gap-6">
                {/* Header Information */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b pb-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2.5">
                            <Building2 className="size-6 text-emerald-600 dark:text-emerald-400" />
                            Profil Perusahaan
                        </h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Kelola data profil resmi dan lihat status legalitas perusahaan Anda di platform PAGORA.
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Badge variant={statusInfo.variant} className="gap-1.5 py-1 px-3 text-xs font-semibold">
                            <StatusIcon className="size-3.5" />
                            {statusInfo.label}
                        </Badge>
                        {isVendor && (
                            <Badge variant={perusahaan?.is_active ? 'success' : 'secondary'} className="py-1 px-3 text-xs">
                                {perusahaan?.is_active ? 'Akun Aktif' : 'Non-Aktif'}
                            </Badge>
                        )}
                    </div>
                </div>

                {/* Form & Sidebar Grid */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Main Form: Company Profile (Col Span 2) */}
                    <div className="lg:col-span-2">
                        <form noValidate onSubmit={handleSubmit}>
                            <Card className="shadow-xs border border-border/80">
                                <CardHeader className="py-4 px-6 border-b border-border/60">
                                    <CardTitle className="text-base font-semibold text-foreground">
                                        Data Resmi Perusahaan
                                    </CardTitle>
                                    <CardDescription className="text-xs text-muted-foreground">
                                        Perubahan nama atau data legal akan tercatat pada dokumen pengadaan dan kontrak.
                                    </CardDescription>
                                </CardHeader>

                                <CardContent className="p-6 space-y-5">
                                    {/* Company Name */}
                                    <div className="space-y-1.5">
                                        <Label htmlFor="company_name" className={cn('text-sm font-medium', errors.company_name && 'text-destructive')}>
                                            Nama Perusahaan <span className="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            id="company_name"
                                            value={data.company_name}
                                            onChange={(e) => {
                                                setData('company_name', e.target.value);
                                                clearErrors('company_name');
                                            }}
                                            aria-invalid={!!errors.company_name}
                                            placeholder="contoh: PT Pagora Nusantara"
                                            className={cn(
                                                'h-10',
                                                errors.company_name && 'border-destructive focus-visible:ring-destructive'
                                            )}
                                        />
                                        {errors.company_name ? (
                                            <p className="text-xs text-destructive">{errors.company_name}</p>
                                        ) : (
                                            <p className="text-xs text-muted-foreground">
                                                Nama resmi badan usaha sesuai dengan akta pendirian atau NIB.
                                            </p>
                                        )}
                                    </div>

                                    {/* NPWP / Tax Number */}
                                    <div className="space-y-1.5">
                                        <Label htmlFor="tax_number" className={cn('text-sm font-medium', errors.tax_number && 'text-destructive')}>
                                            Nomor NPWP Perusahaan <span className="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            id="tax_number"
                                            value={data.tax_number}
                                            onChange={(e) => {
                                                setData('tax_number', e.target.value);
                                                clearErrors('tax_number');
                                            }}
                                            onBlur={() => {
                                                if (data.tax_number) {
                                                    setData('tax_number', normalizeNpwp(data.tax_number));
                                                }
                                            }}
                                            aria-invalid={!!errors.tax_number}
                                            placeholder="contoh: 01.234.567.8-901.000"
                                            className={cn(
                                                'h-10 font-mono text-sm',
                                                errors.tax_number && 'border-destructive focus-visible:ring-destructive'
                                            )}
                                        />
                                        {errors.tax_number ? (
                                            <p className="text-xs text-destructive">{errors.tax_number}</p>
                                        ) : (
                                            <p className="text-xs text-muted-foreground">
                                                Format 15 digit resmi (XX.XXX.XXX.X-XXX.XXX) atau 16 digit angka.
                                            </p>
                                        )}
                                    </div>

                                    {/* Vendor Specific: Contact Email & Phone */}
                                    {isVendor && (
                                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div className="space-y-1.5">
                                                <Label htmlFor="contact_email" className={cn('text-sm font-medium', errors.contact_email && 'text-destructive')}>
                                                    Email Kontak Vendor <span className="text-destructive">*</span>
                                                </Label>
                                                <div className="relative">
                                                    <Mail className="absolute left-3 top-2.5 size-4 text-muted-foreground pointer-events-none" />
                                                    <Input
                                                        id="contact_email"
                                                        type="email"
                                                        value={data.contact_email}
                                                        onChange={(e) => {
                                                            setData('contact_email', e.target.value);
                                                            clearErrors('contact_email');
                                                        }}
                                                        aria-invalid={!!errors.contact_email}
                                                        placeholder="kontak@perusahaan.com"
                                                        className={cn(
                                                            'pl-9 h-10',
                                                            errors.contact_email && 'border-destructive focus-visible:ring-destructive'
                                                        )}
                                                    />
                                                </div>
                                                {errors.contact_email && (
                                                    <p className="text-xs text-destructive">{errors.contact_email}</p>
                                                )}
                                            </div>

                                            <div className="space-y-1.5">
                                                <Label htmlFor="phone" className={cn('text-sm font-medium', errors.phone && 'text-destructive')}>
                                                    Nomor Telepon Kantor
                                                </Label>
                                                <div className="relative">
                                                    <Phone className="absolute left-3 top-2.5 size-4 text-muted-foreground pointer-events-none" />
                                                    <Input
                                                        id="phone"
                                                        type="tel"
                                                        value={data.phone}
                                                        onChange={(e) => {
                                                            setData('phone', e.target.value);
                                                            clearErrors('phone');
                                                        }}
                                                        aria-invalid={!!errors.phone}
                                                        placeholder="081234567890"
                                                        className={cn(
                                                            'pl-9 h-10',
                                                            errors.phone && 'border-destructive focus-visible:ring-destructive'
                                                        )}
                                                    />
                                                </div>
                                                {errors.phone && (
                                                    <p className="text-xs text-destructive">{errors.phone}</p>
                                                )}
                                            </div>
                                        </div>
                                    )}

                                    {/* Address */}
                                    <div className="space-y-1.5">
                                        <Label htmlFor="address" className={cn('text-sm font-medium', errors.address && 'text-destructive')}>
                                            Alamat Lengkap Perusahaan <span className="text-destructive">*</span>
                                        </Label>
                                        <Textarea
                                            id="address"
                                            rows={3}
                                            value={data.address}
                                            onChange={(e) => {
                                                setData('address', e.target.value);
                                                clearErrors('address');
                                            }}
                                            aria-invalid={!!errors.address}
                                            placeholder="Masukkan alamat lengkap kantor operasional atau domisili hukum..."
                                            className={cn(
                                                'resize-none',
                                                errors.address && 'border-destructive focus-visible:ring-destructive'
                                            )}
                                        />
                                        {errors.address ? (
                                            <p className="text-xs text-destructive">{errors.address}</p>
                                        ) : (
                                            <p className="text-xs text-muted-foreground">
                                                Alamat tercatat untuk pengiriman dokumen fisik atau korespondensi resmi.
                                            </p>
                                        )}
                                    </div>
                                </CardContent>

                                <CardFooter className="py-4 px-6 border-t border-border/60 bg-muted/20 flex items-center justify-end">
                                    <LoadingButton
                                        type="submit"
                                        loading={processing}
                                        loadingText="Menyimpan Perubahan..."
                                        className="gap-2 bg-emerald-600 hover:bg-emerald-700 text-white"
                                    >
                                        <Save className="size-4" />
                                        Simpan Perubahan
                                    </LoadingButton>
                                </CardFooter>
                            </Card>
                        </form>
                    </div>

                    {/* Legal Document & Meta Information (Col Span 1) */}
                    <div className="space-y-6">
                        {/* Legal Status Card */}
                        <Card className="shadow-xs border border-border/80">
                            <CardHeader className="py-4 px-5 border-b border-border/60">
                                <CardTitle className="text-sm font-semibold flex items-center gap-2 text-foreground">
                                    <ShieldCheck className="size-4 text-emerald-600 dark:text-emerald-400" />
                                    Legalitas & Verifikasi
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="p-5 space-y-4">
                                <div className="space-y-1">
                                    <span className="text-xs text-muted-foreground">Status Verifikasi</span>
                                    <div className="pt-0.5">
                                        <Badge variant={statusInfo.variant} className="gap-1.5 py-1 px-2.5 text-xs">
                                            <StatusIcon className="size-3.5" />
                                            {statusInfo.label}
                                        </Badge>
                                    </div>
                                </div>

                                {perusahaan?.verification_note && (
                                    <div className="p-3 rounded-lg bg-amber-500/10 border border-amber-500/20 text-xs text-amber-800 dark:text-amber-300 space-y-1">
                                        <div className="font-semibold flex items-center gap-1.5">
                                            <AlertCircle className="size-3.5 shrink-0" />
                                            Catatan Tim Verifikasi
                                        </div>
                                        <p className="leading-relaxed">{perusahaan.verification_note}</p>
                                    </div>
                                )}

                                {perusahaan?.verified_at && (
                                    <div className="space-y-1 text-xs">
                                        <span className="text-muted-foreground">Diverifikasi Pada:</span>
                                        <p className="font-medium text-foreground">{formatDate(perusahaan.verified_at)}</p>
                                        {perusahaan.verified_by && (
                                            <p className="text-muted-foreground">Oleh: {perusahaan.verified_by}</p>
                                        )}
                                    </div>
                                )}

                                {/* Tenant Document Download */}
                                {!isVendor && perusahaan?.has_legal_document && perusahaan?.legal_document_url && (
                                    <div className="pt-2 border-t border-border/60">
                                        <span className="text-xs text-muted-foreground block mb-2">Dokumen Legalitas (NIB / SIUP):</span>
                                        <a
                                            href={perusahaan.legal_document_url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="inline-flex items-center gap-2 text-xs font-medium text-emerald-600 dark:text-emerald-400 hover:underline"
                                        >
                                            <FileText className="size-4" />
                                            Unduh Dokumen Legalitas
                                            <ExternalLink className="size-3" />
                                        </a>
                                    </div>
                                )}

                                {/* Vendor Documents */}
                                {isVendor && perusahaan?.documents && (
                                    <div className="pt-2 border-t border-border/60 space-y-3">
                                        <span className="text-xs text-muted-foreground block">Dokumen Legalitas Vendor:</span>
                                        {perusahaan.documents.length === 0 ? (
                                            <p className="text-xs text-muted-foreground italic">Belum ada dokumen yang diunggah.</p>
                                        ) : (
                                            <div className="space-y-2">
                                                {perusahaan.documents.map((doc) => {
                                                    const docStatus = getStatusBadge(doc.verification_status);
                                                    return (
                                                        <div
                                                            key={doc.uuid}
                                                            className="p-2.5 rounded-lg border border-border/70 bg-muted/30 flex items-center justify-between gap-2"
                                                        >
                                                            <div className="min-w-0">
                                                                <p className="text-xs font-medium text-foreground truncate">
                                                                    {doc.document_type}
                                                                </p>
                                                                {doc.document_number && (
                                                                    <p className="text-[11px] text-muted-foreground font-mono truncate">
                                                                        No: {doc.document_number}
                                                                    </p>
                                                                )}
                                                                <div className="mt-1">
                                                                    <Badge variant={docStatus.variant} className="text-[10px] px-1.5 py-0.5">
                                                                        {docStatus.label}
                                                                    </Badge>
                                                                </div>
                                                            </div>
                                                            {doc.file_url && (
                                                                <a
                                                                    href={doc.file_url}
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    className="p-1.5 rounded-md text-muted-foreground hover:text-foreground hover:bg-muted shrink-0"
                                                                    title="Unduh Berkas"
                                                                >
                                                                    <Download className="size-4" />
                                                                </a>
                                                            )}
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        )}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Additional Info Card */}
                        <Card className="shadow-xs border border-border/80">
                            <CardHeader className="py-4 px-5 border-b border-border/60">
                                <CardTitle className="text-sm font-semibold flex items-center gap-2 text-foreground">
                                    <Calendar className="size-4 text-emerald-600 dark:text-emerald-400" />
                                    Informasi Sistem
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="p-5 space-y-3 text-xs">
                                <div>
                                    <span className="text-muted-foreground">Tipe Akun:</span>
                                    <p className="font-semibold text-foreground capitalize">
                                        {isVendor ? 'Vendor / Rekanan' : 'Tenant (Perusahaan Pembeli)'}
                                    </p>
                                </div>
                                {isVendor && perusahaan?.kategori_komoditas && (
                                    <div>
                                        <span className="text-muted-foreground">Kategori Komoditas:</span>
                                        <div className="mt-1 flex items-center gap-1.5 flex-wrap">
                                            <Tag className="size-3.5 text-muted-foreground" />
                                            <span className="font-medium text-foreground">
                                                {perusahaan.kategori_komoditas}
                                            </span>
                                        </div>
                                    </div>
                                )}
                                <div>
                                    <span className="text-muted-foreground">Terdaftar Sejak:</span>
                                    <p className="font-medium text-foreground">{formatDate(perusahaan?.created_at)}</p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}

