<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PemicuSengketa;
use App\Enums\StatusSengketa;
use App\Helpers\ResponseHelper;
use App\Http\Requests\Sengketa\IndexSengketaRequest;
use App\Http\Requests\Sengketa\ProposeResolutionRequest;
use App\Http\Requests\Sengketa\ResolveSengketaRequest;
use App\Http\Requests\Sengketa\SettlementDecisionRequest;
use App\Http\Requests\Sengketa\StoreTinjauanSengketaRequest;
use App\Http\Resources\SengketaResource;
use App\Models\User;
use App\Services\DisputeResolutionService;
use App\Services\SengketaService;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Thin controller for Reviewing Procurement Issues & Log Sengketa
 * (`UC-18`, `FR-07`, `FR-31`, `FR-38`, `BR-14`, `BR-15`, `BR-17`, `RULES` §4.1 & §6).
 */
class SengketaController extends Controller
{
    public function __construct(
        private readonly SengketaService $sengketaService,
        private readonly DisputeResolutionService $resolutionService,
    ) {}

    /**
     * Display the paginated list of procurement disputes (`Log Sengketa`).
     */
    public function index(IndexSengketaRequest $request): Response|JsonResponse|RedirectResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            $filters = $request->validated();
            $perPage = (int) ($filters['per_page'] ?? 10);

