import { Head, Link } from '@inertiajs/react';
import { FileX } from 'lucide-react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Button } from '@/Components/ui/button';

export function PoNotFound({ message = 'Purchase order tidak ditemukan.' }) {
    return (
        <DashboardLayout breadcrumbs={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Purchase Order', href: '/purchase-order' }]}>
            <Head title="Purchase Order - PAGORA" />
            <div className="flex flex-col items-center justify-center gap-3 py-24 text-center">
                <FileX className="size-10 text-muted-foreground" />
                <p className="text-sm text-muted-foreground">{message}</p>
                <Button asChild variant="outline" size="sm">
                    <Link href="/purchase-order">Kembali ke Daftar</Link>
                </Button>
            </div>
        </DashboardLayout>
    );
}
