<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PemicuSengketa;
use App\Enums\StatusItemPenerimaan;
use App\Enums\StatusPengiriman;
use App\Enums\StatusPurchaseOrder;
use App\Enums\StatusSengketa;
use App\Models\Komoditas;
use App\Models\LogSengketa;
use App\Models\PenerimaanBarang;
use App\Models\PenerimaanBarangItem;
use App\Models\Pengiriman;
use App\Models\PoItem;
use App\Models\PurchaseOrder;
use App\Models\Sengketa;
use App\Models\TenantCommodity;
use App\Models\User;
use App\Repositories\Contracts\PenerimaanBarangRepositoryInterface;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Business logic for Goods Receipt, QC Inspection & Damage Reporting (`goods_receipts`, `goods_receipt_items`,
 * `sengketa`, `log_sengketa` — UC-15 + «extend» Melaporkan Kerusakan Barang, FR-24..26, BR-09, BR-10, BR-14, BR-17, BR-23).
 */
class PenerimaanBarangService
{
    public const STORAGE_DISK = 'local';

    public const REJECTION_PHOTO_DIR = 'goods-receipts/rejections';

    public const SUMMARY_QUEUE_LIMIT = 4;

    public function __construct(
        protected readonly PenerimaanBarangRepositoryInterface $penerimaanRepository,
        protected readonly PurchaseOrderRepositoryInterface $purchaseOrderRepository,
        protected readonly NotificationService $notificationService,
    ) {}

    /**
     * Paginate goods receipts scoped to the authenticated user's Tenant or Vendor.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<PenerimaanBarang>
     *
     * @throws AuthorizationException
     */
    public function paginateForUser(User $actor, array $filters, int $perPage = 10): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', PenerimaanBarang::class);

        if ($actor->tenant_id !== null) {
            return $this->penerimaanRepository->paginateForTenant((int) $actor->tenant_id, $filters, $perPage);
        }

        if ($actor->vendor_id !== null) {
            return $this->penerimaanRepository->paginateForVendor((int) $actor->vendor_id, $filters, $perPage);
        }

