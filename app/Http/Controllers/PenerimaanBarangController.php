<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\PenerimaanBarang\IndexPenerimaanBarangRequest;
use App\Http\Requests\PenerimaanBarang\StorePenerimaanBarangRequest;
use App\Http\Resources\PenerimaanBarangResource;
use App\Models\PenerimaanBarang;
use App\Models\User;
use App\Services\PenerimaanBarangService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Thin controller for Goods Receipt / QC Inspection (`UC-15`, `FR-24`..`FR-26`, `BR-09`, `BR-10`, `BR-17`).
 */
class PenerimaanBarangController extends Controller
{
    public function __construct(
        private readonly PenerimaanBarangService $penerimaanService,
    ) {}

    /**
     * Display the QC inspection queue (`In Transit` orders) and goods receipt history.
     */
    public function index(IndexPenerimaanBarangRequest $request): Response|JsonResponse|RedirectResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            $filters = $request->validated();
            $perPage = (int) ($filters['per_page'] ?? 10);

            $receipts = $this->penerimaanService->paginateForUser($user, $filters, $perPage);
            $statistics = $this->penerimaanService->getStatisticsForUser($user);
            $inspectableQueue = $this->penerimaanService->getInspectableQueueForUser($user);

            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return ResponseHelper::success(
                    'Daftar inspeksi penerimaan barang berhasil diambil.',
                    PenerimaanBarangResource::collection($receipts),
                    null,
                    $request
                );
            }

            return Inertia::render('InspeksiQc/Index', [
                'receipts' => PenerimaanBarangResource::collection($receipts),
                'inspectable_orders' => $inspectableQueue,
                'statistics' => $statistics,
                'filters' => [
                    'search' => $filters['search'] ?? '',
                    'result' => $filters['result'] ?? 'all',
                    'sort' => $filters['sort'] ?? '',
                    'direction' => $filters['direction'] ?? 'desc',
                    'per_page' => $perPage,
                ],
                'current_role' => $user->roleName()?->value ?? 'qc_gudang',
                'can' => [
                    'create' => $user->can('create', PenerimaanBarang::class),
                ],
            ]);
        } catch (AuthorizationException $e) {
            if ($request->expectsJson()) {
                return ResponseHelper::forbidden(
                    'Anda tidak memiliki akses ke halaman inspeksi penerimaan barang.',
                    null,
                    $request
                );
            }

            throw $e;
        } catch (Throwable $e) {
            return ResponseHelper::serverError(
                'Gagal memuat daftar inspeksi penerimaan barang.',
                $e,
                $request
            );
        }
    }

    /**
     * Display the QC Goods Inspection form for `"In Transit"` Purchase Orders (`UC-15`).
     */
    public function create(Request $request): Response|JsonResponse|RedirectResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            Gate::forUser($user)->authorize('create', PenerimaanBarang::class);

            $preselectedPoUuid = $request->query('po');
            $formData = $this->penerimaanService->getCreateFormData(
                $user,
                is_string($preselectedPoUuid) ? trim($preselectedPoUuid) : null
            );

            return Inertia::render('InspeksiQc/Create', $formData);
        } catch (AuthorizationException $e) {
            if ($request->expectsJson()) {
                return ResponseHelper::forbidden(
                    'Hanya QC Gudang dan Owner yang dapat melakukan inspeksi penerimaan barang.',
                    null,
                    $request
                );
            }

            throw $e;
        } catch (ModelNotFoundException $e) {
            if ($request->expectsJson()) {
                return ResponseHelper::notFound(
                    'Purchase Order tidak ditemukan.',
                    null,
                    $request
                );
            }

            throw $e;
        } catch (Throwable $e) {
            return ResponseHelper::serverError(
                'Gagal memuat formulir inspeksi penerimaan barang.',
                $e,
                $request
            );
        }
    }

    /**
     * Store per-item QC inspection results and open disputes for rejected items (`UC-15`).
     */
    public function store(StorePenerimaanBarangRequest $request): JsonResponse|RedirectResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            $validated = $request->validated();

            $uploadedPhotosByIndex = [];
            foreach (array_keys($validated['items'] ?? []) as $index) {
                $files = $request->file("items.{$index}.photos");
                if ($files instanceof UploadedFile) {
                    $uploadedPhotosByIndex[(int) $index] = [$files];
                } elseif (is_array($files)) {
                    $uploadedPhotosByIndex[(int) $index] = array_values(array_filter(
                        $files,
                        static fn ($f): bool => $f instanceof UploadedFile
                    ));
                }
            }

            $receipt = $this->penerimaanService->store($user, $validated, $uploadedPhotosByIndex);

            $hasRejection = $receipt->items->contains(
                static fn ($item): bool => ($item->status->value ?? $item->status) === 'rejected'
            );

            $message = $hasRejection
                ? 'Hasil inspeksi penerimaan barang disimpan. Item yang ditolak telah dicatat ke log sengketa.'
                : 'Hasil inspeksi penerimaan barang berhasil disimpan dengan status sesuai (Disetujui).';

            return ResponseHelper::created(
                $message,
                (new PenerimaanBarangResource($receipt))->resolve($request),
                route('inspeksi-qc.show', $receipt->uuid),
                $request
            );
        } catch (ValidationException $e) {
            return ResponseHelper::validationError(
                $e->errors(),
                'Data formulir inspeksi barang belum lengkap atau belum sesuai.',
                $request
            );
        } catch (AuthorizationException $e) {
            if ($request->expectsJson()) {
                return ResponseHelper::forbidden(
                    'Hanya QC Gudang dan Owner yang dapat menyimpan hasil inspeksi penerimaan barang.',
                    null,
                    $request
                );
            }

            throw $e;
        } catch (ModelNotFoundException $e) {
            if ($request->expectsJson()) {
                return ResponseHelper::notFound(
                    'Purchase Order tidak ditemukan.',
                    null,
                    $request
                );
            }

            throw $e;
        } catch (Throwable $e) {
            return ResponseHelper::serverError(
                'Gagal menyimpan hasil inspeksi penerimaan barang.',
                $e,
                $request
            );
        }
    }

    /**
     * Display details of a completed QC inspection (`goods_receipts`).
     */
    public function show(Request $request, string $uuid): Response|JsonResponse|RedirectResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            $receipt = $this->penerimaanService->findByUuidForUser($user, $uuid);

            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return ResponseHelper::success(
                    'Detail inspeksi penerimaan barang berhasil diambil.',
                    new PenerimaanBarangResource($receipt),
                    null,
                    $request
                );
            }

            return Inertia::render('InspeksiQc/Show', [
                'receipt' => new PenerimaanBarangResource($receipt),
                'current_role' => $user->roleName()?->value ?? 'qc_gudang',
            ]);
        } catch (AuthorizationException $e) {
            if ($request->expectsJson()) {
                return ResponseHelper::forbidden(
                    'Anda tidak memiliki akses ke detail inspeksi ini.',
                    null,
                    $request
                );
            }

            throw $e;
        } catch (ModelNotFoundException $e) {
            if ($request->expectsJson()) {
                return ResponseHelper::notFound(
                    'Data inspeksi penerimaan barang tidak ditemukan.',
                    null,
                    $request
                );
            }

            throw $e;
        } catch (Throwable $e) {
            return ResponseHelper::serverError(
                'Gagal memuat detail inspeksi penerimaan barang.',
                $e,
                $request
            );
        }
    }

    /**
     * Stream an immutable QC rejection photo from private storage to an authorized user (`BR-17`, `RULES` §8.7).
     */
    public function photo(Request $request, string $itemUuid, int $index): StreamedResponse|JsonResponse|RedirectResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            $path = $this->penerimaanService->resolveRejectionPhotoPath($user, $itemUuid, $index);

            /** @var FilesystemAdapter $disk */
            $disk = Storage::disk(PenerimaanBarangService::STORAGE_DISK);

            if (! $disk->exists($path)) {
                if ($request->expectsJson()) {
                    return ResponseHelper::notFound(
                        'Berkas foto bukti penolakan tidak ditemukan.',
                        null,
                        $request
                    );
                }

                throw new NotFoundHttpException('Berkas foto bukti penolakan tidak ditemukan.');
            }

            return $disk->response($path, basename($path), [
                'Cache-Control' => 'private, max-age=3600',
            ]);
        } catch (AuthorizationException $e) {
            if ($request->expectsJson()) {
                return ResponseHelper::forbidden(
                    'Anda tidak memiliki akses untuk melihat foto bukti penolakan ini.',
                    null,
                    $request
                );
            }

            throw $e;
        } catch (ModelNotFoundException|NotFoundHttpException $e) {
            if ($request->expectsJson()) {
                return ResponseHelper::notFound(
                    'Foto bukti penolakan tidak ditemukan.',
                    null,
                    $request
                );
            }

            throw $e;
        } catch (Throwable $e) {
            return ResponseHelper::serverError(
                'Gagal memuat foto bukti penolakan.',
                $e,
                $request
            );
        }
    }
}
