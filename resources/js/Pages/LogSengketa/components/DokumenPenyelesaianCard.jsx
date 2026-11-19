import { useState } from 'react';
import { router } from '@inertiajs/react';
import { FileMinus2, PackageX } from 'lucide-react';
import { formatDate, formatNumber, formatRupiah } from '@/lib/format';
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
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { SettlementDecisionDialog } from '@/Pages/LogSengketa/components/SettlementDecisionDialog';

const DOCUMENT_STATUS_VARIANTS = {
    draft: 'gray',
    awaiting_vendor_confirmation: 'warning',
    confirmed: 'green',
    rejected: 'destructive',
    completed: 'brand',
};

function DocumentStatus({ status, label }) {
    return <Badge variant={DOCUMENT_STATUS_VARIANTS[status] ?? 'gray'}>{label}</Badge>;
}

function DecisionButtons({ can, onDecide }) {
    if (!can?.confirm && !can?.reject) return null;

    return (
        <div className="flex flex-wrap gap-2 pt-1">
            {can.confirm && (
                <Button size="sm" className="cursor-pointer" onClick={() => onDecide('accept')}>
                    Konfirmasi
                </Button>
            )}
            {can.reject && (
                <Button size="sm" variant="outline" className="cursor-pointer" onClick={() => onDecide('reject')}>
                    Tolak
                </Button>
            )}
        </div>
    );
}

/**
 * Credit notes (FR-41) and returns (FR-42) produced by the dispute. The Vendor confirms or refuses each one,
 * and marks a confirmed return as received back.
 */
export function DokumenPenyelesaianCard({ dispute }) {
    const creditNotes = dispute.credit_notes ?? [];
    const purchaseReturns = dispute.purchase_returns ?? [];
    const [decision, setDecision] = useState(null);
    const [completing, setCompleting] = useState(null);
    const [processing, setProcessing] = useState(false);

    if (creditNotes.length === 0 && purchaseReturns.length === 0) return null;

    const submitComplete = () => {
        router.post(
            `/log-sengketa/${dispute.uuid}/retur/${completing.uuid}/selesai`,
            {},
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => setCompleting(null),
                onError: () => notify.error('Retur belum dapat ditandai Selesai.'),
            }
        );
    };

    const decisionCopy = decision
        ? decision.kind === 'credit_note'
            ? {
                  url: `/log-sengketa/${dispute.uuid}/credit-note/${decision.document.uuid}/keputusan`,
                  title: decision.value === 'accept' ? `Konfirmasi Credit Note ${decision.document.number}?` : `Tolak Credit Note ${decision.document.number}?`,
                  description:
                      decision.value === 'accept'
                          ? `Tagihan ${decision.document.invoice_number ?? ''} akan dikurangi ${formatRupiah(decision.document.amount)}. Credit note yang dikonfirmasi bersifat permanen.`
                          : 'Credit note ditolak dan sengketa kembali ke tahap mediasi.',
              }
            : {
                  url: `/log-sengketa/${dispute.uuid}/retur/${decision.document.uuid}/keputusan`,
                  title: decision.value === 'accept' ? `Konfirmasi Retur ${decision.document.number}?` : `Tolak Retur ${decision.document.number}?`,
                  description:
                      decision.value === 'accept'
                          ? 'Anda menyetujui pengembalian barang yang ditolak QC. Barang retur tidak ditagihkan. Retur yang dikonfirmasi bersifat permanen.'
                          : 'Retur ditolak dan sengketa kembali ke tahap mediasi.',
              }
        : null;

    return (
        <Card className="shadow-sm">
            <CardHeader className="border-b">
                <CardTitle className="text-base font-semibold flex items-center gap-2">
                    <FileMinus2 className="size-4 text-primary" />
                    Dokumen Penyelesaian
                </CardTitle>
            </CardHeader>
            <CardContent className="p-5 space-y-4">
                {creditNotes.map((note) => (
                    <div key={note.uuid} className="rounded-lg border p-3 space-y-1.5">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div className="flex items-center gap-2">
                                <FileMinus2 className="size-4 text-muted-foreground" />
                                <span className="font-mono text-sm font-semibold">{note.number}</span>
                                <span className="text-xs text-muted-foreground">Credit Note</span>
                            </div>
                            <DocumentStatus status={note.status} label={note.status_label} />
                        </div>
                        <p className="text-lg font-bold tabular-nums">{formatRupiah(note.amount)}</p>
                        <p className="text-xs text-muted-foreground">
                            Invoice {note.invoice_number ?? '-'} · {formatDate(note.date)} · dibuat {note.created_by_name ?? '-'}
                        </p>
                        <p className="text-sm whitespace-pre-line">{note.reason}</p>
                        <DecisionButtons
                            can={note.can}
                            onDecide={(value) => setDecision({ kind: 'credit_note', value, document: note })}
                        />
                    </div>
                ))}

                {purchaseReturns.map((purchaseReturn) => (
                    <div key={purchaseReturn.uuid} className="rounded-lg border p-3 space-y-1.5">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div className="flex items-center gap-2">
                                <PackageX className="size-4 text-muted-foreground" />
                                <span className="font-mono text-sm font-semibold">{purchaseReturn.number}</span>
                                <span className="text-xs text-muted-foreground">Retur</span>
                            </div>
                            <DocumentStatus status={purchaseReturn.status} label={purchaseReturn.status_label} />
                        </div>
                        <p className="text-xs text-muted-foreground">
                            {formatDate(purchaseReturn.date)} · dibuat {purchaseReturn.created_by_name ?? '-'}
                        </p>
                        <ul className="space-y-1">
                            {purchaseReturn.items.map((item) => (
                                <li key={item.uuid} className="flex items-center justify-between gap-2 text-sm">
                                    <span>{item.commodity_name}</span>
                                    <span className="tabular-nums font-medium">
                                        {formatNumber(item.quantity)} {item.unit}
                                    </span>
                                </li>
                            ))}
                        </ul>
                        <p className="text-sm whitespace-pre-line">{purchaseReturn.reason}</p>
                        <DecisionButtons
                            can={purchaseReturn.can}
                            onDecide={(value) => setDecision({ kind: 'return', value, document: purchaseReturn })}
                        />
                        {purchaseReturn.can?.complete && (
                            <Button
                                size="sm"
                                variant="outline"
                                className="cursor-pointer"
                                onClick={() => setCompleting(purchaseReturn)}
                            >
                                Tandai Barang Retur Diterima
                            </Button>
                        )}
                    </div>
                ))}
            </CardContent>

            {decisionCopy && (
                <SettlementDecisionDialog
                    open
                    onOpenChange={(value) => !value && setDecision(null)}
                    url={decisionCopy.url}
                    decision={decision.value}
                    title={decisionCopy.title}
                    description={decisionCopy.description}
                />
            )}

            <AlertDialog open={completing !== null} onOpenChange={(value) => !processing && !value && setCompleting(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Barang Retur Sudah Diterima?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Retur {completing?.number} akan ditandai Selesai. Tindakan ini permanen.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={processing} className="cursor-pointer">
                            Batal
                        </AlertDialogCancel>
                        <LoadingButton loading={processing} loadingText="Menyimpan..." onClick={submitComplete} className="cursor-pointer">
                            Ya, Tandai Selesai
                        </LoadingButton>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </Card>
    );
}

export default DokumenPenyelesaianCard;
