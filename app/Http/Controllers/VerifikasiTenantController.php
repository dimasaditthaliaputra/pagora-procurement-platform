<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\VerificationStatus;
use App\Helpers\ResponseHelper;
use App\Http\Requests\Verifikasi\IndexVerifikasiTenantRequest;
use App\Http\Requests\Verifikasi\VerifikasiTenantRequest;
use App\Http\Resources\VerifikasiTenantResource;
use App\Models\User;
use App\Services\VerifikasiTenantService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class VerifikasiTenantController extends Controller
{
    public function __construct(
        private readonly VerifikasiTenantService $service
    ) {}

    /**
     * Display a paginated listing of tenant registrations awaiting or processed for verification.
     */
    public function index(IndexVerifikasiTenantRequest $request): Response|JsonResponse|RedirectResponse
    {
        try {
            $filters = $request->validated();
            $perPage = (int) ($filters['per_page'] ?? 10);

            $tenants = $this->service->paginate($filters, $perPage);
            $stats = $this->service->getStatusCounts();

            if ($request->expectsJson()) {
                return ResponseHelper::success(
                    'Daftar pendaftaran tenant berhasil diambil.',
                    [
                        'tenants' => VerifikasiTenantResource::collection($tenants),
                        'stats' => $stats,
                    ]
                );
            }

            return Inertia::render(
                'Master/Tenant/VerifikasiDokumen/Index',
                [
                    'tenants' => VerifikasiTenantResource::collection($tenants),
                    'stats' => $stats,
                    'filters' => [
                        'search' => $filters['search'] ?? '',
                        'verification_status' => $filters['verification_status'] ?? 'all',
                        'per_page' => $perPage,
                    ],
                ]
            );
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memuat daftar verifikasi tenant.', $e);
        }
    }

    /**
     * Approve or reject a tenant registration.
     */
    public function verify(
        VerifikasiTenantRequest $request,
        string $uuid
    ): JsonResponse|RedirectResponse {
        try {
            /** @var User|null $user */
            $user = $request->user();

            if (! $user instanceof User) {
                return ResponseHelper::forbidden('Sesi pengguna tidak valid.', null, $request);
            }

            $validated = $request->validated();

            $tenant = $this->service->verify(
                $uuid,
                (string) ($validated['aksi'] ?? ''),
                $user,
                isset($validated['rejection_reason']) ? (string) $validated['rejection_reason'] : null
            );

            return ResponseHelper::success(
                $tenant->verification_status === VerificationStatus::Approved
                    ? 'Pendaftaran tenant berhasil disetujui.'
                    : 'Pendaftaran tenant ditolak.',
                new VerifikasiTenantResource($tenant),
                route('master.tenant.verifikasi-dokumen.index'),
                $request
            );
        } catch (ValidationException $exception) {
            return ResponseHelper::validationError(
                $exception->errors(),
                'Terjadi kesalahan validasi data.',
                $request
            );
        } catch (AuthorizationException) {
            return ResponseHelper::forbidden('Anda tidak memiliki akses untuk memverifikasi tenant.', null, $request);
        } catch (ModelNotFoundException) {
            return ResponseHelper::notFound(
                'Data tenant tidak ditemukan.',
                null,
                $request
            );
        } catch (Throwable $exception) {
            return ResponseHelper::serverError(
                'Gagal memproses verifikasi tenant.',
                $exception,
                $request
            );
        }
    }

    /**
     * Download the uploaded legal document of a tenant.
     */
    public function download(Request $request, string $uuid): StreamedResponse|JsonResponse|RedirectResponse
    {
        try {
            /** @var User|null $user */
            $user = $request->user();

            if (! $user instanceof User) {
                return ResponseHelper::forbidden('Sesi pengguna tidak valid.');
            }

            $tenant = $this->service->findViewableByUuid($user, $uuid);
            $path = $tenant->legal_document_path;

            /** @var FilesystemAdapter $disk */
            $disk = Storage::disk('local');

            if (
                ! is_string($path)
                || $path === ''
                || ! $disk->exists($path)
            ) {
                return ResponseHelper::notFound('File dokumen legalitas tenant tidak ditemukan.');
            }

            return $disk->download(
                $path,
                basename($path)
            );
        } catch (AuthorizationException) {
            return ResponseHelper::forbidden('Anda tidak memiliki akses ke dokumen ini.');
        } catch (ModelNotFoundException) {
            return ResponseHelper::notFound('Data tenant tidak ditemukan.');
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal mengunduh file dokumen legalitas.', $e);
        }
    }
}
