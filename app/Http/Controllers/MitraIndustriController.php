<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\VerificationStatus;
use App\Helpers\ResponseHelper;
use App\Http\Requests\Vendor\BlokirVendorRequest;
use App\Http\Requests\Vendor\BukaBlokirVendorRequest;
use App\Http\Requests\Vendor\UndangVendorRequest;
use App\Http\Requests\Verifikasi\VerifikasiDokumenRequest;
use App\Http\Requests\Verifikasi\VerifikasiTenantRequest;
use App\Http\Resources\VendorResource;
use App\Http\Resources\VerifikasiTenantResource;
use App\Http\Resources\VerifikasiVendorResource;
use App\Models\User;
use App\Services\VendorRegistrationService;
use App\Services\VendorService;
use App\Services\VerifikasiDokumenService;
use App\Services\VerifikasiTenantService;
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

class MitraIndustriController extends Controller
{
    public function __construct(
        private readonly VerifikasiTenantService $tenantService,
        private readonly VerifikasiDokumenService $vendorDocService,
        private readonly VendorService $vendorService
    ) {}

    /**
     * Tampilan utama Manajemen Mitra Industri untuk Superadmin (Tab Tenant & Tab Vendor).
     */
    public function index(Request $request): Response|JsonResponse|RedirectResponse
    {
        try {
            $tab = (string) $request->input('tab', 'tenant');
            if (! in_array($tab, ['tenant', 'vendor'], true)) {
                $tab = 'tenant';
            }

            $vendorSection = (string) $request->input('vendor_section', 'verifikasi');
            if (! in_array($vendorSection, ['verifikasi', 'rekanan'], true)) {
                $vendorSection = 'verifikasi';
            }

            $search = trim((string) $request->input('search', ''));
            $status = (string) $request->input('status', 'all');
            $statusAktif = (string) $request->input('is_active', 'all');
            $perPage = min(max((int) $request->input('per_page', 10), 5), 50);

            $pageData = [
                'active_tab' => $tab,
                'active_vendor_section' => $vendorSection,
                'tenant_stats' => $this->tenantService->getStatusCounts(),
                'vendor_stats' => $this->vendorService->getStatusCounts(),
                'filters' => [
                    'tab' => $tab,
                    'vendor_section' => $vendorSection,
                    'search' => $search,
                    'status' => $status,
                    'is_active' => $statusAktif,
                    'per_page' => $perPage,
                ],
            ];

            if ($tab === 'tenant') {
                $tenantFilters = [
                    'search' => $search ?: null,
                    'verification_status' => $status !== 'all' ? $status : null,
                    'per_page' => $perPage,
                ];
                $tenants = $this->tenantService->paginate($tenantFilters, $perPage);

                $pageData['tenants'] = VerifikasiTenantResource::collection($tenants);
            } else {
                // Tab Vendor
                if ($vendorSection === 'verifikasi') {
                    $docFilters = [
                        'search' => $search ?: null,
                        'verification_status' => $status !== 'all' ? $status : null,
                        'per_page' => $perPage,
                    ];
                    $vendorDocs = $this->vendorDocService->paginate($docFilters, $perPage);
                    $pageData['vendor_docs'] = VerifikasiVendorResource::collection($vendorDocs);
                } else {
                    $rekananFilters = [
                        'search' => $search ?: null,
                        'is_active' => $statusAktif !== 'all' ? $statusAktif : null,
                        'verification_status' => $status !== 'all' ? $status : null,
                        'sort' => (string) $request->input('sort', 'created_at'),
                        'direction' => (string) $request->input('direction', 'desc'),
                        'per_page' => $perPage,
                    ];
                    $vendorRekanan = $this->vendorService->paginate($rekananFilters, $perPage);
                    $pageData['vendor_rekanan'] = VendorResource::collection($vendorRekanan);
                    $pageData['kategori_options'] = VendorRegistrationService::KATEGORI_OPTIONS;
                    $pageData['can_manage'] = true;
                }
            }

            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return ResponseHelper::success('Data manajemen mitra industri berhasil dimuat.', $pageData, null, $request);
            }

            return Inertia::render('Master/MitraIndustri/Index', $pageData);
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memuat manajemen mitra industri.', $e, $request);
        }
    }

    /**
     * Verifikasi pendaftaran tenant (Setujui / Tolak).
     */
    public function verifyTenant(VerifikasiTenantRequest $request, string $uuid): JsonResponse|RedirectResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            $validated = $request->validated();

            $tenant = $this->tenantService->verify(
                $uuid,
                (string) ($validated['aksi'] ?? ''),
                $user,
                isset($validated['rejection_reason']) ? (string) $validated['rejection_reason'] : null
            );

            $msg = $tenant->verification_status === VerificationStatus::Approved
                ? 'Pendaftaran tenant berhasil disetujui.'
                : 'Pendaftaran tenant ditolak.';

            return ResponseHelper::success(
                $msg,
                new VerifikasiTenantResource($tenant),
                route('manajemen.mitra-industri.index', ['tab' => 'tenant']),
                $request
            );
        } catch (ValidationException $e) {
            return ResponseHelper::validationError($e->errors(), 'Terjadi kesalahan validasi data.', $request);
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memproses verifikasi tenant.', $e, $request);
        }
    }

    /**
     * Unduh dokumen legalitas tenant.
     */
    public function downloadTenantDoc(Request $request, string $uuid): StreamedResponse|JsonResponse|RedirectResponse
    {
        try {
            $tenant = $this->tenantService->findByUuid($uuid);

            if (! $tenant->legal_document_path) {
                return ResponseHelper::notFound('Dokumen legalitas tenant tidak ditemukan.', null, $request);
            }

            /** @var FilesystemAdapter $disk */
            $disk = Storage::disk('local');

            if (! $disk->exists($tenant->legal_document_path)) {
                return ResponseHelper::notFound('Berkas fisik dokumen legalitas tidak ditemukan pada server.', null, $request);
            }

            return $disk->download(
                $tenant->legal_document_path,
                basename($tenant->legal_document_path)
            );
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal mengunduh dokumen legalitas.', $e, $request);
        }
    }

    /**
     * Verifikasi dokumen legalitas vendor (Setujui / Tolak).
     */
    public function verifyVendorDoc(VerifikasiDokumenRequest $request, string $uuid): JsonResponse|RedirectResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            $validated = $request->validated();

            $doc = $this->vendorDocService->verify(
                $uuid,
                (string) ($validated['aksi'] ?? ''),
                $user,
                isset($validated['catatan']) ? (string) $validated['catatan'] : null
            );

            $msg = $doc->verification_status === VerificationStatus::Approved
                ? 'Dokumen legalitas vendor berhasil disetujui.'
                : 'Dokumen legalitas vendor ditolak.';

            return ResponseHelper::success(
                $msg,
                null,
                route('manajemen.mitra-industri.index', ['tab' => 'vendor', 'vendor_section' => 'verifikasi']),
                $request
            );
        } catch (ValidationException $e) {
            return ResponseHelper::validationError($e->errors(), 'Terjadi kesalahan validasi.', $request);
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memproses verifikasi dokumen vendor.', $e, $request);
        }
    }

    /**
     * Unduh dokumen legalitas vendor.
     */
    public function downloadVendorDoc(Request $request, string $uuid): StreamedResponse|JsonResponse|RedirectResponse
    {
        try {
            $document = $this->vendorDocService->findDocumentByUuid($uuid);

            /** @var FilesystemAdapter $disk */
            $disk = Storage::disk('local');

            if (! $document->file_path || ! $disk->exists($document->file_path)) {
                return ResponseHelper::notFound('Berkas fisik dokumen vendor tidak ditemukan.', null, $request);
            }

            return $disk->download(
                $document->file_path,
                basename($document->file_path)
            );
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal mengunduh dokumen vendor.', $e, $request);
        }
    }

    /**
     * Undang vendor baru ke platform.
     */
    public function undangVendor(UndangVendorRequest $request): JsonResponse|RedirectResponse
    {
        try {
            /** @var User $actor */
            $actor = $request->user();

            $invitation = $this->vendorService->undang($request->validated(), $actor);

            return ResponseHelper::success(
                "Undangan kemitraan berhasil dikirim ke {$invitation->email}.",
                redirectTo: route('manajemen.mitra-industri.index', ['tab' => 'vendor', 'vendor_section' => 'rekanan']),
                request: $request
            );
        } catch (ValidationException $e) {
            return ResponseHelper::validationError($e->errors(), 'Gagal mengirim undangan vendor.', $request);
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Terjadi kesalahan saat mengundang vendor.', $e, $request);
        }
    }

    /**
     * Blokir vendor rekanan di platform.
     */
    public function blokirVendor(BlokirVendorRequest $request, string $uuid): JsonResponse|RedirectResponse
    {
        try {
            /** @var User $actor */
            $actor = $request->user();

            $this->vendorService->blokir($uuid, $request->validated('block_reason'), $actor);

            return ResponseHelper::success(
                'Vendor berhasil diblokir.',
                redirectTo: route('manajemen.mitra-industri.index', ['tab' => 'vendor', 'vendor_section' => 'rekanan']),
                request: $request
            );
        } catch (ValidationException $e) {
            return ResponseHelper::validationError($e->errors(), 'Gagal memproses pemblokiran vendor.', $request);
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Terjadi kesalahan saat memblokir vendor.', $e, $request);
        }
    }

    /**
     * Buka blokir vendor rekanan di platform.
     */
    public function bukaBlokirVendor(BukaBlokirVendorRequest $request, string $uuid): JsonResponse|RedirectResponse
    {
        try {
            /** @var User $actor */
            $actor = $request->user();

            $this->vendorService->bukaBlokir($uuid, $actor);

            return ResponseHelper::success(
                'Pemblokiran vendor berhasil dibuka.',
                redirectTo: route('manajemen.mitra-industri.index', ['tab' => 'vendor', 'vendor_section' => 'rekanan']),
                request: $request
            );
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Terjadi kesalahan saat membuka blokir vendor.', $e, $request);
        }
    }
}
