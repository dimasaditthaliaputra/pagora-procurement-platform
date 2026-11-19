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
        'opened_at',
        'status',
        'trigger_type',
        'resolved_at',
        'created_at',
    ];

    public function __construct(
        private readonly Sengketa $model,
        private readonly LogSengketa $logModel,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function paginateForActor(User $actor, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? null, self::SORTABLE, true)
            ? (string) $filters['sort']
            : 'opened_at';
        $direction = (($filters['direction'] ?? 'desc') === 'asc') ? 'asc' : 'desc';
        $safePerPage = min(max($perPage, 1), 100);

        $query = $this->basePartyQuery($actor)
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
            $query->whereDate('opened_at', '>=', $dateFrom);
        }

        $dateTo = isset($filters['date_to']) ? trim((string) $filters['date_to']) : '';
        if ($dateTo !== '') {
            $query->whereDate('opened_at', '<=', $dateTo);
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
    public function getStatisticsForActor(User $actor): array
    {
        $base = $this->basePartyQuery($actor);

        return [
            'total' => (clone $base)->count(),
            'open' => (clone $base)->where('status', StatusSengketa::Open->value)->count(),
            'awaiting_response' => (clone $base)->where('status', StatusSengketa::AwaitingResponse->value)->count(),
            'in_mediation' => (clone $base)->where('status', StatusSengketa::InMediation->value)->count(),
            'resolved' => (clone $base)->where('status', StatusSengketa::Resolved->value)->count(),
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
    public function findByUuidForActor(User $actor, string $uuid): ?Sengketa
    {
        $tenantId = $actor->vendor_id !== null ? null : (int) $actor->tenant_id;

        return $this->basePartyQuery($actor)
            ->where('uuid', $uuid)
            ->with([
                'pembuka.role',
                'purchaseOrder.vendor',
                'purchaseOrder.perusahaanBuyer',
                'purchaseOrder.pembuat.role',
                'purchaseOrder.poItems.komoditas.tenantCommodities' => function ($q) use ($tenantId): void {
                    // The Vendor sees the buyer's tolerance; the resolver picks the PO Tenant's row.
                    $q->when($tenantId !== null, fn ($tq) => $tq->where('tenant_id', $tenantId));
                },
                'purchaseOrder.pengiriman.pelapor',
                'purchaseOrder.penerimaanBarang.inspector',
                'purchaseOrder.penerimaanBarang.shipment',
                'purchaseOrder.penerimaanBarang.items.poItem.komoditas',
                'purchaseOrder.chatNegosiasi.pengirim.role',
                'poItem.komoditas.tenantCommodities' => function ($q) use ($tenantId): void {
                    $q->when($tenantId !== null, fn ($tq) => $tq->where('tenant_id', $tenantId));
                },
                'poItem.penerimaanBarangItems.goodsReceipt.inspector',
                'poItem.penerimaanBarangItems.goodsReceipt.shipment',
                'invoice.verifikator',
                'invoice.creditNotes',
                'purchaseOrder.invoices.creditNotes',
                'creditNotes.invoice',
                'creditNotes.pembuat',
                'creditNotes.vendorConfirmer',
                'purchaseReturns.items.poItem.komoditas',
                'purchaseReturns.items.receiptItem',
                'purchaseReturns.pembuat',
                'purchaseReturns.purchaseOrder',
                'purchaseReturns.vendorConfirmer',
                'vendorConfirmer',
                'penyelesai',
                'logs.penulis.role',
            ])
            ->first();
    }

    /**
     * {@inheritDoc}
     */
    public function lockByUuidForActor(User $actor, string $uuid): ?Sengketa
    {
        return $this->basePartyQuery($actor)
            ->where('uuid', $uuid)
            ->with('purchaseOrder')
            ->lockForUpdate()
            ->first();
    }

    /**
     * {@inheritDoc}
     */
    public function update(Sengketa $sengketa, array $attributes): Sengketa
    {
        if ($sengketa->getOriginal('status') === StatusSengketa::Resolved) {
            throw new LogicException('Sengketa yang telah selesai bersifat permanen (immutable) dan tidak dapat diubah.');
        }

        $sengketa->fill($attributes)->save();

        return $sengketa;
    }

    /**
     * {@inheritDoc}
     */
    public function hasOpenForInvoice(int $invoiceId): bool
    {
        return $this->model->newQuery()
            ->where('invoice_id', $invoiceId)
            ->where('status', '!=', StatusSengketa::Resolved->value)
            ->exists();
    }

    /**
     * {@inheritDoc}
     */
    public function updateStatus(Sengketa $sengketa, StatusSengketa $status): Sengketa
    {
        if ($sengketa->status === StatusSengketa::Resolved) {
            throw new LogicException('Sengketa yang telah selesai bersifat permanen (immutable) dan tidak dapat diubah.');
        }

        $sengketa->status = $status;
        if ($status === StatusSengketa::Resolved && $sengketa->resolved_at === null) {
            $sengketa->resolved_at = now();
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
            'dispute_id' => $data['dispute_id'],
            'user_id' => $data['user_id'],
            'type' => $data['type'],
            'message' => $data['message'],
            'attachment_path' => $data['attachment_path'] ?? null,
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
     * Scope dispute queries to the actor's side of the Purchase Order: the buyer Tenant, or the Vendor
     * of the PO (`NFR-02`, `RULES` §9.3). Any other user sees nothing.
     *
     * @return Builder<Sengketa>
     */
    private function basePartyQuery(User $actor): Builder
    {
        [$column, $partyId] = $actor->vendor_id !== null
            ? ['vendor_id', $actor->vendor_id]
            : ['perusahaan_buyer_id', $actor->tenant_id];

        return $this->model->newQuery()
            ->whereHas('purchaseOrder', function (Builder $poQuery) use ($column, $partyId): void {
                $poQuery->where($column, $partyId ?? 0);
            });
    }
}
