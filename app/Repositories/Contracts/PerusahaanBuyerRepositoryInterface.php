<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\PerusahaanBuyer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface PerusahaanBuyerRepositoryInterface
{
    /**
     * Find a buyer company by its UUID.
     */
    public function findByUuid(string $uuid): ?PerusahaanBuyer;

    /**
     * Retrieve all buyer companies ordered by name.
     *
     * @return Collection<int, PerusahaanBuyer>
     */
    public function getAllOrdered(): Collection;

    /**
     * Create a new buyer company record.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PerusahaanBuyer;

    /**
     * Update a buyer company record.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(PerusahaanBuyer $tenant, array $data): bool;

    /**
     * Paginate tenant companies with search and verification status filter.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 10): LengthAwarePaginator;

    /**
     * Get aggregate verification status counts.
     *
     * @return array{total: int, pending: int, approved: int, rejected: int}
     */
    public function getStatusCounts(): array;

    /**
     * Paginate active, verified tenants that do NOT yet have any partnership with the given vendor.
     *
     * @param  array\u003cstring, mixed\u003e  $filters  Keys: search, lokasi, sort, direction
     */
    public function paginateCalonMitra(int $vendorId, array $filters, int $perPage = 12): LengthAwarePaginator;
}
