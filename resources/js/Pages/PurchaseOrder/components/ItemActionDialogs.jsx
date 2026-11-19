import { useEffect, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { notify } from '@/lib/notify';
import { formatNumber, formatRupiah } from '@/lib/format';
import { Button } from '@/Components/ui/button';
import { CurrencyInput } from '@/Components/currency-input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { LoadingButton } from '@/Components/loading-button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/Components/ui/alert-dialog';

const itemUrl = (purchaseOrderUuid, item, action) => `/purchase-order/${purchaseOrderUuid}/negosiasi/item/${item.uuid}/${action}`;

// Errors not tied to a form field (e.g. the item changed meanwhile) are shown as a toast.
function reportErrors(errors, fields) {
    const generic = Object.entries(errors).find(([key]) => !fields.includes(key));
    if (generic) {
        notify.error(generic[1]);
    } else {
        notify.validationFailed();
    }
}

function suggestionsFor(item, isTenant) {
    const round = (n) => Math.round(n / 50) * 50;
    const list = isTenant
        ? [
              item.ceiling_price && { label: 'Harga Pagu', value: round(item.ceiling_price) },
              item.ceiling_price && { label: 'Pagu −3%', value: round(item.ceiling_price * 0.97) },
              { label: 'Estimasi', value: item.estimated_unit_price },
              item.vendor_offer_price && { label: 'Tawaran Vendor', value: item.vendor_offer_price },
          ]
        : [item.proposed_price && { label: 'Ajuan Purchasing', value: item.proposed_price }];

    return list.filter(Boolean).filter((s, i, all) => all.findIndex((o) => o.value === s.value) === i);
}

export function OfferItemDialog({ purchaseOrderUuid, item, isTenant, onClose }) {
    const { data, setData, post, processing, errors, setError, clearErrors, reset } = useForm({ offer_amount: '', message: '' });
    const open = Boolean(item);

    useEffect(() => {
        if (open) {
            reset();
            clearErrors();
        }
    }, [item?.uuid]);

    if (!item) return null;

    const amount = Number(data.offer_amount);
    const submit = (e) => {
        e.preventDefault();
        if (!(amount > 0)) {
            setError('offer_amount', 'Harga satuan wajib diisi dan lebih dari 0.');
            document.getElementById('offer_amount')?.focus();
            notify.validationFailed();
            return;
        }

        post(itemUrl(purchaseOrderUuid, item, 'tawar'), {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onError: (errs) => reportErrors(errs, ['offer_amount', 'message']),
        });
    };

    return (
        <Dialog open={open} onOpenChange={(value) => !value && !processing && onClose()}>
            <DialogContent>
                <form noValidate onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>{isTenant ? 'Ajukan harga' : 'Ajukan harga lain'} · {item.name}</DialogTitle>
                        <DialogDescription>
                            Harga per {item.unit} untuk {formatNumber(item.quantity)} {item.unit}.
                            {isTenant && ' Harga Pagu dan estimasi hanya terlihat oleh pihak Anda.'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex flex-wrap gap-1.5">
                        {suggestionsFor(item, isTenant).map((suggestion) => (
                            <button
                                key={suggestion.label}
                                type="button"
                                disabled={processing}
                                onClick={() => {
                                    setData('offer_amount', String(suggestion.value));
                                    clearErrors('offer_amount');
                                }}
                                className={cn(
                                    'cursor-pointer rounded-full border px-3 py-1 text-xs transition-colors hover:bg-muted disabled:opacity-50',
                                    amount === suggestion.value && 'border-primary bg-primary/10 text-primary'
                                )}
                            >
                                {suggestion.label} · <span className="tabular-nums">{formatRupiah(suggestion.value)}</span>
                            </button>
                        ))}
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="offer_amount" className={cn('text-sm', errors.offer_amount && 'text-destructive')}>
                            Harga per {item.unit} *
                        </Label>
                        <CurrencyInput
                            id="offer_amount"
                            value={data.offer_amount}
                            onValueChange={(value) => {
                                setData('offer_amount', value);
                                clearErrors('offer_amount');
                            }}
                            disabled={processing}
                            aria-invalid={Boolean(errors.offer_amount)}
                            className={cn(errors.offer_amount && 'border-destructive focus-visible:ring-destructive')}
                        />
                        {errors.offer_amount ? (
                            <p className="text-sm text-destructive">{errors.offer_amount}</p>
                        ) : (
                            amount > 0 && (
                                <p className="text-xs text-muted-foreground">
                                    Subtotal {formatRupiah(amount * item.quantity)}
                                </p>
                            )
                        )}
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="offer_message" className={cn('text-sm', errors.message && 'text-destructive')}>
                            Catatan
                        </Label>
                        <Textarea
                            id="offer_message"
                            rows={2}
                            placeholder="Alasan atau keterangan tawaran (opsional)"
                            value={data.message}
                            onChange={(e) => {
                                setData('message', e.target.value);
                                clearErrors('message');
                            }}
                            disabled={processing}
                            aria-invalid={Boolean(errors.message)}
                            className={cn(errors.message && 'border-destructive focus-visible:ring-destructive')}
                        />
                        {errors.message && <p className="text-sm text-destructive">{errors.message}</p>}
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={onClose} disabled={processing}>
                            Batal
                        </Button>
                        <LoadingButton type="submit" loading={processing} loadingText="Mengirim...">
                            Kirim tawaran
                        </LoadingButton>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function AcceptItemDialog({ purchaseOrderUuid, item, isTenant, onClose }) {
    const [processing, setProcessing] = useState(false);

    if (!item) return null;

    const price = isTenant ? item.vendor_offer_price : item.proposed_price;
    const accept = () => {
        router.post(itemUrl(purchaseOrderUuid, item, 'setujui'), {}, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onSuccess: () => onClose(),
            onError: (errs) => reportErrors(errs, []),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <AlertDialog open onOpenChange={(value) => !value && !processing && onClose()}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Setujui harga {item.name}?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Harga <strong className="text-foreground">{formatRupiah(price)}/{item.unit}</strong> (subtotal{' '}
                        {formatRupiah(price * item.quantity)}) menjadi harga final item ini dan tidak dapat diubah. Bila semua item
                        tersisa sudah disepakati, Purchase Order langsung dikunci.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={processing}>Batal</AlertDialogCancel>
                    <LoadingButton loading={processing} loadingText="Memproses..." onClick={accept}>
                        Ya, setujui
                    </LoadingButton>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

export function RemoveItemDialog({ purchaseOrderUuid, item, onClose }) {
    const { data, setData, post, processing, errors, clearErrors } = useForm({ reason: '' });

    if (!item) return null;

    const remove = () => {
        post(itemUrl(purchaseOrderUuid, item, 'keluarkan'), {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onError: (errs) => reportErrors(errs, ['reason']),
        });
    };

    return (
        <AlertDialog open onOpenChange={(value) => !value && !processing && onClose()}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Keluarkan {item.name} dari PO?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Item yang harganya tidak disepakati dikeluarkan dari pesanan; item lain tetap dinegosiasikan. Bila tidak ada
                        item tersisa, Purchase Order otomatis dibatalkan.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <div className="grid gap-1.5">
                    <Label htmlFor="remove_reason" className={cn('text-sm', errors.reason && 'text-destructive')}>
                        Alasan
                    </Label>
                    <Textarea
                        id="remove_reason"
                        rows={2}
                        placeholder="Mis. harga vendor di atas anggaran (opsional)"
                        value={data.reason}
                        onChange={(e) => {
                            setData('reason', e.target.value);
                            clearErrors('reason');
                        }}
                        disabled={processing}
                        aria-invalid={Boolean(errors.reason)}
                        className={cn(errors.reason && 'border-destructive focus-visible:ring-destructive')}
                    />
                    {errors.reason && <p className="text-sm text-destructive">{errors.reason}</p>}
                </div>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={processing}>Batal</AlertDialogCancel>
                    <LoadingButton variant="destructive" loading={processing} loadingText="Memproses..." onClick={remove}>
                        Ya, keluarkan
                    </LoadingButton>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
