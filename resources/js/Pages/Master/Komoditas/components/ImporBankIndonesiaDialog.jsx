import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { Button } from '@/Components/ui/button';
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

export function ImporBankIndonesiaDialog() {
    const [open, setOpen] = useState(false);
    const [importing, setImporting] = useState(false);

    const handleImport = () => {
        router.post('/master/komoditas/impor-bi', {}, {
            preserveScroll: true,
            onStart: () => setImporting(true),
            onSuccess: () => setOpen(false),
            onFinish: () => setImporting(false),
        });
    };

    return (
        <>
            <Button type="button" variant="outline" className="gap-2 cursor-pointer" onClick={() => setOpen(true)}>
                <Download className="size-4" />
                Impor dari BI
            </Button>

            <AlertDialog open={open} onOpenChange={(value) => !importing && setOpen(value)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Impor Komoditas Bank Indonesia</AlertDialogTitle>
                        <AlertDialogDescription>
                            Sistem mengambil daftar komoditas PIHPS Bank Indonesia dan menambahkan yang belum terdaftar,
                            lengkap dengan pemetaan kodenya. Komoditas yang sudah ada tidak diubah. Kategori, satuan, dan
                            batas susut dapat disesuaikan setelah impor.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={importing}>Batal</AlertDialogCancel>
                        <LoadingButton loading={importing} loadingText="Mengimpor..." onClick={handleImport}>
                            Ya, Impor
                        </LoadingButton>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
