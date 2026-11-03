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

/**
 * Pop-up after a draft is saved (or opened from the list): "Negosiasi?".
 * Confirming moves the PO to `negosiasi` and submits it to the vendor.
 */
export function KonfirmasiNegosiasiDialog({ po, open, onOpenChange, onDecline }) {
    const [processing, setProcessing] = useState(false);

    if (!po) return null;

    const handleConfirm = () => {
        router.post(`/purchase-order/${po.uuid}/konfirmasi`, {}, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => {
                onOpenChange(false);
            },
            onError: () => notify.validationFailed(),
        });
    };

    const vendorName = po.vendor?.nama_perusahaan ?? po.vendor?.company_name ?? 'Vendor';
    const totalAmount = formatRupiah(totalPo(po.items));

    return (
        <AlertDialog open={open} onOpenChange={(value) => !processing && onOpenChange(value)}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle className="flex items-center gap-2">
                        <MessagesSquare className="size-5 text-primary" />
                        Lanjutkan Pengajuan &amp; Negosiasi PO?
                    </AlertDialogTitle>
                    <AlertDialogDescription asChild>
                        <div className="space-y-3 text-sm">
                            <p>
                                <strong className="text-foreground">{po.nomor_po}</strong> akan diajukan ke{' '}
                                <strong className="text-foreground">{vendorName}</strong> senilai{' '}
                                <strong className="text-foreground">{totalAmount}</strong>.
                            </p>
                            <p>
                                Setelah dikonfirmasi, draf akan diajukan ke vendor dan status berubah menjadi Negosiasi.
                                Pilih <em>Simpan sebagai Draf</em> jika masih ingin merevisi sebelum diajukan.
                            </p>
                        </div>
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={processing} onClick={onDecline}>
                        Simpan sebagai Draf
                    </AlertDialogCancel>
                    <LoadingButton loading={processing} loadingText="Mengonfirmasi..." onClick={handleConfirm}>
                        Ya, Ajukan PO
                    </LoadingButton>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
