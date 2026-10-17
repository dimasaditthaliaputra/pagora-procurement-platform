<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Komoditas;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface KomoditasRepositoryInterface
{
    /**
     * Paginate commodities with search, filters, and sorting.
     *
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $columns
     */
    public function paginate(
        array $filters,
        int $perPage = 10,
        array $columns = [
            'id',
            'uuid',
            'slug',
            'nama_komoditas',
            'sku',
            'kategori',
            'satuan',
            'kode_referensi_api',
            'status_aktif',
            'created_at',
        ]
    ): LengthAwarePaginator;

    /**
     * Find a commodity by its UUID.
     */
    public function findByUuid(string $uuid): ?Komoditas;

    /**
     * Find a commodity by its slug.
     */
    public function findBySlug(string $slug): ?Komoditas;

    /**
     * Find a commodity by its SKU.
     */
    public function findBySku(string $sku): ?Komoditas;

    /**
     * Create a new commodity record.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Komoditas;

    /**
     * Update commodity attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Komoditas $komoditas, array $data): bool;

    /**
     * Soft delete a commodity.
     */
    public function delete(Komoditas $komoditas): bool;

    /**
     * Check if a slug already exists, optionally ignoring a specific UUID.
     */
    public function slugExists(string $slug, ?string $ignoreUuid = null): bool;

    /**
     * Check if an SKU already exists, optionally ignoring a specific UUID.
     */
    public function skuExists(string $sku, ?string $ignoreUuid = null): bool;
}
