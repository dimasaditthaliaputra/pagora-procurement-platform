<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Invoice;
use App\Models\PurchaseOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface InvoiceRepositoryInterface
{
    /**
     * Paginate invoices belonging to a vendor.
     *
     * @param array<string, mixed> $filters
     */
    public function paginateForVendor(
        int $vendorId,
        array $filters,
        int $perPage = 10
    ): LengthAwarePaginator;

    /**
     * Find a purchase order belonging to a vendor.
     */
    public function findPurchaseOrderForVendor(
        string $uuid,
        int $vendorId
    ): ?PurchaseOrder;

    /**
     * Get PO items with accepted QC quantities and invoiced quantities.
     *
     * @return Collection<int, \App\Models\PoItem>
     */
    public function getAvailableItems(PurchaseOrder $purchaseOrder): Collection;

    /**
     * Find an invoice by UUID, scoped to its vendor.
     */
    public function findByUuid(
        string $uuid,
        int $vendorId
    ): ?Invoice;

    /**
     * Create an invoice with its invoice items.
     *
     * @param array<string, mixed> $attributes
     * @param list<array<string, mixed>> $items
     */
    public function createWithItems(
        array $attributes,
        array $items
    ): Invoice;
    /**
     * Mengambil daftar PO vendor yang memiliki barang lolos QC.
     */
    public function getPurchaseOrdersForVendorWithAvailableItems(int $vendorId): Collection;

    /**
     * Paginate invoices belonging to a buyer company (tenant).
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateForTenant(
        int $tenantId,
        array $filters,
        int $perPage = 10
    ): LengthAwarePaginator;

    /**
     * Find an invoice by UUID, scoped to buyer company (tenant).
     */
    public function findByUuidForTenant(
        string $uuid,
        int $tenantId
    ): ?Invoice;

    /**
     * Get aggregate matching status counts for a buyer company (tenant).
     *
     * @return array{total: int, pending: int, matched: int, mismatched: int}
     */
    public function getMatchingCountsForTenant(int $tenantId): array;

    /**
     * Update invoice matching verification fields.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateMatching(
        Invoice $invoice,
        array $attributes
    ): bool;
}
