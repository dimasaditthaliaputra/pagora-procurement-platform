import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Head, Link, useForm, useHttp, usePage } from '@inertiajs/react';
import { ArrowLeft, Ban, Lock, MessagesSquare, Send, Wifi, WifiOff } from 'lucide-react';
import { cn } from '@/lib/utils';
import { notify } from '@/lib/notify';
import { formatRupiah } from '@/lib/format';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { LoadingButton } from '@/Components/loading-button';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { StatusBadge } from '@/Pages/PurchaseOrder/components/StatusBadge';
import { NegotiationMessage } from '@/Pages/PurchaseOrder/components/NegotiationMessage';
import { NegotiationItemCard } from '@/Pages/PurchaseOrder/components/NegotiationItemCard';
import { AcceptItemDialog, OfferItemDialog, RemoveItemDialog } from '@/Pages/PurchaseOrder/components/ItemActionDialogs';
import { useNegotiationPolling } from '@/hooks/use-negotiation-polling';

const MESSAGE_MAX = 1000;
const NEAR_BOTTOM_PX = 120;

function mergeMessages(current, incoming) {
    if (!incoming?.length) return current;
    const known = new Set(current.map((message) => message.uuid));
    const fresh = incoming.filter((message) => !known.has(message.uuid));
    return fresh.length ? [...current, ...fresh] : current;
}

// Polls skip Harga Pagu (computed only on page load); keep the values the page already has.
function mergeState(current, incoming) {
    const ceilings = Object.fromEntries(current.items.map((item) => [item.uuid, item.ceiling_price]));
    return {
        ...incoming,
        items: incoming.items.map((item) =>
            item.ceiling_price === null && ceilings[item.uuid] !== undefined ? { ...item, ceiling_price: ceilings[item.uuid] } : item
        ),
    };
}

function failureMessage(response) {
    if (response?.status === 429) return 'Terlalu banyak pesan dalam waktu singkat. Coba lagi sebentar lagi.';
    if (response?.status === 419) return 'Sesi halaman berakhir. Muat ulang halaman lalu coba lagi.';
    return response?.data?.message || 'Pesan gagal terkirim.';
}

/**
 * Sends chat messages as plain JSON requests (no page visit, so no reload of the whole room).
 * A message shows up at once as "pending" and is swapped for the stored one when the server
 * answers; rapid messages go out one at a time, in order.
 */
function MessageComposer({ purchaseOrderUuid, side, onPending, onSent, onFailed }) {
    const { user, auth } = usePage().props;
    const http = useHttp({ message: '' });
    const [text, setText] = useState('');
    const [error, setError] = useState('');
    const queue = useRef([]);
    const draining = useRef(false);

    const sendOne = (item) =>
        new Promise((resolve) => {
            http.transform(() => ({ message: item.text }));
            http
                .post(`/purchase-order/${purchaseOrderUuid}/negosiasi/pesan`, {
                    onSuccess: (body) => resolve({ message: body.data }),
                    onError: (errors) => resolve({ error: Object.values(errors)[0] ?? 'Pesan gagal terkirim.' }),
                    onHttpException: (response) => resolve({ error: failureMessage(response) }),
                    onNetworkError: () => resolve({ error: 'Koneksi terputus. Pesan belum terkirim.' }),
                })
                .catch(() => resolve({ error: 'Pesan gagal terkirim.' }));
        });

    const drain = async () => {
        if (draining.current) return;
        draining.current = true;

        while (queue.current.length > 0) {
            const item = queue.current.shift();
            const result = await sendOne(item);

            if (result.message) {
                onSent(item.tempId, result.message);
            } else {
                onFailed(item.tempId);
                setText((current) => current || item.text);
                notify.error(result.error);
            }
        }

        draining.current = false;
    };

    const send = (e) => {
        e?.preventDefault();
        const message = text.trim();

        if (!message) {
            setError('Pesan tidak boleh kosong.');
            notify.validationFailed();
            return;
        }
        if (message.length > MESSAGE_MAX) {
            setError(`Pesan maksimal ${MESSAGE_MAX} karakter.`);
            notify.validationFailed();
            return;
        }

        const tempId = `pending-${Date.now()}-${Math.random().toString(36).slice(2)}`;
        onPending({
            uuid: tempId,
            type: 'text',
            message,
            offer_amount: null,
            item: null,
            pending: true,
            sender: { name: user?.name ?? 'Anda', side, role_label: auth?.role?.label ?? null, is_me: true },
            created_at: new Date().toISOString(),
        });
        queue.current.push({ tempId, text: message });
        setText('');
        setError('');
        drain();
    };

    return (
        <form noValidate onSubmit={send} className="space-y-1.5">
            <Label htmlFor="chat_message" className="sr-only">
                Tulis pesan
            </Label>
            <div className="flex items-end gap-2">
                <Textarea
                    id="chat_message"
                    rows={2}
                    placeholder="Tulis pesan... (Enter untuk kirim, Shift+Enter untuk baris baru)"
                    value={text}
                    onChange={(e) => {
                        setText(e.target.value);
                        setError('');
                    }}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter' && !e.shiftKey && !e.nativeEvent.isComposing) {
                            e.preventDefault();
                            send();
                        }
                    }}
                    aria-invalid={Boolean(error)}
                    className={cn('min-h-10 resize-none', error && 'border-destructive focus-visible:ring-destructive')}
                />
                <Button type="submit" className="h-10 shrink-0" aria-label="Kirim pesan">
                    <Send className="size-4" />
                </Button>
            </div>
            <div className="flex justify-between text-xs">
                <span className="text-destructive">{error}</span>
                <span className={cn('tabular-nums text-muted-foreground', text.length > MESSAGE_MAX && 'text-destructive')}>
                    {text.length}/{MESSAGE_MAX}
                </span>
            </div>
        </form>
    );
}

