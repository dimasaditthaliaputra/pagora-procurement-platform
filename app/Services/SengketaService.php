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
use Illuminate\Validation\ValidationException;
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

        return $this->sengketaRepository->paginateForTenant((int) $actor->tenant_id, $filters, $perPage);
    }

    /**
     * Compute dispute summary statistics for the authenticated user's Tenant.
     *
     * @return array{
     *     total: int,
     *     open: int,
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

        return $this->sengketaRepository->getStatisticsForTenant((int) $actor->tenant_id);
    }

    /**
     * Retrieve a single dispute with full context (PO, item, QC photos, negotiation chat, immutable audit log)
     * strictly isolated to the actor's Tenant (`RULES` §5.1 & §9.3: cross-tenant returns 404).
     *
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     */
    public function findForUser(User $actor, string $uuid): Sengketa
    {
        Gate::forUser($actor)->authorize('viewAny', Sengketa::class);

        $sengketa = $this->sengketaRepository->findByUuidForTenant((int) $actor->tenant_id, $uuid);
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
     * Record a review/mediation note by Manajemen or Owner into the immutable `log_sengketa` trail,
     * automatically transitioning `terbuka` -> `dalam_mediasi` (`UC-18` step 3, `PRD §9.3`, `BR-17`).
     *
     * @param  array<string, mixed>  $validated
     *
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function storeReviewNote(
        User $actor,
        string $uuid,
        array $validated,
        ?UploadedFile $attachment = null,
    ): Sengketa {
        if ($actor->tenant_id === null || $actor->isSuperadmin()) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk meninjau sengketa pengadaan.');
        }

        $sengketa = $this->sengketaRepository->findByUuidForTenant((int) $actor->tenant_id, $uuid);
        if ($sengketa === null) {
            throw new ModelNotFoundException('Data sengketa tidak ditemukan.');
        }

        Gate::forUser($actor)->authorize('review', $sengketa);

        if ($sengketa->status === StatusSengketa::Selesai) {
            throw ValidationException::withMessages([
                'message' => 'Sengketa yang telah berstatus Selesai bersifat permanen (immutable) dan tidak dapat ditambahkan catatan tinjauan baru.',
            ]);
        }

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

            DB::transaction(function () use ($actor, $sengketa, $message, $storedPath): void {
                $transitionedToMediation = false;

                if ($sengketa->status === StatusSengketa::Terbuka) {
                    $this->sengketaRepository->updateStatus($sengketa, StatusSengketa::DalamMediasi);
                    $transitionedToMediation = true;
                }

                $logType = $storedPath !== null
                    ? 'evidence'
                    : ($transitionedToMediation ? 'status_change' : 'note');

                $prefix = $transitionedToMediation
                    ? '[Status diubah ke Dalam Mediasi] '
                    : '';

                $this->sengketaRepository->appendLog([
                    'sengketa_id' => (int) $sengketa->id,
                    'user_id' => (int) $actor->id,
                    'type' => $logType,
                    'isi_pesan' => $prefix.$message,
                    'file_bukti' => $storedPath,
                ]);
            });
        } catch (Throwable $e) {
            if (is_string($storedPath) && Storage::disk(self::STORAGE_DISK)->exists($storedPath)) {
                Storage::disk(self::STORAGE_DISK)->delete($storedPath);
            }

            throw $e;
        }

        $updated = $this->sengketaRepository->findByUuidForTenant((int) $actor->tenant_id, $uuid) ?? $sengketa;
        $this->notifyStakeholdersOnReview($actor, $updated);

        return $updated;
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
     * Resolve a private-disk attachment belonging to a `log_sengketa` entry of this dispute (`RULES` §8.7).
     *
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     */
    public function resolveLogAttachmentPath(User $actor, string $disputeUuid, string $logUuid): string
    {
        $sengketa = $this->findForUser($actor, $disputeUuid);

        /** @var LogSengketa|null $log */
        $log = $sengketa->logs->firstWhere('uuid', $logUuid);
        if ($log === null || ! is_string($log->file_bukti) || $log->file_bukti === '') {
            throw new ModelNotFoundException('Lampiran bukti log sengketa tidak ditemukan.');
        }

        if (! Storage::disk(self::STORAGE_DISK)->exists($log->file_bukti)) {
            throw new ModelNotFoundException('Berkas lampiran log sengketa tidak ditemukan.');
        }

        return $log->file_bukti;
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
            $summary = "{$actor->name} menambahkan catatan tinjauan pada Log Sengketa {$poNumber} (Status: {$sengketa->status->label()}).";

            foreach ($recipients as $recipient) {
                $actionUrl = $recipient->isVendor()
                    ? '/inspeksi-qc'
                    : route('log-sengketa.show', $sengketa->uuid, false);

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
