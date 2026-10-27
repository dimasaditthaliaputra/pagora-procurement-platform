import { useMemo, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { useReactTable, getCoreRowModel } from '@tanstack/react-table';
import { Plus, Search, FilterX, FileText } from 'lucide-react';
import { cn } from '@/lib/utils';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { DataTable } from '@/Components/data-table/data-table';
import { DataTablePagination } from '@/Components/data-table/data-table-pagination';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Combobox } from '@/Components/combobox';
import { getColumns } from '@/Pages/PurchaseOrder/columns';
import { STATUS_FILTER_OPTIONS, STATUS_PO, totalPo } from '@/Pages/PurchaseOrder/constants';
import { getPurchaseOrders } from '@/Pages/PurchaseOrder/mock-data';
import { KonfirmasiNegosiasiDialog } from '@/Pages/PurchaseOrder/components/KonfirmasiNegosiasiDialog';
import { HapusDrafDialog } from '@/Pages/PurchaseOrder/components/HapusDrafDialog';

const SUMMARY_STATUSES = ['draft', 'negosiasi', 'disepakati', 'dibatalkan'];

const sortValue = {
    nomor_po: (po) => po.nomor_po,
    tanggal_po: (po) => po.tanggal_po,
    total: (po) => totalPo(po.items),
};

export default function PurchaseOrderIndex() {
    // TODO(backend): replace with `purchase_orders` + `filters` props and server-side paging (RULES §8.5).
    const [rows, setRows] = useState(() => getPurchaseOrders());
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('all');
    const [sorting, setSorting] = useState([]);
    const [page, setPage] = useState(1);
    const [perPage, setPerPage] = useState(10);
    const [konfirmasiPo, setKonfirmasiPo] = useState(null);
    const [hapusPo, setHapusPo] = useState(null);

    const refresh = () => setRows([...getPurchaseOrders()]);

    const filtered = useMemo(() => {
        const term = search.trim().toLowerCase();
        let result = rows.filter((po) => {
            const matchStatus = status === 'all' || po.status === status;
            const matchSearch = !term
                || po.nomor_po.toLowerCase().includes(term)
                || po.vendor.nama_perusahaan.toLowerCase().includes(term)
                || po.items.some((i) => i.nama_komoditas.toLowerCase().includes(term));
            return matchStatus && matchSearch;
        });

        const [sort] = sorting;
        if (sort && sortValue[sort.id]) {
            const get = sortValue[sort.id];
            result = [...result].sort((a, b) => {
                const cmp = get(a) > get(b) ? 1 : get(a) < get(b) ? -1 : 0;
                return sort.desc ? -cmp : cmp;
            });
        }
        return result;
    }, [rows, search, status, sorting]);

    const lastPage = Math.max(1, Math.ceil(filtered.length / perPage));
    const currentPage = Math.min(page, lastPage);
    const data = filtered.slice((currentPage - 1) * perPage, currentPage * perPage);
    const meta = {
        current_page: currentPage,
        last_page: lastPage,
        per_page: perPage,
        total: filtered.length,
        from: filtered.length ? (currentPage - 1) * perPage + 1 : 0,
        to: Math.min(currentPage * perPage, filtered.length),
    };

    const columns = useMemo(
        () => getColumns({
            onSortChange: (columnId, direction) => {
                setSorting(direction ? [{ id: columnId, desc: direction === 'desc' }] : []);
                setPage(1);
            },
            onKonfirmasi: setKonfirmasiPo,
            onHapus: setHapusPo,
        }),
        []
    );

    const table = useReactTable({
        data,
        columns,
        getRowId: (row) => row.uuid,
        getCoreRowModel: getCoreRowModel(),
        manualPagination: true,
        manualSorting: true,
        manualFiltering: true,
        pageCount: lastPage,
        state: { sorting },
    });

    const counts = useMemo(
        () => Object.fromEntries(SUMMARY_STATUSES.map((s) => [s, rows.filter((po) => po.status === s).length])),
        [rows]
    );

    const hasFilter = search.trim() !== '' || status !== 'all';

    const breadcrumbs = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Purchase Order', href: '/purchase-order' },
    ];

    return (
        <DashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Purchase Order - PAGORA" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
                            <FileText className="size-6 text-primary" />
                            Purchase Order
                        </h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Buat pesanan bahan baku, negosiasikan harga dengan vendor, dan pantau statusnya.
                        </p>
                    </div>
                    <Button asChild className="gap-2 cursor-pointer shadow-sm">
                        <Link href="/purchase-order/create">
                            <Plus className="size-4" />
                            Buat Purchase Order
                        </Link>
                    </Button>
                </div>

                <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    {SUMMARY_STATUSES.map((s) => (
                        <button
                            key={s}
                            type="button"
                            onClick={() => { setStatus(status === s ? 'all' : s); setPage(1); }}
                            className={cn(
                                'text-left rounded-lg border bg-card p-4 transition-colors hover:bg-muted/50 cursor-pointer',
                                status === s && 'ring-2 ring-primary'
                            )}
                        >
                            <p className="text-xs text-muted-foreground">{STATUS_PO[s].label}</p>
                            <p className="text-2xl font-bold tabular-nums mt-1">{counts[s]}</p>
                        </button>
                    ))}
                </div>

                <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-3 p-4 rounded-lg border bg-card/60 backdrop-blur-xs">
                    <div className="flex flex-1 flex-col sm:flex-row items-center gap-3">
                        <div className="relative w-full sm:w-72">
                            <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground pointer-events-none" />
                            <Input
                                placeholder="Cari nomor PO, vendor, komoditas..."
                                value={search}
                                onChange={(e) => { setSearch(e.target.value); setPage(1); }}
                                className="pl-9 h-9"
                            />
                        </div>
                        <div className="w-full sm:w-56">
                            <Combobox
                                options={STATUS_FILTER_OPTIONS}
                                value={status}
                                onChange={(val) => { setStatus(val || 'all'); setPage(1); }}
                                placeholder="Semua Status"
                                searchPlaceholder="Cari status..."
                                className="h-9"
                            />
                        </div>
                    </div>

                    {hasFilter && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => { setSearch(''); setStatus('all'); setSorting([]); setPage(1); }}
                            className="h-9 gap-1 text-muted-foreground hover:text-foreground cursor-pointer"
                        >
                            <FilterX className="size-4" />
                            Reset Filter
                        </Button>
                    )}
                </div>

                <div className="space-y-4">
                    <DataTable table={table} emptyMessage="Belum ada purchase order yang sesuai." />
                    <DataTablePagination
                        meta={meta}
                        onPageChange={setPage}
                        onPerPageChange={(value) => { setPerPage(Number(value)); setPage(1); }}
                    />
                </div>
            </div>

            <KonfirmasiNegosiasiDialog
                po={konfirmasiPo}
                open={Boolean(konfirmasiPo)}
                onOpenChange={(open) => !open && setKonfirmasiPo(null)}
            />
            <HapusDrafDialog
                po={hapusPo}
                open={Boolean(hapusPo)}
                onOpenChange={(open) => !open && setHapusPo(null)}
                onDeleted={refresh}
            />
        </DashboardLayout>
    );
}
