import { useEffect, useRef, useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
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

function buildRekomendasi(items, paguValue) {
    const pagu = paguValue || totalPagu(items);
    const awal = totalPo(items);
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
    const vendor = message.pengirim?.peran === 'vendor';
    const Icon = vendor ? Building2 : UserRound;

    return (
        <div className={cn('flex gap-2.5 max-w-[85%]', mine ? 'ml-auto flex-row-reverse' : 'mr-auto')}>
            <div className={cn('size-8 shrink-0 rounded-full flex items-center justify-center border', vendor ? 'bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-400' : 'bg-primary/10 text-primary')}>
                <Icon className="size-4" />
            </div>
            <div className={cn('flex flex-col gap-1', mine && 'items-end')}>
                <span className="text-xs text-muted-foreground">
                    {message.pengirim?.nama ?? 'Pengguna'} {message.pengirim?.role_label ? `(${message.pengirim.role_label})` : ''} · {formatTime(message.created_at)}
                </span>
                <div className={cn('rounded-2xl border px-4 py-3 space-y-1.5', mine ? 'bg-primary text-primary-foreground border-primary rounded-tr-sm' : 'bg-card rounded-tl-sm')}>
                    {message.nominal_tawar !== null && message.nominal_tawar !== undefined && (
                        <div>
                            <p className={cn('text-[11px] uppercase tracking-wide', mine ? 'text-primary-foreground/70' : 'text-muted-foreground')}>
                                {vendor ? 'Tawaran Harga Vendor' : 'Harga diajukan'}
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

function TawaranForm({ title, description, submitLabel, rekomendasi, pagu, onSubmit, onCancel, processing }) {
    const { data, setData, errors, setError, clearErrors, reset } = useForm({ nominal_tawar: '', pesan_teks: '' });

    const handleSubmit = (e) => {
        e.preventDefault();
        const cleanNominal = String(data.nominal_tawar || '').replace(/[^0-9.]/g, '');
        const nominalVal = Number(cleanNominal);

        if (!(nominalVal > 0)) {
            setError('nominal_tawar', 'Nominal harga penawaran wajib diisi dan lebih dari 0.');
            document.getElementById('nominal_tawar')?.focus();
            notify.validationFailed();
            return;
        }

        onSubmit({
            nominal_tawar: nominalVal,
            pesan_teks: data.pesan_teks?.trim() || null,
        }, reset);
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
                        placeholder="Contoh: 15000000"
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
                    <Label htmlFor="pesan_teks" className="text-xs">Pesan / Alasan</Label>
                    <Textarea
                        id="pesan_teks"
                        rows={2}
                        placeholder="Alasan atau keterangan penawaran harga (opsional)"
                        value={data.pesan_teks}
                        onChange={(e) => setData('pesan_teks', e.target.value)}
                        disabled={processing}
                        className="min-h-9"
                    />
                </div>
            </div>

            <div className="flex justify-end gap-2">
                {onCancel && (
                    <Button type="button" variant="ghost" size="sm" onClick={onCancel} disabled={processing} className="h-9 cursor-pointer">
                        Batal
                    </Button>
                )}
                <LoadingButton type="submit" size="sm" loading={processing} loadingText="Mengirim..." className="h-9 gap-1.5 cursor-pointer">
                    <Send className="size-3.5" />
                    {submitLabel}
                </LoadingButton>
            </div>
        </form>
    );
}

export default function PurchaseOrderNegosiasi({ purchase_order, uuid }) {
    const po = purchase_order?.data || purchase_order;
    const [mode, setMode] = useState(null);
    const [aksi, setAksi] = useState(null);
    const [batalOpen, setBatalOpen] = useState(false);
    const [alasanBatal, setAlasanBatal] = useState('');
    const [alasanBatalError, setAlasanBatalError] = useState('');
    const endRef = useRef(null);

    useEffect(() => {
        endRef.current?.scrollIntoView({ behavior: 'smooth', block: 'end' });
    }, [po?.chat?.length, po?.status]);

    if (!po) return <PoNotFound />;
    if (po.status === 'draft') {
        return <PoNotFound message={`${po.nomor_po} masih draf. Konfirmasi PO terlebih dahulu untuk membuka ruang negosiasi.`} />;
    }

    // Role detection
    const peran = po.current_role || 'buyer';
    const isVendor = peran === 'vendor';

    // PRD BR-02 / Q-13: Vendor must never see Harga Pagu
    const pagu = isVendor ? null : (po.harga_pagu_acuan ?? totalPagu(po.items));
    const rekomendasi = isVendor ? null : buildRekomendasi(po.items, pagu);

    // Initial offer fallback for POs submitted to vendor
    const totalAwal = totalPo(po.items);
    const terakhir = tawaranTerakhir(po) || (po.status === 'negosiasi' ? {
        nominal_tawar: totalAwal,
        pesan_teks: 'Pengajuan Purchase Order awal.',
        created_at: po.tanggal_po || po.created_at,
        pengirim: {
            nama: po.dibuat_oleh || 'Purchasing',
            peran: 'buyer',
            role_label: 'Purchasing',
        },
    } : null);

    const aktif = po.status === 'negosiasi';
    const giliranVendor = aktif && terakhir?.pengirim?.peran === 'buyer';
    const giliranBuyer = aktif && (!terakhir || terakhir?.pengirim?.peran === 'vendor');

    const kirimTawaran = (payload, resetForm) => {
        setAksi('tawar');
        try {
            router.post(
                `/purchase-order/${po.uuid}/tawar`,
                payload,
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setMode(null);
                        resetForm?.();
                        notify.success('Penawaran harga berhasil diajukan.');
                    },
                    onError: (err) => {
                        notify.error(Object.values(err)[0] || 'Gagal mengajukan harga penawaran.');
                    },
                    onFinish: () => {
                        setAksi(null);
                    },
                }
            );
        } catch {
            setAksi(null);
            notify.error('Terjadi kesalahan saat mengajukan harga.');
        }
    };

    const setujui = () => {
        setAksi('setujui');
        try {
            router.post(
                `/purchase-order/${po.uuid}/setujui`,
                {},
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setMode(null);
                        notify.success('Harga disepakati. Dokumen PO dikunci.');
                    },
                    onError: (err) => {
                        notify.error(Object.values(err)[0] || 'Gagal menyetujui harga.');
                    },
                    onFinish: () => {
                        setAksi(null);
                    },
                }
            );
        } catch {
            setAksi(null);
            notify.error('Terjadi kesalahan saat menyetujui harga.');
        }
    };

    const batalkan = () => {
        const trimmed = alasanBatal.trim();
        if (!trimmed || trimmed.length < 5) {
            setAlasanBatalError('Alasan pembatalan wajib diisi (minimal 5 karakter).');
            document.getElementById('alasan_pembatalan')?.focus();
            notify.validationFailed();
            return;
        }

        setAksi('batal');
        try {
            router.post(
                `/purchase-order/${po.uuid}/batalkan`,
                { alasan: trimmed },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setBatalOpen(false);
                        setAlasanBatal('');
                        setAlasanBatalError('');
                        notify.success(`${po.nomor_po} dibatalkan.`);
                    },
                    onError: (err) => {
                        const msg = Object.values(err)[0] || 'Gagal membatalkan purchase order.';
                        setAlasanBatalError(msg);
                        notify.error(msg);
                    },
                    onFinish: () => {
                        setAksi(null);
                    },
                }
            );
        } catch {
            setAksi(null);
            notify.error('Terjadi kesalahan saat membatalkan PO.');
        }
    };

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
                    <AlertDescription>Dokumen PO dan harga telah disepakati. Ruang chat bersifat hanya-baca sebagai jejak audit permanen.</AlertDescription>
                </Alert>
            );
        }

        if (po.status === 'dibatalkan') {
            return (
                <Alert variant="destructive">
                    <Ban className="size-4" />
                    <AlertTitle>Purchase order dibatalkan</AlertTitle>
                    <AlertDescription className="space-y-1.5 mt-1">
                        <p>Riwayat negosiasi tetap disimpan sebagai jejak audit tidak dapat diubah.</p>
                        {po.alasan_pembatalan && (
                            <p className="text-xs bg-destructive/15 rounded p-2 text-foreground font-medium border border-destructive/20">
                                <strong>Alasan Pembatalan:</strong> {po.alasan_pembatalan}
                            </p>
                        )}
                    </AlertDescription>
                </Alert>
            );
        }

        if (mode === 'tawar') {
            const buyer = peran === 'buyer';
            return (
                <TawaranForm
                    title={buyer ? (terakhir ? 'Ajukan Ulang Harga' : 'Ajukan Harga Purchase Order') : 'Tolak & Ajukan Harga Baru'}
                    description={buyer ? 'Pilih rekomendasi atau isi nominal sendiri. Harga Pagu hanya acuan anggaran internal.' : 'Ajukan total harga penawaran yang dapat Anda penuhi untuk pesanan ini.'}
                    submitLabel="Kirim Penawaran"
                    rekomendasi={buyer ? rekomendasi : null}
                    pagu={pagu}
                    onSubmit={kirimTawaran}
                    onCancel={() => setMode(null)}
                    processing={aksi === 'tawar'}
                />
            );
        }

        const menunggu = (peran === 'buyer' && giliranVendor) || (peran === 'vendor' && giliranBuyer);
        if (menunggu) {
            return (
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                    <div className="flex items-center gap-2">
                        <Clock className="size-4 shrink-0 text-amber-500" />
                        <span>
                            {peran === 'buyer'
                                ? 'Menunggu tanggapan vendor atas penawaran harga yang Anda ajukan.'
                                : 'Menunggu tanggapan purchasing atas penawaran harga yang Anda ajukan.'}
                        </span>
                    </div>
                    {peran === 'buyer' && (
                        <Button
                            size="sm"
                            variant="ghost"
                            disabled={Boolean(aksi)}
                            onClick={() => setBatalOpen(true)}
                            className="h-8 text-destructive hover:text-destructive cursor-pointer self-start sm:self-auto"
                        >
                            <Ban className="mr-1.5 size-3.5" />
                            Batalkan PO
                        </Button>
                    )}
                </div>
            );
        }

        const lawan = peran === 'buyer' ? 'Vendor' : 'Purchasing';
        const nominalDiajukan = terakhir?.nominal_tawar ?? totalAwal;

        return (
            <div className="space-y-3">
                <p className="text-sm">
                    {lawan} mengajukan{' '}
                    <strong className="tabular-nums font-semibold text-foreground">{formatRupiah(nominalDiajukan)}</strong>. Apakah Anda menyetujui harga ini?
                </p>
                <div className="flex flex-wrap items-center gap-2">
                    <LoadingButton
                        size="sm"
                        loading={aksi === 'setujui'}
                        loadingText="Memproses..."
                        disabled={Boolean(aksi)}
                        onClick={setujui}
                        className="h-9 gap-1.5 cursor-pointer"
                    >
                        <Handshake className="size-4" />
                        Setujui Harga
                    </LoadingButton>
                    <Button
                        size="sm"
                        variant="outline"
                        disabled={Boolean(aksi)}
                        onClick={() => setMode('tawar')}
                        className="h-9 cursor-pointer"
                    >
                        {peran === 'buyer' ? 'Ajukan Ulang Harga' : 'Tolak & Ajukan Harga Baru'}
                    </Button>
                    {peran === 'buyer' && (
                        <Button
                            size="sm"
                            variant="ghost"
                            disabled={Boolean(aksi)}
                            onClick={() => setBatalOpen(true)}
                            className="h-9 text-destructive hover:text-destructive cursor-pointer"
                        >
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
                            <span className="font-mono font-medium">{po.nomor_po}</span> · {isVendor ? (po.tenant?.nama_perusahaan ?? 'Tenant') : (po.vendor?.nama_perusahaan ?? 'Vendor')}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs text-muted-foreground bg-muted/40">
                            <UserRound className="size-3.5 text-primary" />
                            <span>Aktor: <strong className="text-foreground capitalize">{isVendor ? 'Vendor' : 'Purchasing'}</strong></span>
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
                    <Card className="shadow-xs lg:col-span-2 flex flex-col overflow-hidden">
                        <CardContent className="p-0 flex flex-col">
                            <div className="h-[min(60vh,560px)] overflow-y-auto p-5 space-y-5 bg-muted/20">
                                <SystemNote icon={Send}>
                                    PO diajukan ke vendor · {formatDate(po.tanggal_po)}
                                </SystemNote>

                                {(!po.chat || po.chat.length === 0) && (
                                    <div className="flex flex-col items-center gap-2 py-8 text-center text-sm text-muted-foreground">
                                        <Sparkles className="size-5 text-primary" />
                                        <span>Pesanan awal diajukan dengan total <strong>{formatRupiah(totalAwal)}</strong>.</span>
                                    </div>
                                )}

                                {po.chat && po.chat.map((message) => (
                                    <ChatBubble
                                        key={message.uuid}
                                        message={message}
                                        mine={message.pengirim?.is_me ?? (message.pengirim?.peran === peran)}
                                        pagu={pagu}
                                    />
                                ))}

                                {po.status === 'disepakati' && (
                                    <SystemNote icon={CheckCircle2} tone="success">
                                        Harga disepakati {terakhir && formatRupiah(terakhir.nominal_tawar)} · dokumen PO dikunci
                                    </SystemNote>
                                )}
                                {po.status === 'dibatalkan' && (
                                    <SystemNote icon={Ban} tone="danger">
                                        Purchase order dibatalkan {po.alasan_pembatalan ? `· ${po.alasan_pembatalan}` : ''}
                                    </SystemNote>
                                )}
                                <div ref={endRef} />
                            </div>

                            <div className="border-t p-4 bg-card">
                                {!aktif && (
                                    <p className="mb-3 flex items-center gap-1.5 text-xs text-muted-foreground">
                                        <Eye className="size-3.5" />
                                        Mode pratinjau (arsip negosiasi)
                                    </p>
                                )}
                                {renderAksi()}
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex flex-col gap-4">
                        <Card className="shadow-xs">
                            <CardHeader className="py-3.5 px-5 border-b">
                                <CardTitle className="text-base font-semibold">Ringkasan Pesanan</CardTitle>
                            </CardHeader>
                            <CardContent className="p-5 space-y-3">
                                <ul className="space-y-2">
                                    {po.items.map((row) => (
                                        <li key={row.uuid} className="flex justify-between gap-3 text-sm">
                                            <div>
                                                <span>{row.nama_komoditas}</span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {formatNumber(row.jumlah_pesanan)} {row.satuan} × {formatRupiah(row.harga_satuan_po)}
                                                </span>
                                                {row.harga_tawar_vendor !== null && row.harga_tawar_vendor !== undefined && (
                                                    <span className="inline-block mt-0.5 text-xs text-amber-600 dark:text-amber-400 font-medium">
                                                        Tawaran Vendor: {formatRupiah(row.harga_tawar_vendor)} / {row.satuan}
                                                    </span>
                                                )}
                                            </div>
                                            <div className="text-right">
                                                <span className="tabular-nums font-medium block">{formatRupiah(row.jumlah_pesanan * row.harga_satuan_po)}</span>
                                                {row.harga_tawar_vendor !== null && row.harga_tawar_vendor !== undefined && (
                                                    <span className="text-xs text-amber-600 dark:text-amber-400 tabular-nums">
                                                        ({formatRupiah(row.jumlah_pesanan * row.harga_tawar_vendor)})
                                                    </span>
                                                )}
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                                <div className="border-t pt-3 space-y-1.5 text-sm">
                                    {!isVendor && pagu !== null && pagu !== undefined && (
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">Total Harga Pagu</span>
                                            <span className="tabular-nums">{formatRupiah(pagu)}</span>
                                        </div>
                                    )}
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

                        {!isVendor && rekomendasi && (
                            <Card className="shadow-xs">
                                <CardHeader className="py-3.5 px-5 border-b">
                                    <CardTitle className="text-base font-semibold flex items-center gap-2">
                                        <Sparkles className="size-4 text-primary" />
                                        Rekomendasi Harga
                                    </CardTitle>
                                    <CardDescription className="text-xs">
                                        Dihitung dari estimasi Harga Pagu komoditas (data BI + regresi linier).
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

            <AlertDialog open={batalOpen} onOpenChange={(open) => {
                if (aksi !== 'batal') {
                    setBatalOpen(open);
                    if (!open) {
                        setAlasanBatalError('');
                    }
                }
            }}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Batalkan Purchase Order?</AlertDialogTitle>
                        <AlertDialogDescription>
                            <strong className="text-foreground">{po.nomor_po}</strong> akan berstatus Dibatalkan dan ruang negosiasi ditutup.
                            Riwayat chat tetap tersimpan permanen sebagai jejak audit. Tindakan ini tidak dapat diurungkan.
                        </AlertDialogDescription>
                    </AlertDialogHeader>

                    <div className="space-y-1.5 py-2">
                        <Label htmlFor="alasan_pembatalan" className={cn('text-xs font-medium', alasanBatalError && 'text-destructive')}>
                            Alasan Pembatalan <span className="text-destructive">*</span>
                        </Label>
                        <Textarea
                            id="alasan_pembatalan"
                            rows={3}
                            placeholder="Tuliskan alasan pembatalan purchase order ini (misal: penyesuaian anggaran, perubahan kebutuhan produksi)..."
                            value={alasanBatal}
                            onChange={(e) => {
                                setAlasanBatal(e.target.value);
                                setAlasanBatalError('');
                            }}
                            disabled={aksi === 'batal'}
                            className={cn('text-sm', alasanBatalError && 'border-destructive focus-visible:ring-destructive')}
                        />
                        {alasanBatalError && (
                            <p className="text-xs text-destructive font-medium">{alasanBatalError}</p>
                        )}
                    </div>

                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={aksi === 'batal'}>Kembali</AlertDialogCancel>
                        <LoadingButton variant="destructive" loading={aksi === 'batal'} loadingText="Membatalkan..." onClick={batalkan} className="cursor-pointer">
                            Ya, Batalkan PO
                        </LoadingButton>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </DashboardLayout>
    );
}
