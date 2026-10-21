import { useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { cn } from '@/lib/utils';
import { notify } from '@/lib/notify';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Switch } from '@/Components/ui/switch';
import { Combobox } from '@/Components/combobox';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';
import { LoadingButton } from '@/Components/loading-button';

export function KomoditasForm({
    initialData = null,
    isEdit = false,
    satuan_options = [],
    kategori_options = [],
}) {
    const { data, setData, post, put, processing, errors, setError, clearErrors } = useForm({
        nama_komoditas: initialData?.nama_komoditas ?? '',
        satuan: initialData?.satuan ?? '',
        batas_susut: initialData?.batas_susut ?? '',
        kode_referensi_api: initialData?.kode_referensi_api ?? '',
        sku: initialData?.sku ?? '',
        kategori: initialData?.kategori ?? '',
        spesifikasi: initialData?.spesifikasi ?? '',
        status: initialData?.status ?? true,
    });

    const handleSubmit = (e) => {
        e.preventDefault();

        // Frontend validation
        let hasError = false;
        if (!data.nama_komoditas.trim()) {
            setError('nama_komoditas', 'Nama komoditas wajib diisi.');
            hasError = true;
        }

        if (!data.satuan) {
            setError('satuan', 'Satuan komoditas wajib dipilih.');
            hasError = true;
        }

        if (hasError) {
            notify.validationFailed();
            return;
        }

        if (isEdit) {
            put(`/master/komoditas/${initialData.uuid}`, {
                onError: () => notify.validationFailed(),
            });
        } else {
            post('/master/komoditas', {
                onError: () => notify.validationFailed(),
            });
        }
    };

    const formattedSatuanOptions = satuan_options.map((s) => ({
        value: s,
        label: s.toUpperCase(),
    }));

    const formattedKategoriOptions = kategori_options.map((k) => ({
        value: k,
        label: k,
    }));

    return (
        <form noValidate onSubmit={handleSubmit} className="w-full">
            <Card className="w-full shadow-sm flex flex-col">
                <CardHeader className="py-3.5 px-5 border-b">
                    <CardTitle className="text-base font-semibold">Informasi Utama Komoditas</CardTitle>
                    <CardDescription className="text-xs">
                        Lengkapi detail bahan baku pengadaan yang akan terhubung ke sistem penawaran dan PO.
                    </CardDescription>
                </CardHeader>

                {/* Card Content with scrollable internal body if viewport is very short */}
                <CardContent className="p-5 max-h-[calc(100vh-14.5rem)] overflow-y-auto space-y-4">
                    {/* Row 1: Nama Komoditas (2/3) + SKU (1/3) */}
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div className="sm:col-span-2 grid gap-1.5">
                            <Label htmlFor="nama_komoditas" className={cn('text-sm', errors.nama_komoditas && 'text-destructive')}>
                                Nama Komoditas <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="nama_komoditas"
                                placeholder="Contoh: Beras Premium IR-64, Gula Pasir Rafinasi"
                                value={data.nama_komoditas}
                                onChange={(e) => {
                                    setData('nama_komoditas', e.target.value);
                                    if (errors.nama_komoditas) clearErrors('nama_komoditas');
                                }}
                                aria-invalid={Boolean(errors.nama_komoditas)}
                                className={cn('h-9', errors.nama_komoditas && 'border-destructive focus-visible:ring-destructive')}
                            />
                            {errors.nama_komoditas && (
                                <p className="text-xs font-medium text-destructive">
                                    {errors.nama_komoditas}
                                </p>
                            )}
                        </div>

                        <div className="sm:col-span-1 grid gap-1.5">
                            <Label htmlFor="sku" className={cn('text-sm', errors.sku && 'text-destructive')}>
                                SKU / Kode Material
                            </Label>
                            <Input
                                id="sku"
                                placeholder="Contoh: RAW-BRS-001"
                                value={data.sku}
                                onChange={(e) => {
                                    setData('sku', e.target.value.toUpperCase());
                                    if (errors.sku) clearErrors('sku');
                                }}
                                aria-invalid={Boolean(errors.sku)}
                                className={cn('h-9', errors.sku && 'border-destructive focus-visible:ring-destructive')}
                            />
                            {errors.sku && (
                                <p className="text-xs font-medium text-destructive">
                                    {errors.sku}
                                </p>
                            )}
                        </div>
                    </div>

                    {/* Row 2: Satuan Unit + Kategori Bahan (1 Row with Searchable Comboboxes) */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div className="grid gap-1.5">
                            <Label htmlFor="satuan" className={cn('text-sm', errors.satuan && 'text-destructive')}>
                                Satuan Unit <span className="text-destructive">*</span>
                            </Label>
                            <Combobox
                                options={formattedSatuanOptions}
                                value={data.satuan}
                                onChange={(val) => {
                                    setData('satuan', val);
                                    if (errors.satuan) clearErrors('satuan');
                                }}
                                placeholder="Pilih satuan..."
                                searchPlaceholder="Cari satuan (KG, TON, SAK...)"
                                emptyMessage="Satuan tidak ditemukan."
                                error={errors.satuan}
                            />
                            {errors.satuan && (
                                <p className="text-xs font-medium text-destructive">
                                    {errors.satuan}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="kategori" className={cn('text-sm', errors.kategori && 'text-destructive')}>
                                Kategori Bahan
                            </Label>
                            <Combobox
                                options={formattedKategoriOptions}
                                value={data.kategori}
                                onChange={(val) => {
                                    setData('kategori', val);
                                    if (errors.kategori) clearErrors('kategori');
                                }}
                                placeholder="Pilih kategori bahan..."
                                searchPlaceholder="Cari kategori (Beras, Minyak...)"
                                emptyMessage="Kategori tidak ditemukan."
                                error={errors.kategori}
                            />
                            {errors.kategori && (
                                <p className="text-xs font-medium text-destructive">
                                    {errors.kategori}
                                </p>
                            )}
                        </div>
                    </div>

                    {/* Row 3: Kode Referensi API + Status Aktif (1 Row) */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                        <div className="grid gap-1.5">
                            <Label htmlFor="kode_referensi_api" className={cn('text-sm', errors.kode_referensi_api && 'text-destructive')}>
                                Kode Referensi API Pangan
                            </Label>
                            <Input
                                id="kode_referensi_api"
                                placeholder="Contoh: 101 (ID komoditas PIHPS / Bapanas)"
                                value={data.kode_referensi_api}
                                onChange={(e) => {
                                    setData('kode_referensi_api', e.target.value);
                                    if (errors.kode_referensi_api) clearErrors('kode_referensi_api');
                                }}
                                aria-invalid={Boolean(errors.kode_referensi_api)}
                                className={cn('h-9', errors.kode_referensi_api && 'border-destructive focus-visible:ring-destructive')}
                            />
                            {errors.kode_referensi_api && (
                                <p className="text-xs font-medium text-destructive">
                                    {errors.kode_referensi_api}
                                </p>
                            )}
                            <p className="text-[11px] text-muted-foreground">
                                Terhubung ke API Bank Indonesia / Bapanas untuk sinkronisasi harga acuan otomatis.
                            </p>
                        </div>

                        <div className="flex items-center justify-between p-3 rounded-lg border bg-muted/30 mt-0.5">
                            <div className="space-y-0.5 pr-2">
                                <Label htmlFor="status" className="text-sm font-medium cursor-pointer">
                                    Status Aktif Pengadaan
                                </Label>
                                <p className="text-[11px] text-muted-foreground leading-tight">
                                    Bahan baku aktif dapat dipilih saat tim purchasing membuat Purchase Order (PO).
                                </p>
                            </div>
                            <Switch
                                id="status"
                                checked={data.status}
                                onCheckedChange={(checked) => setData('status', checked)}
                            />
                        </div>
                    </div>

                    {/* Row 4: Batas Susut */}
                    <div className="grid gap-1.5 sm:w-1/2">
                        <Label htmlFor="batas_susut" className={cn('text-sm', errors.batas_susut && 'text-destructive')}>
                            Batas Susut (%)
                        </Label>
                        <Input
                            id="batas_susut"
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            placeholder="Contoh: 2.5"
                            value={data.batas_susut}
                            onChange={(e) => {
                                setData('batas_susut', e.target.value);
                                if (errors.batas_susut) clearErrors('batas_susut');
                            }}
                            aria-invalid={Boolean(errors.batas_susut)}
                            className={cn('h-9', errors.batas_susut && 'border-destructive focus-visible:ring-destructive')}
                        />
                        {errors.batas_susut && (
                            <p className="text-xs font-medium text-destructive">
                                {errors.batas_susut}
                            </p>
                        )}
                        <p className="text-[11px] text-muted-foreground">
                            Toleransi penyusutan berat yang masih diterima QC gudang saat penerimaan barang.
                        </p>
                    </div>

                    {/* Row 5: Spesifikasi & Syarat Mutu QC */}
                    <div className="grid gap-1.5">
                        <Label htmlFor="spesifikasi" className={cn('text-sm', errors.spesifikasi && 'text-destructive')}>
                            Spesifikasi & Syarat Mutu QC
                        </Label>
                        <Textarea
                            id="spesifikasi"
                            rows={2}
                            placeholder="Contoh: Kadar air maksimal 14%, butir patah maksimal 5%, bebas bau apek dan kutu."
                            value={data.spesifikasi}
                            onChange={(e) => {
                                setData('spesifikasi', e.target.value);
                                if (errors.spesifikasi) clearErrors('spesifikasi');
                            }}
                            aria-invalid={Boolean(errors.spesifikasi)}
                            className={cn('resize-none text-xs leading-relaxed', errors.spesifikasi && 'border-destructive focus-visible:ring-destructive')}
                        />
                        {errors.spesifikasi && (
                            <p className="text-xs font-medium text-destructive">
                                {errors.spesifikasi}
                            </p>
                        )}
                    </div>
                </CardContent>

                {/* Card Footer with Save button inside the card */}
                <CardFooter className="py-3 px-5 border-t bg-muted/20 flex justify-end">
                    <LoadingButton
                        type="submit"
                        loading={processing}
                        loadingText={isEdit ? 'Menyimpan Perubahan...' : 'Menambahkan Komoditas...'}
                        className="gap-2 cursor-pointer shadow-sm h-9"
                    >
                        <Save className="size-4" />
                        {isEdit ? 'Simpan Perubahan' : 'Simpan Komoditas'}
                    </LoadingButton>
                </CardFooter>
            </Card>
        </form>
    );
}
