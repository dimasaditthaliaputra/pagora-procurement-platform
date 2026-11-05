<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StatusPengiriman;
use App\Helpers\ResponseHelper;
use App\Http\Requests\Pengiriman\IndexPengirimanRequest;
use App\Http\Requests\Pengiriman\StorePengirimanRequest;
use App\Http\Resources\PengirimanResource;
use App\Models\Pengiriman;
use App\Models\User;
use App\Services\PengirimanService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Thin controller for Self-Reporting Logistics (`UC-14`, `FR-22`, `FR-23`, `BR-07`, `BR-09`).
 */
class PengirimanController extends Controller
{
    public function __construct(
        protected readonly PengirimanService $service
    ) {}

    /**
     * Display paginated list of shipments scoped to the authenticated Tenant or Vendor.
     */
    public function index(IndexPengirimanRequest $request): Response|JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $filters = $request->validated();
            $perPage = (int) ($filters['per_page'] ?? 10);
            $paginator = $this->service->paginateForUser($user, $filters, $perPage);
            $collection = PengirimanResource::collection($paginator);
            $statistics = $this->service->getStatisticsForUser($user);

            if ($request->expectsJson()) {
                return ResponseHelper::success('Daftar pengiriman logistik berhasil diambil.', [
                    'items' => $collection,
                    'statistics' => $statistics,
                ]);
            }

            $statusOptions = array_map(fn (StatusPengiriman $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ], StatusPengiriman::cases());

            $routeOptions = array_map(fn (string $route): array => [
                'value' => $route,
                'label' => $route,
            ], StatusPengiriman::routes());

            return Inertia::render('Pengiriman/Index', [
                'shipments' => $collection,
                'statistics' => $statistics,
                'filters' => $filters,
                'status_options' => $statusOptions,
                'route_options' => $routeOptions,
                'current_role' => $user->isVendor() ? 'vendor' : 'buyer',
                'can' => [
                    'create' => (bool) $user->can('create', Pengiriman::class),
                ],
            ]);
        } catch (AuthorizationException $e) {
            if ($request->expectsJson()) {
                return ResponseHelper::forbidden($e->getMessage(), null, $request);
            }
            abort(403, $e->getMessage());
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memuat daftar pengiriman logistik.', $e, $request);
        }
    }

    /**
     * Show the self-reporting logistics form (Vendor only — `UC-14`).
     */
    public function create(Request $request): Response|JsonResponse|RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user instanceof User) {
            return ResponseHelper::unauthorized('Sesi tidak valid.');
        }

        try {
            $preselectedPoUuid = is_string($request->query('po')) ? trim($request->query('po')) : null;
            $formData = $this->service->getCreateFormData($user, $preselectedPoUuid);

            return Inertia::render('Pengiriman/Create', [
                'purchase_order_options' => $formData['purchase_order_options'],
                'route_options' => $formData['route_options'],
                'selected_po_uuid' => $formData['selected_po_uuid'],
                'can' => [
                    'create' => true,
                ],
            ]);
        } catch (AuthorizationException $e) {
            if ($request->expectsJson()) {
                return ResponseHelper::forbidden($e->getMessage(), null, $request);
            }
            abort(403, $e->getMessage());
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memuat formulir pengiriman logistik.', $e, $request);
        }
    }

    /**
     * Persist a newly reported shipment, transition PO to `In Transit` (`dalam_pengiriman`),
     * and permanently lock PO document & item quantities (`UC-14`, `FR-22`, `FR-23`, `BR-07`).
     */
    public function store(StorePengirimanRequest $request): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $shipment = $this->service->store($user, $request->validated());
            $poNumber = $shipment->purchaseOrder?->nomor_po ?? '';

            return ResponseHelper::created(
                "Status logistik fisik untuk {$poNumber} berhasil dilaporkan sebagai Dalam Pengiriman (In Transit) dan dokumen PO telah dikunci.",
                new PengirimanResource($shipment),
                route('pengiriman.show', $shipment->uuid),
                $request
            );
        } catch (ModelNotFoundException) {
            if ($request->expectsJson()) {
                return ResponseHelper::notFound('Purchase Order tidak ditemukan.', null, $request);
            }
            abort(404, 'Purchase Order tidak ditemukan.');
        } catch (ValidationException $e) {
            return ResponseHelper::validationError($e->errors(), 'Terjadi kesalahan validasi data.', $request);
        } catch (AuthorizationException $e) {
            if ($request->expectsJson()) {
                return ResponseHelper::forbidden($e->getMessage(), null, $request);
            }
            abort(403, $e->getMessage());
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal menyimpan laporan pengiriman logistik.', $e, $request);
        }
    }

    /**
     * Display the detail page of a reported shipment.
     */
    public function show(Request $request, string $uuid): Response|JsonResponse|RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user instanceof User) {
            return ResponseHelper::unauthorized('Sesi tidak valid.');
        }

        try {
            $shipment = $this->service->findByUuidForUser($user, $uuid);
            $resource = new PengirimanResource($shipment);

            if ($request->expectsJson()) {
                return ResponseHelper::success('Detail pengiriman logistik berhasil diambil.', $resource);
            }

            return Inertia::render('Pengiriman/Show', [
                'shipment' => $resource,
                'current_role' => $user->isVendor() ? 'vendor' : 'buyer',
                'uuid' => $uuid,
            ]);
        } catch (ModelNotFoundException) {
            if ($request->expectsJson()) {
                return ResponseHelper::notFound('Data pengiriman tidak ditemukan.', null, $request);
            }
            abort(404, 'Data pengiriman tidak ditemukan.');
        } catch (AuthorizationException $e) {
            if ($request->expectsJson()) {
                return ResponseHelper::forbidden($e->getMessage(), null, $request);
            }
            abort(403, $e->getMessage());
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memuat detail pengiriman logistik.', $e, $request);
        }
    }
}