            $disputes = $this->sengketaService->paginateForUser($user, $filters, $perPage);
            $statistics = $this->sengketaService->getStatisticsForUser($user);

            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return ResponseHelper::success(
                    'Daftar log sengketa pengadaan berhasil diambil.',
                    [
                        'items' => SengketaResource::collection($disputes),
                        'statistics' => $statistics,
                    ],
                    null,
                    $request
                );
            }

            return Inertia::render('LogSengketa/Index', [
                'disputes' => SengketaResource::collection($disputes),
                'statistics' => $statistics,
                'status_options' => StatusSengketa::options(),
                'trigger_options' => PemicuSengketa::allOptions(),
                'filters' => [
                    'search' => $filters['search'] ?? '',
                    'status' => $filters['status'] ?? 'all',
                    'trigger_type' => $filters['trigger_type'] ?? 'all',
                    'date_from' => $filters['date_from'] ?? '',
                    'date_to' => $filters['date_to'] ?? '',
                    'sort' => $filters['sort'] ?? '',
                    'direction' => $filters['direction'] ?? 'desc',
                    'per_page' => $perPage,
                ],
            ]);
        } catch (AuthorizationException $e) {
            if ($request->expectsJson()) {
                return ResponseHelper::forbidden(
                    'Anda tidak memiliki akses ke halaman Log Sengketa.',
                    null,
                    $request
                );
            }

            throw $e;
        } catch (Throwable $e) {
            return ResponseHelper::serverError(
                'Gagal memuat daftar log sengketa pengadaan.',
                $e,
                $request
            );
        }
    }

    /**
     * Display the complete detail of a selected procurement dispute (`UC-18` step 2, `FR-31`).
     */
    public function show(Request $request, string $uuid): Response|JsonResponse|RedirectResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            $sengketa = $this->sengketaService->findForUser($user, $uuid);

            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return ResponseHelper::success(
                    'Detail sengketa pengadaan berhasil diambil.',
                    (new SengketaResource($sengketa))->resolve($request),
                    null,
                    $request
                );
            }

            return Inertia::render('LogSengketa/Show', [
                'dispute' => (new SengketaResource($sengketa))->resolve($request),
                'settlement' => $this->resolutionService->context($user, $sengketa),
            ]);
        } catch (ModelNotFoundException) {
            if ($request->expectsJson()) {
                return ResponseHelper::notFound('Data sengketa tidak ditemukan.', null, $request);
            }

            throw new NotFoundHttpException('Data sengketa tidak ditemukan.');
        } catch (AuthorizationException $e) {
            if ($request->expectsJson()) {
                return ResponseHelper::forbidden(
                    'Anda tidak memiliki akses untuk meninjau sengketa ini.',
                    null,
                    $request
                );
            }

            throw $e;
        } catch (Throwable $e) {
            return ResponseHelper::serverError(
                'Gagal memuat detail sengketa pengadaan.',
                $e,
                $request
            );
        }
    }

    /**
     * Export and download the complete procurement dispute document as PDF (`UC-18` step 8, `FR-31`).
     */
    public function export(Request $request, string $uuid): HttpResponse|JsonResponse|RedirectResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            $exported = $this->sengketaService->exportPdfForUser($user, $uuid);

            return response($exported['content'], 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$exported['filename'].'"',
                'Cache-Control' => 'private, no-store, max-age=0',
            ]);
        } catch (ModelNotFoundException) {
            if ($request->expectsJson()) {
                return ResponseHelper::notFound('Data sengketa tidak ditemukan.', null, $request);
            }

            throw new NotFoundHttpException('Data sengketa tidak ditemukan.');
        } catch (AuthorizationException $e) {
            if ($request->expectsJson()) {
                return ResponseHelper::forbidden(
                    'Anda tidak memiliki akses untuk mengunduh dokumen sengketa ini.',
                    null,
                    $request
                );
            }

            throw $e;
        } catch (Throwable $e) {
            return ResponseHelper::serverError(
                'Gagal mengekspor dokumen sengketa pengadaan.',
                $e,
                $request
            );
        }
    }

    /**
     * Store an immutable review/mediation log entry by Manajemen or Owner (`UC-18` step 3, `PRD §9.3`).
     */
    public function storeReview(StoreTinjauanSengketaRequest $request, string $uuid): JsonResponse|RedirectResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            $sengketa = $this->sengketaService->storeReviewNote(
                $user,
                $uuid,
                $request->validated(),
                $request->file('attachment')
            );

            return ResponseHelper::created(
                'Catatan tinjauan berhasil disimpan secara permanen pada Log Sengketa.',
                (new SengketaResource($sengketa))->resolve($request),
                route('log-sengketa.show', $sengketa->uuid),
                $request
            );
        } catch (ValidationException $e) {
            return ResponseHelper::validationError(
                $e->errors(),
                'Catatan tinjauan sengketa belum sesuai.',
                $request
            );
        } catch (ModelNotFoundException) {
            if ($request->expectsJson()) {
                return ResponseHelper::notFound('Data sengketa tidak ditemukan.', null, $request);
            }

            throw new NotFoundHttpException('Data sengketa tidak ditemukan.');
        } catch (AuthorizationException $e) {
            if ($request->expectsJson()) {
                return ResponseHelper::forbidden(
                    'Hanya Manajemen dan Owner yang dapat mencatat tinjauan mediasi sengketa.',
                    null,
                    $request
                );
            }

            throw $e;
        } catch (Throwable $e) {
            return ResponseHelper::serverError(
                'Gagal menyimpan catatan tinjauan sengketa.',
                $e,
                $request
            );
        }
    }

    /**
     * Stream a private QC rejection photo associated with this dispute (`RULES` §8.7).
     */
    public function qcPhoto(
        Request $request,
        string $uuid,
        string $itemUuid,
        int $index,
    ): StreamedResponse|JsonResponse|RedirectResponse {
        try {
            /** @var User $user */
            $user = $request->user();
            $path = $this->sengketaService->resolveQcPhotoPath($user, $uuid, $itemUuid, $index);

            /** @var FilesystemAdapter $disk */
            $disk = Storage::disk(SengketaService::STORAGE_DISK);
            $mime = $disk->mimeType($path) ?: 'image/jpeg';

            return $disk->response($path, basename($path), [
                'Content-Type' => $mime,
                'Cache-Control' => 'private, max-age=3600',
            ]);
        } catch (ModelNotFoundException) {
            if ($request->expectsJson()) {
                return ResponseHelper::notFound('Foto bukti QC tidak ditemukan.', null, $request);
            }

            throw new NotFoundHttpException('Foto bukti QC tidak ditemukan.');
        } catch (AuthorizationException $e) {
            if ($request->expectsJson()) {
                return ResponseHelper::forbidden('Anda tidak memiliki akses ke foto bukti ini.', null, $request);
            }

            throw $e;
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memuat foto bukti QC.', $e, $request);
        }
    }

    /**
     * Stream a private attachment belonging to an immutable `dispute_logs` entry (`RULES` §8.7).
     */
    public function attachment(
        Request $request,
        string $uuid,
        string $logUuid,
    ): StreamedResponse|JsonResponse|RedirectResponse {
        try {
            /** @var User $user */
            $user = $request->user();
            $path = $this->sengketaService->resolveLogAttachmentPath($user, $uuid, $logUuid);

            /** @var FilesystemAdapter $disk */
            $disk = Storage::disk(SengketaService::STORAGE_DISK);
            $mime = $disk->mimeType($path) ?: 'application/octet-stream';

            return $disk->response($path, basename($path), [
                'Content-Type' => $mime,
                'Cache-Control' => 'private, max-age=3600',
            ]);
        } catch (ModelNotFoundException) {
            if ($request->expectsJson()) {
                return ResponseHelper::notFound('Lampiran bukti sengketa tidak ditemukan.', null, $request);
            }

            throw new NotFoundHttpException('Lampiran bukti sengketa tidak ditemukan.');
        } catch (AuthorizationException $e) {
            if ($request->expectsJson()) {
                return ResponseHelper::forbidden('Anda tidak memiliki akses ke lampiran bukti ini.', null, $request);
            }

            throw $e;
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memuat lampiran bukti sengketa.', $e, $request);
        }
    }

    /**
     * Settlement form page: reshipment, return, or credit note (`UC-18` step 4, RULES §8.9).
     */
    public function createResolution(Request $request, string $uuid): Response|JsonResponse|RedirectResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            $sengketa = $this->sengketaService->findForUser($user, $uuid);
            $form = $this->resolutionService->proposalForm($user, $sengketa);

            return Inertia::render('LogSengketa/Penyelesaian', [
                'dispute' => (new SengketaResource($sengketa))->resolve($request),
                'form' => $form,
            ]);
        } catch (ModelNotFoundException) {
            throw new NotFoundHttpException('Data sengketa tidak ditemukan.');
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memuat form penyelesaian sengketa.', $e, $request);
        }
    }

    /**
     * Owner/Manajemen propose a settlement and issue its document (`UC-18` step 4–5, FR-40..42).
     */
    public function proposeResolution(ProposeResolutionRequest $request, string $uuid): JsonResponse|RedirectResponse
    {
        return $this->settle($request, $uuid, 'Penyelesaian diajukan dan menunggu konfirmasi Vendor.', fn (User $user) => $this->resolutionService->propose($user, $uuid, $request->validated()));
    }

    /**
     * Owner/Manajemen set the dispute to `resolved` after verifying the settlement (`UC-18` step 6, BR-15).
     */
    public function resolve(ResolveSengketaRequest $request, string $uuid): JsonResponse|RedirectResponse
    {
        return $this->settle($request, $uuid, 'Sengketa ditetapkan Selesai.', fn (User $user) => $this->resolutionService->resolve($user, $uuid, $request->validated('note')));
    }

    /**
     * The Vendor accepts or refuses a proposed reshipment (`UC-18` step 5, 5a).
     */
    public function decideReshipment(SettlementDecisionRequest $request, string $uuid): JsonResponse|RedirectResponse
    {
        $accepted = $request->isAccepted();

        return $this->settle($request, $uuid, $accepted ? 'Pengiriman ulang disetujui.' : 'Pengiriman ulang ditolak.', fn (User $user) => $this->resolutionService->decideReshipment($user, $uuid, $accepted, $request->validated('reason')));
    }

    /**
     * The Vendor confirms or refuses a credit note (FR-41, BR-16).
     */
    public function decideCreditNote(SettlementDecisionRequest $request, string $uuid, string $creditNoteUuid): JsonResponse|RedirectResponse
    {
        $accepted = $request->isAccepted();

        return $this->settle($request, $uuid, $accepted ? 'Credit note dikonfirmasi.' : 'Credit note ditolak.', fn (User $user) => $this->resolutionService->decideCreditNote($user, $creditNoteUuid, $accepted, $request->validated('reason')));
    }

    /**
     * The Vendor confirms or refuses a purchase return (FR-42, BR-27).
     */
    public function decidePurchaseReturn(SettlementDecisionRequest $request, string $uuid, string $returnUuid): JsonResponse|RedirectResponse
    {
        $accepted = $request->isAccepted();

        return $this->settle($request, $uuid, $accepted ? 'Retur dikonfirmasi.' : 'Retur ditolak.', fn (User $user) => $this->resolutionService->decidePurchaseReturn($user, $returnUuid, $accepted, $request->validated('reason')));
    }

    /**
     * The Vendor marks the returned goods as received back (`confirmed` → `completed`).
     */
    public function completePurchaseReturn(Request $request, string $uuid, string $returnUuid): JsonResponse|RedirectResponse
    {
        return $this->settle($request, $uuid, 'Retur ditandai Selesai.', fn (User $user) => $this->resolutionService->completePurchaseReturn($user, $returnUuid));
    }

    /**
     * Run a settlement action and answer with the shared response conventions (`RULES` §6), back on the dispute page.
     */
    private function settle(Request $request, string $uuid, string $successMessage, Closure $action): JsonResponse|RedirectResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            $action($user);

            return ResponseHelper::success($successMessage, null, route('log-sengketa.show', $uuid), $request);
        } catch (ValidationException $e) {
            return ResponseHelper::validationError($e->errors(), 'Penyelesaian sengketa belum dapat diproses.', $request);
        } catch (ModelNotFoundException) {
            return ResponseHelper::notFound('Data sengketa atau dokumen penyelesaian tidak ditemukan.', null, $request);
        } catch (AuthorizationException) {
            return ResponseHelper::forbidden('Anda tidak dapat melakukan aksi ini pada sengketa.', null, $request);
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memproses penyelesaian sengketa.', $e, $request);
        }
    }
}
