<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\Vendor\BlokirVendorRequest;
use App\Http\Requests\Vendor\BukaBlokirVendorRequest;
use App\Http\Requests\Vendor\IndexVendorRequest;
use App\Http\Requests\Vendor\UndangVendorRequest;
use App\Http\Resources\VendorResource;
use App\Models\User;
use App\Services\VendorRegistrationService;
use App\Services\VendorService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class VendorController extends Controller
{
    public function __construct(
        protected readonly VendorService $vendorService
    ) {}

    /**
     * Display a paginated listing of vendors.
     */
    public function index(IndexVendorRequest $request): Response|JsonResponse|RedirectResponse
    {
        try {
            $filters = $request->validated();
            $perPage = (int) ($filters['per_page'] ?? 10);

            $vendors = $this->vendorService->paginate($filters, $perPage);

            if ($request->expectsJson()) {
                return ResponseHelper::success(
                    'Daftar vendor berhasil diambil.',
                    VendorResource::collection($vendors)
                );
            }

            /** @var User $user */
            $user = $request->user();

            return Inertia::render('Master/Vendor/Index', [
                'vendors' => VendorResource::collection($vendors),
                'filters' => [
                    'search' => $filters['search'] ?? '',
                    'status_aktif' => $filters['status_aktif'] ?? 'all',
                    'status_verifikasi' => $filters['status_verifikasi'] ?? 'all',
                    'sort' => $filters['sort'] ?? 'created_at',
                    'direction' => $filters['direction'] ?? 'desc',
                    'per_page' => $perPage,
                ],
                'can_manage' => $user->canManageVendors(),
                'kategori_options' => VendorRegistrationService::KATEGORI_OPTIONS,
            ]);
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memuat daftar vendor.', $e);
        }
    }

    /**
     * Invite a new vendor partner via email.
     */
    public function undang(UndangVendorRequest $request): JsonResponse|RedirectResponse
    {
        try {
            /** @var User $actor */
            $actor = $request->user();

            $invitation = $this->vendorService->undang(
                $request->validated(),
                $actor
            );

            return ResponseHelper::success(
                "Undangan kemitraan berhasil dikirim ke {$invitation->email}.",
                redirectTo: route('master.vendor.index')
            );
        } catch (ValidationException $e) {
            return ResponseHelper::validationError($e->errors(), 'Gagal mengirim undangan vendor.');
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Terjadi kesalahan saat memproses undangan vendor.', $e);
        }
    }

    /**
     * Block a vendor with a specified reason.
     */
    public function blokir(BlokirVendorRequest $request, string $uuid): JsonResponse|RedirectResponse
    {
        try {
            /** @var User $actor */
            $actor = $request->user();

            $vendor = $this->vendorService->blokir(
                $uuid,
                $request->string('alasan_pemblokiran')->toString(),
                $actor
            );

            return ResponseHelper::success(
                "Vendor {$vendor->nama_perusahaan} berhasil diblokir.",
                new VendorResource($vendor)
            );
        } catch (ModelNotFoundException) {
            return ResponseHelper::notFound('Data vendor tidak ditemukan.');
        } catch (ValidationException $e) {
            return ResponseHelper::validationError($e->errors(), 'Gagal memblokir vendor.');
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Terjadi kesalahan saat memproses pemblokiran vendor.', $e);
        }
    }

    /**
     * Unblock a vendor and restore active status.
     */
    public function bukaBlokir(BukaBlokirVendorRequest $request, string $uuid): JsonResponse|RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $vendor = $this->vendorService->bukaBlokir($uuid, $actor);

            return ResponseHelper::success(
                "Blokir vendor {$vendor->nama_perusahaan} berhasil dibuka. Status akun kembali aktif.",
                new VendorResource($vendor)
            );
        } catch (ModelNotFoundException) {
            return ResponseHelper::notFound('Data vendor tidak ditemukan.');
        } catch (ValidationException $e) {
            return ResponseHelper::validationError($e->errors(), 'Gagal membuka blokir vendor.');
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Terjadi kesalahan saat membuka blokir vendor.', $e);
        }
    }
}
