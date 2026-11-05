<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RoleName;
use App\Enums\StatusPengiriman;
use App\Enums\StatusPurchaseOrder;
use App\Enums\StatusSengketa;
use App\Models\Pengiriman;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\PengirimanRepositoryInterface;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Business logic for Self-Reporting Logistics (`shipments` — UC-14, FR-22, FR-23, BR-07, BR-09).
 */
class PengirimanService
{
    public function __construct(
        protected readonly PengirimanRepositoryInterface $pengirimanRepository,
        protected readonly PurchaseOrderRepositoryInterface $purchaseOrderRepository,
        protected readonly NotificationService $notificationService,
    ) {}

    /**
     * Paginate shipments scoped to the authenticated user's Tenant or Vendor.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<Pengiriman>
     *
     * @throws AuthorizationException
     */
    public function paginateForUser(User $actor, array $filters, int $perPage = 10): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', Pengiriman::class);

        if ($actor->tenant_id !== null) {
            return $this->pengirimanRepository->paginateForTenant((int) $actor->tenant_id, $filters, $perPage);
        }

        if ($actor->vendor_id !== null) {
            return $this->pengirimanRepository->paginateForVendor((int) $actor->vendor_id, $filters, $perPage);
        }

        throw new AuthorizationException('Akun Anda tidak memiliki akses ke daftar pengiriman logistik.');
    }

    /**
     * Get summary metrics for the shipment index page.
     *
     * @return array{total_shipments: int, in_transit: int, arrived: int, ready_to_ship: int}
     */
    public function getStatisticsForUser(User $actor): array
    {
        if ($actor->tenant_id !== null) {
            return $this->pengirimanRepository->getStatisticsForTenant((int) $actor->tenant_id);
        }

        if ($actor->vendor_id !== null) {
            return $this->pengirimanRepository->getStatisticsForVendor((int) $actor->vendor_id);
        }

        return [
            'total_shipments' => 0,
            'in_transit' => 0,
            'arrived' => 0,
            'ready_to_ship' => 0,
        ];
    }

    /**
     * Find a shipment by UUID scoped to the user's Tenant or Vendor and authorize `view`.
     *
     * @throws AuthorizationException|ModelNotFoundException
     */
    public function findByUuidForUser(User $actor, string $uuid): Pengiriman
    {
        Gate::forUser($actor)->authorize('viewAny', Pengiriman::class);

        $tenantId = $actor->tenant_id !== null ? (int) $actor->tenant_id : null;
        $vendorId = $actor->vendor_id !== null ? (int) $actor->vendor_id : null;
        $shipment = $this->pengirimanRepository->findByUuid($uuid, $tenantId, $vendorId);

        if ($shipment === null) {
            throw (new ModelNotFoundException)->setModel(Pengiriman::class, [$uuid]);
        }

        Gate::forUser($actor)->authorize('view', $shipment);

        return $shipment;
    }

    /**
     * Retrieve form options for Vendor Self-Reporting Logistics (`UC-14`).
     * Never exposes internal Tenant ceiling prices (`harga_pagu_acuan` / `harga_pagu` — BR-02).
     *
     * @return array{
     *     purchase_order_options: list<array<string, mixed>>,
     *     route_options: list<array{value: string, label: string}>,
     *     selected_po_uuid: string|null
     * }
     *
     * @throws AuthorizationException
     */
    public function getCreateFormData(User $actor, ?string $preselectedPoUuid = null): array
    {
        Gate::forUser($actor)->authorize('create', Pengiriman::class);

        $vendorId = (int) $actor->vendor_id;
        $shippablePos = $this->pengirimanRepository->getShippablePurchaseOrdersForVendor($vendorId);

        $poOptions = $shippablePos->map(function (PurchaseOrder $po): array {
            $items = $po->poItems->map(fn ($item): array => [
                'uuid' => $item->uuid,
                'commodity_name' => $item->komoditas?->name ?? 'Komoditas',
                'unit' => $item->komoditas?->unit ?? 'kg',
                'quantity' => (float) $item->jumlah_pesanan,
                'unit_price' => (float) $item->harga_satuan_po,
                'subtotal' => (float) $item->subtotal,
            ])->values()->all();

            return [
                'uuid' => $po->uuid,
                'po_number' => $po->nomor_po,
                'po_date' => $po->tanggal_po?->format('Y-m-d') ?? (string) $po->tanggal_po,
                'status' => $po->status->value,
                'status_label' => $po->status->label(),
                'is_reshipment' => $po->status === StatusPurchaseOrder::PartiallyReceived || $po->pengiriman->isNotEmpty(),
                'tenant_name' => $po->tenant?->company_name ?? 'Perusahaan Buyer',
                'destination_address' => $po->tenant?->address,
                'total_amount' => (float) $po->poItems->sum('subtotal'),
                'items_count' => count($items),
                'items' => $items,
            ];
        })->values()->all();

        $validSelectedUuid = null;
        if ($preselectedPoUuid !== null && $preselectedPoUuid !== '') {
            $matched = collect($poOptions)->firstWhere('uuid', $preselectedPoUuid);
            if ($matched !== null) {
                $validSelectedUuid = (string) $matched['uuid'];
            }
        }

        $routeOptions = array_map(fn (string $route): array => [
            'value' => $route,
            'label' => $route,
        ], StatusPengiriman::routes());

        return [
            'purchase_order_options' => $poOptions,
            'route_options' => $routeOptions,
            'selected_po_uuid' => $validSelectedUuid,
        ];
    }

    /**
     * Record a self-reported shipment from Vendor, transition PO to `dalam_pengiriman` (`In Transit`),
     * and permanently lock the PO document and item quantities (UC-14, FR-22, FR-23, BR-07, BR-09).
     *
     * @param  array{
     *     purchase_order_uuid: string,
     *     route: string,
     *     carrier_name: string,
     *     vehicle_plate_number: string,
     *     estimated_arrival_date: string
     * }  $validated
     *
     * @throws AuthorizationException|ModelNotFoundException|ValidationException
     */
    public function store(User $actor, array $validated): Pengiriman
    {
        Gate::forUser($actor)->authorize('create', Pengiriman::class);

        $vendorId = (int) $actor->vendor_id;
        $po = $this->pengirimanRepository->findVendorPurchaseOrderByUuid(
            $vendorId,
            (string) $validated['purchase_order_uuid']
        );

        if ($po === null) {
            throw (new ModelNotFoundException)->setModel(PurchaseOrder::class, [$validated['purchase_order_uuid']]);
        }

        if (! $po->status->bisaDikirim()) {
            throw ValidationException::withMessages([
                'purchase_order_uuid' => "Purchase Order {$po->nomor_po} saat ini berstatus {$po->status->label()} dan tidak dapat dilaporkan pengirimannya. Hanya PO berstatus Disepakati atau Diterima Sebagian yang dapat dikirim.",
            ]);
        }

        Gate::forUser($actor)->authorize('kirim', $po);

        $normalizedPlate = strtoupper((string) preg_replace('/\s+/', ' ', trim((string) $validated['vehicle_plate_number'])));
        $carrierName = trim((string) $validated['carrier_name']);
        $route = trim((string) $validated['route']);

        $shipment = DB::transaction(function () use ($actor, $po, $validated, $route, $carrierName, $normalizedPlate): Pengiriman {
            $disputeId = $this->resolveOpenDisputeId($po);

            $created = $this->pengirimanRepository->create([
                'uuid' => (string) Str::uuid(),
                'purchase_order_id' => $po->id,
                'dispute_id' => $disputeId,
                'route' => $route,
                'carrier_name' => $carrierName,
                'vehicle_plate_number' => $normalizedPlate,
                'estimated_arrival_date' => $validated['estimated_arrival_date'],
                'status' => StatusPengiriman::InTransit->value,
                'reported_by' => $actor->id,
            ]);

            $this->purchaseOrderRepository->update($po, [
                'status' => StatusPurchaseOrder::DalamPengiriman->value,
                'locked_at' => $po->locked_at ?? now(),
            ]);

            return $created;
        });

        $recipients = $this->getTenantLogisticsNotificationRecipients((int) $po->perusahaan_buyer_id);
        $vendorName = $po->vendor?->company_name ?? 'Vendor';
        $etaFormatted = date('d/m/Y', strtotime((string) $validated['estimated_arrival_date']));

        $this->notificationService->sendToMany(
            $recipients,
            'shipment',
            "Pengiriman Dilaporkan: {$po->nomor_po}",
            "{$vendorName} melaporkan pengiriman PO {$po->nomor_po} via jalur {$route} ({$carrierName} - {$normalizedPlate}), estimasi tiba {$etaFormatted}.",
            "/pengiriman/{$shipment->uuid}",
            [
                'shipment_uuid' => $shipment->uuid,
                'po_uuid' => $po->uuid,
                'nomor_po' => $po->nomor_po,
                'vehicle_plate_number' => $normalizedPlate,
            ]
        );

        return $this->findByUuidForUser($actor, $shipment->uuid);
    }

    /**
     * Resolve an open dispute ID when reporting a reshipment for a `partially_received` PO (UC-17, BR-09).
     */
    private function resolveOpenDisputeId(PurchaseOrder $po): ?int
    {
        if ($po->status !== StatusPurchaseOrder::PartiallyReceived || ! $po->relationLoaded('invoices')) {
            return null;
        }

        foreach ($po->invoices as $invoice) {
            if (! $invoice->relationLoaded('sengketa')) {
                continue;
            }

            foreach ($invoice->sengketa as $dispute) {
                if ($dispute->tanggal_selesai === null && $dispute->status !== StatusSengketa::Selesai) {
                    return (int) $dispute->id;
                }
            }
        }

        return null;
    }

    /**
     * Get Tenant users entitled to receive shipment notifications (Owner, Purchasing, QC Gudang).
     *
     * @return Collection<int, User>
     */
    private function getTenantLogisticsNotificationRecipients(int $tenantId): Collection
    {
        $roleIds = Role::query()
            ->whereIn('name', [
                RoleName::Owner->value,
                RoleName::Purchasing->value,
                RoleName::QcGudang->value,
            ])
            ->pluck('id');

        return User::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereIn('role_id', $roleIds)
            ->get();
    }
}
