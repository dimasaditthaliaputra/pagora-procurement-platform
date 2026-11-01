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
import { isShrinkageTolerance } from '@/lib/validators';

export function KomoditasForm({
    initialData = null,
    isEdit = false,
    satuan_options = [],
    category_options = [],
}) {
    const { data, setData, post, put, processing, errors, setError, clearErrors } = useForm({
        name: initialData?.name ?? '',
        unit: initialData?.unit ?? '',
        default_shrinkage_tolerance: initialData?.default_shrinkage_tolerance ?? '',
        api_reference_code: initialData?.api_reference_code ?? '',
        sku: initialData?.sku ?? '',
        commodity_category_uuid: initialData?.commodity_category_uuid ?? '',
        specification: initialData?.specification ?? '',
        is_active: initialData?.is_active ?? true,
    });

    const handleSubmit = (e) => {
        e.preventDefault();

        // Frontend validation
        let hasError = false;
        if (!data.name.trim()) {
            setError('name', 'Nama komoditas wajib diisi.');
            hasError = true;
        }

        if (!data.unit) {
            setError('unit', 'Satuan komoditas wajib dipilih.');
            hasError = true;
        }

        if (data.default_shrinkage_tolerance !== '' && data.default_shrinkage_tolerance !== null && data.default_shrinkage_tolerance !== undefined) {
            const batasSusutStr = String(data.default_shrinkage_tolerance).trim();
            if (batasSusutStr.includes(',')) {
                setError('default_shrinkage_tolerance', 'Batas susut hanya menerima format angka desimal dengan titik (contoh: 2.5).');
                hasError = true;
            } else if (!isShrinkageTolerance(batasSusutStr)) {
                setError('default_shrinkage_tolerance', 'Batas susut harus berupa angka antara 0 hingga 100 dengan format titik (contoh: 2.5).');
                hasError = true;
            }
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

    const formattedKategoriOptions = category_options.map((category) => ({
        value: category.uuid,
        label: category.name,
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
                            <Label htmlFor="name" className={cn('text-sm', errors.name && 'text-destructive')}>
                                Nama Komoditas <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="name"
                                placeholder="Contoh: Beras Premium IR-64, Gula Pasir Rafinasi"
                                value={data.name}
                                onChange={(e) => {
                                    setData('name', e.target.value);
                                    if (errors.name) clearErrors('name');
                                }}
                                aria-invalid={Boolean(errors.name)}
                                className={cn('h-9', errors.name && 'border-destructive focus-visible:ring-destructive')}
                            />
                            {errors.name && (
                                <p className="text-xs font-medium text-destructive">
                                    {errors.name}
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
                            <Label htmlFor="unit" className={cn('text-sm', errors.unit && 'text-destructive')}>
                                Satuan Unit <span className="text-destructive">*</span>
                            </Label>
                            <Combobox
                                options={formattedSatuanOptions}
                                value={data.unit}
                                onChange={(val) => {
                                    setData('unit', val);
                                    if (errors.unit) clearErrors('unit');
                                }}
                                placeholder="Pilih satuan..."
                                searchPlaceholder="Cari satuan (KG, TON, SAK...)"
                                emptyMessage="Satuan tidak ditemukan."
                                error={errors.unit}
                            />
                            {errors.unit && (
                                <p className="text-xs font-medium text-destructive">
                                    {errors.unit}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="commodity_category_uuid" className={cn('text-sm', errors.commodity_category_uuid && 'text-destructive')}>
                                Kategori Bahan
                            </Label>
                            <Combobox
                                options={formattedKategoriOptions}
                                value={data.commodity_category_uuid}
                                onChange={(val) => {
                                    setData('commodity_category_uuid', val);
                                    if (errors.commodity_category_uuid) clearErrors('commodity_category_uuid');
                                }}
                                placeholder="Pilih kategori bahan..."
                                searchPlaceholder="Cari kategori (Beras, Minyak...)"
                                emptyMessage="Kategori tidak ditemukan."
                                error={errors.commodity_category_uuid}
                            />
                            {errors.commodity_category_uuid && (
                                <p className="text-xs font-medium text-destructive">
                                    {errors.commodity_category_uuid}
                                </p>
                            )}
                        </div>
                    </div>

                    {/* Row 3: Kode Referensi API + Status Aktif (1 Row) */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                        <div className="grid gap-1.5">
                            <Label htmlFor="api_reference_code" className={cn('text-sm', errors.api_reference_code && 'text-destructive')}>
                                Kode Referensi API Pangan
                            </Label>
                            <Input
                                id="api_reference_code"
                                placeholder="Contoh: 101 (ID komoditas PIHPS / Bapanas)"
                                value={data.api_reference_code}
                                onChange={(e) => {
                                    setData('api_reference_code', e.target.value);
                                    if (errors.api_reference_code) clearErrors('api_reference_code');
                                }}
                                aria-invalid={Boolean(errors.api_reference_code)}
                                className={cn('h-9', errors.api_reference_code && 'border-destructive focus-visible:ring-destructive')}
                            />
                            {errors.api_reference_code && (
                                <p className="text-xs font-medium text-destructive">
                                    {errors.api_reference_code}
                                </p>
                            )}
                            <p className="text-[11px] text-muted-foreground">
                                Terhubung ke API Bank Indonesia / Bapanas untuk sinkronisasi harga acuan otomatis.
                            </p>
                        </div>

                        <div className="flex items-center justify-between p-3 rounded-lg border bg-muted/30 mt-0.5">
                            <div className="space-y-0.5 pr-2">
                                <Label htmlFor="is_active" className="text-sm font-medium cursor-pointer">
                                    Status Aktif Pengadaan
                                </Label>
                                <p className="text-[11px] text-muted-foreground leading-tight">
                                    Bahan baku aktif dapat dipilih saat tim purchasing membuat Purchase Order (PO).
                                </p>
                            </div>
                            <Switch
                                id="is_active"
                                checked={data.is_active}
                                onCheckedChange={(checked) => setData('is_active', checked)}
                            />
                        </div>
                    </div>

                    {/* Row 4: Batas Susut */}
                    <div className="grid gap-1.5 sm:w-1/2">
                        <Label htmlFor="default_shrinkage_tolerance" className={cn('text-sm', errors.default_shrinkage_tolerance && 'text-destructive')}>
                            Batas Susut (%)
                        </Label>
                        <Input
                            id="default_shrinkage_tolerance"
                            type="text"
                            inputMode="decimal"
                            placeholder="Contoh: 2.5"
                            value={data.default_shrinkage_tolerance ?? ''}
                            onKeyDown={(e) => {
                                // Tolak koma, eksponen (e/E), serta tanda plus/minus secara langsung
                                if (e.key === ',' || e.key === 'e' || e.key === 'E' || e.key === '+' || e.key === '-') {
                                    e.preventDefault();
                                }
                            }}
                            onPaste={(e) => {
                                const pasted = e.clipboardData.getData('text');
                                if (pasted.includes(',')) {
                                    e.preventDefault();
                                    // Cegah jika mengandung koma dan bukan format titik yang sah
                                    const sanitized = pasted.replace(/,/g, '.');
                                    if (/^(?:100(?:\.0{0,2})?|[0-9]{0,2}(?:\.[0-9]{0,2})?)$/.test(sanitized)) {
                                        setData('default_shrinkage_tolerance', sanitized);
                                        if (errors.default_shrinkage_tolerance) clearErrors('default_shrinkage_tolerance');
                                    }
                                }
                            }}
                            onChange={(e) => {
                                const val = e.target.value;
                                // Guard format: hanya izinkan string kosong atau desimal bertitik (0-100, maks 2 desimal)
                                if (val === '' || /^(?:100(?:\.0{0,2})?|[0-9]{0,2}(?:\.[0-9]{0,2})?)$/.test(val)) {
                                    setData('default_shrinkage_tolerance', val);
                                    if (errors.default_shrinkage_tolerance) clearErrors('default_shrinkage_tolerance');
                                }
                            }}
                            aria-invalid={Boolean(errors.default_shrinkage_tolerance)}
                            className={cn('h-9', errors.default_shrinkage_tolerance && 'border-destructive focus-visible:ring-destructive')}
                        />
                        {errors.default_shrinkage_tolerance && (
                            <p className="text-xs font-medium text-destructive">
                                {errors.default_shrinkage_tolerance}
                            </p>
                        )}
                        <p className="text-[11px] text-muted-foreground">
                            Toleransi penyusutan berat yang masih diterima QC gudang (0–100%). Hanya menerima format titik (.), contoh: 2.5
                        </p>
                    </div>

                    {/* Row 5: Spesifikasi & Syarat Mutu QC */}
                    <div className="grid gap-1.5">
                        <Label htmlFor="specification" className={cn('text-sm', errors.specification && 'text-destructive')}>
                            Spesifikasi & Syarat Mutu QC
                        </Label>
                        <Textarea
                            id="specification"
                            rows={2}
                            placeholder="Contoh: Kadar air maksimal 14%, butir patah maksimal 5%, bebas bau apek dan kutu."
                            value={data.specification}
                            onChange={(e) => {
                                setData('specification', e.target.value);
                                if (errors.specification) clearErrors('specification');
                            }}
                            aria-invalid={Boolean(errors.specification)}
                            className={cn('resize-none text-xs leading-relaxed', errors.specification && 'border-destructive focus-visible:ring-destructive')}
                        />
                        {errors.specification && (
                            <p className="text-xs font-medium text-destructive">
                                {errors.specification}
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
