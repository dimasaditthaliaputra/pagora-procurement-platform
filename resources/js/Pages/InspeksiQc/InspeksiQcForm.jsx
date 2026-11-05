import { useMemo, useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    Building2,
    Calendar,
    Camera,
    CheckCircle2,
    ClipboardCheck,
    Info,
    PackageCheck,
    RefreshCw,
    ShieldAlert,
    Truck,
    XCircle,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { notify } from '@/lib/notify';
import { formatDate, formatNumber, formatRupiah } from '@/lib/format';
import { Combobox } from '@/Components/combobox';
import { FileDropzone } from '@/Components/file-dropzone';
import { LoadingButton } from '@/Components/loading-button';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';

const PHOTO_ACCEPT = {
    'image/jpeg': ['.jpg', '.jpeg'],
    'image/png': ['.png'],
    'image/webp': ['.webp'],
};

const MAX_PHOTO_SIZE = 5 * 1024 * 1024; // 5 MB (PRD BR-10, RULES §7 & §8.7)
const MAX_PHOTOS_PER_ITEM = 5;

function buildInitialItems(order) {
    if (!order || !Array.isArray(order.items)) {
        return [];
    }

    return order.items.map((poItem) => ({
        po_item_uuid: poItem.uuid,
        status: '',
        received_quantity: '',
        notes: '',
        photos: [],
    }));
}

export function InspeksiQcForm({ purchaseOrderOptions = [], initialPoUuid = null }) {
    const initialOrder = useMemo(
        () => purchaseOrderOptions.find((po) => po.uuid === initialPoUuid) ?? null,
        [purchaseOrderOptions, initialPoUuid]
    );

    const [confirmOpen, setConfirmOpen] = useState(false);

    const { data, setData, post, processing, progress, errors, setError, clearErrors } = useForm({
        purchase_order_uuid: initialOrder?.uuid ?? '',
        matched_plate_number: initialOrder?.shipment?.vehicle_plate_number ?? '',
        notes: '',
        items: buildInitialItems(initialOrder),
    });

    const comboboxOptions = useMemo(
        () =>
            purchaseOrderOptions.map((po) => ({
                value: po.uuid,
                label: `${po.po_number} — ${po.vendor_name}${
                    po.shipment?.vehicle_plate_number ? ` (${po.shipment.vehicle_plate_number})` : ''
                }`,
            })),
        [purchaseOrderOptions]
    );

    const selectedPo = useMemo(
        () => purchaseOrderOptions.find((po) => po.uuid === data.purchase_order_uuid) ?? null,
        [purchaseOrderOptions, data.purchase_order_uuid]
    );

    const handleSelectOrder = (uuid) => {
        const matchedOrder = purchaseOrderOptions.find((po) => po.uuid === uuid) ?? null;
        setData((prev) => ({
            ...prev,
            purchase_order_uuid: uuid,
            matched_plate_number: matchedOrder?.shipment?.vehicle_plate_number ?? '',
            items: buildInitialItems(matchedOrder),
        }));
        clearErrors();
    };

    const updateItem = (index, updater) => {
        setData(
            'items',
            data.items.map((item, idx) => (idx === index ? updater(item) : item))
        );
    };

    const handleSelectItemStatus = (index, statusValue, orderedQty) => {
        updateItem(index, (prev) => {
            let nextQty = prev.received_quantity;
            if (statusValue === 'accepted' && (nextQty === '' || Number(nextQty) <= 0)) {
                nextQty = String(orderedQty);
            } else if (statusValue === 'rejected' && nextQty === '') {
                nextQty = '0';
            }

            return {
                ...prev,
                status: statusValue,
                received_quantity: nextQty,
            };
        });

        clearErrors(
            `items.${index}.status`,
            `items.${index}.received_quantity`,
            `items.${index}.notes`,
            `items.${index}.photos`,
            'items'
        );
    };

    const handleReceivedQuantityChange = (index, value) => {
        updateItem(index, (prev) => ({
            ...prev,
            received_quantity: value,
        }));
        clearErrors(`items.${index}.received_quantity`);
    };

    const handleItemNotesChange = (index, value) => {
        updateItem(index, (prev) => ({
            ...prev,
            notes: value,
        }));
        clearErrors(`items.${index}.notes`);
    };

    const handleAddPhoto = (index, file) => {
        if (!file) return;
        updateItem(index, (prev) => ({
            ...prev,
            photos: [...prev.photos, file].slice(0, MAX_PHOTOS_PER_ITEM),
        }));
        clearErrors(`items.${index}.photos`);
    };

    const handleReplaceOrRemovePhoto = (itemIndex, photoIndex, nextFile) => {
        updateItem(itemIndex, (prev) => {
            const nextPhotos = [...prev.photos];
            if (nextFile instanceof File) {
                nextPhotos[photoIndex] = nextFile;
            } else {
                nextPhotos.splice(photoIndex, 1);
            }
            return {
                ...prev,
                photos: nextPhotos,
            };
        });
        clearErrors(`items.${itemIndex}.photos`, `items.${itemIndex}.photos.${photoIndex}`);
    };

    const getItemPhotoError = (index) => {
        if (errors[`items.${index}.photos`]) {
            return errors[`items.${index}.photos`];
        }

        const nestedKey = Object.keys(errors).find((key) =>
            key.startsWith(`items.${index}.photos.`)
        );
        return nestedKey ? errors[nestedKey] : undefined;
    };

    const validateClientSide = () => {
        const nextErrors = {};

        if (!data.purchase_order_uuid) {
            nextErrors.purchase_order_uuid =
                'Pesanan (Purchase Order) yang berstatus Dalam Pengiriman wajib dipilih.';
        }

        const plate = (data.matched_plate_number || '').trim();
        if (plate !== '' && (plate.length < 3 || plate.length > 20 || !/^[A-Za-z0-9\s\-]+$/.test(plate))) {
            nextErrors.matched_plate_number =
                'Nomor plat armada harus 3–20 karakter (huruf, angka, spasi, atau tanda hubung).';
        }

        if (!selectedPo || data.items.length === 0) {
            if (!nextErrors.purchase_order_uuid) {
                nextErrors.items = 'Pilih pesanan terlebih dahulu untuk memuat daftar barang.';
            }
        } else {
            selectedPo.items.forEach((poItem, index) => {
                const row = data.items[index];
                if (!row || !row.status) {
                    nextErrors[`items.${index}.status`] =
                        'Pilih opsi status pemeriksaan barang (Disetujui atau Ditolak).';
                    return;
                }

                const rawQty = String(row.received_quantity ?? '').trim();
                const parsedQty = Number(rawQty);

                if (rawQty === '' || Number.isNaN(parsedQty) || parsedQty < 0 || parsedQty > poItem.quantity) {
                    nextErrors[`items.${index}.received_quantity`] =
                        `Kuantitas diterima harus di antara 0 dan ${poItem.quantity} ${poItem.unit}.`;
                } else if (row.status === 'accepted' && parsedQty <= 0) {
                    nextErrors[`items.${index}.received_quantity`] =
                        'Kuantitas diterima wajib lebih dari 0 untuk item yang disetujui.';
                }

                if (row.status === 'rejected') {
                    if (!(row.notes || '').trim()) {
                        nextErrors[`items.${index}.notes`] =
                            'Catatan ketidaksesuaian wajib diisi pada kolom item yang ditolak.';
                    }

                    if (!Array.isArray(row.photos) || row.photos.length < 1) {
                        nextErrors[`items.${index}.photos`] =
                            'Unggah minimal 1 foto bukti ketidaksesuaian untuk item yang ditolak.';
                    }
                }
            });
        }

        const errorKeys = Object.keys(nextErrors);
        if (errorKeys.length > 0) {
            clearErrors();
            errorKeys.forEach((field) => setError(field, nextErrors[field]));
            notify.validationFailed();
            return false;
        }

        return true;
    };

    const handleOpenConfirm = (e) => {
        e.preventDefault();
        if (!validateClientSide()) {
            return;
        }
        setConfirmOpen(true);
    };

    const handleConfirmSubmit = () => {
        post('/inspeksi-qc', {
            forceFormData: true,
            onError: () => {
                setConfirmOpen(false);
                notify.validationFailed();
            },
        });
    };

    const summaryCounts = useMemo(() => {
        let accepted = 0;
        let rejected = 0;
        data.items.forEach((item) => {
            if (item.status === 'accepted') accepted += 1;
            if (item.status === 'rejected') rejected += 1;
        });
        return { accepted, rejected, hasRejection: rejected > 0 };
    }, [data.items]);

    const submitButtonLabel = summaryCounts.hasRejection
        ? 'Simpan Form Penolakan'
        : 'Simpan Form Persetujuan';

    return (
        <>
            <form onSubmit={handleOpenConfirm} noValidate className="space-y-6">
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                    <div className="lg:col-span-2 space-y-6">
                        {/* Langkah 1: Pilih Pesanan Berstatus In Transit & Pencocokan Armada */}
                        <Card className="shadow-sm">
                            <CardHeader>
                                <CardTitle className="text-base font-semibold flex items-center gap-2">
                                    <Truck className="size-4 text-primary" />
                                    Pilih Pesanan &amp; Pencocokan Armada (In Transit)
                                </CardTitle>
                                <CardDescription>
                                    Pilih pesanan berstatus Dalam Pengiriman (In Transit) untuk memuat rincian barang dan mencocokkan plat nomor kendaraan di gudang.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-5">
                                <div className="space-y-2">
                                    <Label htmlFor="purchase_order_uuid">
                                        Pesanan Dalam Pengiriman (In Transit) *
                                    </Label>
                                    <Combobox
                                        options={comboboxOptions}
                                        value={data.purchase_order_uuid}
                                        onChange={handleSelectOrder}
                                        placeholder={
                                            comboboxOptions.length > 0
                                                ? 'Pilih Purchase Order berstatus In Transit...'
                                                : 'Tidak ada pesanan berstatus In Transit saat ini'
                                        }
                                        searchPlaceholder="Cari nomor PO, vendor, atau plat armada..."
                                        emptyMessage="Pesanan In Transit tidak ditemukan."
                                        disabled={processing || comboboxOptions.length === 0}
                                        error={errors.purchase_order_uuid}
                                        className="min-h-10"
                                    />
                                    {errors.purchase_order_uuid && (
                                        <p className="text-sm text-destructive">
                                            {errors.purchase_order_uuid}
                                        </p>
                                    )}
                                </div>

                                {selectedPo && (
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                                        <div className="space-y-2">
                                            <Label htmlFor="matched_plate_number">
                                                Nomor Plat Armada Dicocokkan di Gudang
                                            </Label>
                                            <Input
                                                id="matched_plate_number"
                                                type="text"
                                                placeholder="Contoh: B 9124 TKA"
                                                value={data.matched_plate_number}
                                                disabled={processing}
                                                onChange={(e) => {
                                                    setData(
                                                        'matched_plate_number',
                                                        e.target.value.toUpperCase()
                                                    );
                                                    clearErrors('matched_plate_number');
                                                }}
                                                aria-invalid={Boolean(errors.matched_plate_number)}
                                                className={cn(
                                                    'font-mono uppercase tracking-wider min-h-10',
                                                    errors.matched_plate_number &&
                                                        'border-destructive focus-visible:ring-destructive'
                                                )}
                                            />
                                            {errors.matched_plate_number ? (
                                                <p className="text-sm text-destructive">
                                                    {errors.matched_plate_number}
                                                </p>
                                            ) : (
                                                <p className="text-xs text-muted-foreground">
                                                    Dilaporkan vendor:{' '}
                                                    <strong className="font-mono text-foreground">
                                                        {selectedPo.shipment?.vehicle_plate_number ?? '-'}
                                                    </strong>
                                                </p>
                                            )}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="notes">Catatan Umum Penerimaan (Opsional)</Label>
                                            <Input
                                                id="notes"
                                                type="text"
                                                placeholder="Contoh: Segel boks utuh, suhu ruang armada terjaga"
                                                value={data.notes}
                                                disabled={processing}
                                                onChange={(e) => {
                                                    setData('notes', e.target.value);
                                                    clearErrors('notes');
                                                }}
                                                aria-invalid={Boolean(errors.notes)}
                                                className={cn(
                                                    'min-h-10',
                                                    errors.notes &&
                                                        'border-destructive focus-visible:ring-destructive'
                                                )}
                                            />
                                            {errors.notes && (
                                                <p className="text-sm text-destructive">{errors.notes}</p>
                                            )}
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Item yang Sudah Diterima pada Pengiriman Sebelumnya (Pengiriman Ulang) */}
                        {selectedPo &&
                            Array.isArray(selectedPo.already_accepted_items) &&
                            selectedPo.already_accepted_items.length > 0 && (
                                <Alert>
                                    <Info className="size-4" />
                                    <AlertTitle>Pengiriman Ulang (Reshipment)</AlertTitle>
                                    <AlertDescription>
                                        Sebanyak {selectedPo.already_accepted_items.length} item pada PO ini telah disetujui pada inspeksi sebelumnya. Form di bawah ini hanya menampilkan item yang belum disetujui dan sedang dikirim ulang oleh vendor.
                                    </AlertDescription>
                                </Alert>
                            )}

                        {/* Langkah 2: Form Pengecekan Fisik per Item */}
                        {selectedPo ? (
                            <Card className="shadow-sm">
                                <CardHeader className="border-b">
                                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                        <div>
                                            <CardTitle className="text-base font-semibold flex items-center gap-2">
                                                <ClipboardCheck className="size-4 text-primary" />
                                                Form Pengecekan Barang per Item ({selectedPo.po_number})
                                            </CardTitle>
                                            <CardDescription className="mt-0.5">
                                                Periksa kualitas dan kuantitas fisik setiap item. Tekan opsi{' '}
                                                <strong>Disetujui</strong> jika barang sesuai atau{' '}
                                                <strong>Ditolak</strong> jika tidak sesuai spesifikasi.
                                            </CardDescription>
                                        </div>
                                        <Badge variant="outline" className="w-fit">
                                            {selectedPo.items.length} Item Diperiksa
                                        </Badge>
                                    </div>
                                    {errors.items && (
                                        <p className="text-sm text-destructive pt-1">{errors.items}</p>
                                    )}
                                </CardHeader>

                                <CardContent className="p-4 sm:p-6 space-y-5">
                                    {selectedPo.items.map((poItem, index) => {
                                        const row = data.items[index] ?? {
                                            po_item_uuid: poItem.uuid,
                                            status: '',
                                            received_quantity: '',
                                            notes: '',
                                            photos: [],
                                        };
                                        const isAccepted = row.status === 'accepted';
                                        const isRejected = row.status === 'rejected';
                                        const statusError = errors[`items.${index}.status`];
                                        const qtyError = errors[`items.${index}.received_quantity`];
                                        const notesError = errors[`items.${index}.notes`];
                                        const photoError = getItemPhotoError(index);

                                        return (
                                            <div
                                                key={poItem.uuid}
                                                className={cn(
                                                    'rounded-xl border p-4 sm:p-5 space-y-4 transition-colors',
                                                    isAccepted &&
                                                        'border-emerald-500/40 bg-emerald-500/[0.03]',
                                                    isRejected &&
                                                        'border-destructive/50 bg-destructive/[0.03]',
                                                    !isAccepted &&
                                                        !isRejected &&
                                                        (statusError
                                                            ? 'border-destructive bg-destructive/5'
                                                            : 'border-border bg-card')
                                                )}
                                            >
                                                {/* Header Info Item */}
                                                <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                                                    <div>
                                                        <div className="flex items-center gap-2 flex-wrap">
                                                            <span className="inline-flex size-6 items-center justify-center rounded-full bg-muted text-xs font-bold text-foreground">
                                                                {index + 1}
                                                            </span>
                                                            <h3 className="text-base font-semibold text-foreground">
                                                                {poItem.commodity_name}
                                                            </h3>
                                                        </div>
                                                        <p className="text-xs text-muted-foreground mt-1">
                                                            Harga Satuan PO:{' '}
                                                            <span className="font-medium text-foreground tabular-nums">
                                                                {formatRupiah(poItem.unit_price)}
                                                            </span>{' '}
                                                            / {poItem.unit} · Subtotal:{' '}
                                                            <span className="font-medium text-foreground tabular-nums">
                                                                {formatRupiah(poItem.subtotal)}
                                                            </span>
                                                        </p>
                                                    </div>

                                                    <div className="rounded-lg border bg-muted/40 px-3 py-1.5 text-left sm:text-right shrink-0">
                                                        <span className="block text-[11px] text-muted-foreground">
                                                            Kuantitas Pesanan PO (Terkunci)
                                                        </span>
                                                        <span className="text-sm font-bold text-foreground tabular-nums">
                                                            {formatNumber(poItem.quantity)} {poItem.unit}
                                                        </span>
                                                    </div>
                                                </div>

                                                {/* Keputusan: Barang Sesuai? -> Tombol Opsi Status Disetujui / Ditolak */}
                                                <div className="grid grid-cols-1 md:grid-cols-12 gap-4 items-start pt-1">
                                                    <div className="md:col-span-7 space-y-2">
                                                        <Label>
                                                            Apakah Barang Sesuai? (Pilih Opsi Status) *
                                                        </Label>
                                                        <div
                                                            role="group"
                                                            aria-label={`Opsi status pemeriksaan ${poItem.commodity_name}`}
                                                            aria-invalid={Boolean(statusError)}
                                                            className="grid grid-cols-2 gap-3"
                                                        >
                                                            <Button
                                                                type="button"
                                                                variant={isAccepted ? 'default' : 'outline'}
                                                                disabled={processing}
                                                                onClick={() =>
                                                                    handleSelectItemStatus(
                                                                        index,
                                                                        'accepted',
                                                                        poItem.quantity
                                                                    )
                                                                }
                                                                className={cn(
                                                                    'min-h-11 gap-2 font-semibold cursor-pointer',
                                                                    isAccepted &&
                                                                        'bg-emerald-600 text-white hover:bg-emerald-700 border-emerald-600',
                                                                    statusError &&
                                                                        !row.status &&
                                                                        'border-destructive'
                                                                )}
                                                            >
                                                                <CheckCircle2 className="size-4 shrink-0" />
                                                                Disetujui
                                                            </Button>

                                                            <Button
                                                                type="button"
                                                                variant={
                                                                    isRejected ? 'destructive' : 'outline'
                                                                }
                                                                disabled={processing}
                                                                onClick={() =>
                                                                    handleSelectItemStatus(
                                                                        index,
                                                                        'rejected',
                                                                        poItem.quantity
                                                                    )
                                                                }
                                                                className={cn(
                                                                    'min-h-11 gap-2 font-semibold cursor-pointer',
                                                                    statusError &&
                                                                        !row.status &&
                                                                        'border-destructive'
                                                                )}
                                                            >
                                                                <XCircle className="size-4 shrink-0" />
                                                                Ditolak
                                                            </Button>
                                                        </div>
                                                        {statusError && (
                                                            <p className="text-sm text-destructive">
                                                                {statusError}
                                                            </p>
                                                        )}
                                                    </div>

                                                    <div className="md:col-span-5 space-y-2">
                                                        <Label htmlFor={`received_qty_${poItem.uuid}`}>
                                                            Kuantitas Fisik Diterima ({poItem.unit}) *
                                                        </Label>
                                                        <Input
                                                            id={`received_qty_${poItem.uuid}`}
                                                            type="number"
                                                            step="0.001"
                                                            inputMode="decimal"
                                                            placeholder={`Maks. ${poItem.quantity}`}
                                                            value={row.received_quantity}
                                                            disabled={processing}
                                                            onChange={(e) =>
                                                                handleReceivedQuantityChange(
                                                                    index,
                                                                    e.target.value
                                                                )
                                                            }
                                                            aria-invalid={Boolean(qtyError)}
                                                            className={cn(
                                                                'min-h-11 tabular-nums',
                                                                qtyError &&
                                                                    'border-destructive focus-visible:ring-destructive'
                                                            )}
                                                        />
                                                        {qtyError && (
                                                            <p className="text-sm text-destructive">
                                                                {qtyError}
                                                            </p>
                                                        )}
                                                    </div>
                                                </div>

                                                {/* Kolom Ketidaksesuaian & Foto Bukti saat Opsi "Ditolak" Dipilih */}
                                                {isRejected && (
                                                    <div className="rounded-xl border border-destructive/30 bg-background p-4 space-y-4">
                                                        <div className="flex items-start gap-2 text-xs text-destructive">
                                                            <ShieldAlert className="size-4 shrink-0 mt-0.5" />
                                                            <span>
                                                                Item yang ditolak wajib disertai catatan ketidaksesuaian dan minimal 1 foto bukti (maks. 5 MB/foto). Bukti bersifat <strong>immutable</strong> dan akan dicatat langsung ke <strong>Log Sengketa</strong>.
                                                            </span>
                                                        </div>

                                                        <div className="space-y-2">
                                                            <Label htmlFor={`item_notes_${poItem.uuid}`}>
                                                                Catatan Ketidaksesuaian Inspeksi *
                                                            </Label>
                                                            <Textarea
                                                                id={`item_notes_${poItem.uuid}`}
                                                                rows={3}
                                                                placeholder="Jelaskan rincian ketidaksesuaian (misal: kemasan pecah/basah, mutu di bawah spesifikasi, bobot susut melebihi batas)..."
                                                                value={row.notes}
                                                                disabled={processing}
                                                                onChange={(e) =>
                                                                    handleItemNotesChange(
                                                                        index,
                                                                        e.target.value
                                                                    )
                                                                }
                                                                aria-invalid={Boolean(notesError)}
                                                                className={cn(
                                                                    notesError &&
                                                                        'border-destructive focus-visible:ring-destructive'
                                                                )}
                                                            />
                                                            {notesError && (
                                                                <p className="text-sm text-destructive">
                                                                    {notesError}
                                                                </p>
                                                            )}
                                                        </div>

                                                        <div className="space-y-3">
                                                            <div className="flex items-center justify-between gap-2">
                                                                <Label className="flex items-center gap-1.5">
                                                                    <Camera className="size-4 text-muted-foreground" />
                                                                    Foto Bukti Ketidaksesuaian Inspeksi *
                                                                </Label>
                                                                <span className="text-xs text-muted-foreground">
                                                                    {row.photos.length}/{MAX_PHOTOS_PER_ITEM} Foto
                                                                </span>
                                                            </div>

                                                            {row.photos.length > 0 && (
                                                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                                    {row.photos.map((photoFile, photoIdx) => (
                                                                        <FileDropzone
                                                                            key={`${poItem.uuid}-photo-${photoIdx}`}
                                                                            value={photoFile}
                                                                            accept={PHOTO_ACCEPT}
                                                                            maxSize={MAX_PHOTO_SIZE}
                                                                            disabled={processing}
                                                                            uploading={processing}
                                                                            progress={progress?.percentage ?? null}
                                                                            error={
                                                                                errors[
                                                                                    `items.${index}.photos.${photoIdx}`
                                                                                ]
                                                                            }
                                                                            onChange={(nextFile) =>
                                                                                handleReplaceOrRemovePhoto(
                                                                                    index,
                                                                                    photoIdx,
                                                                                    nextFile
                                                                                )
                                                                            }
                                                                            onError={(msg) => {
                                                                                setError(
                                                                                    `items.${index}.photos`,
                                                                                    msg
                                                                                );
                                                                                notify.validationFailed();
                                                                            }}
                                                                        />
                                                                    ))}
                                                                </div>
                                                            )}

                                                            {row.photos.length < MAX_PHOTOS_PER_ITEM && (
                                                                <FileDropzone
                                                                    value={null}
                                                                    accept={PHOTO_ACCEPT}
                                                                    maxSize={MAX_PHOTO_SIZE}
                                                                    disabled={processing}
                                                                    uploading={processing}
                                                                    progress={progress?.percentage ?? null}
                                                                    label={
                                                                        row.photos.length === 0
                                                                            ? 'Unggah Foto Bukti Ketidaksesuaian'
                                                                            : 'Tambah Foto Bukti Lainnya (Opsional)'
                                                                    }
                                                                    description="Format JPG, JPEG, PNG, atau WEBP (Maksimal 5 MB per foto)"
                                                                    error={
                                                                        row.photos.length === 0
                                                                            ? photoError
                                                                            : undefined
                                                                    }
                                                                    onChange={(file) =>
                                                                        handleAddPhoto(index, file)
                                                                    }
                                                                    onError={(msg) => {
                                                                        setError(
                                                                            `items.${index}.photos`,
                                                                            msg
                                                                        );
                                                                        notify.validationFailed();
                                                                    }}
                                                                />
                                                            )}

                                                            {row.photos.length > 0 && photoError && (
                                                                <p className="text-sm text-destructive">
                                                                    {photoError}
                                                                </p>
                                                            )}
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })}
                                </CardContent>
                            </Card>
                        ) : (
                            <Card className="border-dashed shadow-2xs">
                                <CardContent className="flex flex-col items-center justify-center py-12 text-center">
                                    <PackageCheck className="size-10 text-muted-foreground/50 mb-3" />
                                    <p className="text-sm font-semibold text-foreground">
                                        Belum Ada Pesanan yang Dipilih
                                    </p>
                                    <p className="text-xs text-muted-foreground max-w-md mt-1">
                                        Pilih Purchase Order berstatus Dalam Pengiriman (In Transit) pada kotak pilihan di atas untuk memuat daftar barang yang perlu diinspeksi.
                                    </p>
                                </CardContent>
                            </Card>
                        )}

                        {/* Tombol Simpan Form Persetujuan / Penolakan */}
                        <div className="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={processing}
                                asChild
                                className="min-h-11"
                            >
                                <Link href="/inspeksi-qc">Batal</Link>
                            </Button>
                            <LoadingButton
                                type="submit"
                                variant={summaryCounts.hasRejection ? 'destructive' : 'default'}
                                loading={processing}
                                loadingText="Menyimpan Hasil Inspeksi..."
                                disabled={comboboxOptions.length === 0 || !selectedPo}
                                className="min-h-11 gap-2 cursor-pointer"
                            >
                                {summaryCounts.hasRejection ? (
                                    <AlertTriangle className="size-4" />
                                ) : (
                                    <CheckCircle2 className="size-4" />
                                )}
                                {submitButtonLabel}
                            </LoadingButton>
                        </div>
                    </div>

                    {/* Panel Samping: Detail Pesanan & Armada yang Dipilih */}
                    <div className="lg:col-span-1 space-y-4">
                        <Card className="shadow-sm">
                            <CardHeader className="pb-3 border-b">
                                <CardTitle className="text-sm font-semibold">
                                    Rincian Pesanan yang Dipilih
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="p-5 space-y-4 text-sm">
                                {selectedPo ? (
                                    <>
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="font-mono font-bold text-base text-foreground">
                                                {selectedPo.po_number}
                                            </span>
                                            <Badge variant="sky">{selectedPo.status_label}</Badge>
                                        </div>

                                        {selectedPo.shipment?.is_reshipment && (
                                            <Badge variant="warning" className="gap-1">
                                                <RefreshCw className="size-3" />
                                                Pengiriman Ulang Sengketa
                                            </Badge>
                                        )}

                                        <div className="space-y-3 pt-1 border-t text-xs">
                                            <div>
                                                <span className="text-muted-foreground block">
                                                    Vendor Pengirim
                                                </span>
                                                <span className="font-medium text-foreground inline-flex items-center gap-1.5 mt-0.5">
                                                    <Building2 className="size-3.5 text-muted-foreground" />
                                                    {selectedPo.vendor_name}
                                                </span>
                                            </div>

                                            {selectedPo.shipment && (
                                                <>
                                                    <div>
                                                        <span className="text-muted-foreground block">
                                                            Jalur &amp; Armada Pengiriman
                                                        </span>
                                                        <span className="font-medium text-foreground block mt-0.5">
                                                            {selectedPo.shipment.route} —{' '}
                                                            {selectedPo.shipment.carrier_name}
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <span className="text-muted-foreground block">
                                                            Nomor Plat Dilaporkan Vendor
                                                        </span>
                                                        <span className="inline-flex items-center rounded-md border bg-muted/40 px-2 py-0.5 font-mono font-bold text-foreground mt-0.5">
                                                            {selectedPo.shipment.vehicle_plate_number}
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <span className="text-muted-foreground block">
                                                            Estimasi Tanggal Tiba (ETA)
                                                        </span>
                                                        <span className="font-medium text-foreground inline-flex items-center gap-1.5 mt-0.5 tabular-nums">
                                                            <Calendar className="size-3.5 text-muted-foreground" />
                                                            {formatDate(
                                                                selectedPo.shipment
                                                                    .estimated_arrival_date
                                                            )}
                                                        </span>
                                                    </div>
                                                </>
                                            )}

                                            <div className="pt-2 border-t flex items-center justify-between">
                                                <span className="text-muted-foreground">
                                                    Ringkasan Pilihan Saat Ini
                                                </span>
                                                <span className="font-semibold text-foreground">
                                                    {summaryCounts.accepted} Disetujui ·{' '}
                                                    {summaryCounts.rejected} Ditolak
                                                </span>
                                            </div>
                                        </div>
                                    </>
                                ) : (
                                    <p className="text-xs text-muted-foreground">
                                        Rincian vendor, jalur logistik, plat nomor armada, dan daftar komoditas akan tampil setelah Anda memilih pesanan.
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </form>

            {/* Dialog Konfirmasi Aksi Kritikal (PRD NFR-14) */}
            <AlertDialog
                open={confirmOpen}
                onOpenChange={(val) => {
                    if (!processing) setConfirmOpen(val);
                }}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle className="flex items-center gap-2">
                            {summaryCounts.hasRejection ? (
                                <>
                                    <AlertTriangle className="size-5 text-destructive" />
                                    Konfirmasi Simpan Form Penolakan Barang
                                </>
                            ) : (
                                <>
                                    <CheckCircle2 className="size-5 text-emerald-600" />
                                    Konfirmasi Simpan Form Persetujuan Barang
                                </>
                            )}
                        </AlertDialogTitle>
                        <AlertDialogDescription asChild>
                            <div className="space-y-2 text-sm text-muted-foreground">
                                {summaryCounts.hasRejection ? (
                                    <>
                                        <p>
                                            Anda akan menyimpan hasil inspeksi untuk{' '}
                                            <strong className="font-mono text-foreground">
                                                {selectedPo?.po_number}
                                            </strong>{' '}
                                            dengan rincian{' '}
                                            <strong className="text-foreground">
                                                {summaryCounts.accepted} item disetujui
                                            </strong>{' '}
                                            dan{' '}
                                            <strong className="text-destructive">
                                                {summaryCounts.rejected} item ditolak
                                            </strong>
                                            .
                                        </p>
                                        <p>
                                            Item yang ditolak beserta foto bukti bersifat{' '}
                                            <strong className="text-foreground">
                                                permanen (immutable)
                                            </strong>
                                            , otomatis dicatat ke{' '}
                                            <strong className="text-foreground">Log Sengketa</strong>
                                            , dan mengubah status pesanan menjadi{' '}
                                            <strong className="text-foreground">
                                                Diterima Sebagian
                                            </strong>
                                            .
                                        </p>
                                    </>
                                ) : (
                                    <p>
                                        Seluruh{' '}
                                        <strong className="text-foreground">
                                            {summaryCounts.accepted} item
                                        </strong>{' '}
                                        pada{' '}
                                        <strong className="font-mono text-foreground">
                                            {selectedPo?.po_number}
                                        </strong>{' '}
                                        dinyatakan sesuai. Hasil inspeksi akan disimpan secara permanen dan status Purchase Order diperbarui menjadi{' '}
                                        <strong className="text-foreground">Diterima Gudang</strong>.
                                    </p>
                                )}
                            </div>
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={processing} className="cursor-pointer">
                            Periksa Kembali
                        </AlertDialogCancel>
                        <LoadingButton
                            type="button"
                            variant={summaryCounts.hasRejection ? 'destructive' : 'default'}
                            loading={processing}
                            loadingText="Menyimpan..."
                            onClick={handleConfirmSubmit}
                            className="cursor-pointer"
                        >
                            Ya, {submitButtonLabel}
                        </LoadingButton>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}

export default InspeksiQcForm;

