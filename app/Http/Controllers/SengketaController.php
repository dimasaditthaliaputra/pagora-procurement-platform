<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PemicuSengketa;
use App\Enums\StatusSengketa;
use App\Helpers\ResponseHelper;
use App\Http\Requests\Sengketa\IndexSengketaRequest;
use App\Http\Requests\Sengketa\StoreTinjauanSengketaRequest;
use App\Http\Resources\SengketaResource;
use App\Models\User;
use App\Services\SengketaService;
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
     * Stream a private attachment belonging to an immutable `log_sengketa` entry (`RULES` §8.7).
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
}
