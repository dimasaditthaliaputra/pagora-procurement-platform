<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Pengiriman;
use App\Models\PurchaseOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface PengirimanRepositoryInterface
{
    /**
     * Paginate shipments belonging to a specific tenant (buyer).
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<Pengiriman>
     */
    public function paginateForTenant(int $tenantId, array $filters, int $perPage = 10): LengthAwarePaginator;

    /**
     * Paginate shipments belonging to a specific vendor.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<Pengiriman>
     */
    public function paginateForVendor(int $vendorId, array $filters, int $perPage = 10): LengthAwarePaginator;

    /**
     * Find a shipment by UUID, optionally scoped to a tenant or vendor.
     */
    public function findByUuid(string $uuid, ?int $tenantId = null, ?int $vendorId = null): ?Pengiriman;

    /**
     * Retrieve Purchase Orders belonging to a vendor that are eligible for physical shipment (`disepakati` or `partially_received`).
     *
     * @return Collection<int, PurchaseOrder>
     */
    public function getShippablePurchaseOrdersForVendor(int $vendorId): Collection;

    /**
     * Find a Purchase Order by UUID strictly scoped to a vendor (excluding tenant drafts) for shipment creation.
     */
    public function findVendorPurchaseOrderByUuid(int $vendorId, string $poUuid): ?PurchaseOrder;

    /**
     * Get summary statistics for shipments belonging to a tenant.
     *
     * @return array{total_shipments: int, in_transit: int, arrived: int, ready_to_ship: int}
     */
    public function getStatisticsForTenant(int $tenantId): array;

    /**
     * Get summary statistics for shipments belonging to a vendor.
     *
     * @return array{total_shipments: int, in_transit: int, arrived: int, ready_to_ship: int}
     */
    public function getStatisticsForVendor(int $vendorId): array;

    /**
     * Persist a newly reported shipment.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Pengiriman;
}
