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
import { ShieldCheck } from 'lucide-react';
import { notify } from '@/lib/notify';

export function ApprovalDialog({ tenant, open, onOpenChange }) {
    const [loading, setLoading] = useState(false);

    if (!tenant) return null;

    const companyName = tenant.company_name ?? 'Tenant';

    const handleApprove = () => {
        setLoading(true);
        router.post(
            `/master/tenant/verifikasi-dokumen/${tenant.uuid}/verifikasi`,
            {
                aksi: 'approve',
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setLoading(false);
                    onOpenChange(false);
                    notify.success(`Pendaftaran tenant ${companyName} berhasil disetujui.`);
                },
                onError: (errors) => {
                    setLoading(false);
                    notify.error(
                        errors.aksi ||
                        errors.verification_status ||
                        'Gagal memproses persetujuan tenant.'
                    );
                },
            }
        );
    };

    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent className="max-w-md">
                <AlertDialogHeader>
                    <div className="flex items-center gap-2 text-emerald-600">
                        <div className="flex size-10 items-center justify-center rounded-full bg-emerald-50 dark:bg-emerald-950/40">
                            <ShieldCheck className="size-5" />
                        </div>
                        <AlertDialogTitle>Setujui Pendaftaran Tenant</AlertDialogTitle>
                    </div>
                    <AlertDialogDescription className="pt-2 text-sm text-muted-foreground leading-relaxed">
                        Apakah Anda yakin ingin menyetujui pendaftaran tenant{' '}
                        <strong className="text-foreground">{companyName}</strong>?
                        <br />
                        <span className="block mt-2 text-xs">
                            Status perusahaan akan menjadi{' '}
                            <span className="font-semibold text-emerald-600">Disetujui</span>.
                            Pengguna/Owner ({tenant.owner?.email || 'owner'}) akan menerima email notifikasi
                            dan dapat mulai bertransaksi di dalam sistem.
                        </span>
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter className="mt-4 gap-2">
                    <AlertDialogCancel disabled={loading}>
                        Batal
                    </AlertDialogCancel>
                    <LoadingButton
                        onClick={handleApprove}
                        loading={loading}
                        loadingText="Menyetujui..."
                        className="bg-emerald-600 hover:bg-emerald-700 text-white"
                    >
                        Ya, Setujui Tenant
                    </LoadingButton>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

