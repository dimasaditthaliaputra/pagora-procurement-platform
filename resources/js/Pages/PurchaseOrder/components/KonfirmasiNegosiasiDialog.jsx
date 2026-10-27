import { useState } from 'react';
import { router } from '@inertiajs/react';
import { MessagesSquare } from 'lucide-react';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { LoadingButton } from '@/Components/loading-button';
import { notify } from '@/lib/notify';
import { formatRupiah } from '@/lib/format';
import { totalPo } from '@/Pages/PurchaseOrder/constants';
import { simulateRequest, updatePurchaseOrder } from '@/Pages/PurchaseOrder/mock-data';

/**
 * Pop-up after a draft is saved (or opened from the list): "Negosiasi?".
 * Confirming moves the PO to `negosiasi` and opens the Smart Negotiation Chat.
 */
export function KonfirmasiNegosiasiDialog({ po, open, onOpenChange, onDecline }) {
    const [processing, setProcessing] = useState(false);

    if (!po) return null;

    const handleConfirm = async () => {
        setProcessing(true);
        // TODO(backend): router.post(route('purchase-order.konfirmasi', po.uuid))
        await simulateRequest();
        updatePurchaseOrder(po.uuid, { status: 'negosiasi' });
        setProcessing(false);
        onOpenChange(false);
        notify.success(`${po.nomor_po} dikirim ke vendor. Ruang negosiasi dibuka.`);
        router.visit(`/purchase-order/${po.uuid}/negosiasi`);
    };

    return (
        <AlertDialog open={open} onOpenChange={(value) => !processing && onOpenChange(value)}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle className="flex items-center gap-2">
                        <MessagesSquare className="size-5 text-primary" />
                        Lanjutkan ke Negosiasi?
                    </AlertDialogTitle>
                    <AlertDialogDescription asChild>
                        <div className="space-y-3 text-sm">
                            <p>
                                <strong className="text-foreground">{po.nomor_po}</strong> akan dikirim ke{' '}
                                <strong className="text-foreground">{po.vendor.nama_perusahaan}</strong> senilai{' '}
                                <strong className="text-foreground">{formatRupiah(totalPo(po.items))}</strong>.
                            </p>
                            <p>
                                Setelah dikonfirmasi, draf tidak bisa diubah lagi dan ruang Smart Negotiation Chat
                                dibuka. Pilih <em>Simpan sebagai Draf</em> jika masih ingin merevisi.
                            </p>
                        </div>
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={processing} onClick={onDecline}>
                        Simpan sebagai Draf
                    </AlertDialogCancel>
                    <LoadingButton loading={processing} loadingText="Mengonfirmasi..." onClick={handleConfirm}>
                        Ya, Konfirmasi PO
                    </LoadingButton>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
