<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\RoleName;
use App\Enums\StatusItemPenerimaan;
use App\Enums\StatusPengiriman;
use App\Enums\StatusPurchaseOrder;
use App\Models\LogSengketa;
use App\Models\PenerimaanBarang;
use App\Models\PenerimaanBarangItem;
use App\Models\Pengiriman;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Sengketa;
use App\Models\User;
use App\Repositories\Contracts\PenerimaanBarangRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent repository for Goods Receipt / QC Inspection (`goods_receipts`, `goods_receipt_items`).
 * Enforces strict Tenant and Vendor data isolation (RULES §9.3, §9.4).
 */
class PenerimaanBarangRepository implements PenerimaanBarangRepositoryInterface
{
    /**
     * @var list<string>
     */
    private const SORTABLE = [
        'inspected_at',
        'matched_plate_number',
        'created_at',
    ];

    public function __construct(
        private readonly PenerimaanBarang $model,
        private readonly PenerimaanBarangItem $itemModel,
        private readonly PurchaseOrder $purchaseOrderModel,
        private readonly Sengketa $sengketaModel,
        private readonly LogSengketa $logSengketaModel,
    ) {}

    /**
     * @return list<string>
     */
    private function defaultRelations(): array
    {
        return [
            'inspector:id,uuid,name',
            'shipment:id,uuid,purchase_order_id,dispute_id,route,carrier_name,vehicle_plate_number,estimated_arrival_date,status,reported_by,created_at',
            'purchaseOrder:id,uuid,nomor_po,tanggal_po,status,catatan,locked_at,perusahaan_buyer_id,vendor_id',
            'purchaseOrder.tenant:id,uuid,company_name,address',
            'purchaseOrder.vendor:id,uuid,company_name,contact_email,phone,address',
            'items.poItem.komoditas:id,uuid,name,unit',
            'items.poItem.sengketa',
        ];
    }

    /**
     * Paginate goods receipts scoped to a Tenant.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<PenerimaanBarang>
     */
    public function paginateForTenant(int $tenantId, array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $query = $this->model->newQuery()
            ->with($this->defaultRelations())
            ->whereHas('purchaseOrder', function (Builder $poQuery) use ($tenantId): void {
                $poQuery->where('perusahaan_buyer_id', $tenantId);
            });

        return $this->applyFiltersAndPaginate($query, $filters, $perPage);
    }

    /**
     * Paginate goods receipts scoped to a Vendor.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<PenerimaanBarang>
     */
    public function paginateForVendor(int $vendorId, array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $query = $this->model->newQuery()
            ->with($this->defaultRelations())
            ->whereHas('purchaseOrder', function (Builder $poQuery) use ($vendorId): void {
                $poQuery->where('vendor_id', $vendorId)
                    ->where('status', '!=', StatusPurchaseOrder::Draft->value);
            });

        return $this->applyFiltersAndPaginate($query, $filters, $perPage);
    }

    /**
     * Find a goods receipt by UUID strictly scoped to a Tenant or Vendor.
     */
    public function findByUuid(string $uuid, ?int $tenantId = null, ?int $vendorId = null): ?PenerimaanBarang
    {
        if ($tenantId === null && $vendorId === null) {
            return null;
        }

        return $this->model->newQuery()
            ->with([
                ...$this->defaultRelations(),
                'items.poItem.sengketa.logs.penulis:id,uuid,name',
            ])
            ->where('uuid', $uuid)
            ->whereHas('purchaseOrder', function (Builder $poQuery) use ($tenantId, $vendorId): void {
                if ($tenantId !== null) {
                    $poQuery->where('perusahaan_buyer_id', $tenantId);
                } else {
                    $poQuery->where('vendor_id', $vendorId)
                        ->where('status', '!=', StatusPurchaseOrder::Draft->value);
                }
            })
            ->first();
    }

