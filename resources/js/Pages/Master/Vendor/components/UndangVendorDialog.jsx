import { useForm } from '@inertiajs/react';
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
import { Textarea } from '@/Components/ui/textarea';
import { Button } from '@/Components/ui/button';
import { Combobox } from '@/Components/combobox';
import { LoadingButton } from '@/Components/loading-button';
import { MailPlus, Info } from 'lucide-react';
import { notify } from '@/lib/notify';
import { cn } from '@/lib/utils';

export function UndangVendorDialog({ open, onOpenChange, kategoriOptions = [] }) {
    const { data, setData, post, processing, errors, setError, clearErrors, reset } = useForm({
        nama_perusahaan: '',
        email: '',
        kategori_komoditas: '',
        pesan: '',
    });

    const handleClose = (nextOpen) => {
        if (processing) return; // Cegah tutup saat sedang mengirim
        if (!nextOpen) {
            reset();
            clearErrors();
        }
        onOpenChange(nextOpen);
    };

    const handleSubmit = (e) => {
        e.preventDefault();

        let hasError = false;

        const nama = data.nama_perusahaan.trim();
        const email = data.email.trim();

        if (!nama) {
            setError('nama_perusahaan', 'Nama perusahaan vendor wajib diisi.');
            hasError = true;
        } else if (nama.length < 3) {
            setError('nama_perusahaan', 'Nama perusahaan minimal 3 karakter.');
            hasError = true;
        }

        if (!email) {
            setError('email', 'Email kontak vendor wajib diisi.');
            hasError = true;
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            setError('email', 'Format email kontak tidak valid.');
            hasError = true;
        }

        if (hasError) {
            notify.validationFailed();
            return;
        }

        post('/master/vendor/undang', {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                clearErrors();
                onOpenChange(false);
            },
            onError: () => {
                notify.validationFailed();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent
                className="w-full sm:max-w-xl md:max-w-2xl p-6"
                onPointerDownOutside={(e) => processing && e.preventDefault()}
                onEscapeKeyDown={(e) => processing && e.preventDefault()}
            >
                <form onSubmit={handleSubmit} noValidate>
                    <DialogHeader>
                        <div className="flex items-center gap-2.5 text-emerald-600">
                            <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 dark:bg-emerald-950/50">
                                <MailPlus className="size-5" />
                            </div>
                            <div>
                                <DialogTitle className="text-lg font-bold text-foreground">
                                    Undang Rekanan Vendor Baru
                                </DialogTitle>
                                <DialogDescription className="text-xs text-muted-foreground mt-0.5">
                                    Kirim undangan email resmi ke calon rekanan untuk bergabung di PAGORA.
                                </DialogDescription>
                            </div>
                        </div>
                    </DialogHeader>

                    <div className="my-5 space-y-4">
                        {/* Info banner */}
                        <div className="flex items-start gap-2.5 rounded-lg border border-emerald-500/20 bg-emerald-50/50 dark:bg-emerald-950/20 p-3 text-xs text-emerald-800 dark:text-emerald-300">
                            <Info className="size-4 shrink-0 mt-0.5 text-emerald-600 dark:text-emerald-400" />
                            <p className="leading-relaxed">
                                Calon vendor akan menerima email berisi tautan pendaftaran eksklusif yang berlaku selama 7 hari kalender.
                            </p>
                        </div>

                        {/* Nama Perusahaan */}
                        <div className="space-y-1.5">
                            <Label htmlFor="undang_nama_perusahaan" className="text-xs font-semibold">
                                Nama Perusahaan / Rekanan <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="undang_nama_perusahaan"
                                type="text"
                                value={data.nama_perusahaan}
                                onChange={(e) => {
                                    setData('nama_perusahaan', e.target.value);
                                    clearErrors('nama_perusahaan');
                                }}
                                disabled={processing}
                                placeholder="Contoh: PT Agro Perkasa Sentosa"
                                aria-invalid={Boolean(errors.nama_perusahaan)}
                                className={cn(
                                    'h-9 text-sm',
                                    errors.nama_perusahaan && 'border-destructive focus-visible:ring-destructive'
                                )}
                            />
                            {errors.nama_perusahaan && (
                                <p className="text-xs text-destructive font-medium">
                                    {errors.nama_perusahaan}
                                </p>
                            )}
                        </div>

                        {/* Email Vendor */}
                        <div className="space-y-1.5">
                            <Label htmlFor="undang_email" className="text-xs font-semibold">
                                Email Bisnis Vendor <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="undang_email"
                                type="email"
                                value={data.email}
                                onChange={(e) => {
                                    setData('email', e.target.value);
                                    clearErrors('email');
                                }}
                                disabled={processing}
                                placeholder="vendor@perusahaan.co.id"
                                aria-invalid={Boolean(errors.email)}
                                className={cn(
                                    'h-9 text-sm',
                                    errors.email && 'border-destructive focus-visible:ring-destructive'
                                )}
                            />
                            {errors.email && (
                                <p className="text-xs text-destructive font-medium">
                                    {errors.email}
                                </p>
                            )}
                        </div>

                        {/* Kategori Komoditas */}
                        <div className="space-y-1.5">
                            <Label htmlFor="undang_kategori" className="text-xs font-semibold">
                                Kategori Komoditas yang Diajukan (Opsional)
                            </Label>
                            <Combobox
                                options={kategoriOptions}
                                value={data.kategori_komoditas}
                                onChange={(val) => {
                                    setData('kategori_komoditas', val);
                                    clearErrors('kategori_komoditas');
                                }}
                                placeholder="Pilih kategori komoditas utama..."
                                searchPlaceholder="Cari kategori..."
                                emptyMessage="Kategori tidak ditemukan."
                                disabled={processing}
                                error={Boolean(errors.kategori_komoditas)}
                            />
                            {errors.kategori_komoditas && (
                                <p className="text-xs text-destructive font-medium">
                                    {errors.kategori_komoditas}
                                </p>
                            )}
                        </div>

                        {/* Catatan / Pesan Undangan */}
                        <div className="space-y-1.5">
                            <Label htmlFor="undang_pesan" className="text-xs font-semibold">
                                Catatan / Pesan Undangan Khusus (Opsional)
                            </Label>
                            <Textarea
                                id="undang_pesan"
                                value={data.pesan}
                                onChange={(e) => {
                                    setData('pesan', e.target.value);
                                    clearErrors('pesan');
                                }}
                                disabled={processing}
                                placeholder="Tuliskan instruksi atau pengantar kemitraan bagi calon rekanan..."
                                rows={3}
                                aria-invalid={Boolean(errors.pesan)}
                                className={cn(
                                    'text-sm resize-none',
                                    errors.pesan && 'border-destructive focus-visible:ring-destructive'
                                )}
                            />
                            {errors.pesan && (
                                <p className="text-xs text-destructive font-medium">
                                    {errors.pesan}
                                </p>
                            )}
                        </div>
                    </div>

                    <DialogFooter className="gap-2 sm:gap-0">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={processing}
                            onClick={() => handleClose(false)}
                            className="cursor-pointer"
                        >
                            Batal
                        </Button>
                        <LoadingButton
                            type="submit"
                            loading={processing}
                            loadingText="Mengirim Undangan..."
                            className="bg-emerald-600 hover:bg-emerald-700 text-white cursor-pointer"
                        >
                            <MailPlus className="size-4 mr-2" />
                            Kirim Undangan Email
                        </LoadingButton>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
