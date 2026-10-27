import { useState } from 'react';
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
import { deletePurchaseOrder, simulateRequest } from '@/Pages/PurchaseOrder/mock-data';

export function HapusDrafDialog({ po, open, onOpenChange, onDeleted }) {
    const [processing, setProcessing] = useState(false);

    if (!po) return null;

    const handleDelete = async () => {
        setProcessing(true);
        // TODO(backend): router.delete(route('purchase-order.destroy', po.uuid)) — soft delete
        await simulateRequest();
        deletePurchaseOrder(po.uuid);
        setProcessing(false);
        onOpenChange(false);
        notify.success(`Draf ${po.nomor_po} berhasil dihapus.`);
        onDeleted?.();
    };

    return (
        <AlertDialog open={open} onOpenChange={(value) => !processing && onOpenChange(value)}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Hapus Draf Purchase Order</AlertDialogTitle>
                    <AlertDialogDescription>
                        Draf <strong className="text-foreground">{po.nomor_po}</strong> untuk{' '}
                        <strong className="text-foreground">{po.vendor.nama_perusahaan}</strong> akan dihapus.
                        Draf yang dihapus tidak tampil lagi di daftar.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={processing}>Batal</AlertDialogCancel>
                    <LoadingButton variant="destructive" loading={processing} loadingText="Menghapus..." onClick={handleDelete}>
                        Ya, Hapus Draf
                    </LoadingButton>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
