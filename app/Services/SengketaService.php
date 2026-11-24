<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StatusSengketa;
use App\Models\LogSengketa;
use App\Models\PenerimaanBarangItem;
use App\Models\Sengketa;
use App\Models\User;
use App\Repositories\Contracts\SengketaRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Business logic for Reviewing Procurement Issues & Log Sengketa
 * (`UC-18`, `FR-07`, `FR-31`, `FR-38`, `BR-14`, `BR-15`, `BR-17`, `RULES` §4.1, §9.2..§9.4).
 */
class SengketaService
{
    public const STORAGE_DISK = 'local';

    public const EVIDENCE_ATTACHMENT_DIR = 'disputes/attachments';

    public function __construct(
        protected readonly SengketaRepositoryInterface $sengketaRepository,
        protected readonly SengketaPdfExporter $pdfExporter,
        protected readonly NotificationService $notificationService,
        private readonly ?DashboardService $dashboardService = null,
    ) {}

    /**
     * Paginate procurement disputes strictly scoped to the authenticated user's Tenant.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<Sengketa>
     *
     * @throws AuthorizationException
     */
    public function paginateForUser(User $actor, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', Sengketa::class);

        return $this->sengketaRepository->paginateForActor($actor, $filters, $perPage);
    }

    /**
     * Compute dispute summary statistics for the authenticated user's Tenant.
     *
     * @return array{
     *     total: int,
     *     open: int,
     *     awaiting_response: int,
     *     in_mediation: int,
     *     resolved: int,
     *     qc_triggered: int,
     *     invoice_triggered: int
     * }
     *
     * @throws AuthorizationException
     */
    public function getStatisticsForUser(User $actor): array
    {
        Gate::forUser($actor)->authorize('viewAny', Sengketa::class);

        return $this->sengketaRepository->getStatisticsForActor($actor);
    }

    /**
     * Retrieve a single dispute with full context (PO, item, QC photos, negotiation chat, settlement documents,
     * immutable audit log) on the actor's side of the PO (`RULES` §5.1 & §9.3: other parties get 404).
     *
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     */
    public function findForUser(User $actor, string $uuid): Sengketa
    {
        Gate::forUser($actor)->authorize('viewAny', Sengketa::class);

        $sengketa = $this->sengketaRepository->findByUuidForActor($actor, $uuid);
        if ($sengketa === null) {
            throw new ModelNotFoundException('Data sengketa tidak ditemukan.');
        }

        Gate::forUser($actor)->authorize('view', $sengketa);

        return $sengketa;
    }

    /**
     * Export the complete dispute audit record as an official Bahasa Indonesia PDF document (`UC-18` step 8, `FR-31`).
     *
     * @return array{content: string, filename: string}
     *
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     */
    public function exportPdfForUser(User $actor, string $uuid): array
    {
        $sengketa = $this->findForUser($actor, $uuid);
        Gate::forUser($actor)->authorize('export', $sengketa);

        return $this->pdfExporter->generate($sengketa, $actor);
    }

    /**
     * Append a note (optionally with evidence) to the immutable `dispute_logs` trail (`UC-18` step 3, PRD §9.3, BR-17).
     *
     * - Owner/Manajemen: `open` → `in_mediation`; with `request_vendor_response` → `awaiting_response`.
     * - Vendor of the PO: a response moves `awaiting_response` → `in_mediation`.
     *
     * @param  array<string, mixed>  $validated
     *
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     */
    public function storeReviewNote(
        User $actor,
        string $uuid,
        array $validated,
        ?UploadedFile $attachment = null,
    ): Sengketa {
        $sengketa = $this->sengketaRepository->findByUuidForActor($actor, $uuid);
        if ($sengketa === null) {
            throw new ModelNotFoundException('Data sengketa tidak ditemukan.');
        }

        $isVendorSide = $actor->vendor_id !== null;
        Gate::forUser($actor)->authorize($isVendorSide ? 'respond' : 'review', $sengketa);

        $requestsResponse = ! $isVendorSide && (bool) ($validated['request_vendor_response'] ?? false);
        $nextStatus = match (true) {
            $isVendorSide => $sengketa->status === StatusSengketa::AwaitingResponse ? StatusSengketa::InMediation : null,
            $requestsResponse => $sengketa->status !== StatusSengketa::AwaitingResponse ? StatusSengketa::AwaitingResponse : null,
            default => $sengketa->status === StatusSengketa::Open ? StatusSengketa::InMediation : null,
        };

        $message = trim((string) ($validated['message'] ?? ''));
        $storedPath = null;

        try {
            if ($attachment instanceof UploadedFile) {
                $extension = strtolower($attachment->getClientOriginalExtension() ?: $attachment->extension() ?: 'jpg');
                $filename = Str::uuid()->toString().'.'.$extension;
                $storedPath = $attachment->storeAs(
                    self::EVIDENCE_ATTACHMENT_DIR.'/'.$sengketa->uuid,
                    $filename,
                    self::STORAGE_DISK
                );
            }

            DB::transaction(function () use ($actor, $sengketa, $message, $storedPath, $nextStatus): void {
                if ($nextStatus !== null) {
                    $this->sengketaRepository->updateStatus($sengketa, $nextStatus);
                }

                $logType = $storedPath !== null
                    ? 'evidence'
                    : ($nextStatus !== null ? 'status_change' : 'note');

                $prefix = $nextStatus !== null
                    ? "[Status diubah ke {$nextStatus->label()}] "
                    : '';

                $this->sengketaRepository->appendLog([
                    'dispute_id' => (int) $sengketa->id,
                    'user_id' => (int) $actor->id,
                    'type' => $logType,
                    'message' => $prefix.$message,
                    'attachment_path' => $storedPath,
                ]);
            });
        } catch (Throwable $e) {
            if (is_string($storedPath) && Storage::disk(self::STORAGE_DISK)->exists($storedPath)) {
                Storage::disk(self::STORAGE_DISK)->delete($storedPath);
            }

            throw $e;
        }

        if ($nextStatus !== null) {
            $this->invalidateDisputeDashboards($sengketa);
        }

        $updated = $this->sengketaRepository->findByUuidForActor($actor, $uuid) ?? $sengketa;
        $this->notifyStakeholdersOnReview($actor, $updated);

        return $updated;
    }

