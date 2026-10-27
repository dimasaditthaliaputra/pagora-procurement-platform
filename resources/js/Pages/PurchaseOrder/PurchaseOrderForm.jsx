import { useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import { Plus, Save, Trash2, TrendingUp, AlertTriangle } from 'lucide-react';
import { cn } from '@/lib/utils';
import { notify } from '@/lib/notify';
import { formatRupiah } from '@/lib/format';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Combobox } from '@/Components/combobox';
import { LoadingButton } from '@/Components/loading-button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';
import { totalPagu, totalPo } from '@/Pages/PurchaseOrder/constants';
import {
    komoditasOptions,
    savePurchaseOrder,
    simulateRequest,
    vendorOptions,
} from '@/Pages/PurchaseOrder/mock-data';
import { KonfirmasiNegosiasiDialog } from '@/Pages/PurchaseOrder/components/KonfirmasiNegosiasiDialog';

const emptyItem = () => ({ key: crypto.randomUUID(), uuid: null, komoditas_uuid: '', jumlah_pesanan: '', harga_satuan_po: '' });

const invalidClass = (error) => error && 'border-destructive focus-visible:ring-destructive';

function FieldError({ id, message }) {
    return message ? <p id={id} className="text-xs font-medium text-destructive">{message}</p> : null;
}

