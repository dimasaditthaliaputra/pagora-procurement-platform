import { useState, useEffect, useMemo, useRef } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    useReactTable,
    getCoreRowModel,
} from '@tanstack/react-table';
import { Plus, Search, FilterX, Boxes } from 'lucide-react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { DataTable } from '@/Components/data-table/data-table';
import { DataTablePagination } from '@/Components/data-table/data-table-pagination';
import { getColumns } from '@/Pages/Master/Komoditas/columns';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Combobox } from '@/Components/combobox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';

export default function KomoditasIndex({
    komoditas,
    filters: rawFilters = {},
    kategori_options = [],
}) {
    const filters = Array.isArray(rawFilters) || !rawFilters ? {} : rawFilters;
    const data = komoditas?.data ?? [];
    const meta = komoditas?.meta ?? {
        current_page: komoditas?.current_page ?? 1,
        last_page: komoditas?.last_page ?? 1,
        per_page: komoditas?.per_page ?? 10,
        total: komoditas?.total ?? data.length,
        from: komoditas?.from ?? 1,
        to: komoditas?.to ?? data.length,
    };

    const [search, setSearch] = useState(filters.search ?? '');
    const [kategori, setKategori] = useState(filters.kategori ?? 'all');
    const [statusAktif, setStatusAktif] = useState(
        filters.status_aktif !== undefined && filters.status_aktif !== null && filters.status_aktif !== ''
            ? String(filters.status_aktif)
            : 'all'
    );
    const [loading, setLoading] = useState(false);

    // Initial sort state (pastikan filters.sort adalah string dan bukan method Array.prototype.sort)
    const [sorting, setSorting] = useState(
        typeof filters.sort === 'string' && filters.sort
            ? [{ id: filters.sort, desc: filters.direction === 'desc' }]
            : []
    );

    const isFirstRender = useRef(true);

    // Sync state with incoming props (e.g. from popstate, reset, or URL change)
    useEffect(() => {
        setSearch(filters.search ?? '');
        setKategori(filters.kategori ?? 'all');
        setStatusAktif(
            filters.status_aktif !== undefined && filters.status_aktif !== null && filters.status_aktif !== ''
                ? String(filters.status_aktif)
                : 'all'
        );
        if (typeof filters.sort === 'string' && filters.sort) {
            setSorting([{ id: filters.sort, desc: filters.direction === 'desc' }]);
        } else {
            setSorting([]);
        }
    }, [filters.search, filters.kategori, filters.status_aktif, filters.sort, filters.direction]);

    const applyFilters = (newFilters = {}) => {
        const activeSort = typeof sorting[0]?.id === 'string' ? sorting[0].id : undefined;
        const merged = {
            search: search.trim() || undefined,
            kategori: kategori !== 'all' ? kategori : undefined,
            status_aktif: statusAktif !== 'all' ? statusAktif : undefined,
            sort: activeSort,
            direction: activeSort && sorting[0] ? (sorting[0].desc ? 'desc' : 'asc') : undefined,
            per_page: meta.per_page,
            page: 1,
            ...newFilters,
        };

        const cleanParams = {};
        Object.entries(merged).forEach(([key, val]) => {
            if (val !== undefined && val !== null && val !== '' && val !== 'all') {
                cleanParams[key] = val;
            }
        });

        router.get('/master/komoditas', cleanParams, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    };

    // Debounced search
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }

        const timer = setTimeout(() => {
            const currentSearchInUrl = typeof filters.search === 'string' ? filters.search : '';
            if (search.trim() !== currentSearchInUrl) {
                applyFilters({ search: search.trim() || undefined, page: 1 });
            }
        }, 400);

        return () => clearTimeout(timer);
    }, [search]);

    const handleSortChange = (columnId, direction) => {
        const validColumn = typeof columnId === 'string' ? columnId : undefined;
        const newSorting = validColumn && direction ? [{ id: validColumn, desc: direction === 'desc' }] : [];
        setSorting(newSorting);
        applyFilters({
            sort: direction ? validColumn : undefined,
            direction: direction || undefined,
            page: 1,
        });
    };

    const handleKategoriChange = (val) => {
        const nextVal = (!val || val === 'all') ? 'all' : val;
        setKategori(nextVal);
        applyFilters({ kategori: nextVal !== 'all' ? nextVal : undefined, page: 1 });
    };

    const handleStatusChange = (val) => {
        const nextVal = (!val || val === 'all') ? 'all' : val;
        setStatusAktif(nextVal);
        applyFilters({ status_aktif: nextVal !== 'all' ? nextVal : undefined, page: 1 });
    };

    const handleResetFilters = () => {
        setSearch('');
        setKategori('all');
        setStatusAktif('all');
        setSorting([]);
        router.get('/master/komoditas', {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    };

    const kategoriComboboxOptions = useMemo(() => [
        { value: 'all', label: 'Semua Kategori' },
        ...kategori_options.map((k) => ({ value: k, label: k })),
    ], [kategori_options]);

    const columns = useMemo(
        () => getColumns({ onSortChange: handleSortChange }),
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
        pageCount: meta.last_page,
        state: {
            sorting,
        },
    });

    const breadcrumbs = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Master Data', href: '#' },
        { label: 'Komoditas', href: '/master/komoditas' },
    ];

    return (
        <DashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Master Komoditas - PAGORA" />

            <div className="flex flex-col gap-6">
                {/* Header Section */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
                            <Boxes className="size-6 text-primary" />
                            Master Komoditas Bahan Baku
                        </h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Kelola data bahan baku manufaktur, acuan kode API pangan, dan satuan pengadaan.
                        </p>
                    </div>
                    <div>
                        <Button asChild className="gap-2 cursor-pointer shadow-sm">
                            <Link href="/master/komoditas/create">
                                <Plus className="size-4" />
                                Tambah Komoditas
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Filter Toolbar */}
                <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-3 p-4 rounded-lg border bg-card/60 backdrop-blur-xs">
                    <div className="flex flex-1 flex-col sm:flex-row items-center gap-3">
                        <div className="relative w-full sm:w-72">
                            <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground pointer-events-none" />
                            <Input
                                placeholder="Cari nama, SKU, atau ref API..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="pl-9 h-9"
                            />
                        </div>

                        <div className="w-full sm:w-56">
                            <Combobox
                                options={kategoriComboboxOptions}
                                value={kategori}
                                onChange={handleKategoriChange}
                                placeholder="Semua Kategori"
                                searchPlaceholder="Cari kategori..."
                                emptyMessage="Kategori tidak ditemukan."
                                className="h-9"
                            />
                        </div>

                        <div className="w-full sm:w-40">
                            <Select value={statusAktif} onValueChange={handleStatusChange}>
                                <SelectTrigger className="h-9">
                                    <SelectValue placeholder="Semua Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Semua Status</SelectItem>
                                    <SelectItem value="true">Aktif</SelectItem>
                                    <SelectItem value="false">Non-aktif</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    {(search.trim() !== '' || kategori !== 'all' || statusAktif !== 'all') && (
                        <div>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={handleResetFilters}
                                className="h-9 gap-1 text-muted-foreground hover:text-foreground cursor-pointer"
                            >
                                <FilterX className="size-4" />
                                Reset Filter
                            </Button>
                        </div>
                    )}
                </div>

                {/* Data Table */}
                <div className="space-y-4">
                    <DataTable
                        table={table}
                        loading={loading}
                        emptyMessage="Tidak ada data komoditas yang ditemukan."
                    />

                    <DataTablePagination
                        meta={meta}
                        onPageChange={(page) => applyFilters({ page })}
                        onPerPageChange={(perPage) => applyFilters({ per_page: perPage, page: 1 })}
                    />
                </div>
            </div>
        </DashboardLayout>
    );
}
