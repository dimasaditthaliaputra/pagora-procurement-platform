<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\PemicuSengketa;
use App\Enums\RoleName;
use App\Enums\StatusItemPenerimaan;
use App\Enums\StatusPengiriman;
use App\Enums\StatusPurchaseOrder;
use App\Enums\StatusSengketa;
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
 * Eloquent repository for Goods Receipt / QC Inspection & Damage Reports (`goods_receipts`, `goods_receipt_items`).
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

    /**
     * @var list<string>
     */
    private const DAMAGE_SORTABLE = [
        'created_at',
        'received_quantity',
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
            'items.poItem.komoditas:id,uuid,name,unit,default_shrinkage_tolerance',
            'items.poItem.komoditas.tenantCommodities',
            'items.poItem.sengketa',
        ];
    }

    /**
     * @return list<string>
     */
    private function damageReportRelations(): array
    {
        return [
            'goodsReceipt.inspector:id,uuid,name',
            'goodsReceipt.shipment:id,uuid,purchase_order_id,dispute_id,route,carrier_name,vehicle_plate_number,estimated_arrival_date,status,reported_by,created_at',
            'goodsReceipt.purchaseOrder:id,uuid,nomor_po,tanggal_po,status,catatan,locked_at,perusahaan_buyer_id,vendor_id',
            'goodsReceipt.purchaseOrder.tenant:id,uuid,company_name,address',
            'goodsReceipt.purchaseOrder.vendor:id,uuid,company_name,contact_email,phone,address',
            'poItem.komoditas:id,uuid,name,unit,default_shrinkage_tolerance',
            'poItem.komoditas.tenantCommodities',
            'poItem.sengketa.logs.penulis:id,uuid,name',
            'poItem.sengketa.pembuka:id,uuid,name',
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
     * Paginate rejected/damaged goods receipt items (`Laporan Kerusakan Barang`) scoped to a Tenant.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<PenerimaanBarangItem>
     */
    public function paginateDamageReportsForTenant(int $tenantId, array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $query = $this->itemModel->newQuery()
            ->with($this->damageReportRelations())
            ->where('status', StatusItemPenerimaan::Rejected->value)
            ->whereHas('goodsReceipt.purchaseOrder', function (Builder $poQuery) use ($tenantId): void {
                $poQuery->where('perusahaan_buyer_id', $tenantId);
            });

        return $this->applyDamageFiltersAndPaginate($query, $filters, $perPage);
    }

    /**
     * Paginate rejected/damaged goods receipt items (`Laporan Kerusakan Barang`) scoped to a Vendor.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<PenerimaanBarangItem>
     */
    public function paginateDamageReportsForVendor(int $vendorId, array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $query = $this->itemModel->newQuery()
            ->with($this->damageReportRelations())
            ->where('status', StatusItemPenerimaan::Rejected->value)
            ->whereHas('goodsReceipt.purchaseOrder', function (Builder $poQuery) use ($vendorId): void {
                $poQuery->where('vendor_id', $vendorId)
                    ->where('status', '!=', StatusPurchaseOrder::Draft->value);
            });

        return $this->applyDamageFiltersAndPaginate($query, $filters, $perPage);
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
     * Find a rejected/damaged goods receipt item with full relations strictly scoped to a Tenant or Vendor.
     */
    public function findRejectedItemDetailByUuid(string $itemUuid, ?int $tenantId = null, ?int $vendorId = null): ?PenerimaanBarangItem
    {
        if ($tenantId === null && $vendorId === null) {
            return null;
        }

        return $this->itemModel->newQuery()
            ->with($this->damageReportRelations())
            ->where('uuid', $itemUuid)
            ->where('status', StatusItemPenerimaan::Rejected->value)
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
     * Find a dispute log entry by UUID strictly scoped to a Tenant or Vendor.
     */
    public function findDisputeLogByUuid(string $logUuid, ?int $tenantId = null, ?int $vendorId = null): ?LogSengketa
    {
        if ($tenantId === null && $vendorId === null) {
            return null;
        }

        return $this->logSengketaModel->newQuery()
            ->with([
                'sengketa.purchaseOrder:id,uuid,perusahaan_buyer_id,vendor_id,status',
                'sengketa.invoice.purchaseOrder:id,uuid,perusahaan_buyer_id,vendor_id,status',
            ])
            ->where('uuid', $logUuid)
            ->whereHas('sengketa', function (Builder $sq) use ($tenantId, $vendorId): void {
                $sq->where(function (Builder $q) use ($tenantId, $vendorId): void {
                    $q->whereHas('purchaseOrder', function (Builder $poQuery) use ($tenantId, $vendorId): void {
                        if ($tenantId !== null) {
                            $poQuery->where('perusahaan_buyer_id', $tenantId);
                        } else {
                            $poQuery->where('vendor_id', $vendorId)
                                ->where('status', '!=', StatusPurchaseOrder::Draft->value);
                        }
                    })->orWhereHas('invoice.purchaseOrder', function (Builder $poQuery) use ($tenantId, $vendorId): void {
                        if ($tenantId !== null) {
                            $poQuery->where('perusahaan_buyer_id', $tenantId);
                        } else {
                            $poQuery->where('vendor_id', $vendorId)
                                ->where('status', '!=', StatusPurchaseOrder::Draft->value);
                        }
                    });
                });
            })
            ->first();
    }

    /**
     * Retrieve Purchase Orders belonging to a Tenant currently in `dalam_pengiriman` (`In Transit`) status,
     * ordered by latest date (`DESC`) with optional row limit.
     *
     * @return Collection<int, PurchaseOrder>
     */
    public function getInspectablePurchaseOrdersForTenant(int $tenantId, ?int $limit = null): Collection
    {
        $query = $this->baseInspectablePurchaseOrdersQuery($tenantId)
            ->orderByDesc('tanggal_po')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Paginate Purchase Orders belonging to a Tenant currently in `dalam_pengiriman` (`In Transit`) status
     * for the dedicated "View All" QC inspection queue page.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<PurchaseOrder>
     */
    public function paginateInspectablePurchaseOrdersForTenant(int $tenantId, array $filters, int $perPage = 8): LengthAwarePaginator
    {
        $query = $this->baseInspectablePurchaseOrdersQuery($tenantId);

        if (! empty($filters['search']) && is_string($filters['search'])) {
            $term = '%'.mb_strtolower(trim($filters['search'])).'%';

            $query->where(function (Builder $q) use ($term): void {
                $q->whereRaw('LOWER(nomor_po) LIKE ?', [$term])
                    ->orWhereHas('vendor', function (Builder $vq) use ($term): void {
                        $vq->whereRaw('LOWER(company_name) LIKE ?', [$term]);
                    })
                    ->orWhereHas('pengiriman', function (Builder $sq) use ($term): void {
                        $sq->where('status', StatusPengiriman::InTransit->value)
                            ->where(function (Builder $inner) use ($term): void {
                                $inner->whereRaw('LOWER(carrier_name) LIKE ?', [$term])
                                    ->orWhereRaw('LOWER(vehicle_plate_number) LIKE ?', [$term])
                                    ->orWhereRaw('LOWER(route) LIKE ?', [$term]);
                            });
                    });
            });
        }

        if (! empty($filters['route']) && is_string($filters['route']) && $filters['route'] !== 'all') {
            $routeFilter = $filters['route'];
            $query->whereHas('pengiriman', function (Builder $sq) use ($routeFilter): void {
                $sq->where('status', StatusPengiriman::InTransit->value)
                    ->where('route', $routeFilter);
            });
        }

        $sort = isset($filters['sort']) && is_string($filters['sort']) ? $filters['sort'] : 'tanggal_po';
        $direction = isset($filters['direction']) && strtolower((string) $filters['direction']) === 'asc'
            ? 'asc'
            : 'desc';

        if ($sort === 'estimated_arrival_date') {
            $etaSubquery = Pengiriman::query()
                ->select('estimated_arrival_date')
                ->whereColumn('shipments.purchase_order_id', 'purchase_order.id')
                ->where('shipments.status', StatusPengiriman::InTransit->value)
                ->orderByDesc('shipments.id')
                ->limit(1);

            $query->orderBy($etaSubquery, $direction)
                ->orderByDesc('tanggal_po')
                ->orderByDesc('id');
        } elseif ($sort === 'created_at') {
            $query->orderBy('created_at', $direction)
                ->orderBy('id', $direction);
        } else {
            $query->orderBy('tanggal_po', $direction)
                ->orderBy('created_at', $direction)
                ->orderBy('id', $direction);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Base Eloquent query for Tenant Purchase Orders in `dalam_pengiriman` (`In Transit`) status.
     *
     * @return Builder<PurchaseOrder>
     */
    private function baseInspectablePurchaseOrdersQuery(int $tenantId): Builder
    {
        return $this->purchaseOrderModel->newQuery()
            ->where('perusahaan_buyer_id', $tenantId)
            ->where('status', StatusPurchaseOrder::DalamPengiriman->value)
            ->with([
                'vendor:id,uuid,company_name,phone,address',
                'tenant:id,uuid,company_name,address',
                'pengiriman.reporter:id,uuid,name',
                'poItems.komoditas:id,uuid,name,unit,default_shrinkage_tolerance',
                'poItems.komoditas.tenantCommodities' => static fn ($q) => $q->where('tenant_id', $tenantId),
                'poItems.penerimaanBarangItems',
            ]);
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
                'poItems.komoditas:id,uuid,name,unit,default_shrinkage_tolerance',
                'poItems.komoditas.tenantCommodities' => static fn ($q) => $q->where('tenant_id', $tenantId),
                'poItems.penerimaanBarangItems',
            ])
            ->first();
    }

    /**
     * Get summary metrics for goods receipts belonging to a Tenant.
     *
     * @return array{in_transit_orders: int, total_inspections: int, fully_accepted: int, with_rejections: int, rejected_items_count: int}
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

        $rejectedItemsCount = $this->itemModel->newQuery()
            ->where('status', StatusItemPenerimaan::Rejected->value)
            ->whereHas('goodsReceipt.purchaseOrder', fn (Builder $q) => $q->where('perusahaan_buyer_id', $tenantId))
            ->count();

        return [
            'in_transit_orders' => $inTransitOrders,
            'total_inspections' => $totalInspections,
            'fully_accepted' => max(0, $totalInspections - $withRejections),
            'with_rejections' => $withRejections,
            'rejected_items_count' => $rejectedItemsCount,
        ];
    }

    /**
     * Get summary metrics for goods receipts belonging to a Vendor.
     *
     * @return array{in_transit_orders: int, total_inspections: int, fully_accepted: int, with_rejections: int, rejected_items_count: int}
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

        $rejectedItemsCount = $this->itemModel->newQuery()
            ->where('status', StatusItemPenerimaan::Rejected->value)
            ->whereHas('goodsReceipt.purchaseOrder', fn (Builder $q) => $q->where('vendor_id', $vendorId))
            ->count();

        return [
            'in_transit_orders' => $inTransitOrders,
            'total_inspections' => $totalInspections,
            'fully_accepted' => max(0, $totalInspections - $withRejections),
            'with_rejections' => $withRejections,
            'rejected_items_count' => $rejectedItemsCount,
        ];
    }

    /**
     * Get summary metrics for QC damage reports belonging to a Tenant.
     *
     * @return array{total_damage_reports: int, goods_mismatch_count: int, qc_rejected_count: int, open_disputes_count: int}
     */
    public function getDamageStatisticsForTenant(int $tenantId): array
    {
        $base = fn (): Builder => $this->itemModel->newQuery()
            ->where('status', StatusItemPenerimaan::Rejected->value)
            ->whereHas('goodsReceipt.purchaseOrder', fn (Builder $q) => $q->where('perusahaan_buyer_id', $tenantId));

        $total = $base()->count();
        $goodsMismatch = $base()
            ->whereHas('poItem.sengketa', fn (Builder $q) => $q->where('trigger_type', PemicuSengketa::GoodsMismatch->value))
            ->count();
        $openDisputes = $base()
            ->whereHas('poItem.sengketa', fn (Builder $q) => $q->where('status', '!=', StatusSengketa::Selesai->value))
            ->count();

        return [
            'total_damage_reports' => $total,
            'goods_mismatch_count' => $goodsMismatch,
            'qc_rejected_count' => max(0, $total - $goodsMismatch),
            'open_disputes_count' => $openDisputes,
        ];
    }

    /**
     * Get summary metrics for QC damage reports belonging to a Vendor.
     *
     * @return array{total_damage_reports: int, goods_mismatch_count: int, qc_rejected_count: int, open_disputes_count: int}
     */
    public function getDamageStatisticsForVendor(int $vendorId): array
    {
        $base = fn (): Builder => $this->itemModel->newQuery()
            ->where('status', StatusItemPenerimaan::Rejected->value)
            ->whereHas('goodsReceipt.purchaseOrder', fn (Builder $q) => $q->where('vendor_id', $vendorId));

        $total = $base()->count();
        $goodsMismatch = $base()
            ->whereHas('poItem.sengketa', fn (Builder $q) => $q->where('trigger_type', PemicuSengketa::GoodsMismatch->value))
            ->count();
        $openDisputes = $base()
            ->whereHas('poItem.sengketa', fn (Builder $q) => $q->where('status', '!=', StatusSengketa::Selesai->value))
            ->count();

        return [
            'total_damage_reports' => $total,
            'goods_mismatch_count' => $goodsMismatch,
            'qc_rejected_count' => max(0, $total - $goodsMismatch),
            'open_disputes_count' => $openDisputes,
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
     * Apply search, result filter, whitelisted sort, and pagination on goods receipts.
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

    /**
     * Apply search, trigger_type & dispute_status filters, whitelisted sort, and pagination on damage reports.
     *
     * @param  Builder<PenerimaanBarangItem>  $query
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<PenerimaanBarangItem>
     */
    private function applyDamageFiltersAndPaginate(Builder $query, array $filters, int $perPage): LengthAwarePaginator
    {
        $like = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $term = "%{$search}%";
            $query->where(function (Builder $q) use ($term, $like): void {
                $q->where('notes', $like, $term)
                    ->orWhereHas('poItem.komoditas', fn (Builder $cq) => $cq->where('name', $like, $term))
                    ->orWhereHas('goodsReceipt', function (Builder $rq) use ($term, $like): void {
                        $rq->where('matched_plate_number', $like, $term)
                            ->orWhereHas('purchaseOrder', function (Builder $poQ) use ($term, $like): void {
                                $poQ->where('nomor_po', $like, $term)
                                    ->orWhereHas('vendor', fn (Builder $vq) => $vq->where('company_name', $like, $term))
                                    ->orWhereHas('tenant', fn (Builder $tq) => $tq->where('company_name', $like, $term));
                            });
                    });
            });
        }

        $triggerType = trim((string) ($filters['trigger_type'] ?? ''));
        if ($triggerType !== '' && $triggerType !== 'all') {
            $query->whereHas('poItem.sengketa', fn (Builder $sq) => $sq->where('trigger_type', $triggerType));
        }

        $disputeStatus = trim((string) ($filters['dispute_status'] ?? ''));
        if ($disputeStatus !== '' && $disputeStatus !== 'all') {
            $query->whereHas('poItem.sengketa', fn (Builder $sq) => $sq->where('status', $disputeStatus));
        }

        $sort = in_array($filters['sort'] ?? null, self::DAMAGE_SORTABLE, true)
            ? (string) $filters['sort']
            : 'created_at';
        $direction = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        return $query
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate(min(max($perPage, 1), 100))
            ->withQueryString();
    }
}
