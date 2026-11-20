import { useState } from 'react';
import { router } from '@inertiajs/react';
import { CheckCircle2, CreditCard } from 'lucide-react';
import { formatDate, formatRupiah } from '@/lib/format';
import { notify } from '@/lib/notify';
import { LoadingButton } from '@/Components/loading-button';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';

const PAYMENT_STATUS_VARIANTS = {
    pending: 'warning',
    paid: 'green',
    failed: 'destructive',
};

function csrfHeaders() {
    const cookie = document.cookie.split('; ').find((row) => row.startsWith('XSRF-TOKEN='));

    return {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': cookie ? decodeURIComponent(cookie.split('=')[1]) : '',
    };
}

function loadSnap(url, clientKey) {
    if (window.snap) return Promise.resolve(window.snap);

    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = url;
        script.setAttribute('data-client-key', clientKey);
        script.onload = () => resolve(window.snap);
        script.onerror = () => reject(new Error('Snap gagal dimuat.'));
        document.body.appendChild(script);
    });
}

function SummaryRow({ label, value, strong = false }) {
    return (
        <div className="flex items-center justify-between gap-3 text-sm">
            <span className="text-muted-foreground">{label}</span>
            <span className={`tabular-nums ${strong ? 'text-base font-bold' : 'font-medium'}`}>{value}</span>
        </div>
    );
}

/**
 * Payment of a matched invoice through Midtrans Snap (UC-17 steps 5–7, FR-29, FR-39).
 * The bill is reduced by confirmed credit notes (BR-26); the Snap popup handles the payment itself.
 */
export function PembayaranCard({ invoice, canPay, remainingAmount }) {
    const payments = invoice.payments ?? [];
    const paidTotal = Number(invoice.paid_total ?? 0);
    const creditTotal = Number(invoice.confirmed_credit_total ?? 0);
    const isSettled = Number(remainingAmount) < 1;
    const [processing, setProcessing] = useState(false);

    const refresh = () => router.reload({ preserveScroll: true });

    const syncStatus = async () => {
        try {
            await fetch(`/pembayaran/${invoice.uuid}/sinkron`, { method: 'POST', headers: csrfHeaders(), credentials: 'same-origin' });
        } finally {
            refresh();
        }
    };

    const startPayment = async () => {
        setProcessing(true);

        try {
            const response = await fetch(`/pembayaran/${invoice.uuid}`, { method: 'POST', headers: csrfHeaders(), credentials: 'same-origin' });
            const body = await response.json();

            if (!response.ok || !body.success) {
                notify.error(body.message ?? Object.values(body.errors ?? {})[0]?.[0] ?? 'Pembayaran belum dapat dimulai.');
                return;
            }

            const snap = await loadSnap(body.data.snap_url, body.data.client_key);
            snap.pay(body.data.snap_token, {
                onSuccess: syncStatus,
                onPending: syncStatus,
                onError: syncStatus,
                onClose: syncStatus,
            });
        } catch {
            notify.error('Pembayaran belum dapat dimulai. Periksa koneksi Anda lalu coba lagi.');
        } finally {
            setProcessing(false);
        }
    };

    return (
        <Card className="shadow-xs">
            <CardHeader className="border-b px-5 py-3.5">
                <CardTitle className="text-base font-semibold flex items-center gap-2">
                    <CreditCard className="size-4 text-primary" />
                    Pembayaran
                </CardTitle>
                <CardDescription className="text-xs">
                    Jumlah dibayar = tagihan dikurangi credit note yang dikonfirmasi Vendor.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4 p-5">
                <div className="space-y-1.5">
                    <SummaryRow label="Total tagihan" value={formatRupiah(invoice.jumlah_tagihan)} />
                    {creditTotal > 0 && <SummaryRow label="Credit note terkonfirmasi" value={`− ${formatRupiah(creditTotal)}`} />}
                    {paidTotal > 0 && <SummaryRow label="Sudah dibayar" value={`− ${formatRupiah(paidTotal)}`} />}
                    <div className="border-t pt-2">
                        <SummaryRow label="Sisa yang harus dibayar" value={formatRupiah(remainingAmount)} strong />
                    </div>
                </div>

                {isSettled ? (
                    <div className="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-300">
                        <CheckCircle2 className="size-4 shrink-0" />
                        Tagihan ini sudah lunas.
                    </div>
                ) : (
                    canPay && (
                        <LoadingButton loading={processing} loadingText="Menyiapkan pembayaran..." onClick={startPayment} className="w-full gap-1.5 cursor-pointer">
                            <CreditCard className="size-4" />
                            Bayar Sekarang
                        </LoadingButton>
                    )
                )}

                {payments.length > 0 && (
                    <ul className="space-y-2">
                        {payments.map((payment) => (
                            <li key={payment.uuid} className="rounded-lg border p-3 text-sm space-y-1">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <span className="font-mono font-semibold">{payment.number}</span>
                                    <Badge variant={PAYMENT_STATUS_VARIANTS[payment.status] ?? 'gray'}>{payment.status_label}</Badge>
                                </div>
                                <p className="text-lg font-bold tabular-nums">{formatRupiah(payment.amount)}</p>
                                <p className="text-xs text-muted-foreground">
                                    {payment.method ? `${payment.method.replaceAll('_', ' ')} · ` : ''}
                                    {formatDate(payment.paid_at ?? payment.created_at)}
                                    {payment.reference_number ? ` · Ref. ${payment.reference_number}` : ''}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

export default PembayaranCard;
