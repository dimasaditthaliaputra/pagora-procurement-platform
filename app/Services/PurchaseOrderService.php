<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RoleName;
use App\Enums\StatusKerjaSama;
use App\Enums\StatusPurchaseOrder;
use App\Models\KerjaSama;
use App\Models\Komoditas;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\TenantCommodity;
use App\Models\User;
use App\Models\Vendor;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    public function __construct(
        protected readonly PurchaseOrderRepositoryInterface $purchaseOrderRepository,
        protected readonly NotificationService $notificationService,
        protected readonly PricePredictionService $pricePredictionService,
        protected readonly NegotiationService $negotiationService,
        protected readonly DashboardService $dashboardService,
    ) {}

    /**
     * Menghapus cache dashboard tenant dan vendor setelah perubahan PO.
     */
    private function invalidatePurchaseOrderDashboards(int $tenantId, int $vendorId): void
    {
        $this->dashboardService->invalidateTenantDashboards($tenantId);
        $this->dashboardService->invalidateVendorDashboard($vendorId);
    }

    /**
     * Paginate purchase orders for the authenticated user based on role (Tenant or Vendor).
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateForUser(User $actor, array $filters, int $perPage = 10): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', PurchaseOrder::class);

        if ($actor->tenant_id !== null) {
            return $this->purchaseOrderRepository->paginateForTenant((int) $actor->tenant_id, $filters, $perPage);
        }

        if ($actor->vendor_id !== null) {
            return $this->purchaseOrderRepository->paginateForVendor((int) $actor->vendor_id, $filters, $perPage);
        }

        throw new AuthorizationException('Akun Anda tidak memiliki akses ke daftar purchase order.');
    }

    /**
     * Get purchase order summary statistics for the user based on role (Tenant or Vendor).
     *
     * @return array<string, mixed>
     */
    public function getStatisticsForUser(User $actor): array
    {
        if ($actor->tenant_id !== null) {
            return $this->purchaseOrderRepository->getStatisticsForTenant((int) $actor->tenant_id);
        }

        if ($actor->vendor_id !== null) {
            return $this->purchaseOrderRepository->getStatisticsForVendor((int) $actor->vendor_id);
        }

        return [
            'total_aktif' => ['count' => 0, 'draft_count' => 0],
            'menunggu_tindakan' => ['count' => 0],
            'total_nilai_berjalan' => ['amount' => 0.0, 'formatted' => 'Rp 0'],
            'perlu_perhatian' => ['count' => 0],
        ];
    }

    /**
     * Find a purchase order by UUID and verify authorization for the user.
     *
     * @throws ModelNotFoundException|AuthorizationException
     */
    public function findByUuidForUser(User $actor, string $uuid): PurchaseOrder
    {
        $tenantId = $actor->tenant_id !== null ? (int) $actor->tenant_id : null;
        $vendorId = $actor->vendor_id !== null ? (int) $actor->vendor_id : null;
        $po = $this->purchaseOrderRepository->findByUuid($uuid, $tenantId, $vendorId);

        if (! $po) {
            throw (new ModelNotFoundException)->setModel(PurchaseOrder::class, [$uuid]);
        }

        Gate::forUser($actor)->authorize('view', $po);

        return $po;
    }

    /**
     * Retrieve options for creating / editing a Purchase Order.
     *
     * @return array{vendor_options: list<array{value: string, label: string}>, komoditas_options: list<array{value: string, label: string, satuan: string, harga_pagu: float|null}>}
     */
    public function getFormData(User $actor): array
    {
        Gate::forUser($actor)->authorize('create', PurchaseOrder::class);

        $tenantId = (int) $actor->tenant_id;

        $vendorPartners = KerjaSama::query()
            ->where('tenant_id', $tenantId)
            ->where('status', StatusKerjaSama::Agreed->value)
            ->whereNull('blocked_at')
            ->with(['vendor:id,uuid,company_name,is_active,blocked_at'])
            ->get()
            ->filter(fn (KerjaSama $ks) => $ks->vendor && $ks->vendor->is_active && $ks->vendor->blocked_at === null)
            ->map(fn (KerjaSama $ks) => [
                'value' => $ks->vendor->uuid,
                'label' => $ks->vendor->company_name,
            ])
            ->values()
            ->all();

        $tenantCommodityIds = TenantCommodity::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->pluck('commodity_id')
            ->all();

        $commodityQuery = Komoditas::query()
            ->where('is_active', true)
            ->whereNull('deleted_at');

        if (! empty($tenantCommodityIds)) {
            $commodityQuery->whereIn('id', $tenantCommodityIds);
        }

        $commodities = $commodityQuery->orderBy('name')->get();

        $komoditasOptions = $commodities->map(function (Komoditas $commodity) {
            $ceiling = $this->pricePredictionService->latestCeiling($commodity->id);
            $hargaPagu = $ceiling !== null ? (float) $ceiling->recommended_ceiling_price : null;

            return [
                'value' => $commodity->uuid,
                'label' => $commodity->name,
                'satuan' => $commodity->unit ?? 'kg',
                'harga_pagu' => $hargaPagu,
            ];
        })->values()->all();

        return [
            'vendor_options' => $vendorPartners,
            'komoditas_options' => $komoditasOptions,
        ];
    }

    /**
     * Create a new draft Purchase Order with items.
     *
     * @param  array<string, mixed>  $validated
     *
     * @throws AuthorizationException|ValidationException
     */
    public function create(User $actor, array $validated): PurchaseOrder
    {
        Gate::forUser($actor)->authorize('create', PurchaseOrder::class);

        $tenantId = (int) $actor->tenant_id;

        /** @var Vendor|null $vendor */
        $vendor = Vendor::where('uuid', $validated['vendor_uuid'])->first();

        if (! $vendor) {
            throw ValidationException::withMessages(['vendor_uuid' => 'Vendor yang dipilih tidak ditemukan.']);
        }

        $isPartner = KerjaSama::query()
            ->where('tenant_id', $tenantId)
            ->where('vendor_id', $vendor->id)
            ->where('status', StatusKerjaSama::Agreed->value)
            ->whereNull('blocked_at')
            ->exists();

        if (! $isPartner) {
            throw ValidationException::withMessages([
                'vendor_uuid' => 'Vendor harus merupakan mitra yang telah menyetujui kerja sama dan tidak diblokir.',
            ]);
        }

        $itemsData = $this->prepareItemsData($validated['items']);
        $totalPaguAcuan = $itemsData['total_pagu'];
        $firstPrediksiId = $itemsData['prediksi_harga_id'];

        $year = (int) date('Y', strtotime((string) $validated['tanggal_po']));
        $nomorPo = $this->purchaseOrderRepository->getNextPoNumber($year);

        $po = DB::transaction(function () use ($actor, $tenantId, $vendor, $validated, $nomorPo, $totalPaguAcuan, $firstPrediksiId, $itemsData) {
            $po = $this->purchaseOrderRepository->create([
                'uuid' => (string) Str::uuid(),
                'nomor_po' => $nomorPo,
                'vendor_id' => $vendor->id,
                'created_by' => $actor->id,
                'prediksi_harga_id' => $firstPrediksiId,
                'perusahaan_buyer_id' => $tenantId,
                'harga_pagu_acuan' => $totalPaguAcuan,
                'status' => StatusPurchaseOrder::Draft->value,
                'tanggal_po' => $validated['tanggal_po'],
                'catatan' => $validated['catatan'] ?? null,
            ]);

            $this->purchaseOrderRepository->syncItems($po, $itemsData['items']);

            return $this->purchaseOrderRepository->findByUuid($po->uuid, $tenantId) ?? $po;
        });

        $this->invalidatePurchaseOrderDashboards($tenantId, (int) $vendor->id);

        return $po;
    }

    /**
     * Update an existing draft Purchase Order with items.
     *
     * @param  array<string, mixed>  $validated
     *
     * @throws AuthorizationException|ValidationException
     */
    public function update(User $actor, string $uuid, array $validated): PurchaseOrder
    {
        $po = $this->findByUuidForUser($actor, $uuid);

        Gate::forUser($actor)->authorize('update', $po);

        if ($po->status !== StatusPurchaseOrder::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Hanya Purchase Order berstatus draf yang dapat diubah.',
            ]);
        }

        $tenantId = (int) $actor->tenant_id;

        /** @var Vendor|null $vendor */
        $vendor = Vendor::where('uuid', $validated['vendor_uuid'])->first();

        if (! $vendor) {
            throw ValidationException::withMessages(['vendor_uuid' => 'Vendor yang dipilih tidak ditemukan.']);
        }

        $isPartner = KerjaSama::query()
            ->where('tenant_id', $tenantId)
            ->where('vendor_id', $vendor->id)
            ->where('status', StatusKerjaSama::Agreed->value)
            ->whereNull('blocked_at')
            ->exists();

        if (! $isPartner) {
            throw ValidationException::withMessages([
                'vendor_uuid' => 'Vendor harus merupakan mitra yang telah menyetujui kerja sama dan tidak diblokir.',
            ]);
        }

        $itemsData = $this->prepareItemsData($validated['items']);
        $totalPaguAcuan = $itemsData['total_pagu'];
        $oldVendorId = (int) $po->vendor_id;

        $updatedPo = DB::transaction(function () use ($po, $vendor, $validated, $totalPaguAcuan, $itemsData, $tenantId) {
            $this->purchaseOrderRepository->update($po, [
                'vendor_id' => $vendor->id,
                'tanggal_po' => $validated['tanggal_po'],
                'catatan' => $validated['catatan'] ?? null,
                'harga_pagu_acuan' => $totalPaguAcuan,
            ]);

            $this->purchaseOrderRepository->syncItems($po, $itemsData['items']);

            return $this->purchaseOrderRepository->findByUuid($po->uuid, $tenantId) ?? $po;
        });

        $this->invalidatePurchaseOrderDashboards($tenantId, $oldVendorId);
        $this->invalidatePurchaseOrderDashboards($tenantId, (int) $vendor->id);

        return $updatedPo;
    }

    /**
     * Soft delete a draft Purchase Order.
     *
     * @throws AuthorizationException|ValidationException
     */
    public function delete(User $actor, string $uuid): bool
    {
        $po = $this->findByUuidForUser($actor, $uuid);

        Gate::forUser($actor)->authorize('delete', $po);

        if ($po->status !== StatusPurchaseOrder::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Hanya Purchase Order berstatus draf yang dapat dihapus.',
            ]);
        }

        $tenantId = (int) $po->perusahaan_buyer_id;
        $vendorId = (int) $po->vendor_id;

        $deleted = $this->purchaseOrderRepository->delete($po);

        if ($deleted) {
            $this->invalidatePurchaseOrderDashboards($tenantId, $vendorId);
        }

        return $deleted;
    }

    /**
     * Confirm/submit a draft Purchase Order to start negotiation.
     *
     * @throws AuthorizationException|ValidationException
     */
    public function konfirmasi(User $actor, string $uuid): PurchaseOrder
    {
        $po = $this->findByUuidForUser($actor, $uuid);

        Gate::forUser($actor)->authorize('konfirmasi', $po);

        if ($po->status !== StatusPurchaseOrder::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Hanya Purchase Order berstatus draf yang dapat dikonfirmasi untuk diajukan.',
            ]);
        }

        $tenantId = (int) $po->perusahaan_buyer_id;
        $vendorId = (int) $po->vendor_id;

        DB::transaction(function () use ($po, $actor): void {
            $this->purchaseOrderRepository->update($po, [
                'status' => StatusPurchaseOrder::Negosiasi->value,
            ]);

            $this->negotiationService->recordStart($actor, $po);
        });

        $this->invalidatePurchaseOrderDashboards($tenantId, $vendorId);

        $vendorUsers = $this->getVendorPoNotificationRecipients($vendorId);

        $this->notificationService->sendToMany(
            $vendorUsers,
            'purchase_order',
            'Pengajuan PO Baru: '.$po->nomor_po,
            "Tenant mengajukan Purchase Order {$po->nomor_po} untuk dinegosiasikan.",
            "/purchase-order/{$po->uuid}/negosiasi",
            ['po_uuid' => $po->uuid, 'nomor_po' => $po->nomor_po]
        );

        return $this->findByUuidForUser($actor, $uuid);
    }

    /**
     * Cancel the purchase order during negotiation.
     *
     * @throws AuthorizationException|ValidationException
     */
    public function batalkanPo(User $actor, string $uuid, string $alasan): PurchaseOrder
    {
        $alasan = trim($alasan);

        if ($alasan === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan pembatalan Purchase Order wajib diisi.',
            ]);
        }

        $po = $this->findByUuidForUser($actor, $uuid);

        Gate::forUser($actor)->authorize('batalkan', $po);

        if (! $po->status->bisaDibatalkan()) {
            throw ValidationException::withMessages([
                'status' => 'Purchase Order tidak dapat dibatalkan pada status saat ini.',
            ]);
        }

        $tenantId = (int) $po->perusahaan_buyer_id;
        $vendorId = (int) $po->vendor_id;

        DB::transaction(function () use ($po, $actor, $alasan): void {
            $this->purchaseOrderRepository->update($po, [
                'status' => StatusPurchaseOrder::Dibatalkan->value,
                'alasan_pembatalan' => $alasan,
            ]);

            $this->negotiationService->recordCancellation($actor, $po, $alasan);
        });

        $this->invalidatePurchaseOrderDashboards($tenantId, $vendorId);

        $vendorUsers = $this->getVendorPoNotificationRecipients($vendorId);

        $this->notificationService->sendToMany(
            $vendorUsers,
            'purchase_order',
            "PO Dibatalkan: {$po->nomor_po}",
            "Purchase Order {$po->nomor_po} dibatalkan oleh pembeli. Alasan: {$alasan}",
            "/purchase-order/{$po->uuid}",
            ['po_uuid' => $po->uuid, 'nomor_po' => $po->nomor_po, 'alasan' => $alasan]
        );

        return $this->findByUuidForUser($actor, $uuid);
    }

    /**
     * Get tenant users entitled to receive PO negotiation notifications (Purchasing & Owner).
     *
     * @return Collection<int, User>
     */
    protected function getTenantPoNotificationRecipients(int $tenantId): Collection
    {
        $allowedRoleIds = Role::whereIn('name', [RoleName::Purchasing->value, RoleName::Owner->value])->pluck('id');

        return User::where('tenant_id', $tenantId)->whereIn('role_id', $allowedRoleIds)->get();
    }

    /**
     * Get vendor users entitled to receive PO notifications.
     *
     * @return Collection<int, User>
     */
    protected function getVendorPoNotificationRecipients(int $vendorId): Collection
    {
        $vendorRoleId = Role::where('name', RoleName::Vendor->value)->value('id');

        return User::where('vendor_id', $vendorId)->where('role_id', $vendorRoleId)->get();
    }

    /**
     * Prepare items data by resolving commodities and calculating reference prices.
     *
     * @param  list<array{komoditas_uuid: string, jumlah_pesanan: numeric, harga_satuan_po: numeric}>  $rawItems
     * @return array{items: list<array<string, mixed>>, total_pagu: float|null, prediksi_harga_id: int|null}
     */
    protected function prepareItemsData(array $rawItems): array
    {
        $uuids = array_column($rawItems, 'komoditas_uuid');
        $commodities = Komoditas::whereIn('uuid', $uuids)->get()->keyBy('uuid');

        $items = [];
        $totalPagu = 0.0;
        $firstPrediksiId = null;

        foreach ($rawItems as $item) {
            $uuid = $item['komoditas_uuid'];
            $commodity = $commodities->get($uuid);

            if (! $commodity) {
                throw ValidationException::withMessages([
                    'items' => 'Salah satu komoditas yang dipilih tidak valid atau tidak ditemukan.',
                ]);
            }

            $ceiling = $this->pricePredictionService->latestCeiling($commodity->id);

            if ($firstPrediksiId === null && $ceiling) {
                $firstPrediksiId = $ceiling->id;
            }

            $qty = (float) $item['jumlah_pesanan'];

            $totalPagu = $ceiling !== null && $totalPagu !== null
                ? $totalPagu + ($qty * (float) $ceiling->recommended_ceiling_price)
                : null;

            $items[] = [
                'komoditas_id' => $commodity->id,
                'jumlah_pesanan' => $qty,
                'estimated_unit_price' => (float) $item['harga_satuan_po'],
            ];
        }

        return [
            'items' => $items,
            'total_pagu' => $totalPagu,
            'prediksi_harga_id' => $firstPrediksiId,
        ];
    }
}
