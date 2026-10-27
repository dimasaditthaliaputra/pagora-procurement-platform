import { useEffect, useRef, useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Ban,
    Building2,
    CheckCircle2,
    Clock,
    Eye,
    Handshake,
    Lock,
    MessagesSquare,
    Send,
    Sparkles,
    TrendingDown,
    TrendingUp,
    UserRound,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { notify } from '@/lib/notify';
import { formatDate, formatNumber, formatRupiah, formatTime } from '@/lib/format';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
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
import { PoNotFound } from '@/Pages/PurchaseOrder/components/PoNotFound';
import { tawaranTerakhir, totalPagu, totalPo } from '@/Pages/PurchaseOrder/constants';
import {
    CURRENT_BUYER,
    addChat,
    findPurchaseOrder,
    simulateRequest,
    updatePurchaseOrder,
    vendorContact,
} from '@/Pages/PurchaseOrder/mock-data';

function buildRekomendasi(po) {
    const pagu = totalPagu(po.items);
    const awal = totalPo(po.items);
    const round = (n) => Math.round(n / 1000) * 1000;

    return [
        { key: 'pagu-5', label: 'Pagu −5%', hint: 'Agresif', nominal: round(pagu * 0.95) },
        { key: 'pagu-3', label: 'Pagu −3%', hint: 'Seimbang', nominal: round(pagu * 0.97) },
        { key: 'pagu', label: 'Harga Pagu', hint: 'Batas atas wajar', nominal: round(pagu) },
        { key: 'awal', label: 'Pengajuan Awal', hint: 'Sesuai draf PO', nominal: round(awal) },
    ];
}

function Selisih({ nominal, pagu }) {
    if (!pagu) return null;
    const persen = ((nominal - pagu) / pagu) * 100;
    const atas = persen > 0;
    const Icon = atas ? TrendingUp : TrendingDown;

    return (
        <span className={cn('inline-flex items-center gap-1 text-xs', atas ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400')}>
            <Icon className="size-3" />
            {Math.abs(persen).toFixed(1)}% {atas ? 'di atas' : 'di bawah'} pagu
        </span>
    );
}

function ChatBubble({ message, mine, pagu }) {
    const vendor = message.pengirim.peran === 'vendor';
    const Icon = vendor ? Building2 : UserRound;

    return (
        <div className={cn('flex gap-2.5 max-w-[85%]', mine ? 'ml-auto flex-row-reverse' : 'mr-auto')}>
            <div className={cn('size-8 shrink-0 rounded-full flex items-center justify-center border', vendor ? 'bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-400' : 'bg-primary/10 text-primary')}>
                <Icon className="size-4" />
            </div>
            <div className={cn('flex flex-col gap-1', mine && 'items-end')}>
                <span className="text-xs text-muted-foreground">
                    {message.pengirim.nama} · {formatTime(message.created_at)}
                </span>
                <div className={cn('rounded-2xl border px-4 py-3 space-y-1.5', mine ? 'bg-primary text-primary-foreground border-primary rounded-tr-sm' : 'bg-card rounded-tl-sm')}>
                    {message.nominal_tawar !== null && message.nominal_tawar !== undefined && (
                        <div>
                            <p className={cn('text-[11px] uppercase tracking-wide', mine ? 'text-primary-foreground/70' : 'text-muted-foreground')}>
                                Harga diajukan
                            </p>
                            <p className="text-lg font-bold tabular-nums">{formatRupiah(message.nominal_tawar)}</p>
                        </div>
                    )}
                    {message.pesan_teks && <p className="text-sm whitespace-pre-line">{message.pesan_teks}</p>}
                </div>
                {message.nominal_tawar !== null && message.nominal_tawar !== undefined && (
                    <Selisih nominal={message.nominal_tawar} pagu={pagu} />
                )}
            </div>
        </div>
    );
}

function SystemNote({ icon: Icon, children, tone = 'default' }) {
    return (
        <div className="flex justify-center">
            <span className={cn(
                'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs',
                tone === 'success' && 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-400',
                tone === 'danger' && 'border-destructive/40 bg-destructive/10 text-destructive',
                tone === 'default' && 'bg-muted text-muted-foreground'
            )}>
                <Icon className="size-3" />
                {children}
            </span>
        </div>
    );
}

function TawaranForm({ title, description, submitLabel, rekomendasi, pagu, onSubmit, onCancel }) {
    const [processing, setProcessing] = useState(false);
    const { data, setData, errors, setError, clearErrors, reset } = useForm({ nominal_tawar: '', pesan_teks: '' });

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!(Number(data.nominal_tawar) > 0)) {
            setError('nominal_tawar', 'Nominal harga wajib diisi dan lebih dari 0.');
            document.getElementById('nominal_tawar')?.focus();
            notify.validationFailed();
            return;
        }
        setProcessing(true);
        await onSubmit({ nominal_tawar: Number(data.nominal_tawar), pesan_teks: data.pesan_teks.trim() || null });
        setProcessing(false);
        reset();
    };

    return (
        <form noValidate onSubmit={handleSubmit} className="space-y-3">
            <div>
                <p className="text-sm font-semibold">{title}</p>
                {description && <p className="text-xs text-muted-foreground">{description}</p>}
            </div>

            {rekomendasi && (
                <div className="flex flex-wrap gap-1.5">
                    {rekomendasi.map((r) => (
                        <button
                            key={r.key}
                            type="button"
                            disabled={processing}
                            onClick={() => { setData('nominal_tawar', String(r.nominal)); clearErrors('nominal_tawar'); }}
                            className={cn(
                                'rounded-full border px-3 py-1 text-xs transition-colors hover:bg-muted cursor-pointer disabled:opacity-50',
                                Number(data.nominal_tawar) === r.nominal && 'border-primary bg-primary/10 text-primary'
                            )}
                        >
                            {r.label} · <span className="tabular-nums">{formatRupiah(r.nominal)}</span>
                        </button>
                    ))}
                </div>
            )}

            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div className="grid gap-1.5">
                    <Label htmlFor="nominal_tawar" className={cn('text-xs', errors.nominal_tawar && 'text-destructive')}>
                        Total Harga (Rp) <span className="text-destructive">*</span>
                    </Label>
                    <Input
                        id="nominal_tawar"
                        type="number"
                        inputMode="numeric"
                        min="0"
                        value={data.nominal_tawar}
                        onChange={(e) => { setData('nominal_tawar', e.target.value); clearErrors('nominal_tawar'); }}
                        aria-invalid={Boolean(errors.nominal_tawar)}
                        disabled={processing}
                        className={cn('h-9 tabular-nums', errors.nominal_tawar && 'border-destructive focus-visible:ring-destructive')}
                    />
                    {errors.nominal_tawar ? (
                        <p className="text-xs font-medium text-destructive">{errors.nominal_tawar}</p>
                    ) : (
                        Number(data.nominal_tawar) > 0 && <Selisih nominal={Number(data.nominal_tawar)} pagu={pagu} />
                    )}
                </div>
                <div className="sm:col-span-2 grid gap-1.5">
                    <Label htmlFor="pesan_teks" className="text-xs">Pesan</Label>
                    <Textarea
                        id="pesan_teks"
                        rows={2}
                        placeholder="Alasan atau keterangan harga (opsional)"
                        value={data.pesan_teks}
                        onChange={(e) => setData('pesan_teks', e.target.value)}
                        disabled={processing}
                        className="min-h-9"
                    />
                </div>
            </div>

            <div className="flex justify-end gap-2">
                {onCancel && (
                    <Button type="button" variant="ghost" size="sm" onClick={onCancel} disabled={processing} className="h-9">
                        Batal
                    </Button>
                )}
                <LoadingButton type="submit" size="sm" loading={processing} loadingText="Mengirim..." className="h-9 gap-1.5">
                    <Send className="size-3.5" />
                    {submitLabel}
                </LoadingButton>
            </div>
        </form>
    );
}

export default function PurchaseOrderNegosiasi({ uuid }) {
    // TODO(backend): receive `purchase_order` + `chat` props; actions become router.post calls.
    const [po, setPo] = useState(() => findPurchaseOrder(uuid));
    // Prototype-only switch so one person can walk through both sides of the flow.
    const [peran, setPeran] = useState('buyer');
    const [mode, setMode] = useState(null);
    const [aksi, setAksi] = useState(null);
    const [batalOpen, setBatalOpen] = useState(false);
    const endRef = useRef(null);

    useEffect(() => {
        endRef.current?.scrollIntoView({ behavior: 'smooth', block: 'end' });
    }, [po?.chat?.length, po?.status]);

    if (!po) return <PoNotFound />;
    if (po.status === 'draft') return <PoNotFound message={`${po.nomor_po} masih draf. Konfirmasi PO terlebih dahulu untuk membuka negosiasi.`} />;

    const pagu = totalPagu(po.items);
    const rekomendasi = buildRekomendasi(po);
    const terakhir = tawaranTerakhir(po);
    const aktif = po.status === 'negosiasi';
    const giliranVendor = aktif && terakhir?.pengirim.peran === 'buyer';
    const giliranBuyer = aktif && (!terakhir || terakhir.pengirim.peran === 'vendor');
    const pengirim = peran === 'buyer' ? CURRENT_BUYER : vendorContact(po);

    const run = async (key, fn) => {
        setAksi(key);
        await simulateRequest();
        setPo(fn());
        setAksi(null);
        setMode(null);
    };

    const kirimTawaran = async (payload) => {
        await simulateRequest();
        setPo(addChat(po.uuid, { pengirim, ...payload }));
        setMode(null);
        notify.success('Harga berhasil diajukan.');
    };

    const setujui = () => run('setujui', () => {
        addChat(po.uuid, {
            pengirim,
            pesan_teks: peran === 'buyer' ? 'Setuju dengan harga yang diajukan vendor.' : 'Harga disetujui.',
            nominal_tawar: terakhir.nominal_tawar,
        });
        notify.success('Harga disepakati. Dokumen PO dikunci.');
        return updatePurchaseOrder(po.uuid, { status: 'disepakati' });
    });

    const batalkan = () => run('batal', () => {
        setBatalOpen(false);
        notify.success(`${po.nomor_po} dibatalkan.`);
        return updatePurchaseOrder(po.uuid, { status: 'dibatalkan' });
    });

    const breadcrumbs = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Purchase Order', href: '/purchase-order' },
        { label: po.nomor_po, href: `/purchase-order/${po.uuid}` },
        { label: 'Negosiasi', href: '#' },
    ];

    const renderAksi = () => {
        if (po.status === 'disepakati' || (!aktif && po.status !== 'dibatalkan')) {
            return (
                <Alert>
                    <Lock className="size-4" />
                    <AlertTitle>Negosiasi selesai, harga terkunci</AlertTitle>
                    <AlertDescription>Chat hanya dapat dilihat sebagai jejak audit dan tidak bisa dikirimi pesan baru.</AlertDescription>
                </Alert>
            );
        }

        if (po.status === 'dibatalkan') {
            return (
                <Alert variant="destructive">
                    <Ban className="size-4" />
                    <AlertTitle>Purchase order dibatalkan</AlertTitle>
                    <AlertDescription>Riwayat negosiasi tetap disimpan sebagai jejak audit.</AlertDescription>
                </Alert>
            );
        }

        if (mode === 'tawar') {
            const buyer = peran === 'buyer';
            return (
                <TawaranForm
                    title={buyer ? (terakhir ? 'Ajukan Ulang Harga' : 'Ajukan Harga Purchase Order') : 'Tolak & Ajukan Harga Baru'}
                    description={buyer ? 'Pilih rekomendasi atau isi nominal sendiri. Harga Pagu hanya acuan.' : 'Ajukan total harga yang bisa Anda penuhi.'}
                    submitLabel="Kirim Penawaran"
                    rekomendasi={buyer ? rekomendasi : null}
                    pagu={pagu}
                    onSubmit={kirimTawaran}
                    onCancel={terakhir ? () => setMode(null) : null}
                />
            );
        }

        const menunggu = (peran === 'buyer' && giliranVendor) || (peran === 'vendor' && giliranBuyer);
        if (menunggu) {
            return (
                <div className="flex items-center gap-2 rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                    <Clock className="size-4 shrink-0" />
                    {peran === 'buyer'
                        ? 'Menunggu tanggapan vendor atas harga yang Anda ajukan.'
                        : terakhir ? 'Menunggu tanggapan purchasing atas harga yang Anda ajukan.' : 'Menunggu purchasing mengajukan harga.'}
                </div>
            );
        }

        if (peran === 'buyer' && !terakhir) {
            return (
                <TawaranForm
                    title="Ajukan Harga Purchase Order"
                    description="Pilih rekomendasi atau isi nominal sendiri. Harga Pagu hanya acuan, keputusan tetap di Anda."
                    submitLabel="Kirim Penawaran"
                    rekomendasi={rekomendasi}
                    pagu={pagu}
                    onSubmit={kirimTawaran}
                />
            );
        }

        const lawan = peran === 'buyer' ? 'vendor' : 'purchasing';
        return (
            <div className="space-y-3">
                <p className="text-sm">
                    {lawan === 'vendor' ? 'Vendor' : 'Purchasing'} mengajukan{' '}
                    <strong className="tabular-nums">{formatRupiah(terakhir.nominal_tawar)}</strong>. Apakah Anda menyetujui harga ini?
                </p>
                <div className="flex flex-wrap gap-2">
                    <LoadingButton size="sm" loading={aksi === 'setujui'} loadingText="Memproses..." disabled={Boolean(aksi)} onClick={setujui} className="h-9 gap-1.5">
                        <Handshake className="size-4" />
                        Setujui Harga
                    </LoadingButton>
                    <Button size="sm" variant="outline" disabled={Boolean(aksi)} onClick={() => setMode('tawar')} className="h-9 cursor-pointer">
                        {peran === 'buyer' ? 'Ajukan Ulang Harga' : 'Tolak & Ajukan Harga Baru'}
                    </Button>
                    {peran === 'buyer' && (
                        <Button size="sm" variant="ghost" disabled={Boolean(aksi)} onClick={() => setBatalOpen(true)} className="h-9 text-destructive hover:text-destructive cursor-pointer">
                            <Ban className="mr-1.5 size-4" />
                            Batalkan PO
                        </Button>
                    )}
                </div>
            </div>
        );
    };

    return (
        <DashboardLayout breadcrumbs={breadcrumbs}>
            <Head title={`Negosiasi ${po.nomor_po} - PAGORA`} />

            <div className="flex flex-col gap-4 w-full">
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-bold tracking-tight flex flex-wrap items-center gap-2">
                            <MessagesSquare className="size-5 text-primary" />
                            Smart Negotiation Chat
                            <StatusBadge po={po} showLock />
                        </h1>
                        <p className="text-xs text-muted-foreground mt-1">
                            <span className="font-mono">{po.nomor_po}</span> · {po.vendor.nama_perusahaan}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="flex items-center rounded-lg border p-0.5 text-xs" role="group" aria-label="Lihat sebagai (prototipe)">
                            <span className="px-2 text-muted-foreground hidden sm:inline">Lihat sebagai</span>
                            {[['buyer', 'Purchasing'], ['vendor', 'Vendor']].map(([value, label]) => (
                                <button
                                    key={value}
                                    type="button"
                                    onClick={() => { setPeran(value); setMode(null); }}
                                    className={cn('rounded-md px-2.5 py-1 font-medium cursor-pointer', peran === value ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground')}
                                >
                                    {label}
                                </button>
                            ))}
                        </div>
                        <Button variant="outline" size="sm" asChild className="h-8">
                            <Link href={`/purchase-order/${po.uuid}`}>
                                <ArrowLeft className="mr-1.5 size-3.5" />
                                Detail PO
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
                    <Card className="shadow-sm lg:col-span-2 flex flex-col overflow-hidden">
                        <CardContent className="p-0 flex flex-col">
                            <div className="h-[min(60vh,560px)] overflow-y-auto p-5 space-y-5 bg-muted/20">
                                <SystemNote icon={Send}>
                                    PO dikonfirmasi dan dikirim ke vendor · {formatDate(po.tanggal_po)}
                                </SystemNote>

                                {po.chat.length === 0 && (
                                    <div className="flex flex-col items-center gap-2 py-10 text-center text-sm text-muted-foreground">
                                        <Sparkles className="size-6" />
                                        Belum ada penawaran. Purchasing memulai dengan mengajukan harga.
                                    </div>
                                )}

                                {po.chat.map((message) => (
                                    <ChatBubble
                                        key={message.uuid}
                                        message={message}
                                        mine={message.pengirim.peran === peran}
                                        pagu={pagu}
                                    />
                                ))}

                                {po.status === 'disepakati' && (
                                    <SystemNote icon={CheckCircle2} tone="success">
                                        Harga disepakati {terakhir && formatRupiah(terakhir.nominal_tawar)} · dokumen PO dikunci
                                    </SystemNote>
                                )}
                                {po.status === 'dibatalkan' && (
                                    <SystemNote icon={Ban} tone="danger">Purchase order dibatalkan</SystemNote>
                                )}
                                <div ref={endRef} />
                            </div>

                            <div className="border-t p-4 bg-card">
                                {!aktif && (
                                    <p className="mb-3 flex items-center gap-1.5 text-xs text-muted-foreground">
                                        <Eye className="size-3.5" />
                                        Mode pratinjau
                                    </p>
                                )}
                                {renderAksi()}
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex flex-col gap-4">
                        <Card className="shadow-sm">
                            <CardHeader className="py-3.5 px-5 border-b">
                                <CardTitle className="text-base font-semibold">Ringkasan Pesanan</CardTitle>
                            </CardHeader>
                            <CardContent className="p-5 space-y-3">
                                <ul className="space-y-2">
                                    {po.items.map((row) => (
                                        <li key={row.uuid} className="flex justify-between gap-3 text-sm">
                                            <span>
                                                {row.nama_komoditas}
                                                <span className="block text-xs text-muted-foreground">
                                                    {formatNumber(row.jumlah_pesanan)} {row.satuan} × {formatRupiah(row.harga_satuan_po)}
                                                </span>
                                            </span>
                                            <span className="tabular-nums font-medium">{formatRupiah(row.jumlah_pesanan * row.harga_satuan_po)}</span>
                                        </li>
                                    ))}
                                </ul>
                                <div className="border-t pt-3 space-y-1.5 text-sm">
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Total Harga Pagu</span>
                                        <span className="tabular-nums">{formatRupiah(pagu)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Pengajuan Awal</span>
                                        <span className="tabular-nums">{formatRupiah(totalPo(po.items))}</span>
                                    </div>
                                    {terakhir && (
                                        <div className="flex justify-between font-semibold">
                                            <span>{po.status === 'disepakati' ? 'Harga Disepakati' : 'Tawaran Terakhir'}</span>
                                            <span className="tabular-nums text-primary">{formatRupiah(terakhir.nominal_tawar)}</span>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {peran === 'buyer' && (
                            <Card className="shadow-sm">
                                <CardHeader className="py-3.5 px-5 border-b">
                                    <CardTitle className="text-base font-semibold flex items-center gap-2">
                                        <Sparkles className="size-4 text-primary" />
                                        Rekomendasi Harga
                                    </CardTitle>
                                    <CardDescription className="text-xs">
                                        Dihitung dari Harga Pagu (data BI + regresi linier). Hanya pendukung keputusan.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="p-3">
                                    <ul className="divide-y">
                                        {rekomendasi.map((r) => (
                                            <li key={r.key} className="flex items-center justify-between gap-3 px-2 py-2.5">
                                                <span className="text-sm">
                                                    {r.label}
                                                    <span className="block text-xs text-muted-foreground">{r.hint}</span>
                                                </span>
                                                <span className="text-sm font-semibold tabular-nums">{formatRupiah(r.nominal)}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>

            <AlertDialog open={batalOpen} onOpenChange={(open) => aksi !== 'batal' && setBatalOpen(open)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Batalkan Purchase Order?</AlertDialogTitle>
                        <AlertDialogDescription>
                            <strong className="text-foreground">{po.nomor_po}</strong> akan berstatus Dibatalkan dan negosiasi ditutup.
                            Riwayat chat tetap tersimpan. Tindakan ini tidak dapat diurungkan.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={aksi === 'batal'}>Kembali</AlertDialogCancel>
                        <LoadingButton variant="destructive" loading={aksi === 'batal'} loadingText="Membatalkan..." onClick={batalkan}>
                            Ya, Batalkan PO
                        </LoadingButton>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </DashboardLayout>
    );
}
