<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\PemicuSengketa;
use App\Enums\RoleName;
use App\Enums\StatusSengketa;
use App\Models\LogSengketa;
use App\Models\Sengketa;
use App\Models\User;
use App\Repositories\Contracts\SengketaRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Eloquent repository for Procurement Disputes & Log Sengketa with strict Tenant isolation
 * (`UC-18`, `FR-07`, `FR-31`, `BR-17`, `RULES` §8.5, §9.3, §9.4).
 */
class SengketaRepository implements SengketaRepositoryInterface
{
    /**
     * Whitelisted sortable columns (`RULES` §8.5).
     *
     * @var list<string>
     */
    private const SORTABLE = [
        'tanggal_dibuka',
        'status',
        'trigger_type',
        'tanggal_selesai',
        'created_at',
    ];

    public function __construct(
        private readonly Sengketa $model,
        private readonly LogSengketa $logModel,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function paginateForTenant(int $tenantId, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? null, self::SORTABLE, true)
            ? (string) $filters['sort']
            : 'tanggal_dibuka';
        $direction = (($filters['direction'] ?? 'desc') === 'asc') ? 'asc' : 'desc';
        $safePerPage = min(max($perPage, 1), 100);

        $query = $this->baseTenantQuery($tenantId)
            ->with([
                'pembuka.role',
                'purchaseOrder.vendor',
                'poItem.komoditas',
                'invoice',
            ])
            ->withCount('logs');

        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        if ($search !== '') {
            $op = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $like = '%'.$search.'%';
            $query->where(function (Builder $q) use ($like, $op): void {
                $q->where('reason', $op, $like)
                    ->orWhereRaw("CAST(uuid AS TEXT) {$op} ?", [$like])
                    ->orWhereHas('purchaseOrder', function (Builder $poQuery) use ($like, $op): void {
                        $poQuery->where('nomor_po', $op, $like)
                            ->orWhereHas('vendor', function (Builder $vendorQuery) use ($like, $op): void {
                                $vendorQuery->where('company_name', $op, $like);
                            });
                    })
                    ->orWhereHas('poItem.komoditas', function (Builder $comQuery) use ($like, $op): void {
                        $comQuery->where('name', $op, $like);
                    })
                    ->orWhereHas('invoice', function (Builder $invQuery) use ($like, $op): void {
                        $invQuery->where('nomor_invoice_vendor', $op, $like);
                    });
            });
        }

        $status = isset($filters['status']) ? trim((string) $filters['status']) : '';
        if ($status !== '' && $status !== 'all' && in_array($status, StatusSengketa::values(), true)) {
            $query->where('status', $status);
        }

        $triggerType = isset($filters['trigger_type']) ? trim((string) $filters['trigger_type']) : '';
        if ($triggerType !== '' && $triggerType !== 'all' && in_array($triggerType, PemicuSengketa::values(), true)) {
            $query->where('trigger_type', $triggerType);
        }

        $dateFrom = isset($filters['date_from']) ? trim((string) $filters['date_from']) : '';
        if ($dateFrom !== '') {
            $query->whereDate('tanggal_dibuka', '>=', $dateFrom);
        }

        $dateTo = isset($filters['date_to']) ? trim((string) $filters['date_to']) : '';
        if ($dateTo !== '') {
            $query->whereDate('tanggal_dibuka', '<=', $dateTo);
        }

        return $query
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate($safePerPage)
            ->withQueryString();
    }