    /**
     * Invalidate dashboard caches affected by a dispute status change.
     */
    private function invalidateDisputeDashboards(Sengketa $sengketa): void
    {
        if ($this->dashboardService === null) {
            return;
        }

        $purchaseOrder = $sengketa->purchaseOrder;

        if ($purchaseOrder === null) {
            return;
        }

        $tenantId = (int) $purchaseOrder->perusahaan_buyer_id;
        $vendorId = (int) $purchaseOrder->vendor_id;

        if ($tenantId > 0) {
            $this->dashboardService->invalidateTenantDashboards($tenantId);
        }

        if ($vendorId > 0) {
            $this->dashboardService->invalidateVendorDashboard($vendorId);
        }
    }

    /**
     * Resolve a private-disk QC rejection photo belonging to a dispute (`RULES` §8.7).
     *
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     */
    public function resolveQcPhotoPath(User $actor, string $disputeUuid, string $receiptItemUuid, int $photoIndex): string
    {
        $sengketa = $this->findForUser($actor, $disputeUuid);

        /** @var PenerimaanBarangItem|null $matchedItem */
        $matchedItem = null;

        if ($sengketa->poItem !== null && $sengketa->poItem->relationLoaded('penerimaanBarangItems')) {
            $matchedItem = $sengketa->poItem->penerimaanBarangItems->firstWhere('uuid', $receiptItemUuid);
        }

        if ($matchedItem === null && $sengketa->purchaseOrder !== null && $sengketa->purchaseOrder->relationLoaded('penerimaanBarang')) {
            foreach ($sengketa->purchaseOrder->penerimaanBarang as $receipt) {
                $candidate = $receipt->items->firstWhere('uuid', $receiptItemUuid);
                if ($candidate !== null) {
                    $matchedItem = $candidate;
                    break;
                }
            }
        }

        if ($matchedItem === null) {
            throw new ModelNotFoundException('Foto bukti pemeriksaan QC tidak ditemukan pada sengketa ini.');
        }

        $photos = is_array($matchedItem->rejection_photos) ? array_values($matchedItem->rejection_photos) : [];
        $path = $photos[$photoIndex] ?? null;

        if (! is_string($path) || $path === '' || ! Storage::disk(self::STORAGE_DISK)->exists($path)) {
            throw new ModelNotFoundException('Berkas foto bukti pemeriksaan QC tidak ditemukan.');
        }

        return $path;
    }

    /**
     * Resolve a private-disk attachment belonging to a `dispute_logs` entry of this dispute (`RULES` §8.7).
     *
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     */
    public function resolveLogAttachmentPath(User $actor, string $disputeUuid, string $logUuid): string
    {
        $sengketa = $this->findForUser($actor, $disputeUuid);

        /** @var LogSengketa|null $log */
        $log = $sengketa->logs->firstWhere('uuid', $logUuid);
        if ($log === null || ! is_string($log->attachment_path) || $log->attachment_path === '') {
            throw new ModelNotFoundException('Lampiran bukti log sengketa tidak ditemukan.');
        }

        if (! Storage::disk(self::STORAGE_DISK)->exists($log->attachment_path)) {
            throw new ModelNotFoundException('Berkas lampiran log sengketa tidak ditemukan.');
        }

        return $log->attachment_path;
    }

    private function notifyStakeholdersOnReview(User $actor, Sengketa $sengketa): void
    {
        try {
            $recipients = $this->sengketaRepository->getStakeholderRecipients($sengketa, (int) $actor->id);
            if ($recipients->isEmpty()) {
                return;
            }

            $poNumber = $sengketa->purchaseOrder?->nomor_po ?? 'PO';
            $title = "Tinjauan Kendala Pengadaan ({$poNumber})";
            $summary = "{$actor->name} menambahkan catatan pada Log Sengketa {$poNumber} (Status: {$sengketa->status->label()}).";
            $actionUrl = route('log-sengketa.show', $sengketa->uuid, false);

            foreach ($recipients as $recipient) {
                $this->notificationService->send(
                    $recipient,
                    'system',
                    $title,
                    $summary,
                    $actionUrl,
                    [
                        'dispute_uuid' => $sengketa->uuid,
                        'po_number' => $poNumber,
                    ]
                );
            }
        } catch (Throwable) {
            // Notification failure must never abort dispute review persistence.
        }
    }
}
