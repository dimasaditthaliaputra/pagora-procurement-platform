<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\PoItem;
use App\Models\PurchaseOrder;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseOrderRepository implements PurchaseOrderRepositoryInterface
{
    /**
     * Whitelist of sortable column names.
     *
     * @var list<string>
     */
    private const SORTABLE = [
        'nomor_po',
        'tanggal_po',
        'status',
        'created_at',
    ];

    public const ACTIVE_STATUSES = [
        'negosiasi',
        'negotiation',
        'menunggu_tanggapan',
        'disepakati',
        'agreed',
        'dalam_pengiriman',
        'in_transit',
        'diterima_gudang',
        'received',
        'partially_received',
        'ditagih',
        'invoiced',
        'ready_to_pay',
        'siap_dibayar',
    ];

    public const RUNNING_COMMITTED_STATUSES = [
        'disepakati',
        'agreed',
        'dalam_pengiriman',
        'in_transit',
        'diterima_gudang',
        'received',
        'partially_received',
        'ditagih',
        'invoiced',
        'ready_to_pay',
        'siap_dibayar',
    ];

    public function __construct(
        protected PurchaseOrder $model
    ) {}

    /**
     * Paginate purchase orders for a specific tenant with filters and sorting.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateForTenant(int $tenantId, array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? null, self::SORTABLE, true)
            ? (string) $filters['sort']
            : 'created_at';

        $direction = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $driver = DB::getDriverName();
        $like = $driver === 'pgsql' ? 'ilike' : 'like';

        return $this->model->newQuery()
            ->where('perusahaan_buyer_id', $tenantId)
            ->with([
                'vendor:id,uuid,company_name,contact_email,phone',
                'pembuat:id,uuid,name,email',
                'poItems.komoditas:id,uuid,name,unit',
            ])
            ->when(! empty($filters['search']), function ($query) use ($filters, $like): void {
                $term = '%'.trim((string) $filters['search']).'%';
                $query->where(function ($q) use ($term, $like): void {
                    $q->where('nomor_po', $like, $term)
                        ->orWhereHas('vendor', fn ($v) => $v->where('company_name', $like, $term))
                        ->orWhereHas('poItems.komoditas', fn ($k) => $k->where('name', $like, $term));
                });
            })
            ->when(! empty($filters['status']) && $filters['status'] !== 'all', function ($query) use ($filters): void {
                $this->applyStatusFilter($query, (string) $filters['status']);
            })
            ->orderBy($sort, $direction)
            ->paginate(min($perPage, 100))
            ->withQueryString();
    }

    /**
     * Paginate purchase orders for a specific vendor with filters and sorting.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateForVendor(int $vendorId, array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? null, self::SORTABLE, true)
            ? (string) $filters['sort']
            : 'created_at';

        $direction = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $driver = DB::getDriverName();
        $like = $driver === 'pgsql' ? 'ilike' : 'like';

        return $this->model->newQuery()
            ->where('vendor_id', $vendorId)
            ->where('status', '!=', 'draft') // Drafts are internal to tenant
            ->with([
                'tenant:id,uuid,company_name,tax_number,address',
                'pembuat:id,uuid,name,email',
                'poItems.komoditas:id,uuid,name,unit',
            ])
            ->when(! empty($filters['search']), function ($query) use ($filters, $like): void {
                $term = '%'.trim((string) $filters['search']).'%';
                $query->where(function ($q) use ($term, $like): void {
                    $q->where('nomor_po', $like, $term)
                        ->orWhereHas('tenant', fn ($t) => $t->where('company_name', $like, $term))
                        ->orWhereHas('poItems.komoditas', fn ($k) => $k->where('name', $like, $term));
                });
            })
            ->when(! empty($filters['status']) && $filters['status'] !== 'all', function ($query) use ($filters): void {
                $this->applyStatusFilter($query, (string) $filters['status']);
            })
            ->orderBy($sort, $direction)
            ->paginate(min($perPage, 100))
            ->withQueryString();
    }

    /**
     * Find a purchase order by UUID, optionally scoped to a tenant.
     */
    public function findByUuid(string $uuid, ?int $tenantId = null): ?PurchaseOrder
    {
        return $this->model->newQuery()
            ->when($tenantId !== null, fn ($q) => $q->where('perusahaan_buyer_id', $tenantId))
            ->where('uuid', $uuid)
            ->with([
                'vendor:id,uuid,company_name,contact_email,phone,address',
                'pembuat:id,uuid,name,email',
                'poItems.komoditas:id,uuid,name,unit',
            ])
            ->first();
    }

    /**
     * Generate the next unique PO number for the specified year.
     */
    public function getNextPoNumber(int $year): string
    {
        $prefix = sprintf('PO-%04d-', $year);

        $latest = $this->model->newQuery()
            ->withTrashed()
            ->where('nomor_po', 'like', $prefix.'%')
            ->orderByDesc('nomor_po')
            ->value('nomor_po');

        $sequence = 1;

        if ($latest !== null) {
            $parts = explode('-', $latest);
            $lastSeq = end($parts);
            if (is_numeric($lastSeq)) {
                $sequence = ((int) $lastSeq) + 1;
            }
        }

        return sprintf('%s%04d', $prefix, $sequence);
    }

    /**
     * Create a new purchase order.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): PurchaseOrder
    {
        return $this->model->newQuery()->create($attributes);
    }

    /**
     * Update an existing purchase order.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(PurchaseOrder $purchaseOrder, array $attributes): bool
    {
        return $purchaseOrder->update($attributes);
    }

    /**
     * Soft delete a purchase order.
     */
    public function delete(PurchaseOrder $purchaseOrder): bool
    {
        return (bool) $purchaseOrder->delete();
    }

    /**
     * Sync items for a purchase order.
     *
     * @param  list<array<string, mixed>>  $items
     */
    public function syncItems(PurchaseOrder $purchaseOrder, array $items): void
    {
        $purchaseOrder->poItems()->delete();

        foreach ($items as $item) {
            $qty = (float) $item['jumlah_pesanan'];
            $price = (float) $item['harga_satuan_po'];
            $subtotal = $qty * $price;

            PoItem::create([
                'uuid' => (string) Str::uuid(),
                'purchase_order_id' => $purchaseOrder->id,
                'komoditas_id' => $item['komoditas_id'],
                'jumlah_pesanan' => $qty,
                'harga_satuan_po' => $price,
                'subtotal' => $subtotal,
                'status_item' => $item['status_item'] ?? 'menunggu',
            ]);
        }
    }

    /**
     * Apply status filter including preset groupings.
     */
    public function applyStatusFilter($query, string $status): void
    {
        if ($status === 'aktif') {
            $query->whereIn('status', self::ACTIVE_STATUSES);
        } elseif ($status === 'menunggu_tindakan') {
            $this->applyActionNeededScope($query);
        } elseif ($status === 'berjalan') {
            $query->whereIn('status', self::RUNNING_COMMITTED_STATUSES);
        } elseif ($status === 'sengketa') {
            $this->applySengketaScope($query);
        } else {
            $query->where('status', $status);
        }
    }

    /**
     * Apply action needed scope (new offers in negotiation or ready to pay after 3-way matching).
     */
    public function applyActionNeededScope($query): void
    {
        $query->where(function ($q): void {
            // Kondisi 1: PO dalam negosiasi dengan tawaran baru
            $q->where(function ($sub): void {
                $sub->whereIn('status', ['negosiasi', 'negotiation'])
                    ->where(function ($offerQ): void {
                        $offerQ->whereHas('poItems', function ($itemQ): void {
                            $itemQ->whereNotNull('harga_tawar_vendor')
                                ->where('harga_tawar_vendor', '>', 0);
                        })->orWhereHas('chatNegosiasi', function ($chatQ): void {
                            $chatQ->whereNotNull('nominal_tawar')
                                ->where('nominal_tawar', '>', 0);
                        });
                    });
            })
            // Kondisi 2: PO siap dibayar pasca lolos 3-Way Matching (belum lunas/selesai)
                ->orWhere(function ($sub): void {
                    $sub->whereNotIn('status', ['lunas', 'paid', 'dibatalkan', 'cancelled', 'sengketa'])
                        ->where(function ($readyQ): void {
                            $readyQ->whereIn('status', ['ready_to_pay', 'siap_dibayar'])
                                ->orWhereHas('invoices', function ($invQ): void {
                                    $invQ->whereIn('status_matching', ['cocok', 'matched']);
                                });
                        });
                });
        });
    }

    /**
     * Apply sengketa / attention scope (dispute status, failed 3-way matching, or rejected QC).
     */
    public function applySengketaScope($query): void
    {
        $query->where(function ($q): void {
            $q->whereIn('status', ['sengketa', 'dispute'])
                ->orWhereHas('invoices', function ($invQ): void {
                    $invQ->whereIn('status_matching', ['gagal', 'mismatched'])
                        ->orWhereHas('sengketa', function ($disputeQ): void {
                            $disputeQ->whereNull('tanggal_selesai')
                                ->where('status', '!=', 'selesai');
                        });
                })
                ->orWhereHas('penerimaanBarang.items', function ($qcQ): void {
                    $qcQ->whereIn('status', ['rejected', 'ditolak']);
                });
        });
    }

    /**
     * Get summary statistics for purchase orders belonging to a tenant.
     *
     * @return array<string, mixed>
     */
    public function getStatisticsForTenant(int $tenantId): array
    {
        $activeCount = $this->model->newQuery()
            ->where('perusahaan_buyer_id', $tenantId)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->count();

        $draftCount = $this->model->newQuery()
            ->where('perusahaan_buyer_id', $tenantId)
            ->where('status', 'draft')
            ->count();

        $actionNeededCount = $this->model->newQuery()
            ->where('perusahaan_buyer_id', $tenantId)
            ->tap(fn ($q) => $this->applyActionNeededScope($q))
            ->count();

        $runningTotalAmount = (float) PoItem::whereHas('purchaseOrder', function ($q) use ($tenantId): void {
            $q->where('perusahaan_buyer_id', $tenantId)
                ->whereIn('status', self::RUNNING_COMMITTED_STATUSES);
        })->sum('subtotal');

        $perluPerhatianCount = $this->model->newQuery()
            ->where('perusahaan_buyer_id', $tenantId)
            ->tap(fn ($q) => $this->applySengketaScope($q))
            ->count();

        return [
            'total_aktif' => [
                'count' => $activeCount,
                'draft_count' => $draftCount,
            ],
            'menunggu_tindakan' => [
                'count' => $actionNeededCount,
            ],
            'total_nilai_berjalan' => [
                'amount' => $runningTotalAmount,
                'formatted' => 'Rp '.number_format($runningTotalAmount, 0, ',', '.'),
            ],
            'perlu_perhatian' => [
                'count' => $perluPerhatianCount,
            ],
        ];
    }

    /**
     * Get summary statistics for purchase orders belonging to a vendor.
     *
     * @return array<string, mixed>
     */
    public function getStatisticsForVendor(int $vendorId): array
    {
        $activeCount = $this->model->newQuery()
            ->where('vendor_id', $vendorId)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->count();

        $actionNeededCount = $this->model->newQuery()
            ->where('vendor_id', $vendorId)
            ->tap(fn ($q) => $this->applyActionNeededScope($q))
            ->count();

        $runningTotalAmount = (float) PoItem::whereHas('purchaseOrder', function ($q) use ($vendorId): void {
            $q->where('vendor_id', $vendorId)
                ->whereIn('status', self::RUNNING_COMMITTED_STATUSES);
        })->sum('subtotal');

        $perluPerhatianCount = $this->model->newQuery()
            ->where('vendor_id', $vendorId)
            ->tap(fn ($q) => $this->applySengketaScope($q))
            ->count();

        return [
            'total_aktif' => [
                'count' => $activeCount,
                'draft_count' => 0,
            ],
            'menunggu_tindakan' => [
                'count' => $actionNeededCount,
            ],
            'total_nilai_berjalan' => [
                'amount' => $runningTotalAmount,
                'formatted' => 'Rp '.number_format($runningTotalAmount, 0, ',', '.'),
            ],
            'perlu_perhatian' => [
                'count' => $perluPerhatianCount,
            ],
        ];
    }
}