    /**
     * {@inheritDoc}
     */
    public function getStatisticsForTenant(int $tenantId): array
    {
        $base = $this->baseTenantQuery($tenantId);

        return [
            'total' => (clone $base)->count(),
            'open' => (clone $base)->where('status', StatusSengketa::Terbuka->value)->count(),
            'in_mediation' => (clone $base)->where('status', StatusSengketa::DalamMediasi->value)->count(),
            'resolved' => (clone $base)->where('status', StatusSengketa::Selesai->value)->count(),
            'qc_triggered' => (clone $base)->whereIn('trigger_type', [
                PemicuSengketa::GoodsMismatch->value,
                PemicuSengketa::QcRejected->value,
            ])->count(),
            'invoice_triggered' => (clone $base)->whereIn('trigger_type', [
                PemicuSengketa::QuantityMismatch->value,
                PemicuSengketa::AmountMismatch->value,
            ])->count(),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function findByUuidForTenant(int $tenantId, string $uuid): ?Sengketa
    {
        return $this->baseTenantQuery($tenantId)
            ->where('uuid', $uuid)
            ->with([
                'pembuka.role',
                'purchaseOrder.vendor',
                'purchaseOrder.perusahaanBuyer',
                'purchaseOrder.pembuat.role',
                'purchaseOrder.poItems.komoditas.tenantCommodities' => function ($q) use ($tenantId): void {
                    $q->where('tenant_id', $tenantId);
                },
                'purchaseOrder.pengiriman.pelapor',
                'purchaseOrder.penerimaanBarang.inspector',
                'purchaseOrder.penerimaanBarang.shipment',
                'purchaseOrder.penerimaanBarang.items.poItem.komoditas',
                'purchaseOrder.chatNegosiasi.pengirim.role',
                'poItem.komoditas.tenantCommodities' => function ($q) use ($tenantId): void {
                    $q->where('tenant_id', $tenantId);
                },
                'poItem.penerimaanBarangItems.goodsReceipt.inspector',
                'poItem.penerimaanBarangItems.goodsReceipt.shipment',
                'invoice.verifikator',
                'logs.penulis.role',
            ])
            ->first();
    }

    /**
     * {@inheritDoc}
     */
    public function updateStatus(Sengketa $sengketa, StatusSengketa $status): Sengketa
    {
        if ($sengketa->status === StatusSengketa::Selesai) {
            throw new LogicException('Sengketa yang telah selesai bersifat permanen (immutable) dan tidak dapat diubah.');
        }

        $sengketa->status = $status;
        if ($status === StatusSengketa::Selesai && $sengketa->tanggal_selesai === null) {
            $sengketa->tanggal_selesai = now();
        }
        $sengketa->save();

        return $sengketa;
    }

    /**
     * {@inheritDoc}
     */
    public function appendLog(array $data): LogSengketa
    {
        return $this->logModel->create([
            'sengketa_id' => $data['sengketa_id'],
            'user_id' => $data['user_id'],
            'type' => $data['type'],
            'isi_pesan' => $data['isi_pesan'],
            'file_bukti' => $data['file_bukti'] ?? null,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function getStakeholderRecipients(Sengketa $sengketa, int $excludeUserId): Collection
    {
        $po = $sengketa->purchaseOrder;
        if ($po === null) {
            return collect();
        }

        $tenantId = (int) $po->perusahaan_buyer_id;
        $vendorId = (int) $po->vendor_id;

        return User::query()
            ->where('is_active', true)
            ->where('id', '!=', $excludeUserId)
            ->where(function (Builder $q) use ($tenantId, $vendorId): void {
                $q->where(function (Builder $tq) use ($tenantId): void {
                    $tq->where('tenant_id', $tenantId)
                        ->whereHas('role', function (Builder $rq): void {
                            $rq->whereIn('name', [
                                RoleName::Owner->value,
                                RoleName::Manajemen->value,
                                RoleName::Purchasing->value,
                            ]);
                        });
                })->orWhere(function (Builder $vq) use ($vendorId): void {
                    $vq->where('vendor_id', $vendorId)
                        ->whereHas('role', function (Builder $rq): void {
                            $rq->where('name', RoleName::Vendor->value);
                        });
                });
            })
            ->get();
    }

    /**
     * Scope all dispute queries strictly to Purchase Orders belonging to the given Tenant (`NFR-02`, `RULES` §9.3).
     *
     * @return Builder<Sengketa>
     */
    private function baseTenantQuery(int $tenantId): Builder
    {
        return $this->model->newQuery()
            ->whereHas('purchaseOrder', function (Builder $poQuery) use ($tenantId): void {
                $poQuery->where('perusahaan_buyer_id', $tenantId);
            });
    }
}
