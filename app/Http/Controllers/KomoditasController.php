<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\Komoditas\IndexKomoditasRequest;
use App\Http\Requests\Komoditas\StoreKomoditasRequest;
use App\Http\Requests\Komoditas\UpdateKomoditasRequest;
use App\Http\Resources\KomoditasResource;
use App\Models\Komoditas;
use App\Services\KomoditasService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class KomoditasController extends Controller
{
    public function __construct(
        protected KomoditasService $service
    ) {}

    /**
     * Display a listing of commodities.
     */
    public function index(IndexKomoditasRequest $request): Response|JsonResponse
    {
        $filters = $request->validated();
        $perPage = (int) $request->input('per_page', 10);
        $komoditas = $this->service->paginate($filters, $perPage);

        if ($request->expectsJson()) {
            return ResponseHelper::success(
                'Daftar komoditas berhasil diambil.',
                KomoditasResource::collection($komoditas)
            );
        }

        return Inertia::render('Master/Komoditas/Index', [
            'komoditas' => KomoditasResource::collection($komoditas),
            'filters' => (object) array_filter(
                $request->only('search', 'sort', 'direction', 'commodity_category_uuid', 'status', 'per_page'),
                fn ($val) => $val !== null && $val !== ''
            ),
            'satuan_options' => $this->service->getSatuanOptions(),
            'category_options' => $this->service->getCategoryOptions(),
            'can' => [
                'create' => $request->user()->can('create', Komoditas::class),
            ],
        ]);
    }

    /**
     * Show the form for creating a new commodity.
     */
    public function create(): Response
    {
        return Inertia::render('Master/Komoditas/Create', [
            'satuan_options' => $this->service->getSatuanOptions(),
            'category_options' => $this->service->getCategoryOptions(),
        ]);
    }

    /**
     * Store a newly created commodity.
     */
    public function store(StoreKomoditasRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $komoditas = $this->service->create($request->validated());

            return ResponseHelper::created(
                'Komoditas berhasil ditambahkan.',
                new KomoditasResource($komoditas),
                route('master.komoditas.index')
            );
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal menambahkan data komoditas.', $e);
        }
    }

    /**
     * Show the form for editing the specified commodity.
     */
    public function edit(string $uuid): Response|JsonResponse|RedirectResponse
    {
        try {
            $komoditas = $this->service->findByUuid($uuid);

            return Inertia::render('Master/Komoditas/Edit', [
                'komoditas' => new KomoditasResource($komoditas),
                'satuan_options' => $this->service->getSatuanOptions(),
                'category_options' => $this->service->getCategoryOptions(),
            ]);
        } catch (ModelNotFoundException) {
            return ResponseHelper::notFound('Komoditas tidak ditemukan.');
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memuat form edit komoditas.', $e);
        }
    }

    /**
     * Update the specified commodity.
     */
    public function update(UpdateKomoditasRequest $request, string $uuid): JsonResponse|RedirectResponse
    {
        try {
            $komoditas = $this->service->update($request->user(), $uuid, $request->validated());

            return ResponseHelper::success(
                'Komoditas berhasil diperbarui.',
                new KomoditasResource($komoditas),
                route('master.komoditas.index')
            );
        } catch (AuthorizationException) {
            return ResponseHelper::forbidden('Anda tidak memiliki akses untuk mengubah komoditas.');
        } catch (ModelNotFoundException) {
            return ResponseHelper::notFound('Komoditas tidak ditemukan.');
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memperbarui data komoditas.', $e);
        }
    }

    /**
     * Remove the specified commodity.
     */
    public function destroy(Request $request, string $uuid): JsonResponse|RedirectResponse
    {
        try {
            $this->service->delete($request->user(), $uuid);

            return ResponseHelper::success(
                'Komoditas berhasil dihapus.',
                null,
                route('master.komoditas.index')
            );
        } catch (AuthorizationException) {
            return ResponseHelper::forbidden('Anda tidak memiliki akses untuk menghapus komoditas.');
        } catch (ModelNotFoundException) {
            return ResponseHelper::notFound('Komoditas tidak ditemukan.');
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal menghapus data komoditas.', $e);
        }
    }
}
