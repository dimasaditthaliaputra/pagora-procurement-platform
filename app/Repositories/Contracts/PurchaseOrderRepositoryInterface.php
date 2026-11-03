<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\PurchaseOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PurchaseOrderRepositoryInterface
{
    /**
     * Paginate purchase orders for a specific tenant with filters and sorting.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateForTenant(int $tenantId, array $filters, int $perPage = 10): LengthAwarePaginator;

    /**
     * Paginate purchase orders for a specific vendor with filters and sorting.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateForVendor(int $vendorId, array $filters, int $perPage = 10): LengthAwarePaginator;

    /**
     * Find a purchase order by UUID, optionally scoped to a tenant.
     */
    public function findByUuid(string $uuid, ?int $tenantId = null): ?PurchaseOrder;

    /**
     * Generate the next unique PO number for the specified year.
     */
    public function getNextPoNumber(int $year): string;

    /**
     * Create a new purchase order.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): PurchaseOrder;

    /**
     * Update an existing purchase order.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(PurchaseOrder $purchaseOrder, array $attributes): bool;

    /**
     * Soft delete a purchase order.
     */
    public function delete(PurchaseOrder $purchaseOrder): bool;

    /**
     * Sync items for a purchase order.
     *
     * @param  list<array<string, mixed>>  $items
     */
    public function syncItems(PurchaseOrder $purchaseOrder, array $items): void;

    /**
     * Get summary statistics for purchase orders belonging to a tenant.
     *
     * @return array<string, mixed>
     */
    public function getStatisticsForTenant(int $tenantId): array;

    /**
     * Get summary statistics for purchase orders belonging to a vendor.
     *
     * @return array<string, mixed>
     */
    public function getStatisticsForVendor(int $vendorId): array;
}
