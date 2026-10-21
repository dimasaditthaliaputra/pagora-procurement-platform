<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\Pegawai\IndexPegawaiRequest;
use App\Http\Requests\Pegawai\StorePegawaiRequest;
use App\Http\Requests\Pegawai\UpdatePegawaiRequest;
use App\Http\Resources\PegawaiResource;
use App\Services\PegawaiService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PegawaiController extends Controller
{
    public function __construct(
        protected PegawaiService $service
    ) {}

    /**
     * Display a listing of internal employee accounts.
     */
    public function index(IndexPegawaiRequest $request): Response|JsonResponse
    {
        $filters = $request->validated();
        $perPage = (int) $request->input('per_page', 10);
        $pegawai = $this->service->paginate($filters, $perPage);

        if ($request->expectsJson()) {
            return ResponseHelper::success(
                'Daftar pegawai internal berhasil diambil.',
                PegawaiResource::collection($pegawai)
            );
        }

        return Inertia::render('Manajemen/Pengguna/Index', [
            'pegawai' => PegawaiResource::collection($pegawai),
            'filters' => $request->only('search', 'role', 'status_aktif', 'sort', 'direction', 'per_page'),
            'role_options' => $this->service->getRoleOptions(),
            'default_password' => PegawaiService::DEFAULT_PASSWORD,
        ]);
    }

    /**
     * Display the specified employee account.
     */
    public function show(string $uuid): JsonResponse|RedirectResponse
    {
        try {
            $pegawai = $this->service->findByUuid($uuid);

            return ResponseHelper::success(
                'Detail akun pegawai berhasil diambil.',
                new PegawaiResource($pegawai)
            );
        } catch (ModelNotFoundException) {
            return ResponseHelper::notFound('Akun pegawai tidak ditemukan.');
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memuat detail akun pegawai.', $e);
        }
    }

    /**
     * Store a newly created employee account and send initial credentials notification.
     */
    public function store(StorePegawaiRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $pegawai = $this->service->create($request->validated());

            return ResponseHelper::created(
                'Akun pegawai berhasil dibuat dan notifikasi akses telah dikirim ke email pegawai.',
                new PegawaiResource($pegawai),
                route('manajemen.pengguna.index')
            );
        } catch (ValidationException $e) {
            return ResponseHelper::validationError($e->errors());
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal membuat akun pegawai internal baru.', $e);
        }
    }

    /**
     * Update the specified employee account.
     */
    public function update(UpdatePegawaiRequest $request, string $uuid): JsonResponse|RedirectResponse
    {
        try {
            $pegawai = $this->service->update($uuid, $request->validated());

            return ResponseHelper::success(
                'Data akun pegawai berhasil diperbarui.',
                new PegawaiResource($pegawai),
                route('manajemen.pengguna.index')
            );
        } catch (ModelNotFoundException) {
            return ResponseHelper::notFound('Akun pegawai tidak ditemukan.');
        } catch (ValidationException $e) {
            return ResponseHelper::validationError($e->errors());
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memperbarui data akun pegawai.', $e);
        }
    }

    /**
     * Soft delete the specified employee account.
     */
    public function destroy(Request $request, string $uuid): JsonResponse|RedirectResponse
    {
        try {
            $this->service->delete($uuid, $request->user());

            return ResponseHelper::success(
                'Akun pegawai berhasil dinonaktifkan.',
                null,
                route('manajemen.pengguna.index')
            );
        } catch (ModelNotFoundException) {
            return ResponseHelper::notFound('Akun pegawai tidak ditemukan.');
        } catch (ValidationException $e) {
            return ResponseHelper::validationError($e->errors());
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal menghapus data akun pegawai.', $e);
        }
    }
}
