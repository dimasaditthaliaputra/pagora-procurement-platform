import { useState } from 'react';
import { router } from '@inertiajs/react';
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

export function HapusDrafDialog({ po, open, onOpenChange, onDeleted }) {
    const [processing, setProcessing] = useState(false);

    if (!po) return null;

    const handleDelete = () => {
        router.delete(`/purchase-order/${po.uuid}`, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => {
                onOpenChange(false);
                onDeleted?.();
            },
            onError: () => notify.validationFailed(),
        });
    };

    const vendorName = po.vendor?.nama_perusahaan ?? po.vendor?.company_name ?? 'Vendor';

    return (
        <AlertDialog open={open} onOpenChange={(value) => !processing && onOpenChange(value)}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Hapus Draf Purchase Order</AlertDialogTitle>
                    <AlertDialogDescription>
                        Draf <strong className="text-foreground">{po.nomor_po}</strong> untuk{' '}
                        <strong className="text-foreground">{vendorName}</strong> akan dihapus.
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
