import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Check, Edit, FileText, Lock, MessagesSquare, Send, Trash2, XCircle } from 'lucide-react';
import { cn } from '@/lib/utils';
import { formatDate, formatNumber, formatRupiah } from '@/lib/format';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Button } from '@/Components/ui/button';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { StatusBadge } from '@/Pages/PurchaseOrder/components/StatusBadge';
import { PoNotFound } from '@/Pages/PurchaseOrder/components/PoNotFound';
import { KonfirmasiNegosiasiDialog } from '@/Pages/PurchaseOrder/components/KonfirmasiNegosiasiDialog';
import { HapusDrafDialog } from '@/Pages/PurchaseOrder/components/HapusDrafDialog';
import { hasNegotiation, isEditable, isLocked, tawaranTerakhir, totalPagu, totalPo } from '@/Pages/PurchaseOrder/constants';

const ALUR = ['draft', 'negosiasi', 'disepakati', 'dalam_pengiriman', 'diterima_gudang', 'ditagih', 'lunas'];
const ALUR_LABEL = {
    draft: 'Draft',
    negosiasi: 'Negosiasi',
    disepakati: 'Disepakati',
    dalam_pengiriman: 'Dikirim',
    diterima_gudang: 'Diterima',
    ditagih: 'Ditagih',
    lunas: 'Lunas',
};

function AlurStatus({ status }) {
    const batal = status === 'dibatalkan';
    const current = ALUR.indexOf(status);

    return (
        <ol className="flex items-center gap-1 overflow-x-auto pb-1">
            {ALUR.map((step, index) => {
                const done = !batal && index < current;
                const active = !batal && index === current;
                return (
                    <li key={step} className="flex items-center gap-1 shrink-0">
                        <span
                            className={cn(
                                'flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium',
                                done && 'border-primary/30 bg-primary/10 text-primary',
                                active && 'border-primary bg-primary text-primary-foreground',
                                !done && !active && 'text-muted-foreground'
                            )}
                        >
                            {done && <Check className="size-3" />}
                            {ALUR_LABEL[step] ?? step}
                        </span>
                        {index < ALUR.length - 1 && <span className="h-px w-4 bg-border" />}
                    </li>
                );
            })}
            {batal && (
                <li className="flex items-center gap-1.5 rounded-full border border-destructive/40 bg-destructive/10 px-2.5 py-1 text-xs font-medium text-destructive shrink-0 ml-2">
                    <XCircle className="size-3" />
                    Dibatalkan
                </li>
            )}
        </ol>
    );
}

function Info({ label, children }) {
    return (
        <div className="grid gap-0.5">
            <span className="text-xs text-muted-foreground">{label}</span>
            <span className="text-sm font-medium">{children}</span>
        </div>
    );
}

