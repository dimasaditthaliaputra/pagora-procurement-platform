<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\VerificationStatus;
use App\Helpers\ResponseHelper;
use App\Http\Requests\Verifikasi\IndexVerifikasiDokumenRequest;
use App\Http\Requests\Verifikasi\VerifikasiDokumenRequest;
use App\Http\Resources\DokumenLegalitasVendorResource;
use App\Http\Resources\VerifikasiVendorResource;
use App\Models\User;
use App\Services\VerifikasiDokumenService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class VerifikasiDokumenController extends Controller
{
    public function __construct(
        private readonly VerifikasiDokumenService $service
    ) {}

    /**
     * Display vendors that submitted legal documents, one record per vendor.
     */
    public function index(IndexVerifikasiDokumenRequest $request): Response|JsonResponse|RedirectResponse
    {
        try {
            $filters = $request->validated();
            $perPage = (int) ($filters['per_page'] ?? 10);

            $vendors = $this->service->paginate($filters, $perPage);

            if ($request->expectsJson()) {
                return ResponseHelper::success(
                    'Daftar dokumen legalitas berhasil diambil.',
                    VerifikasiVendorResource::collection($vendors)
                );
            }

            return Inertia::render(
                'Master/Vendor/VerifikasiDokumen/Index',
                [
                    'vendors' => VerifikasiVendorResource::collection($vendors),
                    'filters' => [
                        'search' => $filters['search'] ?? '',
                        'verification_status' => $filters['verification_status'] ?? 'all',
                        'per_page' => $perPage,
                    ],
                ]
            );
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memuat daftar dokumen legalitas.', $e);
        }
    }

    /**
     * Verify a vendor legal document.
     */
    public function verify(
        VerifikasiDokumenRequest $request,
        string $uuid
    ): JsonResponse|RedirectResponse {
        try {
            /** @var User|null $user */
            $user = $request->user();

            if (! $user instanceof User) {
                return ResponseHelper::forbidden('Sesi pengguna tidak valid.', null, $request);
            }

            $validated = $request->validated();

            $document = $this->service->verify(
                $uuid,
                (string) ($validated['aksi'] ?? ''),
                (int) $user->id,
                isset($validated['rejection_reason']) ? (string) $validated['rejection_reason'] : null
            );

            return ResponseHelper::success(
                $document->verification_status === VerificationStatus::Approved
                    ? 'Dokumen legalitas berhasil disetujui.'
                    : 'Dokumen legalitas ditolak.',
                new DokumenLegalitasVendorResource($document),
                route('master.vendor.verifikasi-dokumen.index'),
                $request
            );
        } catch (ValidationException $exception) {
            return ResponseHelper::validationError(
                $exception->errors(),
                'Terjadi kesalahan validasi data.',
                $request
            );
        } catch (ModelNotFoundException) {
            return ResponseHelper::notFound(
                'Dokumen legalitas tidak ditemukan.',
                null,
                $request
            );
        } catch (Throwable $exception) {
            return ResponseHelper::serverError(
                'Gagal memproses verifikasi dokumen legalitas.',
                $exception,
                $request
            );
        }
    }

    /**
     * Stream a private legal document to an authorized user.
     */
    public function download(string $uuid): StreamedResponse|JsonResponse|RedirectResponse
    {
        try {
            $document = $this->service->findByUuid($uuid);
            $path = $document->file_path;

            /** @var FilesystemAdapter $disk */
            $disk = Storage::disk('local');

            if (
                ! is_string($path)
                || $path === ''
                || ! $disk->exists($path)
            ) {
                return ResponseHelper::notFound('File dokumen legalitas tidak ditemukan.');
            }

            return $disk->download(
                $path,
                basename($path)
            );
        } catch (ModelNotFoundException) {
            return ResponseHelper::notFound('Dokumen legalitas tidak ditemukan.');
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal mengunduh file dokumen legalitas.', $e);
        }
    }
}
