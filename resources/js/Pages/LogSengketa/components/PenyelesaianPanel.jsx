import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { CheckCircle2, Clock, Gavel, Handshake, Truck } from 'lucide-react';
import { formatDate, formatTime } from '@/lib/format';
import { notify } from '@/lib/notify';
import { LoadingButton } from '@/Components/loading-button';
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
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { SettlementDecisionDialog } from '@/Pages/LogSengketa/components/SettlementDecisionDialog';

function formatDateTime(value) {
    if (!value) return '-';
    return `${formatDate(value)}, ${formatTime(value)} WIB`;
}

/**
 * Settlement status and actions (UC-18 step 4–6): Owner/Manajemen propose and resolve,
 * the Vendor accepts or refuses a reshipment. Credit notes and returns are decided in `DokumenPenyelesaianCard`.
 */
export function PenyelesaianPanel({ dispute, resolveBlocker }) {
    const resolution = dispute.resolution ?? {};
    const isResolved = dispute.status === 'resolved';
    const [resolveOpen, setResolveOpen] = useState(false);
    const [resolveNote, setResolveNote] = useState('');
    const [resolving, setResolving] = useState(false);
    const [reshipmentDecision, setReshipmentDecision] = useState(null);

    const submitResolve = () => {
        router.post(
            `/log-sengketa/${dispute.uuid}/selesai`,
            { note: resolveNote.trim() || null },
            {
                preserveScroll: true,
                onStart: () => setResolving(true),
                onFinish: () => setResolving(false),
                onSuccess: () => {
                    setResolveOpen(false);
                    setResolveNote('');
                },
                onError: (errors) => notify.error(errors.resolution ?? errors.note ?? 'Sengketa belum dapat diselesaikan.'),
            }
        );
    };

    return (
        <Card className="shadow-sm border-primary/20">
            <CardHeader className="border-b">
                <CardTitle className="text-base font-semibold flex items-center gap-2">
                    <Gavel className="size-4 text-primary" />
                    Penyelesaian Sengketa
                </CardTitle>
                <CardDescription>
                    Penyelesaian wajib dikonfirmasi Vendor sebelum sengketa ditetapkan Selesai.
                </CardDescription>
            </CardHeader>
            <CardContent className="p-5 space-y-4 text-sm">
                {resolution.type ? (
                    <div className="space-y-2">
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="brand">{resolution.type_label}</Badge>
                            {resolution.vendor_confirmed_at ? (
                                <Badge variant="green" className="gap-1">
                                    <CheckCircle2 className="size-3" />
                                    Dikonfirmasi Vendor
                                </Badge>
                            ) : (
                                <Badge variant="warning" className="gap-1">
                                    <Clock className="size-3" />
                                    Menunggu Konfirmasi Vendor
                                </Badge>
                            )}
                        </div>
                        {resolution.notes && (
                            <p className="text-sm text-foreground whitespace-pre-line">{resolution.notes}</p>
                        )}
                        {resolution.vendor_confirmed_at && (
                            <p className="text-xs text-muted-foreground">
                                Dikonfirmasi {resolution.vendor_confirmed_by_name ?? 'Vendor'} pada{' '}
                                {formatDateTime(resolution.vendor_confirmed_at)}
                            </p>
                        )}
                        {isResolved && resolution.resolved_by_name && (
                            <p className="text-xs text-muted-foreground">
                                Ditetapkan Selesai oleh {resolution.resolved_by_name} pada {formatDateTime(dispute.resolved_at)}
                            </p>
                        )}
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        {isResolved ? 'Sengketa telah selesai.' : 'Belum ada penyelesaian yang diajukan.'}
                    </p>
                )}

                {dispute.can?.respond && resolution.awaiting_reshipment_decision && (
                    <div className="rounded-lg border bg-muted/30 p-3 space-y-3">
                        <p className="text-xs text-muted-foreground flex items-start gap-1.5">
                            <Truck className="size-3.5 mt-0.5 shrink-0" />
                            Perusahaan pembeli meminta Anda mengirim ulang item yang ditolak QC.
                        </p>
                        <div className="flex flex-wrap gap-2">
                            <Button size="sm" className="cursor-pointer" onClick={() => setReshipmentDecision('accept')}>
                                Setujui Pengiriman Ulang
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                className="cursor-pointer"
                                onClick={() => setReshipmentDecision('reject')}
                            >
                                Tolak
                            </Button>
                        </div>
                    </div>
                )}

                {dispute.can?.respond &&
                    resolution.type === 'reshipment' &&
                    resolution.vendor_confirmed_at &&
                    dispute.po_status === 'partially_received' && (
                        <Button asChild size="sm" variant="outline" className="w-full gap-1.5">
                            <Link href={`/pengiriman/create?po=${dispute.po_uuid}`}>
                                <Truck className="size-4" />
                                Laporkan Pengiriman Ulang
                            </Link>
                        </Button>
                    )}

                {(dispute.can?.propose_resolution || dispute.can?.resolve) && (
                    <div className="flex flex-col gap-2 pt-1 border-t">
                        {dispute.can?.propose_resolution && (
                            <Button asChild variant="outline" className="w-full gap-1.5 mt-3">
                                <Link href={`/log-sengketa/${dispute.uuid}/penyelesaian`}>
                                    <Handshake className="size-4" />
                                    {resolution.type === 'credit_note' ? 'Tambah Credit Note / Ubah Penyelesaian' : 'Ajukan Penyelesaian'}
                                </Link>
                            </Button>
                        )}
                        {dispute.can?.resolve && (
                            <>
                                <Button
                                    className="w-full gap-1.5 cursor-pointer"
                                    disabled={Boolean(resolveBlocker)}
                                    onClick={() => setResolveOpen(true)}
                                >
                                    <CheckCircle2 className="size-4" />
                                    Tetapkan Selesai
                                </Button>
                                {resolveBlocker && <p className="text-xs text-muted-foreground">{resolveBlocker}</p>}
                            </>
                        )}
                    </div>
                )}
            </CardContent>

            <AlertDialog open={resolveOpen} onOpenChange={(value) => !resolving && setResolveOpen(value)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Tetapkan Sengketa Selesai?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Penyelesaian {resolution.type_label} sudah dikonfirmasi Vendor. Setelah Selesai, sengketa dan Log
                            Sengketa menjadi permanen dan Purchase Order {dispute.po_number} dilanjutkan dari progres terakhirnya.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <div className="space-y-2">
                        <Label htmlFor="resolve_note">Catatan Verifikasi (Opsional)</Label>
                        <Textarea
                            id="resolve_note"
                            rows={3}
                            value={resolveNote}
                            disabled={resolving}
                            onChange={(e) => setResolveNote(e.target.value)}
                            placeholder="Contoh: Credit note telah diterima dan barang retur sudah dikembalikan."
                        />
                    </div>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={resolving} className="cursor-pointer">
                            Batal
                        </AlertDialogCancel>
                        <LoadingButton loading={resolving} loadingText="Menyimpan..." onClick={submitResolve} className="cursor-pointer">
                            Ya, Tetapkan Selesai
                        </LoadingButton>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <SettlementDecisionDialog
                open={reshipmentDecision !== null}
                onOpenChange={(value) => !value && setReshipmentDecision(null)}
                url={`/log-sengketa/${dispute.uuid}/pengiriman-ulang/keputusan`}
                decision={reshipmentDecision}
                title={reshipmentDecision === 'reject' ? 'Tolak Pengiriman Ulang?' : 'Setujui Pengiriman Ulang?'}
                description={
                    reshipmentDecision === 'reject'
                        ? 'Sengketa kembali ke tahap mediasi. Perusahaan pembeli akan menerima alasan penolakan Anda.'
                        : 'Anda menyanggupi pengiriman ulang item yang ditolak. Laporkan pengirimannya melalui menu Pengiriman.'
                }
            />
        </Card>
    );
}

export default PenyelesaianPanel;
