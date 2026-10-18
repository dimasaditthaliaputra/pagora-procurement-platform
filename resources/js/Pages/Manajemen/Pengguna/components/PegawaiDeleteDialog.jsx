import { useState } from 'react';
import { router } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
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

export function PegawaiDeleteDialog({
    open,
    onOpenChange,
    pegawai = null,
}) {
    const [deleting, setDeleting] = useState(false);

    if (!pegawai) return null;

    const handleDelete = () => {
        setDeleting(true);
        router.delete(`/manajemen/pengguna/${pegawai.uuid}`, {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
            },
            onError: () => {
                notify.validationFailed();
            },
            onFinish: () => {
                setDeleting(false);
            },
        });
    };

    return (
        <AlertDialog open={open} onOpenChange={(val) => !deleting && onOpenChange(val)}>
            <AlertDialogContent className="sm:max-w-[460px]">
                <AlertDialogHeader>
                    <div className="flex items-center gap-3">
                        <div className="p-2.5 rounded-full bg-destructive/10 text-destructive shrink-0">
                            <AlertTriangle className="size-5" />
                        </div>
                        <div>
                            <AlertDialogTitle className="text-base font-bold">
                                Hapus Akun Pegawai
                            </AlertDialogTitle>
                            <AlertDialogDescription className="text-xs text-muted-foreground mt-0.5">
                                Tindakan penonaktifan akses pegawai internal.
                            </AlertDialogDescription>
                        </div>
                    </div>
                </AlertDialogHeader>

                <div className="py-2 text-sm text-muted-foreground space-y-2">
                    <p>
                        Apakah Anda yakin ingin menghapus akun pegawai{' '}
                        <strong className="text-foreground">{pegawai.nama_lengkap}</strong> (
                        <span className="font-mono text-xs">{pegawai.email}</span>)?
                    </p>
                    <div className="p-3 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-800 dark:text-amber-300 text-xs leading-relaxed">
                        <strong>Catatan Soft Delete:</strong> Akun ini akan dinonaktifkan sehingga pegawai tidak lagi dapat masuk.
                        Data riwayat kerja, inspeksi, dan penawaran yang pernah dibuat tetap tersimpan dengan aman di database.
                    </div>
                </div>

                <AlertDialogFooter className="mt-2">
                    <AlertDialogCancel disabled={deleting} className="cursor-pointer">
                        Batal
                    </AlertDialogCancel>
                    <LoadingButton
                        variant="destructive"
                        loading={deleting}
                        loadingText="Menghapus..."
                        onClick={handleDelete}
                        className="cursor-pointer"
                    >
                        Ya, Hapus Akun
                    </LoadingButton>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
