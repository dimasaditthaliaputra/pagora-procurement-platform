import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, FileMinus2, Handshake, PackageX, Truck } from 'lucide-react';
import { cn } from '@/lib/utils';
import { notify } from '@/lib/notify';
import { formatNumber, formatRupiah } from '@/lib/format';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { CurrencyInput } from '@/Components/currency-input';
import { LoadingButton } from '@/Components/loading-button';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/Components/ui/card';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';

const TYPE_ICONS = { reshipment: Truck, return: PackageX, credit_note: FileMinus2 };

const invalidClass = (error) => error && 'border-destructive focus-visible:ring-destructive';

function FieldError({ message }) {
    return message ? <p className="text-xs font-medium text-destructive">{message}</p> : null;
}

/**
 * Settlement form (UC-18 step 4–5, FR-40..42): reshipment, return of QC-rejected goods, or a credit note.
 * The document goes to the Vendor for confirmation.
 */
export default function LogSengketaPenyelesaian({ dispute: rawDispute, form: options }) {
    const dispute = rawDispute?.data ?? rawDispute;
    const invoices = options.creditable_invoices ?? [];
    const returnable = (options.returnable_items ?? []).filter((item) => item.remaining_quantity > 0);
    const today = new Date().toISOString().split('T')[0];

    const available = {
        reshipment: options.can_reship,
        return: returnable.length > 0,
        credit_note: invoices.some((invoice) => invoice.remaining_amount > 0),
    };
    const unavailableReason = {
        reshipment: 'Hanya untuk PO berstatus Diterima Sebagian.',
        return: 'Tidak ada barang ditolak QC yang tersisa untuk diretur.',
        credit_note: 'Belum ada invoice dengan sisa nilai pada PO ini.',
    };

    const { data, setData, post, processing, errors, setError, clearErrors, transform } = useForm({
        resolution_type: '',
        resolution_notes: '',
        document_date: today,
        invoice_uuid: invoices.length === 1 ? invoices[0].uuid : '',
        amount: '',
        rows: returnable.map((item) => ({
            receipt_item_uuid: item.receipt_item_uuid,
            selected: returnable.length === 1,
            quantity: returnable.length === 1 ? String(item.remaining_quantity) : '',
            notes: '',
        })),
    });

    const selectedInvoice = invoices.find((invoice) => invoice.uuid === data.invoice_uuid);
    const updateRow = (index, patch) => {
        setData('rows', data.rows.map((row, i) => (i === index ? { ...row, ...patch } : row)));
        clearErrors(`rows.${index}.quantity`, 'items');
    };

    const validate = () => {
        const found = {};
        if (!data.resolution_type) found.resolution_type = 'Pilih jenis penyelesaian.';
        if (data.resolution_notes.trim().length < 5) {
            found.resolution_notes = data.resolution_notes.trim() === '' ? 'Alasan / catatan penyelesaian wajib diisi.' : 'Alasan penyelesaian minimal 5 karakter.';
        }

        if (data.resolution_type === 'credit_note') {
            if (!data.invoice_uuid) found.invoice_uuid = 'Pilih invoice yang dipotong.';
            const amount = Number(data.amount);
            if (!data.amount || amount <= 0) found.amount = 'Nominal credit note harus lebih dari 0.';
            else if (selectedInvoice && amount > selectedInvoice.remaining_amount) {
                found.amount = `Nominal melebihi sisa nilai invoice (${formatRupiah(selectedInvoice.remaining_amount)}).`;
            }
        }

        if (data.resolution_type === 'return') {
            const chosen = data.rows.filter((row) => row.selected);
            if (chosen.length === 0) found.items = 'Pilih minimal 1 item yang diretur.';
            data.rows.forEach((row, index) => {
                if (!row.selected) return;
                const quantity = Number(row.quantity);
                if (!row.quantity || quantity <= 0) found[`rows.${index}.quantity`] = 'Kuantitas retur harus lebih dari 0.';
                else if (quantity > returnable[index].remaining_quantity) {
                    found[`rows.${index}.quantity`] = `Maksimal ${formatNumber(returnable[index].remaining_quantity)} ${returnable[index].unit}.`;
                }
            });
        }

        return found;
    };

    const submit = (event) => {
        event.preventDefault();
        clearErrors();
        const found = validate();
        if (Object.keys(found).length > 0) {
            Object.entries(found).forEach(([field, message]) => setError(field, message));
            notify.validationFailed();
            return;
        }

        transform(({ rows, ...payload }) => ({
            ...payload,
            items:
                payload.resolution_type === 'return'
                    ? rows
                          .filter((row) => row.selected)
                          .map(({ receipt_item_uuid, quantity, notes }) => ({ receipt_item_uuid, quantity, notes: notes || null }))
                    : undefined,
            invoice_uuid: payload.resolution_type === 'credit_note' ? payload.invoice_uuid : undefined,
            amount: payload.resolution_type === 'credit_note' ? payload.amount : undefined,
        }));

        post(`/log-sengketa/${dispute.uuid}/penyelesaian`, {
            preserveScroll: true,
            onError: () => notify.validationFailed(),
        });
    };

    // Server errors on items.N.* refer to the selected rows only, in order.
    const selectedIndexes = data.rows.map((row, index) => (row.selected ? index : null)).filter((index) => index !== null);
    const rowError = (index) =>
        errors[`rows.${index}.quantity`] ??
        errors[`items.${selectedIndexes.indexOf(index)}.quantity`] ??
        errors[`items.${selectedIndexes.indexOf(index)}.receipt_item_uuid`];

    const breadcrumbs = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Log Sengketa', href: '/log-sengketa' },
        { label: dispute.po_number, href: `/log-sengketa/${dispute.uuid}` },
        { label: 'Ajukan Penyelesaian' },
    ];

    return (
        <DashboardLayout breadcrumbs={breadcrumbs}>
            <Head title={`Penyelesaian Sengketa ${dispute.po_number} - PAGORA`} />

            <form onSubmit={submit} noValidate className="flex flex-col gap-5 w-full max-w-3xl">
                <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-bold tracking-tight flex items-center gap-2">
                            <Handshake className="size-5 text-primary" />
                            Ajukan Penyelesaian Sengketa
                        </h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            {dispute.po_number} · {dispute.vendor_name} · {dispute.trigger_type_label}
                        </p>
                    </div>
                    <Button variant="outline" asChild className="h-9 gap-1.5 self-start">
                        <Link href={`/log-sengketa/${dispute.uuid}`}>
                            <ArrowLeft className="size-4" />
                            Kembali
                        </Link>
                    </Button>
                </div>

                <Card className="shadow-sm">
                    <CardHeader className="border-b">
                        <CardTitle className="text-base font-semibold">Jenis Penyelesaian</CardTitle>
                        <CardDescription>Semua jenis penyelesaian wajib dikonfirmasi Vendor.</CardDescription>
                    </CardHeader>
                    <CardContent className="p-5 space-y-2">
                        <div className="grid gap-3 sm:grid-cols-3" role="radiogroup" aria-invalid={Boolean(errors.resolution_type)}>
                            {options.resolution_options.map((option) => {
                                const Icon = TYPE_ICONS[option.value];
                                const enabled = available[option.value];
                                const active = data.resolution_type === option.value;

                                return (
                                    <button
                                        key={option.value}
                                        type="button"
                                        role="radio"
                                        aria-checked={active}
                                        disabled={!enabled || processing}
                                        onClick={() => {
                                            setData('resolution_type', option.value);
                                            clearErrors('resolution_type');
                                        }}
                                        className={cn(
                                            'rounded-lg border p-3 text-left space-y-1 transition-colors cursor-pointer disabled:cursor-not-allowed disabled:opacity-50',
                                            active ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'hover:bg-muted/50',
                                            errors.resolution_type && 'border-destructive'
                                        )}
                                    >
                                        <span className="flex items-center gap-2 text-sm font-semibold">
                                            <Icon className="size-4 text-primary" />
                                            {option.label}
                                        </span>
                                        <span className="block text-xs text-muted-foreground">
                                            {enabled ? option.description : unavailableReason[option.value]}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                        <FieldError message={errors.resolution_type} />
                    </CardContent>
                </Card>

                {data.resolution_type === 'credit_note' && (
                    <Card className="shadow-sm">
                        <CardHeader className="border-b">
                            <CardTitle className="text-base font-semibold">Credit Note</CardTitle>
                            <CardDescription>Mengurangi tagihan setelah dikonfirmasi Vendor.</CardDescription>
                        </CardHeader>
                        <CardContent className="p-5 grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2 sm:col-span-2">
                                <Label htmlFor="invoice_uuid">Invoice *</Label>
                                <Select
                                    value={data.invoice_uuid}
                                    onValueChange={(value) => {
                                        setData('invoice_uuid', value);
                                        clearErrors('invoice_uuid', 'amount');
                                    }}
                                    disabled={processing}
                                >
                                    <SelectTrigger id="invoice_uuid" className={cn('w-full', invalidClass(errors.invoice_uuid))} aria-invalid={Boolean(errors.invoice_uuid)}>
                                        <SelectValue placeholder="Pilih invoice" />
                                    </SelectTrigger>
                                    <SelectContent position="popper">
                                        {invoices.map((invoice) => (
                                            <SelectItem key={invoice.uuid} value={invoice.uuid} disabled={invoice.remaining_amount <= 0}>
                                                {invoice.number} · sisa {formatRupiah(invoice.remaining_amount)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <FieldError message={errors.invoice_uuid} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="amount">Nominal Potongan *</Label>
                                <CurrencyInput
                                    id="amount"
                                    value={data.amount}
                                    disabled={processing}
                                    onValueChange={(value) => {
                                        setData('amount', value);
                                        clearErrors('amount');
                                    }}
                                    aria-invalid={Boolean(errors.amount)}
                                    className={invalidClass(errors.amount)}
                                    placeholder="0"
                                />
                                {selectedInvoice && (
                                    <p className="text-xs text-muted-foreground">
                                        Tagihan {formatRupiah(selectedInvoice.total_amount)} · sudah dipotong {formatRupiah(selectedInvoice.credited_amount)}
                                    </p>
                                )}
                                <FieldError message={errors.amount} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="document_date">Tanggal Credit Note</Label>
                                <Input
                                    id="document_date"
                                    type="date"
                                    max={today}
                                    value={data.document_date}
                                    disabled={processing}
                                    onChange={(e) => {
                                        setData('document_date', e.target.value);
                                        clearErrors('document_date');
                                    }}
                                    aria-invalid={Boolean(errors.document_date)}
                                    className={invalidClass(errors.document_date)}
                                />
                                <FieldError message={errors.document_date} />
                            </div>
                        </CardContent>
                    </Card>
                )}

                {data.resolution_type === 'return' && (
                    <Card className="shadow-sm">
                        <CardHeader className="border-b">
                            <CardTitle className="text-base font-semibold">Item Diretur</CardTitle>
                            <CardDescription>Hanya barang yang ditolak QC; barang diretur tidak ditagihkan.</CardDescription>
                        </CardHeader>
                        <CardContent className="p-5 space-y-3">
                            {returnable.map((item, index) => {
                                const row = data.rows[index];
                                const error = rowError(index);

                                return (
                                    <div key={item.receipt_item_uuid} className={cn('rounded-lg border p-3 space-y-3', row.selected && 'border-primary/40 bg-primary/5')}>
                                        <label className="flex items-start gap-2.5 cursor-pointer">
                                            <Checkbox
                                                checked={row.selected}
                                                disabled={processing}
                                                onCheckedChange={(checked) =>
                                                    updateRow(index, {
                                                        selected: checked === true,
                                                        quantity: checked === true && !row.quantity ? String(item.remaining_quantity) : row.quantity,
                                                    })
                                                }
                                                className="mt-0.5"
                                            />
                                            <span className="text-sm">
                                                <span className="font-semibold">{item.commodity_name}</span>
                                                <span className="block text-xs text-muted-foreground">
                                                    Ditolak {formatNumber(item.rejected_quantity)} {item.unit}
                                                    {item.returned_quantity > 0 && ` · sudah diretur ${formatNumber(item.returned_quantity)} ${item.unit}`}
                                                </span>
                                            </span>
                                        </label>
                                        {row.selected && (
                                            <div className="grid gap-3 sm:grid-cols-2">
                                                <div className="space-y-1.5">
                                                    <Label htmlFor={`row_qty_${index}`}>Kuantitas Retur ({item.unit}) *</Label>
                                                    <Input
                                                        id={`row_qty_${index}`}
                                                        type="number"
                                                        inputMode="decimal"
                                                        min="0"
                                                        step="any"
                                                        value={row.quantity}
                                                        disabled={processing}
                                                        onChange={(e) => updateRow(index, { quantity: e.target.value })}
                                                        aria-invalid={Boolean(error)}
                                                        className={invalidClass(error)}
                                                    />
                                                    <FieldError message={error} />
                                                </div>
                                                <div className="space-y-1.5">
                                                    <Label htmlFor={`row_notes_${index}`}>Catatan Item</Label>
                                                    <Input
                                                        id={`row_notes_${index}`}
                                                        value={row.notes}
                                                        disabled={processing}
                                                        onChange={(e) => updateRow(index, { notes: e.target.value })}
                                                        placeholder="Opsional"
                                                    />
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                            <FieldError message={errors.items} />
                            <div className="space-y-2 max-w-xs">
                                <Label htmlFor="document_date">Tanggal Retur</Label>
                                <Input
                                    id="document_date"
                                    type="date"
                                    max={today}
                                    value={data.document_date}
                                    disabled={processing}
                                    onChange={(e) => {
                                        setData('document_date', e.target.value);
                                        clearErrors('document_date');
                                    }}
                                    aria-invalid={Boolean(errors.document_date)}
                                    className={invalidClass(errors.document_date)}
                                />
                                <FieldError message={errors.document_date} />
                            </div>
                        </CardContent>
                    </Card>
                )}

                {data.resolution_type === 'reshipment' && (
                    <Alert>
                        <Truck className="size-4" />
                        <AlertDescription className="text-sm">
                            Setelah Vendor menyetujui, Vendor melaporkan pengiriman ulang lewat menu Pengiriman. PO kembali
                            Dalam Pengiriman dan item diinspeksi ulang oleh QC Gudang.
                        </AlertDescription>
                    </Alert>
                )}

                <Card className="shadow-sm">
                    <CardContent className="p-5 space-y-2">
                        <Label htmlFor="resolution_notes">Alasan / Catatan Penyelesaian *</Label>
                        <Textarea
                            id="resolution_notes"
                            rows={4}
                            value={data.resolution_notes}
                            disabled={processing}
                            onChange={(e) => {
                                setData('resolution_notes', e.target.value);
                                clearErrors('resolution_notes');
                            }}
                            placeholder="Contoh: Disepakati potongan tagihan atas 200 kg beras yang tidak memenuhi kadar air."
                            aria-invalid={Boolean(errors.resolution_notes)}
                            className={invalidClass(errors.resolution_notes)}
                        />
                        <FieldError message={errors.resolution_notes} />
                    </CardContent>
                    <CardFooter className="border-t p-5 flex justify-end gap-2">
                        <Button variant="outline" asChild disabled={processing}>
                            <Link href={`/log-sengketa/${dispute.uuid}`}>Batal</Link>
                        </Button>
                        <LoadingButton type="submit" loading={processing} loadingText="Mengirim..." className="cursor-pointer">
                            Kirim ke Vendor
                        </LoadingButton>
                    </CardFooter>
                </Card>
            </form>
        </DashboardLayout>
    );
}
