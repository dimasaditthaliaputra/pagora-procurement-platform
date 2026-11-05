import { useEffect, useMemo, useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    FilterX,
    PackageCheck,
    Search,
    Truck,
} from 'lucide-react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { CardGridPagination } from '@/Pages/InspeksiQc/components/CardGridPagination';
import { InspectableOrderList } from '@/Pages/InspeksiQc/components/InspectableOrderCard';
import { InspectableOrderGridSkeleton } from '@/Pages/InspeksiQc/components/InspectableOrderCardSkeleton';

const ROUTE_FILTER_OPTIONS = [
    { value: 'all', label: 'Semua Jalur Pengiriman' },
    { value: 'Darat', label: 'Jalur Darat' },
    { value: 'Laut', label: 'Jalur Laut' },
    { value: 'Udara', label: 'Jalur Udara' },
    { value: 'Kereta Api', label: 'Jalur Kereta Api' },
];

const SORT_OPTIONS = [
    { value: 'tanggal_po:desc', label: 'Tanggal Terbaru (DESC)' },
    { value: 'tanggal_po:asc', label: 'Tanggal Terlama (ASC)' },
    { value: 'estimated_arrival_date:asc', label: 'ETA Terdekat' },
    { value: 'estimated_arrival_date:desc', label: 'ETA Terjauh' },
];

