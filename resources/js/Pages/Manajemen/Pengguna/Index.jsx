import { useState, useEffect, useMemo, useRef } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    useReactTable,
    getCoreRowModel,
} from '@tanstack/react-table';
import { Plus, Search, FilterX, Users2 } from 'lucide-react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { DataTable } from '@/Components/data-table/data-table';
import { DataTablePagination } from '@/Components/data-table/data-table-pagination';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Combobox } from '@/Components/combobox'; // <-- Import Combobox
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { getColumns } from '@/Pages/Manajemen/Pengguna/columns';
import { PegawaiFormModal } from '@/Pages/Manajemen/Pengguna/components/PegawaiFormModal';
import { PegawaiDetailModal } from '@/Pages/Manajemen/Pengguna/components/PegawaiDetailModal';
import { PegawaiDeleteDialog } from '@/Pages/Manajemen/Pengguna/components/PegawaiDeleteDialog';

export default function PenggunaIndex({
    pegawai,
    filters: rawFilters = {},
    role_options = [],
    default_password = 'Pagora@2026',
}) {
    const filters = Array.isArray(rawFilters) || !rawFilters ? {} : rawFilters;
    const data = pegawai?.data ?? [];
    const meta = pegawai?.meta ?? {
        current_page: pegawai?.current_page ?? 1,
        last_page: pegawai?.last_page ?? 1,
        per_page: pegawai?.per_page ?? 10,
        total: pegawai?.total ?? data.length,
        from: pegawai?.from ?? 1,
        to: pegawai?.to ?? data.length,
    };

    const [search, setSearch] = useState(filters.search ?? '');
    const [role, setRole] = useState(filters.role ?? 'all');
    const [statusAktif, setStatusAktif] = useState(
        filters.status_aktif !== undefined && filters.status_aktif !== null && filters.status_aktif !== ''
            ? String(filters.status_aktif)
            : 'all'
    );
    const [loading, setLoading] = useState(false);

    // Initial sort state
    const [sorting, setSorting] = useState(
        typeof filters.sort === 'string' && filters.sort
            ? [{ id: filters.sort, desc: filters.direction === 'desc' }]
            : []
    );

    // Modal states
    const [formModalOpen, setFormModalOpen] = useState(false);
    const [detailModalOpen, setDetailModalOpen] = useState(false);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [selectedPegawai, setSelectedPegawai] = useState(null);

    const isFirstRender = useRef(true);

    // Sync state with incoming props
    useEffect(() => {
        setSearch(filters.search ?? '');
        setRole(filters.role ?? 'all');
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
    }, [filters.search, filters.role, filters.status_aktif, filters.sort, filters.direction]);

    const applyFilters = (newFilters = {}) => {
        const activeSort = typeof sorting[0]?.id === 'string' ? sorting[0].id : undefined;
        const merged = {
            search: search.trim() || undefined,
            role: role !== 'all' ? role : undefined,
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

        router.get('/manajemen/pengguna', cleanParams, {
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
            const currentSearchInUrl = filters.search ?? '';
            if (search.trim() !== currentSearchInUrl) {
                applyFilters({ search: search.trim() || undefined, page: 1 });
            }
        }, 400);

        return () => clearTimeout(timer);
    }, [search]);

    const handleSortChange = (columnId, direction) => {
        const newSorting = direction ? [{ id: columnId, desc: direction === 'desc' }] : [];
        setSorting(newSorting);
        applyFilters({
            sort: direction ? columnId : undefined,
            direction: direction || undefined,
            page: 1,
        });
    };

    const handleRoleChange = (val) => {
        const nextVal = !val || val === 'all' ? 'all' : val;
        setRole(nextVal);
        applyFilters({ role: nextVal !== 'all' ? nextVal : undefined, page: 1 });
    };

    const handleStatusChange = (val) => {
        const nextVal = !val || val === 'all' ? 'all' : val;
        setStatusAktif(nextVal);
        applyFilters({ status_aktif: nextVal !== 'all' ? nextVal : undefined, page: 1 });
    };

    const handleResetFilters = () => {
        setSearch('');
        setRole('all');
        setStatusAktif('all');
        setSorting([]);
        router.get(
            '/manajemen/pengguna',
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            }
        );
    };

    // Modal action handlers
    const handleOpenCreate = () => {
        setSelectedPegawai(null);
        setFormModalOpen(true);
    };

    const handleOpenEdit = (pegawaiItem) => {
        setSelectedPegawai(pegawaiItem);
        setFormModalOpen(true);
    };

    const handleOpenDetail = (pegawaiItem) => {
        setSelectedPegawai(pegawaiItem);
        setDetailModalOpen(true);
    };

    const handleOpenDelete = (pegawaiItem) => {
        setSelectedPegawai(pegawaiItem);
        setDeleteDialogOpen(true);
    };

    // Memoized options untuk Combobox Filter Role
    const roleComboboxOptions = useMemo(() => {
        const options = [{ value: 'all', label: 'Semua Role' }];
        const seen = new Set(['all']);
        for (const opt of role_options) {
            if (!seen.has(opt.value)) {
                seen.add(opt.value);
                options.push({ value: opt.value, label: opt.label });
            }
        }
        return options;
    }, [role_options]);

    const columns = useMemo(
        () =>
            getColumns({
                onSortChange: handleSortChange,
                onViewDetail: handleOpenDetail,
                onEdit: handleOpenEdit,
                onDelete: handleOpenDelete,
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
        pageCount: meta.last_page,
        state: {
            sorting,
        },
    });

    const breadcrumbs = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Manajemen', href: '#' },
        { label: 'Pengguna', href: '/manajemen/pengguna' },
    ];

    return (
        <DashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Kelola Internal Perusahaan - PAGORA" />

            <div className="flex flex-col gap-6">
                {/* Header Section */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2.5">
                            <Users2 className="size-6 text-primary" />
                            Kelola Internal Perusahaan
                        </h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Manajemen akun akses pegawai internal (Buyer dan QC Gudang) dengan otorisasi berbasis peran.
                        </p>
                    </div>
                    <div>
                        <Button
                            onClick={handleOpenCreate}
                            className="gap-2 cursor-pointer shadow-sm"
                        >
                            <Plus className="size-4" />
                            Tambah Pegawai Baru
                        </Button>
                    </div>
                </div>

                {/* Filter Toolbar */}
                <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-3 p-4 rounded-lg border bg-card/60 backdrop-blur-xs">
                    <div className="flex flex-1 flex-col sm:flex-row items-center gap-3">
                        {/* Input Pencarian */}
                        <div className="relative w-full sm:w-80">
                            <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground pointer-events-none" />
                            <Input
                                placeholder="Cari nama pegawai atau email..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="pl-9 h-9"
                            />
                        </div>

                        {/* Grup Dropdown Filter */}
                        <div className="flex w-full sm:w-auto flex-col sm:flex-row items-center gap-3">
                            {/* <-- Diubah menjadi Combobox --> */}
                            <div className="w-full sm:w-56">
                                <Combobox
                                    options={roleComboboxOptions}
                                    value={role}
                                    onChange={handleRoleChange}
                                    placeholder="Semua Role"
                                    searchPlaceholder="Cari role..."
                                    emptyMessage="Role tidak ditemukan."
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
                    </div>

                    {/* Tombol Reset */}
                    {(search.trim() !== '' || role !== 'all' || statusAktif !== 'all') && (
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
                        emptyMessage="Tidak ada data akun pegawai internal yang terdaftar."
                    />

                    <DataTablePagination
                        meta={meta}
                        onPageChange={(page) => applyFilters({ page })}
                        onPerPageChange={(perPage) => applyFilters({ per_page: perPage, page: 1 })}
                    />
                </div>
            </div>

            {/* Modal: Form Tambah / Ubah Pegawai */}
            <PegawaiFormModal
                open={formModalOpen}
                onOpenChange={setFormModalOpen}
                pegawai={selectedPegawai}
                role_options={role_options}
                default_password={default_password}
            />

            {/* Modal: Detail Akun Pegawai */}
            <PegawaiDetailModal
                open={detailModalOpen}
                onOpenChange={setDetailModalOpen}
                pegawai={selectedPegawai}
                onEdit={handleOpenEdit}
            />

            {/* Modal: Konfirmasi Soft Delete */}
            <PegawaiDeleteDialog
                open={deleteDialogOpen}
                onOpenChange={setDeleteDialogOpen}
                pegawai={selectedPegawai}
            />
        </DashboardLayout>
    );
}