        throw new AuthorizationException('Akun Anda tidak memiliki akses ke daftar inspeksi penerimaan barang.');
    }

    /**
     * Paginate QC damage reports (`goods_receipt_items` with `status = 'rejected'`) scoped to Tenant or Vendor.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<PenerimaanBarangItem>
     *
     * @throws AuthorizationException
     */
    public function paginateDamageReportsForUser(User $actor, array $filters, int $perPage = 10): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', PenerimaanBarang::class);

        if ($actor->tenant_id !== null) {
            return $this->penerimaanRepository->paginateDamageReportsForTenant((int) $actor->tenant_id, $filters, $perPage);
        }

        if ($actor->vendor_id !== null) {
            return $this->penerimaanRepository->paginateDamageReportsForVendor((int) $actor->vendor_id, $filters, $perPage);
        }

        throw new AuthorizationException('Akun Anda tidak memiliki akses ke daftar laporan kerusakan barang.');
    }

    /**
     * Get summary metrics for the QC inspection index page.
     *
     * @return array{in_transit_orders: int, total_inspections: int, fully_accepted: int, with_rejections: int, rejected_items_count: int}
     */
    public function getStatisticsForUser(User $actor): array
    {
        if ($actor->tenant_id !== null) {
            return $this->penerimaanRepository->getStatisticsForTenant((int) $actor->tenant_id);
        }

        if ($actor->vendor_id !== null) {
            return $this->penerimaanRepository->getStatisticsForVendor((int) $actor->vendor_id);
        }

        return [
            'in_transit_orders' => 0,
            'total_inspections' => 0,
            'fully_accepted' => 0,
            'with_rejections' => 0,
            'rejected_items_count' => 0,
        ];
    }

    /**
     * Get summary metrics for the QC damage reports page (`Laporan Kerusakan Barang`).
     *
     * @return array{total_damage_reports: int, goods_mismatch_count: int, qc_rejected_count: int, open_disputes_count: int}
     */
    public function getDamageStatisticsForUser(User $actor): array
    {
        if ($actor->tenant_id !== null) {
            return $this->penerimaanRepository->getDamageStatisticsForTenant((int) $actor->tenant_id);
        }

        if ($actor->vendor_id !== null) {
            return $this->penerimaanRepository->getDamageStatisticsForVendor((int) $actor->vendor_id);
        }

        return [
            'total_damage_reports' => 0,
            'goods_mismatch_count' => 0,
            'qc_rejected_count' => 0,
            'open_disputes_count' => 0,
        ];
    }

    /**
     * Return queue of `"In Transit"` (`dalam_pengiriman`) orders awaiting QC inspection for the Index summary card
     * (ordered by latest date `DESC` and capped at `$limit`, defaulting to 4).
     *
     * @return list<array<string, mixed>>
     */
    public function getInspectableQueueForUser(User $actor, ?int $limit = self::SUMMARY_QUEUE_LIMIT): array
    {
        if ($actor->tenant_id === null || ! $actor->can('create', PenerimaanBarang::class)) {
            return [];
        }

        return $this->penerimaanRepository
            ->getInspectablePurchaseOrdersForTenant((int) $actor->tenant_id, $limit)
            ->map(fn (PurchaseOrder $po): array => $this->formatInspectablePurchaseOrder($po))
            ->values()
            ->all();
    }

    /**
     * Paginate all `"In Transit"` (`dalam_pengiriman`) Purchase Orders for the dedicated "View All" QC queue page.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<PurchaseOrder>
     *
     * @throws AuthorizationException
     */
    public function paginateInspectableOrdersForUser(User $actor, array $filters, int $perPage = 8): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('create', PenerimaanBarang::class);

        if ($actor->tenant_id === null) {
            throw new AuthorizationException('Hanya petugas QC Gudang atau Owner perusahaan pembeli yang dapat melihat antrean inspeksi pesanan.');
        }

        return $this->penerimaanRepository->paginateInspectablePurchaseOrdersForTenant(
            (int) $actor->tenant_id,
            $filters,
            $perPage
        );
    }

    /**
     * Find a goods receipt by UUID scoped to the user's Tenant or Vendor and authorize `view`.
     *
     * @throws AuthorizationException|ModelNotFoundException
     */
    public function findByUuidForUser(User $actor, string $uuid): PenerimaanBarang
    {
        Gate::forUser($actor)->authorize('viewAny', PenerimaanBarang::class);

        $tenantId = $actor->tenant_id !== null ? (int) $actor->tenant_id : null;
        $vendorId = $actor->vendor_id !== null ? (int) $actor->vendor_id : null;
        $receipt = $this->penerimaanRepository->findByUuid($uuid, $tenantId, $vendorId);

        if ($receipt === null) {
            throw (new ModelNotFoundException)->setModel(PenerimaanBarang::class, [$uuid]);
        }

        Gate::forUser($actor)->authorize('view', $receipt);

        return $receipt;
    }

    /**
     * Find a single rejected/damaged goods receipt item (`Laporan Kerusakan Barang`) scoped to Tenant or Vendor.
     *
     * @throws AuthorizationException|ModelNotFoundException
     */
    public function findDamageReportByUuidForUser(User $actor, string $itemUuid): PenerimaanBarangItem
    {
        Gate::forUser($actor)->authorize('viewAny', PenerimaanBarang::class);

        $tenantId = $actor->tenant_id !== null ? (int) $actor->tenant_id : null;
        $vendorId = $actor->vendor_id !== null ? (int) $actor->vendor_id : null;
        $item = $this->penerimaanRepository->findRejectedItemDetailByUuid($itemUuid, $tenantId, $vendorId);

        if ($item === null || $item->goodsReceipt === null) {
            throw (new ModelNotFoundException)->setModel(PenerimaanBarangItem::class, [$itemUuid]);
        }

        Gate::forUser($actor)->authorize('view', $item->goodsReceipt);

        return $item;
    }

    /**
     * Resolve an authorized rejection photo storage path for inline viewing.
     *
     * @throws AuthorizationException|ModelNotFoundException
     */
    public function resolveRejectionPhotoPath(User $actor, string $itemUuid, int $photoIndex): string
    {
        Gate::forUser($actor)->authorize('viewAny', PenerimaanBarang::class);

        $tenantId = $actor->tenant_id !== null ? (int) $actor->tenant_id : null;
        $vendorId = $actor->vendor_id !== null ? (int) $actor->vendor_id : null;
        $item = $this->penerimaanRepository->findItemByUuid($itemUuid, $tenantId, $vendorId);

        if ($item === null || $item->goodsReceipt === null) {
            throw (new ModelNotFoundException)->setModel(PenerimaanBarangItem::class, [$itemUuid]);
        }

        Gate::forUser($actor)->authorize('view', $item->goodsReceipt);

        $photos = is_array($item->rejection_photos) ? array_values($item->rejection_photos) : [];
        if (! isset($photos[$photoIndex]) || ! is_string($photos[$photoIndex]) || $photos[$photoIndex] === '') {
            throw (new ModelNotFoundException)->setModel(PenerimaanBarangItem::class, [$itemUuid]);
        }

        return $photos[$photoIndex];
    }

    /**
     * Resolve an authorized supplementary evidence photo path from `log_sengketa.file_bukti`.
     *
     * @throws AuthorizationException|ModelNotFoundException
     */
    public function resolveDisputeLogPhotoPath(User $actor, string $logUuid): string
    {
        Gate::forUser($actor)->authorize('viewAny', PenerimaanBarang::class);

        $tenantId = $actor->tenant_id !== null ? (int) $actor->tenant_id : null;
        $vendorId = $actor->vendor_id !== null ? (int) $actor->vendor_id : null;
        $log = $this->penerimaanRepository->findDisputeLogByUuid($logUuid, $tenantId, $vendorId);

        if ($log === null || ! is_string($log->file_bukti) || $log->file_bukti === '') {
            throw (new ModelNotFoundException)->setModel(LogSengketa::class, [$logUuid]);
        }

        return $log->file_bukti;
    }

    /**
     * Retrieve form options for QC Gudang / Owner inspection & damage reporting form (`UC-15`).
     *
     * @return array{
     *     purchase_order_options: list<array<string, mixed>>,
     *     selected_po_uuid: string|null,
     *     damage_mode: bool,
     *     trigger_options: list<array{value: string, label: string, description: string}>
     * }
     *
     * @throws AuthorizationException|ModelNotFoundException
     */
    public function getCreateFormData(User $actor, ?string $preselectedPoUuid = null, bool $damageMode = false): array
    {
        Gate::forUser($actor)->authorize('create', PenerimaanBarang::class);

        $tenantId = (int) $actor->tenant_id;

        if ($preselectedPoUuid !== null && $preselectedPoUuid !== '') {
            $targetPo = $this->penerimaanRepository->findTenantPurchaseOrderByUuid($tenantId, $preselectedPoUuid);
            if ($targetPo === null) {
                throw (new ModelNotFoundException)->setModel(PurchaseOrder::class, [$preselectedPoUuid]);
            }
        }

        $inspectablePos = $this->penerimaanRepository->getInspectablePurchaseOrdersForTenant($tenantId);
        $options = $inspectablePos
            ->map(fn (PurchaseOrder $po): array => $this->formatInspectablePurchaseOrder($po))
            ->values()
            ->all();

        $selectedUuid = null;
        if ($preselectedPoUuid !== null && $preselectedPoUuid !== '') {
            $matched = collect($options)->firstWhere('uuid', $preselectedPoUuid);
            if ($matched !== null) {
                $selectedUuid = (string) $matched['uuid'];
            }
        }

        return [
            'purchase_order_options' => $options,
            'selected_po_uuid' => $selectedUuid,
            'damage_mode' => $damageMode,
            'trigger_options' => PemicuSengketa::qcInspectionOptions(),
        ];
    }

    /**
     * Store QC inspection results per item, transition shipment & PO status, and record immutable
     * dispute logs + rejection photos for any rejected/damaged items (UC-15, FR-24..26, BR-09, BR-10, BR-17).
     *
     * @param  array{
     *     purchase_order_uuid: string,
     *     matched_plate_number?: string|null,
     *     notes?: string|null,
     *     items: list<array{
     *         po_item_uuid: string,
     *         status: string,
     *         trigger_type?: string|null,
     *         received_quantity: float|int|string,
     *         notes?: string|null
     *     }>
     * }  $validated
     * @param  array<int, list<UploadedFile>>  $uploadedPhotosByIndex
     *
     * @throws AuthorizationException|ModelNotFoundException|ValidationException|Throwable
     */
    public function store(User $actor, array $validated, array $uploadedPhotosByIndex = []): PenerimaanBarang
    {
        Gate::forUser($actor)->authorize('create', PenerimaanBarang::class);

        $tenantId = (int) $actor->tenant_id;
        $po = $this->penerimaanRepository->findTenantPurchaseOrderByUuid(
            $tenantId,
            (string) $validated['purchase_order_uuid']
        );

        if ($po === null) {
            throw (new ModelNotFoundException)->setModel(PurchaseOrder::class, [$validated['purchase_order_uuid']]);
        }

        if (! $po->status->bisaDiinspeksi()) {
            throw ValidationException::withMessages([
                'purchase_order_uuid' => "Purchase Order {$po->nomor_po} saat ini berstatus {$po->status->label()} dan tidak dapat diinspeksi. Hanya pesanan berstatus Dalam Pengiriman (In Transit) yang dapat diperiksa.",
            ]);
        }

        Gate::forUser($actor)->authorize('inspeksi', $po);

        /** @var Pengiriman|null $shipment */
        $shipment = $po->pengiriman->firstWhere('status', StatusPengiriman::InTransit)
            ?? $po->pengiriman->first();

        if ($shipment === null) {
            throw ValidationException::withMessages([
                'purchase_order_uuid' => "Purchase Order {$po->nomor_po} belum memiliki laporan pengiriman logistik dari vendor.",
            ]);
        }

        $inspectablePoItems = $this->resolveInspectablePoItems($po)->keyBy('uuid');
        $submittedItems = $validated['items'] ?? [];

        $this->validateInspectionItems($inspectablePoItems->all(), $submittedItems, $uploadedPhotosByIndex);

        $matchedPlate = strtoupper((string) preg_replace('/\s+/', ' ', trim((string) ($validated['matched_plate_number'] ?? ''))));
        if ($matchedPlate === '') {
            $matchedPlate = (string) $shipment->vehicle_plate_number;
        }

        $headerNotes = trim((string) ($validated['notes'] ?? ''));
        $storedFilePaths = [];
        $hasRejection = false;

        try {
            $receipt = DB::transaction(function () use (
                $actor,
                $po,
                $shipment,
                $inspectablePoItems,
                $submittedItems,
                $uploadedPhotosByIndex,
                $matchedPlate,
                $headerNotes,
                &$storedFilePaths,
                &$hasRejection,
            ): PenerimaanBarang {
                $createdReceipt = $this->penerimaanRepository->createReceipt([
                    'uuid' => (string) Str::uuid(),
                    'purchase_order_id' => $po->id,
                    'shipment_id' => $shipment->id,
                    'inspected_by' => $actor->id,
                    'matched_plate_number' => $matchedPlate,
                    'inspected_at' => now(),
                    'notes' => $headerNotes !== '' ? $headerNotes : null,
                ]);

                foreach ($submittedItems as $index => $itemInput) {
                    /** @var PoItem $poItem */
                    $poItem = $inspectablePoItems->get((string) $itemInput['po_item_uuid']);
                    $statusEnum = StatusItemPenerimaan::from((string) $itemInput['status']);
                    $receivedQty = round((float) $itemInput['received_quantity'], 3);
                    $itemNotes = trim((string) ($itemInput['notes'] ?? ''));
                    $rawTriggerType = trim((string) ($itemInput['trigger_type'] ?? ''));
                    $triggerEnum = PemicuSengketa::tryFrom($rawTriggerType) ?? PemicuSengketa::QcRejected;

                    $itemPhotoPaths = [];
                    if ($statusEnum === StatusItemPenerimaan::Rejected) {
                        $hasRejection = true;
                        foreach ($uploadedPhotosByIndex[$index] ?? [] as $photoFile) {
                            $storedPath = $this->storeRejectionPhoto($photoFile);
                            if ($storedPath !== null) {
                                $itemPhotoPaths[] = $storedPath;
                                $storedFilePaths[] = $storedPath;
                            }
                        }
                    }

                    $this->penerimaanRepository->createReceiptItem([
                        'uuid' => (string) Str::uuid(),
                        'goods_receipt_id' => $createdReceipt->id,
                        'purchase_order_item_id' => $poItem->id,
                        'received_quantity' => $receivedQty,
                        'status' => $statusEnum->value,
                        'notes' => $itemNotes !== '' ? $itemNotes : null,
                        'rejection_photos' => $statusEnum === StatusItemPenerimaan::Rejected ? $itemPhotoPaths : null,
                    ]);

                    if ($statusEnum === StatusItemPenerimaan::Rejected) {
                        $commodityName = $poItem->komoditas?->name ?? 'Komoditas';
                        $unit = $poItem->komoditas?->unit ?? 'kg';
                        $orderedFormatted = rtrim(rtrim(number_format((float) $poItem->jumlah_pesanan, 3, ',', '.'), '0'), ',');
                        $receivedFormatted = rtrim(rtrim(number_format($receivedQty, 3, ',', '.'), '0'), ',');

                        $dispute = $this->penerimaanRepository->createDispute([
                            'uuid' => (string) Str::uuid(),
                            'purchase_order_id' => $po->id,
                            'purchase_order_item_id' => $poItem->id,
                            'invoice_id' => null,
                            'trigger_type' => $triggerEnum->value,
                            'reason' => $itemNotes,
                            'dibuka_oleh' => $actor->id,
                            'status' => StatusSengketa::Terbuka->value,
                            'tanggal_dibuka' => now(),
                            'tanggal_selesai' => null,
                        ]);

                        $this->penerimaanRepository->createDisputeLog([
                            'uuid' => (string) Str::uuid(),
                            'sengketa_id' => $dispute->id,
                            'user_id' => $actor->id,
                            'type' => 'status_change',
                            'isi_pesan' => "Laporan Kerusakan / Penolakan QC Gudang [{$triggerEnum->label()}] untuk item {$commodityName} (Dipesan: {$orderedFormatted} {$unit}, Diterima: {$receivedFormatted} {$unit}). Catatan: {$itemNotes}",
                            'file_bukti' => $itemPhotoPaths[0] ?? null,
                        ]);

                        $extraPhotos = array_slice($itemPhotoPaths, 1);
                        foreach ($extraPhotos as $extraIdx => $extraPath) {
                            $photoOrder = $extraIdx + 2;
                            $this->penerimaanRepository->createDisputeLog([
                                'uuid' => (string) Str::uuid(),
                                'sengketa_id' => $dispute->id,
                                'user_id' => $actor->id,
                                'type' => 'evidence',
                                'isi_pesan' => "Lampiran foto bukti kerusakan ke-{$photoOrder} untuk item {$commodityName}.",
                                'file_bukti' => $extraPath,
                            ]);
                        }
                    }
                }

                $this->penerimaanRepository->markShipmentArrived($shipment);

                if (! $hasRejection && $shipment->dispute_id !== null) {
                    $linkedDispute = $this->penerimaanRepository->findDisputeById((int) $shipment->dispute_id);
                    if ($linkedDispute !== null) {
                        $this->penerimaanRepository->createDisputeLog([
                            'uuid' => (string) Str::uuid(),
                            'sengketa_id' => $linkedDispute->id,
                            'user_id' => $actor->id,
                            'type' => 'note',
                            'isi_pesan' => "Barang pengiriman ulang ({$shipment->vehicle_plate_number}) telah diperiksa dan disetujui oleh QC Gudang.",
                            'file_bukti' => null,
                        ]);
                    }
                }

                $targetPoStatus = $hasRejection
                    ? StatusPurchaseOrder::PartiallyReceived
                    : StatusPurchaseOrder::DiterimaGudang;

                $this->purchaseOrderRepository->update($po, [
                    'status' => $targetPoStatus->value,
                ]);

                return $createdReceipt;
            });
        } catch (Throwable $e) {
            foreach ($storedFilePaths as $path) {
                Storage::disk(self::STORAGE_DISK)->delete($path);
            }

            throw $e;
        }

        $recipients = $this->penerimaanRepository->getInspectionNotificationRecipients(
            (int) $po->perusahaan_buyer_id,
            (int) $po->vendor_id,
            $hasRejection,
            (int) $actor->id
        );

        $notificationTitle = $hasRejection
            ? "Laporan Kerusakan & Penolakan QC: {$po->nomor_po}"
            : "Barang Disetujui QC Gudang: {$po->nomor_po}";

        $notificationMessage = $hasRejection
            ? "Hasil inspeksi QC untuk PO {$po->nomor_po} mencatat laporan kerusakan/penolakan item dan otomatis masuk ke log sengketa. Status PO kini Diterima Sebagian."
            : "Seluruh item pada PO {$po->nomor_po} telah diperiksa oleh QC Gudang dengan hasil Disetujui (Sesuai).";

        $this->notificationService->sendToMany(
            $recipients,
            $hasRejection ? 'dispute' : 'goods_receipt',
            $notificationTitle,
            $notificationMessage,
            "/inspeksi-qc/{$receipt->uuid}",
            [
                'goods_receipt_uuid' => $receipt->uuid,
                'po_uuid' => $po->uuid,
                'po_number' => $po->nomor_po,
                'has_rejection' => $hasRejection,
            ]
        );

        return $this->findByUuidForUser($actor, $receipt->uuid);
    }

    /**
     * Append supplementary damage notes and/or photo evidence to an open QC dispute (`UC-15 «extend»`,
     * `BR-17`, `RULES` §9.4: strictly append-only `log_sengketa`, never mutates existing records).
     *
     * @param  array{message: string}  $validated
     * @param  list<UploadedFile>  $uploadedPhotos
     *
     * @throws AuthorizationException|ModelNotFoundException|ValidationException|Throwable
     */
    public function appendDamageEvidence(
        User $actor,
        string $itemUuid,
        array $validated,
        array $uploadedPhotos = []
    ): PenerimaanBarangItem {
        if ($actor->tenant_id === null) {
            throw new AuthorizationException('Hanya petugas QC Gudang atau Owner perusahaan pembeli yang dapat menambahkan bukti kerusakan.');
        }

        $item = $this->penerimaanRepository->findRejectedItemDetailByUuid(
            $itemUuid,
            (int) $actor->tenant_id,
            null
        );

        if ($item === null || $item->goodsReceipt === null) {
            throw (new ModelNotFoundException)->setModel(PenerimaanBarangItem::class, [$itemUuid]);
        }

        /** @var Sengketa|null $dispute */
        $dispute = $item->poItem?->sengketa?->sortByDesc('id')->first();

        if ($dispute === null) {
            throw ValidationException::withMessages([
                'message' => 'Data sengketa untuk item kerusakan ini tidak ditemukan.',
            ]);
        }

        if ($dispute->status === StatusSengketa::Selesai) {
            throw ValidationException::withMessages([
                'message' => 'Sengketa untuk laporan kerusakan ini telah berstatus Selesai (immutable) dan tidak dapat ditambahkan bukti baru.',
            ]);
        }

        Gate::forUser($actor)->authorize('reportDamageEvidence', [$item->goodsReceipt, $dispute]);

        $message = trim((string) ($validated['message'] ?? ''));
        $commodityName = $item->poItem?->komoditas?->name ?? 'Komoditas';
        $po = $item->goodsReceipt->purchaseOrder;
        $storedFilePaths = [];

        try {
            DB::transaction(function () use (
                $actor,
                $dispute,
                $message,
                $commodityName,
                $uploadedPhotos,
                &$storedFilePaths
            ): void {
                foreach ($uploadedPhotos as $photoFile) {
                    $storedPath = $this->storeRejectionPhoto($photoFile);
                    if ($storedPath !== null) {
                        $storedFilePaths[] = $storedPath;
                    }
                }

                $this->penerimaanRepository->createDisputeLog([
                    'uuid' => (string) Str::uuid(),
                    'sengketa_id' => $dispute->id,
                    'user_id' => $actor->id,
                    'type' => $storedFilePaths !== [] ? 'evidence' : 'note',
                    'isi_pesan' => "Catatan tambahan kerusakan QC Gudang ({$commodityName}): {$message}",
                    'file_bukti' => $storedFilePaths[0] ?? null,
                ]);

                $extraPhotos = array_slice($storedFilePaths, 1);
                foreach ($extraPhotos as $extraIdx => $extraPath) {
                    $photoOrder = $extraIdx + 2;
                    $this->penerimaanRepository->createDisputeLog([
                        'uuid' => (string) Str::uuid(),
                        'sengketa_id' => $dispute->id,
                        'user_id' => $actor->id,
                        'type' => 'evidence',
                        'isi_pesan' => "Lampiran foto bukti tambahan kerusakan ke-{$photoOrder} untuk item {$commodityName}.",
                        'file_bukti' => $extraPath,
                    ]);
                }
            });
        } catch (Throwable $e) {
            foreach ($storedFilePaths as $path) {
                Storage::disk(self::STORAGE_DISK)->delete($path);
            }

            throw $e;
        }

        if ($po !== null) {
            $recipients = $this->penerimaanRepository->getInspectionNotificationRecipients(
                (int) $po->perusahaan_buyer_id,
                (int) $po->vendor_id,
                true,
                (int) $actor->id
            );

            $this->notificationService->sendToMany(
                $recipients,
                'dispute',
                "Bukti Tambahan Kerusakan QC: {$po->nomor_po}",
                "Petugas QC Gudang menambahkan catatan/foto bukti lanjutan pada laporan kerusakan {$commodityName} ({$po->nomor_po}).",
                "/inspeksi-qc/laporan-kerusakan/{$item->uuid}",
                [
                    'damage_report_item_uuid' => $item->uuid,
                    'goods_receipt_uuid' => $item->goodsReceipt->uuid,
                    'po_uuid' => $po->uuid,
                    'po_number' => $po->nomor_po,
                ]
            );
        }

        return $this->findDamageReportByUuidForUser($actor, $item->uuid);
    }

    /**
     * Resolve effective commodity shrinkage tolerance for a Tenant (`BR-23`:
     * `tenant_commodities.shrinkage_tolerance ?? commodities.default_shrinkage_tolerance`).
     */
    public static function resolveEffectiveShrinkageTolerance(?Komoditas $commodity, ?int $tenantId): float
    {
        if ($commodity === null) {
            return 0.0;
        }

        if ($tenantId !== null && $commodity->relationLoaded('tenantCommodities')) {
            /** @var TenantCommodity|null $tenantConfig */
            $tenantConfig = $commodity->tenantCommodities->firstWhere('tenant_id', $tenantId);
            if ($tenantConfig !== null && $tenantConfig->shrinkage_tolerance !== null) {
                return round((float) $tenantConfig->shrinkage_tolerance, 2);
            }
        }

        return round((float) ($commodity->default_shrinkage_tolerance ?? 0), 2);
    }

    /**
     * Store a single uploaded damage/rejection photo onto private storage with a server-generated UUID filename.
     */
    private function storeRejectionPhoto(UploadedFile $photoFile): ?string
    {
        $extension = strtolower($photoFile->getClientOriginalExtension() ?: $photoFile->extension() ?: 'jpg');
        $filename = Str::uuid()->toString().'.'.$extension;
        $storedPath = $photoFile->storeAs(
            self::REJECTION_PHOTO_DIR,
            $filename,
            self::STORAGE_DISK
        );

        return is_string($storedPath) && $storedPath !== '' ? $storedPath : null;
    }

    /**
     * Determine which PO items require inspection in the current delivery (unaccepted items, or all items).
     *
     * @return Collection<int, PoItem>
     */
    private function resolveInspectablePoItems(PurchaseOrder $po): Collection
    {
        $unaccepted = $po->poItems->filter(function (PoItem $item): bool {
            if (! $item->relationLoaded('penerimaanBarangItems')) {
                return true;
            }

            return ! $item->penerimaanBarangItems->contains(
                fn (PenerimaanBarangItem $receiptItem): bool => $receiptItem->status === StatusItemPenerimaan::Accepted
            );
        })->values();

        return $unaccepted->isNotEmpty() ? $unaccepted : $po->poItems->values();
    }

    /**
     * Validate per-item inspection completeness, quantities, trigger type, notes, and photo evidence (BR-10, FR-26).
     *
     * @param  array<string, PoItem>  $inspectableItemsByUuid
     * @param  list<array<string, mixed>>  $submittedItems
     * @param  array<int, list<UploadedFile>>  $uploadedPhotosByIndex
     *
     * @throws ValidationException
     */
    private function validateInspectionItems(
        array $inspectableItemsByUuid,
        array $submittedItems,
        array $uploadedPhotosByIndex
    ): void {
        $errors = [];
        $seenUuids = [];
        $allowedTriggers = PemicuSengketa::qcInspectionValues();

        foreach ($submittedItems as $index => $itemInput) {
            $itemUuid = (string) ($itemInput['po_item_uuid'] ?? '');

            if ($itemUuid === '' || ! isset($inspectableItemsByUuid[$itemUuid])) {
                $errors["items.{$index}.po_item_uuid"] = 'Item pesanan tidak valid atau tidak termasuk dalam daftar inspeksi ini.';

                continue;
            }

            if (isset($seenUuids[$itemUuid])) {
                $errors["items.{$index}.po_item_uuid"] = 'Item pesanan tidak boleh diduplikasi pada formulir inspeksi.';

                continue;
            }
            $seenUuids[$itemUuid] = true;

            $poItem = $inspectableItemsByUuid[$itemUuid];
            $orderedQty = round((float) $poItem->jumlah_pesanan, 3);
            $receivedQty = round((float) ($itemInput['received_quantity'] ?? 0), 3);
            $status = (string) ($itemInput['status'] ?? '');
            $notes = trim((string) ($itemInput['notes'] ?? ''));
            $triggerType = trim((string) ($itemInput['trigger_type'] ?? ''));

            if ($receivedQty < 0 || $receivedQty > $orderedQty) {
                $errors["items.{$index}.received_quantity"] = "Kuantitas diterima harus di antara 0 dan kuantitas pesanan ({$orderedQty}).";
            }

            if ($status === StatusItemPenerimaan::Accepted->value && $receivedQty <= 0) {
                $errors["items.{$index}.received_quantity"] = 'Kuantitas diterima wajib lebih dari 0 untuk item yang disetujui.';
            }

            if ($status === StatusItemPenerimaan::Rejected->value) {
                if ($triggerType !== '' && ! in_array($triggerType, $allowedTriggers, true)) {
                    $errors["items.{$index}.trigger_type"] = 'Kategori kerusakan / pemicu sengketa yang dipilih tidak valid.';
                }

                if ($notes === '') {
                    $errors["items.{$index}.notes"] = 'Catatan ketidaksesuaian / kerusakan barang wajib diisi untuk item yang ditolak.';
                }

                $photos = $uploadedPhotosByIndex[$index] ?? [];
                if (count($photos) < 1) {
                    $errors["items.{$index}.photos"] = 'Minimal 1 foto bukti kerusakan / ketidaksesuaian wajib diunggah untuk item yang ditolak.';
                }
            }
        }

        $missingCount = count($inspectableItemsByUuid) - count($seenUuids);
        if ($missingCount > 0 && ! isset($errors['items'])) {
            $errors['items'] = 'Seluruh item dalam pengiriman ini wajib diperiksa dan dipilih statusnya (Disetujui atau Ditolak).';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Format an `"In Transit"` Purchase Order for the QC inspection queue and form options.
     *
     * @return array<string, mixed>
     */
    public function formatInspectablePurchaseOrder(PurchaseOrder $po): array
    {
        /** @var Pengiriman|null $activeShipment */
        $activeShipment = $po->pengiriman->firstWhere('status', StatusPengiriman::InTransit)
            ?? $po->pengiriman->first();

        $inspectableUuids = $this->resolveInspectablePoItems($po)->pluck('uuid')->flip();
        $tenantId = (int) $po->perusahaan_buyer_id;

        $inspectableItems = [];
        $alreadyAcceptedItems = [];

        foreach ($po->poItems as $item) {
            $orderedQty = (float) $item->jumlah_pesanan;
            $shrinkageTolerance = self::resolveEffectiveShrinkageTolerance($item->komoditas, $tenantId);
            $minAcceptableQty = round($orderedQty * (1 - ($shrinkageTolerance / 100)), 3);

            $mapped = [
                'uuid' => $item->uuid,
                'commodity_uuid' => $item->komoditas?->uuid,
                'commodity_name' => $item->komoditas?->name ?? 'Komoditas',
                'unit' => $item->komoditas?->unit ?? 'kg',
                'quantity' => $orderedQty,
                'shrinkage_tolerance' => $shrinkageTolerance,
                'min_acceptable_quantity' => $minAcceptableQty,
                'unit_price' => (float) $item->harga_satuan_po,
                'subtotal' => (float) $item->subtotal,
            ];

            if ($inspectableUuids->has($item->uuid)) {
                $inspectableItems[] = $mapped;
            } else {
                $alreadyAcceptedItems[] = $mapped;
            }
        }

        return [
            'uuid' => $po->uuid,
            'po_number' => $po->nomor_po,
            'po_date' => $po->tanggal_po?->format('Y-m-d') ?? (string) $po->tanggal_po,
            'status' => $po->status->value,
            'status_label' => $po->status->label(),
            'notes' => $po->catatan,
            'vendor_name' => $po->vendor?->company_name ?? 'Vendor',
            'vendor_phone' => $po->vendor?->phone,
            'total_amount' => (float) $po->poItems->sum('subtotal'),
            'items_count' => count($inspectableItems),
            'items' => $inspectableItems,
            'already_accepted_items' => $alreadyAcceptedItems,
            'shipment' => $activeShipment !== null ? [
                'uuid' => $activeShipment->uuid,
                'route' => $activeShipment->route,
                'carrier_name' => $activeShipment->carrier_name,
                'vehicle_plate_number' => $activeShipment->vehicle_plate_number,
                'estimated_arrival_date' => $activeShipment->estimated_arrival_date?->format('Y-m-d') ?? (string) $activeShipment->estimated_arrival_date,
                'is_reshipment' => $activeShipment->dispute_id !== null,
                'reporter_name' => $activeShipment->reporter?->name ?? 'Vendor',
            ] : null,
        ];
    }
}
