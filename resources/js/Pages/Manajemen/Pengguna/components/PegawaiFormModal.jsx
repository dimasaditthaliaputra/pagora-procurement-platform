import { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { UserPlus, UserCog, Mail, ShieldAlert, KeyRound } from 'lucide-react';
import { cn } from '@/lib/utils';
import { notify } from '@/lib/notify';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { LoadingButton } from '@/Components/loading-button';
import { Button } from '@/Components/ui/button';

export function PegawaiFormModal({
    open,
    onOpenChange,
    pegawai = null,
    role_options = [],
    default_password = 'Pagora@2026',
}) {
    const isEdit = Boolean(pegawai);

    const {
        data,
        setData,
        post,
        put,
        processing,
        errors,
        setError,
        clearErrors,
        reset,
    } = useForm({
        nama_lengkap: '',
        email: '',
        role: '',
        password: '',
        status_aktif: true,
    });

    // Reset or populate form when dialog opens or selected employee changes
    useEffect(() => {
        if (open) {
            if (pegawai) {
                setData({
                    nama_lengkap: pegawai.nama_lengkap ?? '',
                    email: pegawai.email ?? '',
                    role: pegawai.role?.name ?? '',
                    password: '',
                    status_aktif: pegawai.status_aktif ?? true,
                });
            } else {
                setData({
                    nama_lengkap: '',
                    email: '',
                    role: '',
                    password: '',
                    status_aktif: true,
                });
            }
            clearErrors();
        }
    }, [open, pegawai]);

    const handleClose = () => {
        if (processing) return;
        reset();
        clearErrors();
        onOpenChange(false);
    };

    const handleSubmit = (e) => {
        e.preventDefault();

        // Frontend validation
        let hasError = false;

        if (!data.nama_lengkap.trim()) {
            setError('nama_lengkap', 'Nama lengkap pegawai wajib diisi.');
            hasError = true;
        } else if (data.nama_lengkap.trim().length < 3) {
            setError('nama_lengkap', 'Nama lengkap minimal 3 karakter.');
            hasError = true;
        }

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!data.email.trim()) {
            setError('email', 'Email pegawai wajib diisi.');
            hasError = true;
        } else if (!emailRegex.test(data.email.trim())) {
            setError('email', 'Format email tidak valid.');
            hasError = true;
        }

        if (!data.role) {
            setError('role', 'Role pegawai (Buyer atau QC Gudang) wajib dipilih.');
            hasError = true;
        }

        if (isEdit && data.password && data.password.length < 8) {
            setError('password', 'Kata sandi baru minimal 8 karakter.');
            hasError = true;
        }

        if (hasError) {
            notify.validationFailed();
            return;
        }

        if (isEdit) {
            put(`/manajemen/pengguna/${pegawai.uuid}`, {
                preserveScroll: true,
                onSuccess: () => {
                    handleClose();
                },
                onError: () => {
                    notify.validationFailed();
                },
            });
        } else {
            post('/manajemen/pengguna', {
                preserveScroll: true,
                onSuccess: () => {
                    handleClose();
                },
                onError: () => {
                    notify.validationFailed();
                },
            });
        }
    };

    return (
        <Dialog open={open} onOpenChange={(val) => !processing && (val ? onOpenChange(true) : handleClose())}>
            <DialogContent
                className="sm:max-w-[520px] p-0 overflow-hidden"
                onPointerDownOutside={(e) => processing && e.preventDefault()}
                onEscapeKeyDown={(e) => processing && e.preventDefault()}
            >
                <form noValidate onSubmit={handleSubmit}>
                    <DialogHeader className="p-6 pb-4 border-b bg-muted/20">
                        <div className="flex items-center gap-2.5">
                            <div className="p-2 rounded-lg bg-primary/10 text-primary">
                                {isEdit ? <UserCog className="size-5" /> : <UserPlus className="size-5" />}
                            </div>
                            <div>
                                <DialogTitle className="text-lg font-bold">
                                    {isEdit ? 'Ubah Akun Pegawai Internal' : 'Tambah Pegawai Internal Baru'}
                                </DialogTitle>
                                <DialogDescription className="text-xs text-muted-foreground mt-0.5">
                                    {isEdit
                                        ? 'Perbarui informasi profil, akses peran, atau setel ulang kata sandi pegawai.'
                                        : 'Buat akun akses untuk tim Buyer atau QC Gudang dengan kredensial awal.'}
                                </DialogDescription>
                            </div>
                        </div>
                    </DialogHeader>

                    <div className="p-6 space-y-4">
                        {/* Notice Banner for Create */}
                        {!isEdit && (
                            <div className="flex items-start gap-3 p-3.5 rounded-lg border border-sky-500/20 bg-sky-500/10 text-sky-900 dark:text-sky-200">
                                <Mail className="size-4 shrink-0 mt-0.5 text-sky-600 dark:text-sky-400" />
                                <div className="text-xs space-y-1">
                                    <p className="font-semibold">Otomatisasi Kredensial Awal</p>
                                    <p className="leading-relaxed text-muted-foreground dark:text-sky-300">
                                        Akun akan dibuat menggunakan email pegawai dan password default{' '}
                                        <code className="font-mono font-semibold px-1 py-0.5 rounded bg-background/80 text-foreground">
                                            {default_password}
                                        </code>
                                        . Notifikasi aktivasi akun akan dikirim otomatis ke email pegawai.
                                    </p>
                                </div>
                            </div>
                        )}

                        {/* Nama Lengkap */}
                        <div className="space-y-1.5">
                            <Label htmlFor="nama_lengkap" className={cn('text-xs font-semibold', errors.nama_lengkap && 'text-destructive')}>
                                Nama Lengkap Pegawai <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="nama_lengkap"
                                placeholder="Contoh: Budi Santoso, S.T."
                                value={data.nama_lengkap}
                                onChange={(e) => {
                                    setData('nama_lengkap', e.target.value);
                                    if (errors.nama_lengkap) clearErrors('nama_lengkap');
                                }}
                                disabled={processing}
                                aria-invalid={Boolean(errors.nama_lengkap)}
                                className={cn('h-9 text-sm', errors.nama_lengkap && 'border-destructive focus-visible:ring-destructive')}
                            />
                            {errors.nama_lengkap && (
                                <p className="text-xs font-medium text-destructive">
                                    {errors.nama_lengkap}
                                </p>
                            )}
                        </div>

                        {/* Email Pegawai */}
                        <div className="space-y-1.5">
                            <Label htmlFor="email" className={cn('text-xs font-semibold', errors.email && 'text-destructive')}>
                                Email Perusahaan Pegawai <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="email"
                                type="email"
                                placeholder="budi.santoso@perusahaan.com"
                                value={data.email}
                                onChange={(e) => {
                                    setData('email', e.target.value);
                                    if (errors.email) clearErrors('email');
                                }}
                                disabled={processing}
                                aria-invalid={Boolean(errors.email)}
                                className={cn('h-9 text-sm', errors.email && 'border-destructive focus-visible:ring-destructive')}
                            />
                            {errors.email && (
                                <p className="text-xs font-medium text-destructive">
                                    {errors.email}
                                </p>
                            )}
                        </div>

                        {/* Role Pegawai */}
                        <div className="space-y-1.5">
                            <Label htmlFor="role" className={cn('text-xs font-semibold', errors.role && 'text-destructive')}>
                                Role / Jabatan Akses <span className="text-destructive">*</span>
                            </Label>
                            <Select
                                value={data.role}
                                onValueChange={(val) => {
                                    setData('role', val);
                                    if (errors.role) clearErrors('role');
                                }}
                                disabled={processing}
                            >
                                <SelectTrigger
                                    id="role"
                                    aria-invalid={Boolean(errors.role)}
                                    className={cn('h-9 text-sm w-full', errors.role && 'border-destructive focus-visible:ring-destructive')}
                                >
                                    <SelectValue placeholder="Pilih Role (Buyer atau QC Gudang)" />
                                </SelectTrigger>
                                <SelectContent>
                                    {role_options.map((opt) => (
                                        <SelectItem key={opt.value} value={opt.value}>
                                            {opt.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.role && (
                                <p className="text-xs font-medium text-destructive">
                                    {errors.role}
                                </p>
                            )}
                        </div>

                        {/* Edit Only: Password (Optional) */}
                        {isEdit && (
                            <div className="space-y-1.5 pt-1">
                                <Label htmlFor="password" className={cn('text-xs font-semibold', errors.password && 'text-destructive')}>
                                    Kata Sandi Baru (Opsional)
                                </Label>
                                <div className="relative">
                                    <KeyRound className="absolute left-2.5 top-2.5 size-4 text-muted-foreground pointer-events-none" />
                                    <Input
                                        id="password"
                                        type="password"
                                        placeholder="Kosongkan jika tidak ingin mengubah kata sandi"
                                        value={data.password}
                                        onChange={(e) => {
                                            setData('password', e.target.value);
                                            if (errors.password) clearErrors('password');
                                        }}
                                        disabled={processing}
                                        aria-invalid={Boolean(errors.password)}
                                        className={cn('pl-9 h-9 text-sm', errors.password && 'border-destructive focus-visible:ring-destructive')}
                                    />
                                </div>
                                {errors.password ? (
                                    <p className="text-xs font-medium text-destructive">
                                        {errors.password}
                                    </p>
                                ) : (
                                    <p className="text-[11px] text-muted-foreground">
                                        Isi hanya jika pegawai meminta reset kata sandi akun secara manual.
                                    </p>
                                )}
                            </div>
                        )}

                        {/* Edit Only: Status Aktif */}
                        {isEdit && (
                            <div className="flex items-center justify-between p-3 rounded-lg border bg-muted/30 mt-2">
                                <div className="space-y-0.5 pr-2">
                                    <Label htmlFor="status_aktif" className="text-xs font-semibold cursor-pointer">
                                        Status Keaktifan Akun
                                    </Label>
                                    <p className="text-[11px] text-muted-foreground leading-tight">
                                        Jika dinonaktifkan, pegawai tidak dapat login ke sistem PAGORA.
                                    </p>
                                </div>
                                <Switch
                                    id="status_aktif"
                                    checked={data.status_aktif}
                                    onCheckedChange={(checked) => setData('status_aktif', checked)}
                                    disabled={processing}
                                />
                            </div>
                        )}
                    </div>

                    <DialogFooter className="p-4 px-6 border-t bg-muted/20 flex flex-row items-center justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleClose}
                            disabled={processing}
                            className="h-9 cursor-pointer"
                        >
                            Batal
                        </Button>
                        <LoadingButton
                            type="submit"
                            loading={processing}
                            loadingText={isEdit ? 'Menyimpan Perubahan...' : 'Mendaftarkan Pegawai...'}
                            className="h-9 cursor-pointer shadow-sm"
                        >
                            {isEdit ? 'Simpan Perubahan' : 'Simpan Pegawai'}
                        </LoadingButton>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
