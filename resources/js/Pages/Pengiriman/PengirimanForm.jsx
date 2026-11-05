import { useMemo, useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    Building2,
    Lock,
    MapPin,
    Save,
    Truck,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { notify } from '@/lib/notify';
import { formatDate, formatNumber, formatRupiah } from '@/lib/format';
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
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { Combobox } from '@/Components/combobox';
import { LoadingButton } from '@/Components/loading-button';

const invalidClass = (error) => error && 'border-destructive focus-visible:ring-destructive';

function FieldError({ id, message }) {
    return message ? (
        <p id={id} className="text-xs font-medium text-destructive">
            {message}
        </p>
    ) : null;
}

export function PengirimanForm({
    purchaseOrderOptions = [],
    routeOptions = [],
    selectedPoUuid = null,
}) {
    const [confirmOpen, setConfirmOpen] = useState(false);

    const todayIso = useMemo(() => {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }, []);

    const { data, setData, post, processing, errors, setError, clearErrors } = useForm({
        purchase_order_uuid: selectedPoUuid ?? '',
        route: '',
        carrier_name: '',
        vehicle_plate_number: '',
        estimated_arrival_date: '',
    });

    const comboboxPoOptions = useMemo(
        () =>
            purchaseOrderOptions.map((po) => ({
                value: po.uuid,
                label: `${po.po_number} — ${po.tenant_name} (${po.status_label})`,
            })),
        [purchaseOrderOptions]
    );

    const selectedPo = useMemo(
        () => purchaseOrderOptions.find((po) => po.uuid === data.purchase_order_uuid) ?? null,
        [purchaseOrderOptions, data.purchase_order_uuid]
    );

    const validate = () => {
        const found = {};

        if (!data.purchase_order_uuid) {
            found.purchase_order_uuid = 'Purchase Order wajib dipilih.';
        }

        if (!data.route) {
            found.route = 'Jalur pengiriman wajib dipilih.';
        }

        const trimmedCarrier = (data.carrier_name ?? '').trim();
        if (!trimmedCarrier) {
            found.carrier_name = 'Nama armada atau ekspedisi wajib diisi.';
        } else if (trimmedCarrier.length < 2) {
            found.carrier_name = 'Nama armada atau ekspedisi minimal 2 karakter.';
        } else if (trimmedCarrier.length > 150) {
            found.carrier_name = 'Nama armada atau ekspedisi maksimal 150 karakter.';
        }

        const trimmedPlate = (data.vehicle_plate_number ?? '').trim();
        if (!trimmedPlate) {
            found.vehicle_plate_number = 'Nomor plat armada wajib diisi.';
        } else if (trimmedPlate.length < 3) {
            found.vehicle_plate_number = 'Nomor plat armada minimal 3 karakter.';
        } else if (trimmedPlate.length > 20) {
            found.vehicle_plate_number = 'Nomor plat armada maksimal 20 karakter.';
        } else if (!/^[A-Za-z0-9\s-]+$/.test(trimmedPlate)) {
            found.vehicle_plate_number = 'Nomor plat armada hanya boleh berisi huruf, angka, spasi, atau tanda hubung.';
        }

        if (!data.estimated_arrival_date) {
            found.estimated_arrival_date = 'Perkiraan tanggal tiba (ETA) wajib diisi.';
        } else if (data.estimated_arrival_date < todayIso) {
            found.estimated_arrival_date = 'Perkiraan tanggal tiba minimal hari ini atau setelahnya.';
        }

        return found;
    };

    const handleSubmitRequest = (e) => {
        e.preventDefault();

        const found = validate();
        if (Object.keys(found).length > 0) {
            setError(found);
            notify.validationFailed();
            return;
        }

        setConfirmOpen(true);
    };

    const handleConfirmSave = () => {
        post('/pengiriman', {
            preserveScroll: true,
            onSuccess: () => {
                setConfirmOpen(false);
            },
            onError: () => {
                setConfirmOpen(false);
                notify.validationFailed();
            },
        });
    };

    if (purchaseOrderOptions.length === 0) {
        return (
            <Alert>
                <AlertCircle className="size-4" />
                <AlertTitle>Belum ada Purchase Order yang siap dikirim</AlertTitle>
                <AlertDescription className="space-y-3 mt-1">
                    <p>
                        Pelaporan status logistik fisik hanya dapat dilakukan untuk Purchase Order yang telah mencapai status{' '}
                        <strong>Disepakati</strong> (atau <strong>Diterima Sebagian</strong> untuk pengiriman ulang).
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <Button size="sm" variant="outline" asChild>
                            <Link href="/purchase-order">Lihat Daftar Purchase Order</Link>
                        </Button>
                        <Button size="sm" variant="ghost" asChild>
                            <Link href="/pengiriman">Kembali ke Daftar Pengiriman</Link>
                        </Button>
                    </div>
                </AlertDescription>
            </Alert>
        );
    }

    return (
        <>
            <form noValidate onSubmit={handleSubmitRequest} className="w-full space-y-4">
                <Card className="w-full shadow-sm">
                    <CardHeader className="py-3.5 px-5 border-b">
                        <CardTitle className="text-base font-semibold">
                            1. Pilih Dokumen Purchase Order
                        </CardTitle>
                        <CardDescription className="text-xs">
                            Hanya menampilkan Purchase Order milik perusahaan Anda dengan harga yang sudah disepakati bersama buyer.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-5 space-y-4">
                        <div className="grid gap-1.5">
                            <Label
                                htmlFor="purchase_order_uuid"
                                className={cn('text-sm', errors.purchase_order_uuid && 'text-destructive')}
                            >
                                Purchase Order <span className="text-destructive">*</span>
                            </Label>
                            <div id="purchase_order_uuid" tabIndex={-1}>
                                <Combobox
                                    options={comboboxPoOptions}
                                    value={data.purchase_order_uuid}
                                    onChange={(val) => {
                                        setData('purchase_order_uuid', val);
                                        clearErrors('purchase_order_uuid');
                                    }}
                                    placeholder="Pilih nomor Purchase Order yang akan dikirim"
                                    searchPlaceholder="Cari nomor PO atau nama perusahaan buyer..."
                                    emptyMessage="Purchase Order tidak ditemukan."
                                    error={errors.purchase_order_uuid}
                                    disabled={processing}
                                    className="h-9"
                                />
                            </div>
                            <FieldError id="error-po-uuid" message={errors.purchase_order_uuid} />
                        </div>

                        {selectedPo && (
                            <div className="rounded-lg border bg-muted/20 p-4 space-y-3">
                                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                    <div className="space-y-0.5">
                                        <div className="flex items-center gap-2 flex-wrap">
                                            <span className="font-mono text-sm font-bold text-foreground">
                                                {selectedPo.po_number}
                                            </span>
                                            <Badge variant={selectedPo.is_reshipment ? 'warning' : 'green'}>
                                                {selectedPo.status_label}
                                            </Badge>
                                        </div>
                                        <p className="text-xs text-muted-foreground flex items-center gap-1.5">
                                            <Building2 className="size-3.5" />
                                            Tujuan: <strong className="text-foreground">{selectedPo.tenant_name}</strong> · Tanggal PO {formatDate(selectedPo.po_date)}
                                        </p>
                                    </div>
                                    <div className="text-left sm:text-right">
                                        <span className="text-xs text-muted-foreground block">Nilai Disepakati</span>
                                        <span className="text-base font-bold tabular-nums text-foreground">
                                            {formatRupiah(selectedPo.total_amount)}
                                        </span>
                                    </div>
                                </div>

                                {selectedPo.destination_address && (
                                    <p className="text-xs text-muted-foreground flex items-start gap-1.5 border-t pt-2.5">
                                        <MapPin className="size-3.5 shrink-0 mt-0.5" />
                                        <span>Alamat Gudang Buyer: {selectedPo.destination_address}</span>
                                    </p>
                                )}

                                <div className="rounded-md border bg-background overflow-hidden">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead className="pl-4">Komoditas</TableHead>
                                                <TableHead className="text-right">Kuantitas PO</TableHead>
                                                <TableHead className="text-right">Harga Satuan</TableHead>
                                                <TableHead className="text-right pr-4">Subtotal</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {selectedPo.items.map((item) => (
                                                <TableRow key={item.uuid}>
                                                    <TableCell className="pl-4 font-medium text-xs">
                                                        {item.commodity_name}
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums text-xs">
                                                        {formatNumber(item.quantity)} {item.unit}
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums text-xs">
                                                        {formatRupiah(item.unit_price)}
                                                    </TableCell>
                                                    <TableCell className="text-right pr-4 font-semibold tabular-nums text-xs">
                                                        {formatRupiah(item.subtotal)}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                        <TableFooter>
                                            <TableRow className="text-xs font-semibold">
                                                <TableCell colSpan={3} className="pl-4">
                                                    Total Nilai Purchase Order
                                                </TableCell>
                                                <TableCell className="text-right pr-4 tabular-nums">
                                                    {formatRupiah(selectedPo.total_amount)}
                                                </TableCell>
                                            </TableRow>
                                        </TableFooter>
                                    </Table>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card className="w-full shadow-sm">
                    <CardHeader className="py-3.5 px-5 border-b">
                        <CardTitle className="text-base font-semibold">
                            2. Data Operasional Pengiriman Fisik
                        </CardTitle>
                        <CardDescription className="text-xs">
                            Isi jalur pengiriman, nama armada ekspedisi, nomor plat kendaraan (dicocokkan saat inspeksi QC gudang), dan perkiraan tanggal tiba.
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="p-5 space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="grid gap-1.5">
                                <Label
                                    htmlFor="route"
                                    className={cn('text-sm', errors.route && 'text-destructive')}
                                >
                                    Jalur Pengiriman <span className="text-destructive">*</span>
                                </Label>
                                <Select
                                    value={data.route}
                                    onValueChange={(val) => {
                                        setData('route', val);
                                        clearErrors('route');
                                    }}
                                    disabled={processing}
                                >
                                    <SelectTrigger
                                        id="route"
                                        aria-invalid={Boolean(errors.route)}
                                        className={cn('h-9 bg-background', invalidClass(errors.route))}
                                    >
                                        <SelectValue placeholder="Pilih jalur pengiriman" />
                                    </SelectTrigger>
                                    <SelectContent position="popper" side="bottom" align="start" avoidCollisions={false}>
                                        {routeOptions.map((opt) => (
                                            <SelectItem key={opt.value} value={opt.value}>
                                                {opt.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <FieldError id="error-route" message={errors.route} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label
                                    htmlFor="estimated_arrival_date"
                                    className={cn('text-sm', errors.estimated_arrival_date && 'text-destructive')}
                                >
                                    Perkiraan Tanggal Tiba (ETA) <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="estimated_arrival_date"
                                    type="date"
                                    min={todayIso}
                                    value={data.estimated_arrival_date}
                                    onChange={(e) => {
                                        setData('estimated_arrival_date', e.target.value);
                                        clearErrors('estimated_arrival_date');
                                    }}
                                    aria-invalid={Boolean(errors.estimated_arrival_date)}
                                    disabled={processing}
                                    className={cn('h-9', invalidClass(errors.estimated_arrival_date))}
                                />
                                <FieldError id="error-eta" message={errors.estimated_arrival_date} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label
                                    htmlFor="carrier_name"
                                    className={cn('text-sm', errors.carrier_name && 'text-destructive')}
                                >
                                    Nama Armada / Ekspedisi <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="carrier_name"
                                    type="text"
                                    placeholder="Contoh: Wahana Express / Truk Box Internal"
                                    value={data.carrier_name}
                                    onChange={(e) => {
                                        setData('carrier_name', e.target.value);
                                        clearErrors('carrier_name');
                                    }}
                                    aria-invalid={Boolean(errors.carrier_name)}
                                    disabled={processing}
                                    className={cn('h-9', invalidClass(errors.carrier_name))}
                                />
                                <FieldError id="error-carrier-name" message={errors.carrier_name} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label
                                    htmlFor="vehicle_plate_number"
                                    className={cn('text-sm', errors.vehicle_plate_number && 'text-destructive')}
                                >
                                    Nomor Plat Armada <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="vehicle_plate_number"
                                    type="text"
                                    placeholder="Contoh: L 5678 CD"
                                    value={data.vehicle_plate_number}
                                    onChange={(e) => {
                                        setData('vehicle_plate_number', e.target.value.toUpperCase());
                                        clearErrors('vehicle_plate_number');
                                    }}
                                    aria-invalid={Boolean(errors.vehicle_plate_number)}
                                    disabled={processing}
                                    className={cn('h-9 font-mono uppercase', invalidClass(errors.vehicle_plate_number))}
                                />
                                <p className="text-[11px] text-muted-foreground">
                                    Nomor plat ini akan dicocokkan oleh petugas QC Gudang saat barang tiba.
                                </p>
                                <FieldError id="error-plate" message={errors.vehicle_plate_number} />
                            </div>
                        </div>

                        <Alert className="bg-amber-500/5 border-amber-500/30">
                            <Lock className="size-4 text-amber-600 dark:text-amber-400" />
                            <AlertTitle className="text-xs font-semibold">
                                Penguncian Dokumen Purchase Order Otomatis
                            </AlertTitle>
                            <AlertDescription className="text-xs text-muted-foreground">
                                Saat formulir ini disimpan, status logistik akan berubah menjadi{' '}
                                <strong className="text-foreground">Dalam Pengiriman (In Transit)</strong> dan detail dokumen beserta kuantitas Purchase Order akan dikunci secara mutlak.
                            </AlertDescription>
                        </Alert>
                    </CardContent>

                    <CardFooter className="py-3 px-5 border-t bg-muted/20 flex justify-end gap-2">
                        <Button type="button" variant="outline" asChild disabled={processing} className="h-9">
                            <Link href="/pengiriman">Batal</Link>
                        </Button>
                        <LoadingButton
                            type="submit"
                            loading={processing}
                            loadingText="Menyimpan..."
                            className="gap-2 cursor-pointer shadow-sm h-9"
                        >
                            <Save className="size-4" />
                            Simpan Pengiriman
                        </LoadingButton>
                    </CardFooter>
                </Card>
            </form>

            <AlertDialog
                open={confirmOpen}
                onOpenChange={(openState) => !processing && setConfirmOpen(openState)}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle className="flex items-start gap-2.5 leading-snug">
                            <Truck className="size-5 shrink-0 mt-0.5 text-primary" />
                            <span>Konfirmasi Laporan Pengiriman Fisik?</span>
                        </AlertDialogTitle>
                        <AlertDialogDescription asChild>
                            <div className="space-y-3 text-sm">
                                <p>
                                    Anda akan melaporkan pengiriman untuk{' '}
                                    <strong className="text-foreground font-mono">
                                        {selectedPo?.po_number ?? '-'}
                                    </strong>{' '}
                                    tujuan <strong className="text-foreground">{selectedPo?.tenant_name ?? '-'}</strong>:
                                </p>
                                <div className="rounded-lg border bg-muted/40 p-3 text-xs space-y-1.5 text-foreground">
                                    <div className="flex justify-between gap-2">
                                        <span className="text-muted-foreground">Jalur Pengiriman:</span>
                                        <span className="font-semibold">{data.route}</span>
                                    </div>
                                    <div className="flex justify-between gap-2">
                                        <span className="text-muted-foreground">Armada / Ekspedisi:</span>
                                        <span className="font-semibold">{data.carrier_name}</span>
                                    </div>
                                    <div className="flex justify-between gap-2">
                                        <span className="text-muted-foreground">Nomor Plat Armada:</span>
                                        <span className="font-mono font-semibold">{data.vehicle_plate_number}</span>
                                    </div>
                                    <div className="flex justify-between gap-2">
                                        <span className="text-muted-foreground">Perkiraan Tanggal Tiba:</span>
                                        <span className="font-semibold">{formatDate(data.estimated_arrival_date)}</span>
                                    </div>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Sistem PAGORA akan mengubah status logistik barang menjadi{' '}
                                    <strong className="text-foreground">In Transit (Dalam Pengiriman)</strong> dan mengunci detail dokumen serta kuantitas Purchase Order secara permanen.
                                </p>
                            </div>
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={processing}>Periksa Kembali</AlertDialogCancel>
                        <LoadingButton
                            loading={processing}
                            loadingText="Melaporkan..."
                            onClick={handleConfirmSave}
                            className="cursor-pointer"
                        >
                            Ya, Konfirmasi &amp; Simpan
                        </LoadingButton>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}

