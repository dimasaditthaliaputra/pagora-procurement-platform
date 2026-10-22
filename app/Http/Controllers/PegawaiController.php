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
     * Display a listing of user accounts.
     *
     * - superadmin / owner / manajemen : lihat semua akun internal (buyer-side)
     * - vendor user                    : hanya lihat akun dari vendor yang sama
     */
    public function index(IndexPegawaiRequest $request): Response|JsonResponse
    {
        /** @var User $authUser */
        $authUser = $request->user();

        // If the logged-in user belongs to a vendor, scope the listing to that vendor only
        $vendorId = $authUser->vendor_id;

        $filters = $request->validated();
        $perPage = (int) $request->input('per_page', 10);
        $pegawai = $this->service->paginate($filters, $perPage, vendorId: $vendorId);

        if ($request->expectsJson()) {
            return ResponseHelper::success(
                'Daftar pengguna berhasil diambil.',
                PegawaiResource::collection($pegawai)
            );
        }

        return Inertia::render('Manajemen/Pengguna/Index', [
            'pegawai' => PegawaiResource::collection($pegawai),
            'filters' => $request->only('search', 'role', 'status_aktif', 'sort', 'direction', 'per_page'),
            'role_options' => $this->service->getRoleOptionsForUser($authUser->load('role')),
            'default_password' => PegawaiService::DEFAULT_PASSWORD,
        ]);
    }

    /**
     * Display the specified user account.
     */
    public function show(string $uuid): JsonResponse|RedirectResponse
    {
        try {
            $pegawai = $this->service->findByUuid($uuid);

            return ResponseHelper::success(
                'Detail akun pengguna berhasil diambil.',
                new PegawaiResource($pegawai)
            );
        } catch (ModelNotFoundException) {
            return ResponseHelper::notFound('Akun pengguna tidak ditemukan.');
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memuat detail akun pengguna.', $e);
        }
    }

    /**
     * Store a newly created user account.
     *
     * Authorization:
     * - superadmin : semua roles
     * - owner      : manajemen, buyer, qc_gudang
     * - manajemen  : buyer, qc_gudang
     * - vendor     : vendor
     */
    public function store(StorePegawaiRequest $request): JsonResponse|RedirectResponse
    {
        /** @var User $authUser */
        $authUser = $request->user()->load('role');

        if (! $this->isAuthorizedRole($authUser)) {
            return ResponseHelper::forbidden('Anda tidak memiliki akses untuk membuat akun pengguna.');
        }

        try {
            $pegawai = $this->service->create($request->validated(), $authUser);

            return ResponseHelper::created(
                'Akun pengguna berhasil dibuat dan notifikasi akses telah dikirim ke email.',
                new PegawaiResource($pegawai),
                route('manajemen.pengguna.index')
            );
        } catch (ValidationException $e) {
            return ResponseHelper::validationError($e->errors());
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal membuat akun pengguna baru.', $e);
        }
    }

    /**
     * Update the specified user account.
     */
    public function update(UpdatePegawaiRequest $request, string $uuid): JsonResponse|RedirectResponse
    {
        /** @var User $authUser */
        $authUser = $request->user()->load('role');

        if (! $this->isAuthorizedRole($authUser)) {
            return ResponseHelper::forbidden('Anda tidak memiliki akses untuk mengubah akun pengguna.');
        }

        try {
            $pegawai = $this->service->update($uuid, $request->validated(), $authUser);

            return ResponseHelper::success(
                'Data akun pengguna berhasil diperbarui.',
                new PegawaiResource($pegawai),
                route('manajemen.pengguna.index')
            );
        } catch (ModelNotFoundException) {
            return ResponseHelper::notFound('Akun pengguna tidak ditemukan.');
        } catch (ValidationException $e) {
            return ResponseHelper::validationError($e->errors());
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memperbarui data akun pengguna.', $e);
        }
    }

    /**
     * Soft delete the specified user account.
     */
    public function destroy(Request $request, string $uuid): JsonResponse|RedirectResponse
    {
        /** @var User $authUser */
        $authUser = $request->user()->load('role');

        if (! $this->isAuthorizedRole($authUser)) {
            return ResponseHelper::forbidden('Anda tidak memiliki akses untuk menghapus akun pengguna.');
        }

        try {
            $this->service->delete($uuid, $authUser);

            return ResponseHelper::success(
                'Akun pengguna berhasil dinonaktifkan.',
                null,
                route('manajemen.pengguna.index')
            );
        } catch (ModelNotFoundException) {
            return ResponseHelper::notFound('Akun pengguna tidak ditemukan.');
        } catch (ValidationException $e) {
            return ResponseHelper::validationError($e->errors());
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal menghapus data akun pengguna.', $e);
        }
    }

    /**
     * Check whether the user's role is allowed to access the Manajemen Pengguna feature.
     */
    private function isAuthorizedRole(User $user): bool
    {
        return in_array($user->role?->nama_role, ['superadmin', 'manajemen', 'owner'], true);
    }
}
