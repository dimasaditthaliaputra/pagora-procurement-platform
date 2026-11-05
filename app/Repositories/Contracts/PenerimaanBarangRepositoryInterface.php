<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\LogSengketa;
use App\Models\PenerimaanBarang;
use App\Models\PenerimaanBarangItem;
use App\Models\Pengiriman;
use App\Models\PurchaseOrder;
use App\Models\Sengketa;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface PenerimaanBarangRepositoryInterface
{
    /**
     * Paginate goods receipts scoped to a Tenant.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<PenerimaanBarang>
     */
    public function paginateForTenant(int $tenantId, array $filters, int $perPage = 10): LengthAwarePaginator;

    /**
     * Paginate goods receipts scoped to a Vendor.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<PenerimaanBarang>
     */
    public function paginateForVendor(int $vendorId, array $filters, int $perPage = 10): LengthAwarePaginator;

    /**
     * Find a goods receipt by UUID strictly scoped to a Tenant or Vendor.
     */
    public function findByUuid(string $uuid, ?int $tenantId = null, ?int $vendorId = null): ?PenerimaanBarang;

    /**
     * Find a goods receipt item by UUID strictly scoped to a Tenant or Vendor (for authorized photo streaming).
     */
    public function findItemByUuid(string $itemUuid, ?int $tenantId = null, ?int $vendorId = null): ?PenerimaanBarangItem;

    /**
     * Retrieve Purchase Orders belonging to a Tenant currently in `dalam_pengiriman` (`In Transit`) status.
     *
     * @return Collection<int, PurchaseOrder>
     */
    public function getInspectablePurchaseOrdersForTenant(int $tenantId): Collection;

    /**
     * Find a Tenant's Purchase Order by UUID for QC inspection.
     */
    public function findTenantPurchaseOrderByUuid(int $tenantId, string $poUuid): ?PurchaseOrder;

    /**
     * Get summary metrics for goods receipts belonging to a Tenant.
     *
     * @return array{in_transit_orders: int, total_inspections: int, fully_accepted: int, with_rejections: int}
     */
    public function getStatisticsForTenant(int $tenantId): array;

    /**
     * Get summary metrics for goods receipts belonging to a Vendor.
     *
     * @return array{in_transit_orders: int, total_inspections: int, fully_accepted: int, with_rejections: int}
     */
    public function getStatisticsForVendor(int $vendorId): array;

    /**
     * Create a new immutable goods receipt header (`goods_receipts`).
     *
     * @param  array<string, mixed>  $data
     */
    public function createReceipt(array $data): PenerimaanBarang;

    /**
     * Create a new immutable goods receipt item (`goods_receipt_items`).
     *
     * @param  array<string, mixed>  $data
     */
    public function createReceiptItem(array $data): PenerimaanBarangItem;

    /**
     * Mark a shipment as arrived at the destination warehouse.
     */
    public function markShipmentArrived(Pengiriman $shipment): bool;

    /**
     * Create a per-item QC rejection dispute (`sengketa`).
     *
     * @param  array<string, mixed>  $data
     */
    public function createDispute(array $data): Sengketa;

    /**
     * Append an immutable dispute log entry (`log_sengketa`).
     *
     * @param  array<string, mixed>  $data
     */
    public function createDisputeLog(array $data): LogSengketa;

    /**
     * Find an open dispute by ID.
     */
    public function findDisputeById(int $disputeId): ?Sengketa;

    /**
     * Get active notification recipients for QC inspection completion (Vendor + Tenant Owner/Purchasing/Manajemen).
     *
     * @return Collection<int, User>
     */
    public function getInspectionNotificationRecipients(int $tenantId, int $vendorId, bool $hasRejection, int $excludeUserId): Collection;
}

