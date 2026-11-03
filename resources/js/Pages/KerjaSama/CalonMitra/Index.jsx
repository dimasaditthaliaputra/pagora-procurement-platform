import { useState, useEffect, useRef } from 'react';
import { Head, router } from '@inertiajs/react';
import { useReactTable, getCoreRowModel } from '@tanstack/react-table';
import { Building2, FilterX, Handshake, MapPin, Search } from 'lucide-react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { DataTable } from '@/Components/data-table/data-table';
import { DataTablePagination } from '@/Components/data-table/data-table-pagination';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Card, CardContent } from '@/Components/ui/card';
import { getColumns } from './columns';

export default function CalonMitraIndex({ tenants, filters = {} }) {
    const data = tenants?.data ?? [];
    const meta = tenants?.meta ?? {
        current_page: tenants?.current_page ?? 1,
        last_page: tenants?.last_page ?? 1,
        per_page: tenants?.per_page ?? 12,
        total: tenants?.total ?? data.length,
        from: tenants?.from ?? 1,
        to: tenants?.to ?? data.length,
    };

    const [search, setSearch] = useState(filters.search ?? '');
    const [lokasi, setLokasi] = useState(filters.lokasi ?? '');
    const [loading, setLoading] = useState(false);

    const isFirstRender = useRef(true);

    // Sync state when server-driven filter props change
    useEffect(() => {
        setSearch(filters.search ?? '');
        setLokasi(filters.lokasi ?? '');
    }, [filters.search, filters.lokasi]);

    const applyFilters = (newFilters = {}) => {
        const merged = {
            search: search.trim() || undefined,
            lokasi: lokasi.trim() || undefined,
            per_page: meta.per_page,
            page: 1,
            ...newFilters,
        };

        const cleanParams = {};
        Object.entries(merged).forEach(([key, val]) => {
            if (val !== undefined && val !== '') {
                cleanParams[key] = val;
            }
        });

        router.get('/kerja-sama/calon-mitra', cleanParams, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['tenants', 'filters'],
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    };

    // Debounce search
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }

        const timer = setTimeout(() => {
            if (search !== (filters.search ?? '')) {
                applyFilters({ search: search.trim() || undefined, lokasi: lokasi.trim() || undefined });
            }
        }, 400);

        return () => clearTimeout(timer);
    }, [search]);

    // Debounce lokasi
    useEffect(() => {
        if (lokasi === (filters.lokasi ?? '')) return;

        const timer = setTimeout(() => {
            applyFilters({ search: search.trim() || undefined, lokasi: lokasi.trim() || undefined });
        }, 400);

        return () => clearTimeout(timer);
    }, [lokasi]);

    const handleResetFilters = () => {
        setSearch('');
        setLokasi('');
        router.get('/kerja-sama/calon-mitra', {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['tenants', 'filters'],
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    };

    const handlePageChange = (page) => {
        router.get(
            '/kerja-sama/calon-mitra',
            {
                search: search.trim() || undefined,
                lokasi: lokasi.trim() || undefined,
                per_page: meta.per_page,
                page,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['tenants', 'filters'],
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            }
        );
    };

    const handlePerPageChange = (per_page) => {
        router.get(
            '/kerja-sama/calon-mitra',
            {
                search: search.trim() || undefined,
                lokasi: lokasi.trim() || undefined,
                per_page,
                page: 1,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['tenants', 'filters'],
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            }
        );
    };

    const columns = getColumns();

    const table = useReactTable({
        data,
        columns,
        getCoreRowModel: getCoreRowModel(),
        manualPagination: true,
        manualSorting: true,
        pageCount: meta.last_page,
        getRowId: (row) => row.uuid,
        state: {
            pagination: {
                pageIndex: meta.current_page - 1,
                pageSize: meta.per_page,
            },
        },
    });

    const hasActiveFilters = (filters.search ?? '') !== '' || (filters.lokasi ?? '') !== '';

    return (
        <DashboardLayout>
            <Head title="Calon Mitra" />

            <div className="space-y-6">
                {/* Page Header */}
                <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <Handshake className="size-6 text-emerald-600 dark:text-emerald-400" />
                            <h1 className="text-2xl font-bold tracking-tight text-foreground">
                                Daftar Calon Mitra
                            </h1>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Temukan perusahaan yang belum menjadi mitra kerja sama Anda.
                        </p>
                    </div>
                </div>

                {/* Stats Card */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Card className="border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/30">
                        <CardContent className="flex items-center gap-4 p-4">
                            <div className="flex size-10 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/50">
                                <Building2 className="size-5 text-emerald-600 dark:text-emerald-400" />
                            </div>
                            <div>
                                <p className="text-xs font-medium text-emerald-700 dark:text-emerald-300">
                                    Total Calon Mitra
                                </p>
                                <p className="text-2xl font-bold text-emerald-800 dark:text-emerald-200">
                                    {meta.total ?? 0}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground pointer-events-none" />
                        <Input
                            placeholder="Cari nama perusahaan..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="pl-9"
                        />
                    </div>

                    <div className="relative sm:w-56">
                        <MapPin className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground pointer-events-none" />
                        <Input
                            placeholder="Filter lokasi / kota..."
                            value={lokasi}
                            onChange={(e) => setLokasi(e.target.value)}
                            className="pl-9"
                        />
                    </div>

                    {hasActiveFilters && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleResetFilters}
                            className="shrink-0"
                        >
                            <FilterX className="mr-1.5 size-4" />
                            Reset Filter
                        </Button>
                    )}
                </div>

                {/* DataTable */}
                <div className="rounded-xl border bg-card shadow-sm overflow-hidden">
                    <DataTable
                        table={table}
                        loading={loading}
                        emptyMessage={
                            hasActiveFilters
                                ? 'Tidak ada calon mitra yang sesuai dengan filter.'
                                : 'Semua perusahaan yang tersedia sudah menjadi mitra Anda.'
                        }
                    />
                    <div className="border-t px-4 py-3">
                        <DataTablePagination
                            meta={meta}
                            onPageChange={handlePageChange}
                            onPerPageChange={handlePerPageChange}
                        />
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}
