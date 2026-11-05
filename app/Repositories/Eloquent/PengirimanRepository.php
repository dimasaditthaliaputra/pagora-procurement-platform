<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\StatusPengiriman;
use App\Enums\StatusPurchaseOrder;
use App\Models\Pengiriman;
use App\Models\PurchaseOrder;
use App\Repositories\Contracts\PengirimanRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PengirimanRepository implements PengirimanRepositoryInterface
{
    /**
     * Whitelist of sortable column names.
     *
     * @var list<string>
     */
    private const SORTABLE = [
        'created_at',
        'estimated_arrival_date',
        'status',
        'route',
        'carrier_name',
    ];

    /**
     * PO statuses eligible for physical shipment reporting (BR-09, UC-14).
     *
     * @var list<string>
     */
    private const SHIPPABLE_PO_STATUSES = [
        StatusPurchaseOrder::Disepakati->value,
        StatusPurchaseOrder::PartiallyReceived->value,
    ];

    public function __construct(
        protected Pengiriman $model,
        protected PurchaseOrder $purchaseOrderModel,
    ) {}

    /**
     * Paginate shipments belonging to a specific tenant (buyer).
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<Pengiriman>
     */
    public function paginateForTenant(int $tenantId, array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $query = $this->model->newQuery()
            ->whereHas('purchaseOrder', fn (Builder $q) => $q->where('perusahaan_buyer_id', $tenantId))
            ->with([
                'purchaseOrder.vendor:id,uuid,company_name,contact_email,phone,address',
                'purchaseOrder.tenant:id,uuid,company_name,tax_number,address',
                'purchaseOrder.poItems.komoditas:id,uuid,name,unit',
                'reporter:id,uuid,name,email',
            ]);

        return $this->applyFiltersAndPaginate($query, $filters, $perPage, isTenant: true);
    }

    /**
     * Paginate shipments belonging to a specific vendor.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<Pengiriman>
     */
    public function paginateForVendor(int $vendorId, array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $query = $this->model->newQuery()
            ->whereHas('purchaseOrder', function (Builder $q) use ($vendorId): void {
                $q->where('vendor_id', $vendorId)
                    ->where('status', '!=', StatusPurchaseOrder::Draft->value);
            })
            ->with([
                'purchaseOrder.vendor:id,uuid,company_name,contact_email,phone,address',
                'purchaseOrder.tenant:id,uuid,company_name,tax_number,address',
                'purchaseOrder.poItems.komoditas:id,uuid,name,unit',
                'reporter:id,uuid,name,email',
            ]);

        return $this->applyFiltersAndPaginate($query, $filters, $perPage, isTenant: false);
    }

    /**
     * Find a shipment by UUID, optionally scoped to a tenant or vendor.
     */
    public function findByUuid(string $uuid, ?int $tenantId = null, ?int $vendorId = null): ?Pengiriman
    {
        return $this->model->newQuery()
            ->where('uuid', $uuid)
            ->when($tenantId !== null, function (Builder $query) use ($tenantId): void {
                $query->whereHas('purchaseOrder', fn (Builder $q) => $q->where('perusahaan_buyer_id', $tenantId));
            })
            ->when($vendorId !== null, function (Builder $query) use ($vendorId): void {
                $query->whereHas('purchaseOrder', function (Builder $q) use ($vendorId): void {
                    $q->where('vendor_id', $vendorId)
                        ->where('status', '!=', StatusPurchaseOrder::Draft->value);
                });
            })
            ->with([
                'purchaseOrder.vendor:id,uuid,company_name,contact_email,phone,address',
                'purchaseOrder.tenant:id,uuid,company_name,tax_number,address',
                'purchaseOrder.pembuat:id,uuid,name,email',
                'purchaseOrder.poItems.komoditas:id,uuid,name,unit',
                'purchaseOrder.pengiriman.reporter:id,uuid,name,email',
                'reporter:id,uuid,name,email',
                'dispute',
            ])
            ->first();
    }

    /**
     * Retrieve Purchase Orders belonging to a vendor that are eligible for physical shipment.
     *
     * @return Collection<int, PurchaseOrder>
     */
    public function getShippablePurchaseOrdersForVendor(int $vendorId): Collection
    {
        return $this->purchaseOrderModel->newQuery()
            ->where('vendor_id', $vendorId)
            ->whereIn('status', self::SHIPPABLE_PO_STATUSES)
            ->with([
                'tenant:id,uuid,company_name,address',
                'poItems.komoditas:id,uuid,name,unit',
                'pengiriman:id,uuid,purchase_order_id,status,created_at',
            ])
            ->orderByDesc('tanggal_po')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Find a Purchase Order by UUID strictly scoped to a vendor (excluding tenant drafts) for shipment creation.
     */
    public function findVendorPurchaseOrderByUuid(int $vendorId, string $poUuid): ?PurchaseOrder
    {
        return $this->purchaseOrderModel->newQuery()
            ->where('uuid', $poUuid)
            ->where('vendor_id', $vendorId)
            ->where('status', '!=', StatusPurchaseOrder::Draft->value)
            ->with([
                'tenant:id,uuid,company_name,address',
                'vendor:id,uuid,company_name',
                'poItems.komoditas:id,uuid,name,unit',
                'invoices.sengketa',
            ])
            ->first();
    }

    /**
     * Get summary statistics for shipments belonging to a tenant.
     *
     * @return array{total_shipments: int, in_transit: int, arrived: int, ready_to_ship: int}
     */
    public function getStatisticsForTenant(int $tenantId): array
    {
        $base = fn (): Builder => $this->model->newQuery()
            ->whereHas('purchaseOrder', fn (Builder $q) => $q->where('perusahaan_buyer_id', $tenantId));

        $readyToShip = $this->purchaseOrderModel->newQuery()
            ->where('perusahaan_buyer_id', $tenantId)
            ->whereIn('status', self::SHIPPABLE_PO_STATUSES)
            ->count();

        return [
            'total_shipments' => $base()->count(),
            'in_transit' => $base()->where('status', StatusPengiriman::InTransit->value)->count(),
            'arrived' => $base()->where('status', StatusPengiriman::Arrived->value)->count(),
            'ready_to_ship' => $readyToShip,
        ];
    }

    /**
     * Get summary statistics for shipments belonging to a vendor.
     *
     * @return array{total_shipments: int, in_transit: int, arrived: int, ready_to_ship: int}
     */
    public function getStatisticsForVendor(int $vendorId): array
    {
        $base = fn (): Builder => $this->model->newQuery()
            ->whereHas('purchaseOrder', function (Builder $q) use ($vendorId): void {
                $q->where('vendor_id', $vendorId)
                    ->where('status', '!=', StatusPurchaseOrder::Draft->value);
            });

        $readyToShip = $this->purchaseOrderModel->newQuery()
            ->where('vendor_id', $vendorId)
            ->whereIn('status', self::SHIPPABLE_PO_STATUSES)
            ->count();

        return [
            'total_shipments' => $base()->count(),
            'in_transit' => $base()->where('status', StatusPengiriman::InTransit->value)->count(),
            'arrived' => $base()->where('status', StatusPengiriman::Arrived->value)->count(),
            'ready_to_ship' => $readyToShip,
        ];
    }

    /**
     * Persist a newly reported shipment.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Pengiriman
    {
        return $this->model->newQuery()->create($attributes);
    }

    /**
     * Apply search, status, route, date range, and sorting filters to a shipment query.
     *
     * @param  Builder<Pengiriman>  $query
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<Pengiriman>
     */
    private function applyFiltersAndPaginate(
        Builder $query,
        array $filters,
        int $perPage,
        bool $isTenant
    ): LengthAwarePaginator {
        $sort = in_array($filters['sort'] ?? null, self::SORTABLE, true)
            ? (string) $filters['sort']
            : 'created_at';

        $direction = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $like = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return $query
            ->when(! empty($filters['search']), function (Builder $q) use ($filters, $like, $isTenant): void {
                $term = '%'.trim((string) $filters['search']).'%';
                $q->where(function (Builder $sub) use ($term, $like, $isTenant): void {
                    $sub->where('carrier_name', $like, $term)
                        ->orWhere('vehicle_plate_number', $like, $term)
                        ->orWhere('route', $like, $term)
                        ->orWhereHas('purchaseOrder', function (Builder $poQuery) use ($term, $like, $isTenant): void {
                            $poQuery->where('nomor_po', $like, $term)
                                ->orWhereHas(
                                    $isTenant ? 'vendor' : 'tenant',
                                    fn (Builder $partner) => $partner->where('company_name', $like, $term)
                                );
                        });
                });
            })
            ->when(! empty($filters['status']) && $filters['status'] !== 'all', function (Builder $q) use ($filters): void {
                $q->where('status', (string) $filters['status']);
            })
            ->when(! empty($filters['route']) && $filters['route'] !== 'all', function (Builder $q) use ($filters): void {
                $q->where('route', (string) $filters['route']);
            })
            ->when(! empty($filters['date_from']), function (Builder $q) use ($filters): void {
                $q->whereDate('estimated_arrival_date', '>=', (string) $filters['date_from']);
            })
            ->when(! empty($filters['date_to']), function (Builder $q) use ($filters): void {
                $q->whereDate('estimated_arrival_date', '<=', (string) $filters['date_to']);
            })
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate(min(max($perPage, 1), 100))
            ->withQueryString();
    }
}
