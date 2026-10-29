<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\Pegawai\IndexPegawaiRequest;
use App\Http\Requests\Pegawai\StorePegawaiRequest;
use App\Http\Requests\Pegawai\UpdatePegawaiRequest;
use App\Http\Resources\PegawaiResource;
use App\Models\User;
use App\Services\PegawaiService;
use Illuminate\Auth\Access\AuthorizationException;
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
        protected readonly PegawaiService $service
    ) {}

    /**
     * Display a listing of user accounts scoped to the signed-in user's company.
     *
     * - superadmin        : lihat & kelola seluruh akun lintas perusahaan + filter perusahaan
     * - owner / manajemen : hanya lihat & kelola akun pada perusahaan buyer sendiri
     * - vendor            : hanya lihat & kelola akun pada perusahaan vendor sendiri
     */
    public function index(IndexPegawaiRequest $request): Response|JsonResponse|RedirectResponse
    {
        /** @var User $authUser */
        $authUser = $request->user()->loadMissing('role');

        if (! $authUser->canManagePengguna()) {
            return ResponseHelper::forbidden('Anda tidak memiliki akses ke halaman Manajemen Pengguna.');
        }

        $filters = $request->validated();
        $perPage = (int) $request->input('per_page', 10);
        $pegawai = $this->service->paginateForActor($filters, $authUser, $perPage);

        if ($request->expectsJson()) {
            return ResponseHelper::success(
                'Daftar pengguna berhasil diambil.',
                PegawaiResource::collection($pegawai)
            );
        }

        $isSuperadmin = $authUser->isSuperadmin();

        return Inertia::render('Manajemen/Pengguna/Index', [
            'pegawai' => PegawaiResource::collection($pegawai),
            'filters' => $request->only('search', 'role', 'perusahaan', 'status_aktif', 'sort', 'direction', 'per_page'),
            'role_options' => $this->service->getRoleOptionsForUser($authUser),
            'filter_role_options' => $this->service->getFilterRoleOptionsForUser($authUser),
            'is_superadmin' => $isSuperadmin,
            'current_perusahaan' => $isSuperadmin ? null : $this->service->getCurrentPerusahaanPayload($authUser),
            'perusahaan_filter_options' => $isSuperadmin ? $this->service->getPerusahaanFilterOptions() : [],
            'perusahaan_buyer_options' => $isSuperadmin ? $this->service->getPerusahaanBuyerOptions() : [],
            'vendor_options' => $isSuperadmin ? $this->service->getVendorOptions() : [],
            'default_password' => PegawaiService::DEFAULT_PASSWORD,
        ]);
    }

    /**
     * Display the specified user account if within actor's company scope.
     */
    public function show(Request $request, string $uuid): JsonResponse|RedirectResponse
    {
        /** @var User $authUser */
        $authUser = $request->user()->loadMissing('role');

        if (! $authUser->canManagePengguna()) {
            return ResponseHelper::forbidden('Anda tidak memiliki akses untuk melihat detail akun pengguna.');
        }

        try {
            $pegawai = $this->service->findByUuidForActor($uuid, $authUser);

            return ResponseHelper::success(
                'Detail akun pengguna berhasil diambil.',
                new PegawaiResource($pegawai)
            );
        } catch (AuthorizationException $e) {
            return ResponseHelper::forbidden($e->getMessage());
        } catch (ModelNotFoundException) {
            return ResponseHelper::notFound('Akun pengguna tidak ditemukan.');
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memuat detail akun pengguna.', $e);
        }
    }

    /**
     * Store a newly created user account scoped to the actor's company (or selected company for superadmin).
     */
    public function store(StorePegawaiRequest $request): JsonResponse|RedirectResponse
    {
        /** @var User $authUser */
        $authUser = $request->user()->loadMissing('role');

        if (! $authUser->canManagePengguna()) {
            return ResponseHelper::forbidden('Anda tidak memiliki akses untuk membuat akun pengguna.');
        }

        try {
            $pegawai = $this->service->create($request->validated(), $authUser);

            return ResponseHelper::created(
                'Akun pengguna berhasil dibuat dan notifikasi akses telah dikirim ke email.',
                new PegawaiResource($pegawai),
                route('manajemen.pengguna.index')
            );
        } catch (AuthorizationException $e) {
            return ResponseHelper::forbidden($e->getMessage());
        } catch (ValidationException $e) {
            return ResponseHelper::validationError($e->errors());
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal membuat akun pengguna baru.', $e);
        }
    }

    /**
     * Update the specified user account if within actor's company & role scope.
     */
    public function update(UpdatePegawaiRequest $request, string $uuid): JsonResponse|RedirectResponse
    {
        /** @var User $authUser */
        $authUser = $request->user()->loadMissing('role');

        if (! $authUser->canManagePengguna()) {
            return ResponseHelper::forbidden('Anda tidak memiliki akses untuk mengubah akun pengguna.');
        }

        try {
            $pegawai = $this->service->update($uuid, $request->validated(), $authUser);

            return ResponseHelper::success(
                'Data akun pengguna berhasil diperbarui.',
                new PegawaiResource($pegawai),
                route('manajemen.pengguna.index')
            );
        } catch (AuthorizationException $e) {
            return ResponseHelper::forbidden($e->getMessage());
        } catch (ModelNotFoundException) {
            return ResponseHelper::notFound('Akun pengguna tidak ditemukan.');
        } catch (ValidationException $e) {
            return ResponseHelper::validationError($e->errors());
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memperbarui data akun pengguna.', $e);
        }
    }

    /**
     * Soft delete the specified user account if within actor's company & role scope.
     */
    public function destroy(Request $request, string $uuid): JsonResponse|RedirectResponse
    {
        /** @var User $authUser */
        $authUser = $request->user()->loadMissing('role');

        if (! $authUser->canManagePengguna()) {
            return ResponseHelper::forbidden('Anda tidak memiliki akses untuk menghapus akun pengguna.');
        }

        try {
            $this->service->delete($uuid, $authUser);

            return ResponseHelper::success(
                'Akun pengguna berhasil dinonaktifkan.',
                null,
                route('manajemen.pengguna.index')
            );
        } catch (AuthorizationException $e) {
            return ResponseHelper::forbidden($e->getMessage());
        } catch (ModelNotFoundException) {
            return ResponseHelper::notFound('Akun pengguna tidak ditemukan.');
        } catch (ValidationException $e) {
            return ResponseHelper::validationError($e->errors());
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal menghapus data akun pengguna.', $e);
        }
    }
}