    /**
     * Find a goods receipt item by UUID strictly scoped to a Tenant or Vendor.
     */
    public function findItemByUuid(string $itemUuid, ?int $tenantId = null, ?int $vendorId = null): ?PenerimaanBarangItem
    {
        if ($tenantId === null && $vendorId === null) {
            return null;
        }

        return $this->itemModel->newQuery()
            ->with([
                'goodsReceipt.purchaseOrder:id,uuid,perusahaan_buyer_id,vendor_id,status',
            ])
            ->where('uuid', $itemUuid)
            ->whereHas('goodsReceipt.purchaseOrder', function (Builder $poQuery) use ($tenantId, $vendorId): void {
                if ($tenantId !== null) {
                    $poQuery->where('perusahaan_buyer_id', $tenantId);
                } else {
                    $poQuery->where('vendor_id', $vendorId)
                        ->where('status', '!=', StatusPurchaseOrder::Draft->value);
                }
            })
            ->first();
    }

    /**
     * Retrieve Purchase Orders belonging to a Tenant currently in `dalam_pengiriman` (`In Transit`) status.
     *
     * @return Collection<int, PurchaseOrder>
     */
    public function getInspectablePurchaseOrdersForTenant(int $tenantId): Collection
    {
        return $this->purchaseOrderModel->newQuery()
            ->where('perusahaan_buyer_id', $tenantId)
            ->where('status', StatusPurchaseOrder::DalamPengiriman->value)
            ->with([
                'vendor:id,uuid,company_name,phone,address',
                'tenant:id,uuid,company_name,address',
                'pengiriman.reporter:id,uuid,name',
                'poItems.komoditas:id,uuid,name,unit',
                'poItems.penerimaanBarangItems',
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Find a Tenant's Purchase Order by UUID for QC inspection.
     */
    public function findTenantPurchaseOrderByUuid(int $tenantId, string $poUuid): ?PurchaseOrder
    {
        return $this->purchaseOrderModel->newQuery()
            ->where('uuid', $poUuid)
            ->where('perusahaan_buyer_id', $tenantId)
            ->with([
                'vendor:id,uuid,company_name,phone,address',
                'tenant:id,uuid,company_name,address',
                'pengiriman.reporter:id,uuid,name',
                'poItems.komoditas:id,uuid,name,unit',
                'poItems.penerimaanBarangItems',
            ])
            ->first();
    }

    /**
     * Get summary metrics for goods receipts belonging to a Tenant.
     *
     * @return array{in_transit_orders: int, total_inspections: int, fully_accepted: int, with_rejections: int}
     */
    public function getStatisticsForTenant(int $tenantId): array
    {
        $inTransitOrders = $this->purchaseOrderModel->newQuery()
            ->where('perusahaan_buyer_id', $tenantId)
            ->where('status', StatusPurchaseOrder::DalamPengiriman->value)
            ->count();

        $base = fn (): Builder => $this->model->newQuery()
            ->whereHas('purchaseOrder', fn (Builder $q) => $q->where('perusahaan_buyer_id', $tenantId));

        $totalInspections = $base()->count();
        $withRejections = $base()
            ->whereHas('items', fn (Builder $q) => $q->where('status', StatusItemPenerimaan::Rejected->value))
            ->count();

        return [
            'in_transit_orders' => $inTransitOrders,
            'total_inspections' => $totalInspections,
            'fully_accepted' => max(0, $totalInspections - $withRejections),
            'with_rejections' => $withRejections,
        ];
    }

    /**
     * Get summary metrics for goods receipts belonging to a Vendor.
     *
     * @return array{in_transit_orders: int, total_inspections: int, fully_accepted: int, with_rejections: int}
     */
    public function getStatisticsForVendor(int $vendorId): array
    {
        $inTransitOrders = $this->purchaseOrderModel->newQuery()
            ->where('vendor_id', $vendorId)
            ->where('status', StatusPurchaseOrder::DalamPengiriman->value)
            ->count();

        $base = fn (): Builder => $this->model->newQuery()
            ->whereHas('purchaseOrder', fn (Builder $q) => $q->where('vendor_id', $vendorId));

        $totalInspections = $base()->count();
        $withRejections = $base()
            ->whereHas('items', fn (Builder $q) => $q->where('status', StatusItemPenerimaan::Rejected->value))
            ->count();

        return [
            'in_transit_orders' => $inTransitOrders,
            'total_inspections' => $totalInspections,
            'fully_accepted' => max(0, $totalInspections - $withRejections),
            'with_rejections' => $withRejections,
        ];
    }

    /**
     * Create a new immutable goods receipt header (`goods_receipts`).
     *
     * @param  array<string, mixed>  $data
     */
    public function createReceipt(array $data): PenerimaanBarang
    {
        return $this->model->create($data);
    }

    /**
     * Create a new immutable goods receipt item (`goods_receipt_items`).
     *
     * @param  array<string, mixed>  $data
     */
    public function createReceiptItem(array $data): PenerimaanBarangItem
    {
        return $this->itemModel->create($data);
    }

    /**
     * Mark a shipment as arrived at the destination warehouse.
     */
    public function markShipmentArrived(Pengiriman $shipment): bool
    {
        return $shipment->update([
            'status' => StatusPengiriman::Arrived->value,
        ]);
    }

    /**
     * Create a per-item QC rejection dispute (`sengketa`).
     *
     * @param  array<string, mixed>  $data
     */
    public function createDispute(array $data): Sengketa
    {
        return $this->sengketaModel->create($data);
    }

    /**
     * Append an immutable dispute log entry (`log_sengketa`).
     *
     * @param  array<string, mixed>  $data
     */
    public function createDisputeLog(array $data): LogSengketa
    {
        return $this->logSengketaModel->create($data);
    }

    /**
     * Find an open dispute by ID.
     */
    public function findDisputeById(int $disputeId): ?Sengketa
    {
        return $this->sengketaModel->newQuery()->find($disputeId);
    }

    /**
     * Get active notification recipients for QC inspection completion (Vendor + Tenant Owner/Purchasing/Manajemen).
     *
     * @return Collection<int, User>
     */
    public function getInspectionNotificationRecipients(int $tenantId, int $vendorId, bool $hasRejection, int $excludeUserId): Collection
    {
        $tenantRoles = [
            RoleName::Owner->value,
            RoleName::Purchasing->value,
        ];

        if ($hasRejection) {
            $tenantRoles[] = RoleName::Manajemen->value;
        }

        $tenantRoleIds = Role::query()
            ->whereIn('name', $tenantRoles)
            ->pluck('id');

        $vendorRoleId = Role::query()
            ->where('name', RoleName::Vendor->value)
            ->value('id');

        return User::query()
            ->where('is_active', true)
            ->where('id', '!=', $excludeUserId)
            ->where(function (Builder $q) use ($tenantId, $tenantRoleIds, $vendorId, $vendorRoleId): void {
                $q->where(function (Builder $tq) use ($tenantId, $tenantRoleIds): void {
                    $tq->where('tenant_id', $tenantId)
                        ->whereIn('role_id', $tenantRoleIds);
                });

                if ($vendorRoleId !== null) {
                    $q->orWhere(function (Builder $vq) use ($vendorId, $vendorRoleId): void {
                        $vq->where('vendor_id', $vendorId)
                            ->where('role_id', $vendorRoleId);
                    });
                }
            })
            ->get();
    }

    /**
     * Apply search, result filter, whitelisted sort, and pagination.
     *
     * @param  Builder<PenerimaanBarang>  $query
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<PenerimaanBarang>
     */
    private function applyFiltersAndPaginate(Builder $query, array $filters, int $perPage): LengthAwarePaginator
    {
        $like = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $term = "%{$search}%";
            $query->where(function (Builder $q) use ($term, $like): void {
                $q->where('matched_plate_number', $like, $term)
                    ->orWhere('notes', $like, $term)
                    ->orWhereHas('purchaseOrder', function (Builder $poQ) use ($term, $like): void {
                        $poQ->where('nomor_po', $like, $term)
                            ->orWhereHas('vendor', fn (Builder $vq) => $vq->where('company_name', $like, $term))
                            ->orWhereHas('tenant', fn (Builder $tq) => $tq->where('company_name', $like, $term));
                    });
            });
        }

        $resultFilter = trim((string) ($filters['result'] ?? ''));
        if ($resultFilter === 'accepted') {
            $query->whereDoesntHave('items', fn (Builder $q) => $q->where('status', StatusItemPenerimaan::Rejected->value));
        } elseif ($resultFilter === 'rejected') {
            $query->whereHas('items', fn (Builder $q) => $q->where('status', StatusItemPenerimaan::Rejected->value));
        }

        $sort = in_array($filters['sort'] ?? null, self::SORTABLE, true)
            ? (string) $filters['sort']
            : 'inspected_at';
        $direction = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        return $query
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate(min(max($perPage, 1), 100))
            ->withQueryString();
    }
}