function CancelPurchaseOrderDialog({ purchaseOrder, open, onOpenChange }) {
    const { data, setData, post, processing, errors, setError, clearErrors, reset } = useForm({ alasan: '' });

    const cancel = () => {
        if (data.alasan.trim().length < 5) {
            setError('alasan', 'Alasan pembatalan wajib diisi (minimal 5 karakter).');
            document.getElementById('alasan_pembatalan')?.focus();
            notify.validationFailed();
            return;
        }

        post(`/purchase-order/${purchaseOrder.uuid}/batalkan`, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
            onError: () => notify.validationFailed(),
        });
    };

    return (
        <AlertDialog open={open} onOpenChange={(value) => !processing && onOpenChange(value)}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Batalkan Purchase Order?</AlertDialogTitle>
                    <AlertDialogDescription>
                        <strong className="text-foreground">{purchaseOrder.nomor_po}</strong> akan berstatus Dibatalkan dan ruang
                        negosiasi ditutup. Riwayat chat tetap tersimpan permanen sebagai jejak audit.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <div className="grid gap-1.5">
                    <Label htmlFor="alasan_pembatalan" className={cn('text-sm', errors.alasan && 'text-destructive')}>
                        Alasan Pembatalan *
                    </Label>
                    <Textarea
                        id="alasan_pembatalan"
                        rows={3}
                        placeholder="Mis. penyesuaian anggaran atau perubahan kebutuhan produksi"
                        value={data.alasan}
                        onChange={(e) => {
                            setData('alasan', e.target.value);
                            clearErrors('alasan');
                        }}
                        disabled={processing}
                        aria-invalid={Boolean(errors.alasan)}
                        className={cn(errors.alasan && 'border-destructive focus-visible:ring-destructive')}
                    />
                    {errors.alasan && <p className="text-sm text-destructive">{errors.alasan}</p>}
                </div>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={processing}>Kembali</AlertDialogCancel>
                    <LoadingButton variant="destructive" loading={processing} loadingText="Membatalkan..." onClick={cancel}>
                        Ya, batalkan PO
                    </LoadingButton>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

export default function PurchaseOrderNegosiasi({ negotiation: initialNegotiation, messages: initialMessages, poll_interval_ms: pollIntervalMs }) {
    const [negotiation, setNegotiation] = useState(initialNegotiation);
    const [messages, setMessages] = useState(initialMessages);
    const [dialog, setDialog] = useState({ type: null, item: null });
    const [cancelOpen, setCancelOpen] = useState(false);
    const scrollRef = useRef(null);
    const stickToBottom = useRef(true);

    // Inertia reloads props after every action; they are the source of truth when they change.
    useEffect(() => setNegotiation(initialNegotiation), [initialNegotiation]);
    useEffect(() => setMessages((current) => mergeMessages(initialMessages, current.filter((m) => !initialMessages.some((i) => i.uuid === m.uuid)))), [initialMessages]);

    const isTenant = negotiation.side === 'tenant';
    const purchaseOrderUuid = negotiation.uuid;

    const addPending = useCallback((message) => {
        stickToBottom.current = true;
        setMessages((current) => [...current, message]);
    }, []);
    // Swap the pending copy for the stored message (or drop it if polling already delivered the stored one).
    const confirmSent = useCallback(
        (tempId, stored) =>
            setMessages((current) =>
                current.some((message) => message.uuid === stored.uuid)
                    ? current.filter((message) => message.uuid !== tempId)
                    : current.map((message) => (message.uuid === tempId ? stored : message))
            ),
        []
    );
    const dropPending = useCallback((tempId) => setMessages((current) => current.filter((message) => message.uuid !== tempId)), []);

    const { connected } = useNegotiationPolling({
        url: `/purchase-order/${purchaseOrderUuid}/negosiasi/pembaruan`,
        intervalMs: pollIntervalMs,
        enabled: negotiation.is_open,
        getCursor: () => messages.findLast((message) => !message.pending)?.uuid ?? null,
        onUpdate: useCallback((data) => {
            setMessages((current) => mergeMessages(current, data.messages));
            setNegotiation((current) => {
                const next = mergeState(current, data.negotiation);
                if (current.is_open && !next.is_open) {
                    notify.info(next.status === 'disepakati' ? 'Semua item telah disepakati. PO dikunci.' : 'Negosiasi ditutup.');
                }
                return next;
            });
        }, []),
    });

    useEffect(() => {
        const box = scrollRef.current;
        if (box && stickToBottom.current) box.scrollTop = box.scrollHeight;
    }, [messages.length]);

    const ceilingByItem = useMemo(
        () => (isTenant ? Object.fromEntries(negotiation.items.map((item) => [item.uuid, item.ceiling_price])) : {}),
        [isTenant, negotiation.items]
    );

    const activeItems = negotiation.items.filter((item) => item.status !== 'removed');
    const agreedCount = activeItems.filter((item) => item.status === 'agreed').length;
    const busy = dialog.type !== null;
    const openDialog = (type) => (item) => setDialog({ type, item });
    const closeDialog = () => setDialog({ type: null, item: null });

    const breadcrumbs = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Purchase Order', href: '/purchase-order' },
        { label: negotiation.nomor_po, href: `/purchase-order/${purchaseOrderUuid}` },
        { label: 'Negosiasi' },
    ];

    return (
        <DashboardLayout breadcrumbs={breadcrumbs}>
            <Head title={`Negosiasi ${negotiation.nomor_po} - PAGORA`} />

            <div className="flex w-full flex-col gap-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <div>
                        <h1 className="flex flex-wrap items-center gap-2 text-xl font-bold tracking-tight">
                            <MessagesSquare className="size-5 text-primary" />
                            Smart Negotiation Chat
                            <StatusBadge po={negotiation} showLock />
                        </h1>
                        <p className="mt-1 text-xs text-muted-foreground">
                            <span className="font-mono font-medium">{negotiation.nomor_po}</span> · {negotiation.counterparty}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {negotiation.is_open && (
                            <span
                                className={cn(
                                    'inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs',
                                    connected ? 'text-muted-foreground' : 'border-amber-300 text-amber-700 dark:text-amber-400'
                                )}
                                role="status"
                            >
                                {connected ? <Wifi className="size-3.5" /> : <WifiOff className="size-3.5" />}
                                {connected ? 'Diperbarui otomatis' : 'Koneksi terputus, mencoba lagi...'}
                            </span>
                        )}
                        {negotiation.can.cancel && (
                            <Button size="sm" variant="ghost" className="h-8 text-destructive hover:text-destructive" onClick={() => setCancelOpen(true)}>
                                <Ban className="mr-1.5 size-3.5" />
                                Batalkan PO
                            </Button>
                        )}
                        <Button variant="outline" size="sm" asChild className="h-8">
                            <Link href={`/purchase-order/${purchaseOrderUuid}`}>
                                <ArrowLeft className="mr-1.5 size-3.5" />
                                Detail PO
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid grid-cols-1 items-start gap-4 lg:grid-cols-3">
                    <Card className="flex flex-col overflow-hidden shadow-xs lg:col-span-2">
                        <CardContent className="flex flex-col p-0">
                            <div
                                ref={scrollRef}
                                onScroll={(e) => {
                                    const box = e.currentTarget;
                                    stickToBottom.current = box.scrollHeight - box.scrollTop - box.clientHeight < NEAR_BOTTOM_PX;
                                }}
                                className="h-[min(60vh,560px)] space-y-4 overflow-y-auto bg-muted/20 p-5"
                                aria-live="polite"
                                aria-label="Riwayat negosiasi"
                            >
                                {messages.length === 0 && (
                                    <p className="py-8 text-center text-sm text-muted-foreground">Belum ada pesan.</p>
                                )}
                                {messages.map((message) => (
                                    <NegotiationMessage key={message.uuid} message={message} ceilingByItem={ceilingByItem} />
                                ))}
                            </div>

                            <div className="border-t bg-card p-4">
                                {negotiation.can.send_message ? (
                                    <MessageComposer
                                        purchaseOrderUuid={purchaseOrderUuid}
                                        side={negotiation.side}
                                        onPending={addPending}
                                        onSent={confirmSent}
                                        onFailed={dropPending}
                                    />
                                ) : negotiation.status === 'dibatalkan' ? (
                                    <Alert variant="destructive">
                                        <Ban className="size-4" />
                                        <AlertTitle>Purchase Order dibatalkan</AlertTitle>
                                        <AlertDescription>
                                            {negotiation.alasan_pembatalan ? `Alasan: ${negotiation.alasan_pembatalan}. ` : ''}
                                            Riwayat negosiasi tetap tersimpan sebagai jejak audit.
                                        </AlertDescription>
                                    </Alert>
                                ) : (
                                    <Alert>
                                        <Lock className="size-4" />
                                        <AlertTitle>Negosiasi selesai, harga terkunci</AlertTitle>
                                        <AlertDescription>Chat bersifat hanya-baca sebagai jejak audit permanen.</AlertDescription>
                                    </Alert>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="shadow-xs">
                        <CardHeader className="border-b px-5 py-3.5">
                            <CardTitle className="text-base font-semibold">Item Pesanan</CardTitle>
                            <CardDescription className="text-xs">
                                {agreedCount} dari {activeItems.length} item disepakati
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3 p-4">
                            <ul className="space-y-2.5">
                                {negotiation.items.map((item) => (
                                    <NegotiationItemCard
                                        key={item.uuid}
                                        item={item}
                                        isTenant={isTenant}
                                        busy={busy}
                                        onOffer={openDialog('offer')}
                                        onAccept={openDialog('accept')}
                                        onRemove={openDialog('remove')}
                                    />
                                ))}
                            </ul>
                            {agreedCount > 0 && (
                                <div className="flex justify-between border-t pt-3 text-sm font-semibold">
                                    <span>Total disepakati</span>
                                    <span className="tabular-nums text-primary">{formatRupiah(negotiation.total_agreed)}</span>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>

            <OfferItemDialog
                purchaseOrderUuid={purchaseOrderUuid}
                item={dialog.type === 'offer' ? dialog.item : null}
                isTenant={isTenant}
                onClose={closeDialog}
            />
            {dialog.type === 'accept' && (
                <AcceptItemDialog purchaseOrderUuid={purchaseOrderUuid} item={dialog.item} isTenant={isTenant} onClose={closeDialog} />
            )}
            {dialog.type === 'remove' && <RemoveItemDialog purchaseOrderUuid={purchaseOrderUuid} item={dialog.item} onClose={closeDialog} />}
            {negotiation.can.cancel && (
                <CancelPurchaseOrderDialog purchaseOrder={negotiation} open={cancelOpen} onOpenChange={setCancelOpen} />
            )}
        </DashboardLayout>
    );
}
