<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\Komoditas\IndexKomoditasRequest;
use App\Http\Requests\Komoditas\StoreKomoditasRequest;
use App\Http\Requests\Komoditas\UpdateKomoditasRequest;
use App\Http\Resources\KomoditasResource;
use App\Services\KomoditasService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
        $komoditas = $this->service->paginate($filters, $perPage, [
            'id',
            'uuid',
            'nama_komoditas',
            'sku',
            'kategori',
            'satuan',
            'kode_referensi_api',
            'status_aktif',
            'created_at',
        ]);

        if ($request->expectsJson()) {
            return ResponseHelper::success(
                'Daftar komoditas berhasil diambil.',
                KomoditasResource::collection($komoditas)
            );
        }

        return Inertia::render('Master/Komoditas/Index', [
            'komoditas' => KomoditasResource::collection($komoditas),
            'filters' => (object) array_filter(
                $request->only('search', 'sort', 'direction', 'kategori', 'status_aktif', 'per_page'),
                fn ($val) => $val !== null && $val !== ''
            ),
            'satuan_options' => $this->service->getSatuanOptions(),
            'kategori_options' => $this->service->getKategoriOptions(),
        ]);
    }

    /**
     * Show the form for creating a new commodity.
     */
    public function create(): Response
    {
        return Inertia::render('Master/Komoditas/Create', [
            'satuan_options' => $this->service->getSatuanOptions(),
            'kategori_options' => $this->service->getKategoriOptions(),
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
                'kategori_options' => $this->service->getKategoriOptions(),
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
            $komoditas = $this->service->update($uuid, $request->validated());

            return ResponseHelper::success(
                'Komoditas berhasil diperbarui.',
                new KomoditasResource($komoditas),
                route('master.komoditas.index')
            );
        } catch (ModelNotFoundException) {
            return ResponseHelper::notFound('Komoditas tidak ditemukan.');
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memperbarui data komoditas.', $e);
        }
    }

    /**
     * Remove the specified commodity.
     */
    public function destroy(string $uuid): JsonResponse|RedirectResponse
    {
        try {
            $this->service->delete($uuid);

            return ResponseHelper::success(
                'Komoditas berhasil dihapus.',
                null,
                route('master.komoditas.index')
            );
        } catch (ModelNotFoundException) {
            return ResponseHelper::notFound('Komoditas tidak ditemukan.');
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal menghapus data komoditas.', $e);
        }
    }
}
