import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    ArrowLeft,
    ArrowRight,
    FilePlus2,
    Package,
    Search,
    ShoppingCart,
} from 'lucide-react';

import DashboardLayout from '@/Layouts/DashboardLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';

export default function Create({ purchase_orders = [] }) {
    const [search, setSearch] = useState('');
    const [selectedPo, setSelectedPo] = useState('');

    const filteredOrders = purchase_orders.filter((po) => {
        const keyword = search.toLowerCase();

        return (
            (po.nomor_po || '').toLowerCase().includes(keyword) ||
            (po.tenant?.company_name || '').toLowerCase().includes(keyword)
        );
    });

    const handlePreview = () => {
        if (!selectedPo) return;

        router.visit(`/invoice/preview/${selectedPo}`);
    };

    return (
        <DashboardLayout
            breadcrumbs={[
                { label: 'Invoice', href: '/invoice' },
                { label: 'Buat Invoice' },
            ]}
        >
            <Head title="Buat Invoice" />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            Buat Invoice
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Pilih Purchase Order yang memiliki barang lolos QC
                            dan belum ditagihkan.
                        </p>
                    </div>

                    <Button variant="outline" asChild>
                        <Link href="/invoice">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Kembali
                        </Link>
                    </Button>
                </div>

                <div className="rounded-xl border bg-card p-5 shadow-sm">
                    <div className="mb-5 flex items-center gap-3">
                        <div className="rounded-lg bg-primary/10 p-2">
                            <FilePlus2 className="h-5 w-5 text-primary" />
                        </div>
                        <div>
                            <h2 className="font-semibold">
                                Pilih Purchase Order
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Pilih PO untuk melihat rincian barang yang
                                dapat ditagihkan.
                            </p>
                        </div>
                    </div>

                    <div className="relative mb-4">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Cari nomor PO atau nama pembeli..."
                            className="pl-9"
                        />
                    </div>

                    {filteredOrders.length > 0 ? (
                        <div className="space-y-3">
                            {filteredOrders.map((po) => (
                                <button
                                    key={po.uuid}
                                    type="button"
                                    onClick={() => setSelectedPo(po.uuid)}
                                    className={`w-full rounded-lg border p-4 text-left transition ${
                                        selectedPo === po.uuid
                                            ? 'border-primary bg-primary/5 ring-1 ring-primary'
                                            : 'hover:border-primary/50 hover:bg-muted/40'
                                    }`}
                                >
                                    <div className="flex items-start gap-3">
                                        <div className="mt-1 rounded-lg bg-muted p-2">
                                            <ShoppingCart className="h-5 w-5" />
                                        </div>

                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <p className="font-semibold">
                                                    {po.nomor_po}
                                                </p>

                                                <span className="rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700">
                                                    Tersedia untuk ditagihkan
                                                </span>
                                            </div>

                                            <p className="mt-1 text-sm text-muted-foreground">
                                                Pembeli:{' '}
                                                {po.tenant?.company_name || '-'}
                                            </p>

                                            <p className="mt-1 text-xs text-muted-foreground">
                                                Status PO:{' '}
                                                {po.status?.label ||
                                                    po.status ||
                                                    '-'}
                                            </p>
                                        </div>

                                        <div className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border">
                                            {selectedPo === po.uuid && (
                                                <div className="h-3 w-3 rounded-full bg-primary" />
                                            )}
                                        </div>
                                    </div>
                                </button>
                            ))}
                        </div>
                    ) : (
                        <div className="rounded-lg border border-dashed py-12 text-center">
                            <Package className="mx-auto mb-3 h-10 w-10 text-muted-foreground" />
                            <p className="font-medium">
                                Tidak ada Purchase Order
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {search
                                    ? 'Tidak ditemukan PO yang sesuai pencarian.'
                                    : 'Belum ada PO dengan barang lolos QC yang dapat ditagihkan.'}
                            </p>
                        </div>
                    )}

                    <div className="mt-6 flex flex-col-reverse justify-between gap-3 border-t pt-5 sm:flex-row sm:items-center">
                        <p className="text-sm text-muted-foreground">
                            {filteredOrders.length} Purchase Order tersedia
                        </p>

                        <Button
                            onClick={handlePreview}
                            disabled={!selectedPo}
                        >
                            Lanjut Preview
                            <ArrowRight className="ml-2 h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}