export default function PurchaseOrderShow({ purchase_order }) {
    const po = purchase_order?.data ?? purchase_order;
    const [konfirmasiOpen, setKonfirmasiOpen] = useState(false);
    const [hapusOpen, setHapusOpen] = useState(false);

    if (!po) return <PoNotFound />;

    const draft = isEditable(po);
    const locked = isLocked(po) && po.status !== 'dibatalkan';
    const total = totalPo(po.items);
    const pagu = totalPagu(po.items);
    const sepakat = locked ? tawaranTerakhir(po) : null;

    const vendorName = po.vendor?.nama_perusahaan ?? po.vendor?.company_name ?? 'Vendor';

    const breadcrumbs = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Purchase Order', href: '/purchase-order' },
        { label: po.nomor_po, href: '#' },
    ];

    return (
        <DashboardLayout breadcrumbs={breadcrumbs}>
            <Head title={`${po.nomor_po} - PAGORA`} />

            <div className="flex flex-col gap-4 w-full">
                <div className="flex flex-col md:flex-row md:items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-bold tracking-tight flex flex-wrap items-center gap-2">
                            <FileText className="size-5 text-primary" />
                            <span className="font-mono">{po.nomor_po}</span>
                            <StatusBadge po={po} showLock />
                        </h1>
                        <p className="text-xs text-muted-foreground mt-1">
                            {vendorName} · {formatDate(po.tanggal_po)}
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" size="sm" asChild className="h-8">
                            <Link href="/purchase-order">
                                <ArrowLeft className="mr-1.5 size-3.5" />
                                Kembali
                            </Link>
                        </Button>
                        {draft && (
                            <>
                                <Button variant="outline" size="sm" onClick={() => setHapusOpen(true)} className="h-8 text-destructive hover:text-destructive cursor-pointer">
                                    <Trash2 className="mr-1.5 size-3.5" />
                                    Hapus Draf
                                </Button>
                                <Button variant="outline" size="sm" asChild className="h-8">
                                    <Link href={`/purchase-order/${po.uuid}/edit`}>
                                        <Edit className="mr-1.5 size-3.5" />
                                        Ubah Draf
                                    </Link>
                                </Button>
                                <Button size="sm" onClick={() => setKonfirmasiOpen(true)} className="h-8 cursor-pointer">
                                    <Send className="mr-1.5 size-3.5" />
                                    Konfirmasi &amp; Negosiasi
                                </Button>
                            </>
                        )}
                        {(po.status === 'negosiasi' || hasNegotiation(po)) && (
                            <Button size="sm" variant={po.status === 'negosiasi' ? 'default' : 'outline'} asChild className="h-8">
                                <Link href={`/purchase-order/${po.uuid}/negosiasi`}>
                                    <MessagesSquare className="mr-1.5 size-3.5" />
                                    {po.status === 'negosiasi' ? 'Buka Negosiasi' : 'Riwayat Negosiasi'}
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <Card className="shadow-sm">
                    <CardContent className="p-4">
                        <AlurStatus status={po.status} />
                    </CardContent>
                </Card>

                {locked && (
                    <Alert>
                        <Lock className="size-4" />
                        <AlertTitle>Dokumen terkunci</AlertTitle>
                        <AlertDescription>
                            Harga sudah disepakati sehingga rincian dan harga PO tidak dapat diubah lagi.
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <Card className="shadow-sm lg:col-span-1">
                        <CardHeader className="py-3.5 px-5 border-b">
                            <CardTitle className="text-base font-semibold">Informasi Pesanan</CardTitle>
                        </CardHeader>
                        <CardContent className="p-5 grid gap-4">
                            <Info label="Vendor">{vendorName}</Info>
                            <Info label="Tanggal PO">{formatDate(po.tanggal_po)}</Info>
                            <Info label="Dibuat oleh">{po.dibuat_oleh}</Info>
                            <Info label="Catatan">
                                {po.catatan ? po.catatan : <span className="text-muted-foreground font-normal italic">Tidak ada catatan</span>}
                            </Info>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm lg:col-span-2">
                        <CardHeader className="py-3.5 px-5 border-b">
                            <CardTitle className="text-base font-semibold">Rincian Komoditas</CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="pl-5">Komoditas</TableHead>
                                        <TableHead className="text-right">Kuantitas</TableHead>
                                        {pagu > 0 && <TableHead className="text-right">Harga Pagu</TableHead>}
                                        <TableHead className="text-right">Harga Satuan</TableHead>
                                        <TableHead className="text-right pr-5">Subtotal</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {po.items.map((item) => (
                                        <TableRow key={item.uuid}>
                                            <TableCell className="pl-5 font-medium">{item.nama_komoditas}</TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatNumber(item.jumlah_pesanan)} {item.satuan}
                                            </TableCell>
                                            {pagu > 0 && (
                                                <TableCell className="text-right tabular-nums text-muted-foreground">
                                                    {item.harga_pagu ? formatRupiah(item.harga_pagu) : '-'}
                                                </TableCell>
                                            )}
                                            <TableCell className="text-right tabular-nums">
                                                {formatRupiah(item.harga_satuan_po)}
                                            </TableCell>
                                            <TableCell className="text-right pr-5 font-semibold tabular-nums">
                                                {formatRupiah(item.subtotal)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                                <TableFooter>
                                    {pagu > 0 && (
                                        <TableRow>
                                            <TableCell colSpan={2} className="pl-5 text-muted-foreground">
                                                Total Harga Pagu Acuan
                                            </TableCell>
                                            <TableCell colSpan={3} className="text-right pr-5 tabular-nums text-muted-foreground">
                                                {formatRupiah(pagu)}
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    <TableRow className="font-semibold text-base">
                                        <TableCell colSpan={2} className="pl-5">
                                            {locked ? 'Total Disepakati' : 'Total Estimasi PO'}
                                        </TableCell>
                                        <TableCell colSpan={pagu > 0 ? 3 : 2} className="text-right pr-5 tabular-nums">
                                            {formatRupiah(sepakat ? sepakat.nominal_tawar : total)}
                                        </TableCell>
                                    </TableRow>
                                </TableFooter>
                            </Table>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <KonfirmasiNegosiasiDialog
                po={po}
                open={konfirmasiOpen}
                onOpenChange={setKonfirmasiOpen}
            />
            <HapusDrafDialog
                po={po}
                open={hapusOpen}
                onOpenChange={setHapusOpen}
                onDeleted={() => router.visit('/purchase-order')}
            />
        </DashboardLayout>
    );
}