export function PurchaseOrderForm({ initialData = null }) {
    const isEdit = Boolean(initialData);
    const [processing, setProcessing] = useState(false);
    const [savedPo, setSavedPo] = useState(null);

    const { data, setData, errors, setError, clearErrors } = useForm({
        vendor_uuid: initialData?.vendor.uuid ?? '',
        tanggal_po: initialData?.tanggal_po ?? '',
        catatan: initialData?.catatan ?? '',
        items: initialData?.items.map((i) => ({
            key: i.uuid,
            uuid: i.uuid,
            komoditas_uuid: i.komoditas_uuid,
            jumlah_pesanan: String(i.jumlah_pesanan),
            harga_satuan_po: String(i.harga_satuan_po),
        })) ?? [emptyItem()],
    });

    const itemsWithKomoditas = data.items.map((row) => {
        const k = komoditasOptions.find((o) => o.value === row.komoditas_uuid);
        return { ...row, satuan: k?.satuan, harga_pagu: k?.harga_pagu };
    });
    const total = totalPo(itemsWithKomoditas);
    const pagu = totalPagu(itemsWithKomoditas);

    const updateItem = (index, field, value) => {
        setData('items', data.items.map((row, i) => (i === index ? { ...row, [field]: value } : row)));
        clearErrors(`items.${index}.${field}`, 'items');
    };

    const addItem = () => {
        setData('items', [...data.items, emptyItem()]);
        clearErrors('items');
    };

    const removeItem = (index) => {
        setData('items', data.items.filter((_, i) => i !== index));
        clearErrors(`items.${index}.komoditas_uuid`, `items.${index}.jumlah_pesanan`, `items.${index}.harga_satuan_po`);
    };

    const validate = () => {
        const found = {};
        if (!data.vendor_uuid) found.vendor_uuid = 'Vendor wajib dipilih.';
        if (!data.tanggal_po) found.tanggal_po = 'Tanggal PO wajib diisi.';
        if (data.items.length === 0) found.items = 'Tambahkan minimal satu komoditas.';

        const seen = new Set();
        data.items.forEach((row, i) => {
            if (!row.komoditas_uuid) {
                found[`items.${i}.komoditas_uuid`] = 'Komoditas wajib dipilih.';
            } else if (seen.has(row.komoditas_uuid)) {
                found[`items.${i}.komoditas_uuid`] = 'Komoditas sudah ada di baris lain.';
            }
            seen.add(row.komoditas_uuid);

            if (!(Number(row.jumlah_pesanan) > 0)) found[`items.${i}.jumlah_pesanan`] = 'Kuantitas harus lebih dari 0.';
            if (!(Number(row.harga_satuan_po) > 0)) found[`items.${i}.harga_satuan_po`] = 'Harga harus lebih dari 0.';
        });

        return found;
    };

    const handleSubmit = async (e) => {
        e.preventDefault();

        const found = validate();
        if (Object.keys(found).length > 0) {
            setError(found);
            document.getElementById(Object.keys(found)[0].replaceAll('.', '_'))?.focus();
            notify.validationFailed();
            return;
        }

        setProcessing(true);
        // TODO(backend): post(route('purchase-order.store')) / put(route('purchase-order.update', uuid))
        await simulateRequest();
        const po = savePurchaseOrder(data, initialData?.uuid ?? null);
        setProcessing(false);
        notify.success(isEdit ? `Perubahan ${po.nomor_po} disimpan.` : `Draf ${po.nomor_po} berhasil dibuat.`);
        setSavedPo(po);
    };

    return (
        <>
            <form noValidate onSubmit={handleSubmit} className="w-full space-y-4">
                <Card className="w-full shadow-sm">
                    <CardHeader className="py-3.5 px-5 border-b">
                        <CardTitle className="text-base font-semibold">Informasi Pesanan</CardTitle>
                        <CardDescription className="text-xs">
                            Pilih vendor yang sudah bekerja sama dan tanggal pesanan.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-5 grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div className="md:col-span-2 grid gap-1.5">
                            <Label htmlFor="vendor_uuid" className={cn('text-sm', errors.vendor_uuid && 'text-destructive')}>
                                Vendor <span className="text-destructive">*</span>
                            </Label>
                            <div id="vendor_uuid" tabIndex={-1}>
                                <Combobox
                                    options={vendorOptions}
                                    value={data.vendor_uuid}
                                    onChange={(val) => { setData('vendor_uuid', val); clearErrors('vendor_uuid'); }}
                                    placeholder="Pilih vendor"
                                    searchPlaceholder="Cari vendor..."
                                    emptyMessage="Vendor tidak ditemukan."
                                    error={errors.vendor_uuid}
                                    disabled={processing}
                                    className="h-9"
                                />
                            </div>
                            <FieldError message={errors.vendor_uuid} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="tanggal_po" className={cn('text-sm', errors.tanggal_po && 'text-destructive')}>
                                Tanggal PO <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="tanggal_po"
                                type="date"
                                value={data.tanggal_po}
                                onChange={(e) => { setData('tanggal_po', e.target.value); clearErrors('tanggal_po'); }}
                                aria-invalid={Boolean(errors.tanggal_po)}
                                disabled={processing}
                                className={cn('h-9', invalidClass(errors.tanggal_po))}
                            />
                            <FieldError message={errors.tanggal_po} />
                        </div>
                        <div className="md:col-span-3 grid gap-1.5">
                            <Label htmlFor="catatan" className="text-sm">Catatan</Label>
                            <Textarea
                                id="catatan"
                                rows={2}
                                placeholder="Catatan untuk vendor (opsional)"
                                value={data.catatan}
                                onChange={(e) => setData('catatan', e.target.value)}
                                disabled={processing}
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card className="w-full shadow-sm">
                    <CardHeader className="py-3.5 px-5 border-b flex flex-row items-center justify-between gap-4 space-y-0">
                        <div>
                            <CardTitle className="text-base font-semibold">Rincian Komoditas</CardTitle>
                            <CardDescription className="text-xs mt-1">
                                Harga Pagu adalah batas atas harga wajar hasil prediksi, sebagai acuan (tidak memaksa).
                            </CardDescription>
                        </div>
                        <Button type="button" variant="outline" size="sm" onClick={addItem} disabled={processing} className="gap-1.5 h-8 shrink-0 cursor-pointer">
                            <Plus className="size-3.5" />
                            Tambah Baris
                        </Button>
                    </CardHeader>

                    <CardContent className="p-5 space-y-3">
                        {errors.items && (
                            <p className="text-xs font-medium text-destructive">{errors.items}</p>
                        )}

                        {itemsWithKomoditas.map((row, index) => {
                            const err = (field) => errors[`items.${index}.${field}`];
                            const subtotal = Number(row.jumlah_pesanan || 0) * Number(row.harga_satuan_po || 0);
                            const diAtasPagu = row.harga_pagu && Number(row.harga_satuan_po) > row.harga_pagu;

                            return (
                                <div key={row.key} className="rounded-lg border p-4 grid grid-cols-1 md:grid-cols-12 gap-3 items-start">
                                    <div className="md:col-span-4 grid gap-1.5">
                                        <Label className={cn('text-xs', err('komoditas_uuid') && 'text-destructive')}>
                                            Komoditas <span className="text-destructive">*</span>
                                        </Label>
                                        <div id={`items_${index}_komoditas_uuid`} tabIndex={-1}>
                                            <Combobox
                                                options={komoditasOptions}
                                                value={row.komoditas_uuid}
                                                onChange={(val) => updateItem(index, 'komoditas_uuid', val)}
                                                placeholder="Pilih komoditas"
                                                searchPlaceholder="Cari komoditas..."
                                                emptyMessage="Komoditas tidak ditemukan."
                                                error={err('komoditas_uuid')}
                                                disabled={processing}
                                                className="h-9"
                                            />
                                        </div>
                                        {row.harga_pagu ? (
                                            <p className="text-xs text-muted-foreground flex items-center gap-1">
                                                <TrendingUp className="size-3" />
                                                Harga Pagu: <strong className="text-foreground">{formatRupiah(row.harga_pagu)}</strong>/{row.satuan}
                                            </p>
                                        ) : null}
                                        <FieldError message={err('komoditas_uuid')} />
                                    </div>

                                    <div className="md:col-span-2 grid gap-1.5">
                                        <Label htmlFor={`items_${index}_jumlah_pesanan`} className={cn('text-xs', err('jumlah_pesanan') && 'text-destructive')}>
                                            Kuantitas {row.satuan && `(${row.satuan})`} <span className="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            id={`items_${index}_jumlah_pesanan`}
                                            type="number"
                                            inputMode="decimal"
                                            min="0"
                                            value={row.jumlah_pesanan}
                                            onChange={(e) => updateItem(index, 'jumlah_pesanan', e.target.value)}
                                            aria-invalid={Boolean(err('jumlah_pesanan'))}
                                            disabled={processing}
                                            className={cn('h-9 tabular-nums', invalidClass(err('jumlah_pesanan')))}
                                        />
                                        <FieldError message={err('jumlah_pesanan')} />
                                    </div>

                                    <div className="md:col-span-3 grid gap-1.5">
                                        <Label htmlFor={`items_${index}_harga_satuan_po`} className={cn('text-xs', err('harga_satuan_po') && 'text-destructive')}>
                                            Harga Satuan (Rp) <span className="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            id={`items_${index}_harga_satuan_po`}
                                            type="number"
                                            inputMode="numeric"
                                            min="0"
                                            value={row.harga_satuan_po}
                                            onChange={(e) => updateItem(index, 'harga_satuan_po', e.target.value)}
                                            aria-invalid={Boolean(err('harga_satuan_po'))}
                                            disabled={processing}
                                            className={cn('h-9 tabular-nums', invalidClass(err('harga_satuan_po')))}
                                        />
                                        {diAtasPagu && !err('harga_satuan_po') && (
                                            <p className="text-xs text-amber-600 dark:text-amber-400 flex items-center gap-1">
                                                <AlertTriangle className="size-3" />
                                                Di atas Harga Pagu
                                            </p>
                                        )}
                                        <FieldError message={err('harga_satuan_po')} />
                                    </div>

                                    <div className="md:col-span-2 grid gap-1.5">
                                        <span className="text-xs font-medium">Subtotal</span>
                                        <span className="h-9 flex items-center text-sm font-semibold tabular-nums">{formatRupiah(subtotal)}</span>
                                    </div>

                                    <div className="md:col-span-1 flex md:justify-end md:pt-6">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => removeItem(index)}
                                            disabled={processing || data.items.length === 1}
                                            className="size-9 text-muted-foreground hover:text-destructive cursor-pointer"
                                            aria-label="Hapus baris"
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                </div>
                            );
                        })}

                        <div className="flex flex-col items-end gap-1 pt-2 border-t">
                            <div className="flex items-center gap-6 text-sm">
                                <span className="text-muted-foreground">Total Harga Pagu</span>
                                <span className="tabular-nums w-40 text-right">{formatRupiah(pagu)}</span>
                            </div>
                            <div className="flex items-center gap-6">
                                <span className="text-sm font-medium">Total Purchase Order</span>
                                <span className={cn('text-lg font-bold tabular-nums w-40 text-right', pagu > 0 && total > pagu && 'text-amber-600 dark:text-amber-400')}>
                                    {formatRupiah(total)}
                                </span>
                            </div>
                        </div>
                    </CardContent>

                    <CardFooter className="py-3 px-5 border-t bg-muted/20 flex justify-end gap-2">
                        <Button type="button" variant="outline" asChild disabled={processing} className="h-9">
                            <Link href={isEdit ? `/purchase-order/${initialData.uuid}` : '/purchase-order'}>Batal</Link>
                        </Button>
                        <LoadingButton
                            type="submit"
                            loading={processing}
                            loadingText="Menyimpan..."
                            className="gap-2 cursor-pointer shadow-sm h-9"
                        >
                            <Save className="size-4" />
                            {isEdit ? 'Simpan Perubahan' : 'Simpan Draf'}
                        </LoadingButton>
                    </CardFooter>
                </Card>
            </form>

            <KonfirmasiNegosiasiDialog
                po={savedPo}
                open={Boolean(savedPo)}
                onOpenChange={(open) => !open && setSavedPo(null)}
                onDecline={() => router.visit(`/purchase-order/${savedPo.uuid}`)}
            />
        </>
    );
}