export default function InspeksiQcInTransitOrders({
    orders,
    statistics = {},
    filters: rawFilters = {},
}) {
    const filters = Array.isArray(rawFilters) || !rawFilters ? {} : rawFilters;
    const data = orders?.data ?? [];
    const meta = orders?.meta ?? {
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: data.length,
        from: data.length ? 1 : 0,
        to: data.length,
    };

    const initialSortKey = `${filters.sort || 'tanggal_po'}:${filters.direction || 'desc'}`;

    const [search, setSearch] = useState(filters.search ?? '');
    const [routeFilter, setRouteFilter] = useState(filters.route ?? 'all');
    const [sortOption, setSortOption] = useState(initialSortKey);
    const [isLoading, setIsLoading] = useState(false);

    const isInitialMount = useRef(true);

    const applyFilters = (overrides = {}) => {
        const activeSortKey = overrides.sortKey !== undefined ? overrides.sortKey : sortOption;
        const [sortCol, sortDir] = activeSortKey.split(':');

        const query = {
            search: overrides.search !== undefined ? overrides.search : search,
            route: overrides.route !== undefined ? overrides.route : routeFilter,
            sort: sortCol || 'tanggal_po',
            direction: sortDir || 'desc',
            page: overrides.page !== undefined ? overrides.page : meta.current_page,
            per_page: overrides.per_page !== undefined ? overrides.per_page : meta.per_page,
        };

        if (!query.search || !query.search.trim()) {
            delete query.search;
        } else {
            query.search = query.search.trim();
        }

        if (!query.route || query.route === 'all') {
            delete query.route;
        }

        if (query.sort === 'tanggal_po' && query.direction === 'desc') {
            delete query.sort;
            delete query.direction;
        }

        router.get('/inspeksi-qc/pesanan-in-transit', query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['orders', 'filters', 'statistics'],
            onStart: () => setIsLoading(true),
            onFinish: () => setIsLoading(false),
        });
    };

    useEffect(() => {
        if (isInitialMount.current) {
            isInitialMount.current = false;
            return;
        }

        const timer = setTimeout(() => {
            applyFilters({ search, page: 1 });
        }, 400);

        return () => clearTimeout(timer);
    }, [search]);

    const handleRouteChange = (nextRoute) => {
        const target = nextRoute || 'all';
        setRouteFilter(target);
        applyFilters({ route: target, page: 1 });
    };

    const handleSortChange = (nextSortKey) => {
        const target = nextSortKey || 'tanggal_po:desc';
        setSortOption(target);
        applyFilters({ sortKey: target, page: 1 });
    };

    const handlePageChange = (nextPage) => {
        applyFilters({ page: nextPage });
    };

    const handlePerPageChange = (nextPerPage) => {
        applyFilters({ per_page: nextPerPage, page: 1 });
    };

    const handleResetFilters = () => {
        setSearch('');
        setRouteFilter('all');
        setSortOption('tanggal_po:desc');
        router.get(
            '/inspeksi-qc/pesanan-in-transit',
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['orders', 'filters', 'statistics'],
                onStart: () => setIsLoading(true),
                onFinish: () => setIsLoading(false),
            }
        );
    };

    const totalInTransit = useMemo(
        () => statistics?.in_transit_orders ?? meta.total ?? 0,
        [statistics, meta.total]
    );

    const hasActiveFilters =
        (search && search.trim() !== '') ||
        (routeFilter && routeFilter !== 'all') ||
        sortOption !== 'tanggal_po:desc';

    const skeletonCount = Math.min(Math.max(Number(meta.per_page) || 10, 3), 10);

    const breadcrumbs = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Inspeksi QC', href: '/inspeksi-qc' },
        { label: 'Pesanan Dalam Pengiriman (In Transit)', href: '#' },
    ];

    return (
        <DashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Daftar Pesanan Dalam Pengiriman (In Transit) - PAGORA" />

            <div className="flex flex-col gap-6">
                {/* Header Halaman */}
                <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <div className="flex flex-wrap items-center gap-2.5">
                            <Truck className="size-6 text-sky-600 dark:text-sky-400 shrink-0" />
                            <h1 className="text-2xl font-bold tracking-tight text-foreground">
                                Daftar Pesanan Dalam Pengiriman (In Transit)
                            </h1>
                            <Badge variant="sky" className="tabular-nums">
                                {totalInTransit} Pesanan Menunggu QC
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground mt-1 max-w-3xl">
                            Seluruh pesanan bahan baku berstatus In Transit yang siap diperiksa di
                            dermaga gudang. Gunakan tombol aksi pada baris kartu pesanan untuk
                            membuat Form Pengecekan Barang atau melaporkan kerusakan.
                        </p>
                    </div>

                    <Button variant="outline" size="sm" asChild className="h-9 shrink-0 w-fit cursor-pointer">
                        <Link href="/inspeksi-qc">
                            <ArrowLeft className="mr-1.5 size-4" />
                            Kembali ke Inspeksi QC
                        </Link>
                    </Button>
                </div>

                {/* Filter & Pengurutan Kartu Pesanan */}
                <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-3 p-4 rounded-lg border bg-card/60 backdrop-blur-xs">
                    <div className="flex flex-1 flex-col sm:flex-row flex-wrap items-center gap-3">
                        <div className="relative w-full sm:w-80">
                            <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground pointer-events-none" />
                            <Input
                                placeholder="Cari nomor PO, vendor, armada, plat nomor..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="pl-9 h-9 bg-background"
                            />
                        </div>

                        <div className="w-full sm:w-52">
                            <Select value={routeFilter} onValueChange={handleRouteChange}>
                                <SelectTrigger className="h-9 text-sm w-full bg-background">
                                    <SelectValue placeholder="Semua Jalur Pengiriman" />
                                </SelectTrigger>
                                <SelectContent
                                    position="popper"
                                    side="bottom"
                                    align="start"
                                    avoidCollisions={false}
                                >
                                    {ROUTE_FILTER_OPTIONS.map((opt) => (
                                        <SelectItem key={opt.value} value={opt.value}>
                                            {opt.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="w-full sm:w-56">
                            <Select value={sortOption} onValueChange={handleSortChange}>
                                <SelectTrigger className="h-9 text-sm w-full bg-background">
                                    <SelectValue placeholder="Urutkan Tanggal" />
                                </SelectTrigger>
                                <SelectContent
                                    position="popper"
                                    side="bottom"
                                    align="start"
                                    avoidCollisions={false}
                                >
                                    {SORT_OPTIONS.map((opt) => (
                                        <SelectItem key={opt.value} value={opt.value}>
                                            {opt.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    {hasActiveFilters && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={handleResetFilters}
                            disabled={isLoading}
                            className="h-9 gap-1 text-muted-foreground hover:text-foreground cursor-pointer"
                        >
                            <FilterX className="size-4" />
                            Reset Filter
                        </Button>
                    )}
                </div>

                {/* Konten Utama: Skeleton Loading / Tabular Row-Cards / Empty State + Pagination */}
                <div className="space-y-4">
                    {isLoading ? (
                        <InspectableOrderGridSkeleton count={skeletonCount} />
                    ) : data.length > 0 ? (
                        <InspectableOrderList orders={data} />
                    ) : (
                        <Card className="border-dashed">
                            <CardContent className="flex flex-col items-center justify-center py-14 text-center">
                                <div className="flex size-12 items-center justify-center rounded-full bg-sky-500/10 text-sky-600 dark:text-sky-400 mb-3">
                                    <PackageCheck className="size-6" />
                                </div>
                                <h2 className="text-base font-semibold text-foreground">
                                    {hasActiveFilters
                                        ? 'Tidak ada pesanan In Transit yang cocok dengan filter'
                                        : 'Seluruh pesanan In Transit telah selesai diinspeksi'}
                                </h2>
                                <p className="text-sm text-muted-foreground mt-1 max-w-md">
                                    {hasActiveFilters
                                        ? 'Coba ubah kata kunci pencarian atau reset filter jalur pengiriman untuk menampilkan pesanan lainnya.'
                                        : 'Belum ada pengiriman baru yang sedang menuju gudang saat ini.'}
                                </p>
                                {hasActiveFilters && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={handleResetFilters}
                                        className="mt-4 cursor-pointer"
                                    >
                                        <FilterX className="mr-1.5 size-4" />
                                        Reset Filter
                                    </Button>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    <CardGridPagination
                        meta={meta}
                        loading={isLoading}
                        onPageChange={handlePageChange}
                        onPerPageChange={handlePerPageChange}
                    />
                </div>
            </div>
        </DashboardLayout>
    );
}
