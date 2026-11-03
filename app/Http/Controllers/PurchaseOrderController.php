<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StatusPurchaseOrder;
use App\Helpers\ResponseHelper;
use App\Http\Requests\PurchaseOrder\IndexPurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\StorePurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\StoreTawaranRequest;
use App\Http\Requests\PurchaseOrder\UpdatePurchaseOrderRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\PurchaseOrderService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PurchaseOrderController extends Controller
{
    public function __construct(
        protected readonly PurchaseOrderService $service
    ) {}

    /**
     * Display the purchase order list.
     */
    public function index(IndexPurchaseOrderRequest $request): Response|JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $filters = $request->validated();
            $perPage = (int) $request->input('per_page', 10);
            $paginator = $this->service->paginateForUser($user, $filters, $perPage);
            $collection = PurchaseOrderResource::collection($paginator);
            $statistics = $this->service->getStatisticsForUser($user);

            if ($request->expectsJson()) {
                return ResponseHelper::success('Daftar Purchase Order berhasil diambil.', [
                    'items' => $collection,
                    'statistics' => $statistics,
                ]);
            }

            $statusOptions = array_map(fn (StatusPurchaseOrder $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ], StatusPurchaseOrder::cases());

            return Inertia::render('PurchaseOrder/Index', [
                'purchase_orders' => $collection,
                'statistics' => $statistics,
                'filters' => $filters,
                'status_options' => $statusOptions,
                'can' => [
                    'create' => (bool) $user->can('create', PurchaseOrder::class),
                ],
            ]);
        } catch (AuthorizationException $e) {
            if ($request->expectsJson()) {
                return ResponseHelper::forbidden($e->getMessage(), null, $request);
            }
            abort(403, $e->getMessage());
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memuat daftar purchase order.', $e, $request);
        }
    }

    /**
     * Show the form for creating a purchase order.
     */
    public function create(Request $request): Response|JsonResponse|RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user instanceof User) {
            return ResponseHelper::unauthorized('Sesi tidak valid.');
        }

        try {
            $formData = $this->service->getFormData($user);

            return Inertia::render('PurchaseOrder/Create', [
                'vendor_options' => $formData['vendor_options'],
                'komoditas_options' => $formData['komoditas_options'],
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
            return ResponseHelper::serverError('Gagal memuat formulir purchase order.', $e, $request);
        }
    }

    /**
     * Store a newly created draft purchase order.
     */
    public function store(StorePurchaseOrderRequest $request): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $po = $this->service->create($user, $request->validated());

            return ResponseHelper::created(
                "Draf Purchase Order {$po->nomor_po} berhasil dibuat.",
                new PurchaseOrderResource($po),
                route('purchase-order.show', $po->uuid)
            );
        } catch (ValidationException $e) {
            return ResponseHelper::validationError($e->errors());
        } catch (AuthorizationException $e) {
            if ($request->expectsJson()) {
                return ResponseHelper::forbidden($e->getMessage(), null, $request);
            }
            abort(403, $e->getMessage());
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal membuat purchase order.', $e, $request);
        }
    }

    /**
     * Display a purchase order.
     */
    public function show(Request $request, string $uuid): Response|JsonResponse|RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user instanceof User) {
            return ResponseHelper::unauthorized('Sesi tidak valid.');
        }

        try {
            $po = $this->service->findByUuidForUser($user, $uuid);
            $resource = new PurchaseOrderResource($po);

            if ($request->expectsJson()) {
                return ResponseHelper::success('Detail Purchase Order berhasil diambil.', $resource);
            }

            return Inertia::render('PurchaseOrder/Show', [
                'purchase_order' => $resource,
                'uuid' => $uuid,
            ]);
        } catch (ModelNotFoundException) {
            if ($request->expectsJson()) {
                return ResponseHelper::notFound('Purchase Order tidak ditemukan.', null, $request);
            }
            abort(404, 'Purchase Order tidak ditemukan.');
        } catch (AuthorizationException $e) {
            if ($request->expectsJson()) {
                return ResponseHelper::forbidden($e->getMessage(), null, $request);
            }
            abort(403, $e->getMessage());
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memuat detail purchase order.', $e, $request);
        }
    }

    /**
     * Show the form for editing a draft purchase order.
     */
    public function edit(Request $request, string $uuid): Response|JsonResponse|RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user instanceof User) {
            return ResponseHelper::unauthorized('Sesi tidak valid.');
        }

        try {
            $po = $this->service->findByUuidForUser($user, $uuid);

            if (! $user->can('update', $po)) {
                if ($request->expectsJson()) {
                    return ResponseHelper::forbidden('Hanya draf Purchase Order yang dapat diubah.', null, $request);
                }
                abort(403, 'Hanya draf Purchase Order yang dapat diubah.');
            }

            $formData = $this->service->getFormData($user);

            return Inertia::render('PurchaseOrder/Edit', [
                'purchase_order' => new PurchaseOrderResource($po),
                'vendor_options' => $formData['vendor_options'],
                'komoditas_options' => $formData['komoditas_options'],
                'uuid' => $uuid,
            ]);
        } catch (ModelNotFoundException) {
            if ($request->expectsJson()) {
                return ResponseHelper::notFound('Purchase Order tidak ditemukan.', null, $request);
            }
            abort(404, 'Purchase Order tidak ditemukan.');
        } catch (AuthorizationException $e) {
            if ($request->expectsJson()) {
                return ResponseHelper::forbidden($e->getMessage(), null, $request);
            }
            abort(403, $e->getMessage());
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memuat halaman edit purchase order.', $e, $request);
        }
    }

    /**
     * Update an existing draft purchase order.
     */
    public function update(UpdatePurchaseOrderRequest $request, string $uuid): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $po = $this->service->update($user, $uuid, $request->validated());

            return ResponseHelper::success(
                "Perubahan pada Purchase Order {$po->nomor_po} berhasil disimpan.",
                new PurchaseOrderResource($po),
                route('purchase-order.show', $po->uuid)
            );
        } catch (ModelNotFoundException) {
            if ($request->expectsJson()) {
                return ResponseHelper::notFound('Purchase Order tidak ditemukan.', null, $request);
            }
            abort(404, 'Purchase Order tidak ditemukan.');
        } catch (ValidationException $e) {
            return ResponseHelper::validationError($e->errors());
        } catch (AuthorizationException $e) {
            if ($request->expectsJson()) {
                return ResponseHelper::forbidden($e->getMessage(), null, $request);
            }
            abort(403, $e->getMessage());
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memperbarui purchase order.', $e, $request);
        }
    }

    /**
     * Delete a draft purchase order.
     */
    public function destroy(Request $request, string $uuid): JsonResponse|RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user instanceof User) {
            return ResponseHelper::unauthorized('Sesi tidak valid.');
        }

        try {
            $this->service->delete($user, $uuid);

            return ResponseHelper::success(
                'Draf Purchase Order berhasil dihapus.',
                null,
                route('purchase-order.index')
            );
        } catch (ModelNotFoundException) {
            if ($request->expectsJson()) {
                return ResponseHelper::notFound('Purchase Order tidak ditemukan.', null, $request);
            }
            abort(404, 'Purchase Order tidak ditemukan.');
        } catch (ValidationException $e) {
            return ResponseHelper::validationError($e->errors());
        } catch (AuthorizationException $e) {
            if ($request->expectsJson()) {
                return ResponseHelper::forbidden($e->getMessage(), null, $request);
            }
            abort(403, $e->getMessage());
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal menghapus draf purchase order.', $e, $request);
        }
    }

    /**
     * Confirm/submit a draft purchase order to start negotiation.
     */
    public function konfirmasi(Request $request, string $uuid): JsonResponse|RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user instanceof User) {
            return ResponseHelper::unauthorized('Sesi tidak valid.');
        }

        try {
            $po = $this->service->konfirmasi($user, $uuid);

            return ResponseHelper::success(
                "Purchase Order {$po->nomor_po} berhasil diajukan ke vendor.",
                new PurchaseOrderResource($po),
                route('purchase-order.show', $po->uuid)
            );
        } catch (ModelNotFoundException) {
            if ($request->expectsJson()) {
                return ResponseHelper::notFound('Purchase Order tidak ditemukan.', null, $request);
            }
            abort(404, 'Purchase Order tidak ditemukan.');
        } catch (ValidationException $e) {
            return ResponseHelper::validationError($e->errors());
        } catch (AuthorizationException $e) {
            if ($request->expectsJson()) {
                return ResponseHelper::forbidden($e->getMessage(), null, $request);
            }
            abort(403, $e->getMessage());
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal mengonfirmasi purchase order.', $e, $request);
        }
    }

    /**
     * Display the negotiation chat of a purchase order.
     */
    public function negosiasi(Request $request, string $uuid): Response|JsonResponse|RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user instanceof User) {
            return ResponseHelper::unauthorized('Sesi tidak valid.');
        }

        try {
            $po = $this->service->findByUuidForUser($user, $uuid);

            Gate::forUser($user)->authorize('negosiasi', $po);

            return Inertia::render('PurchaseOrder/Negosiasi', [
                'purchase_order' => new PurchaseOrderResource($po),
                'uuid' => $uuid,
            ]);
        } catch (ModelNotFoundException) {
            if ($request->expectsJson()) {
                return ResponseHelper::notFound('Purchase Order tidak ditemukan.', null, $request);
            }
            abort(404, 'Purchase Order tidak ditemukan.');
        } catch (AuthorizationException $e) {
            if ($request->expectsJson()) {
                return ResponseHelper::forbidden($e->getMessage(), null, $request);
            }
            abort(403, $e->getMessage());
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memuat ruang negosiasi purchase order.', $e, $request);
        }
    }

    /**
     * Submit an offer / counter-offer in the negotiation chat.
     */
    public function tawar(StoreTawaranRequest $request, string $uuid): JsonResponse|RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user instanceof User) {
            return ResponseHelper::unauthorized('Sesi tidak valid.');
        }

        try {
            $po = $this->service->kirimTawaran($user, $uuid, $request->validated());

            if ($request->expectsJson()) {
                return ResponseHelper::success('Penawaran harga berhasil dikirim.', new PurchaseOrderResource($po));
            }

            return back()->with('success', 'Penawaran harga berhasil dikirim.');
        } catch (ModelNotFoundException) {
            if ($request->expectsJson()) {
                return ResponseHelper::notFound('Purchase Order tidak ditemukan.', null, $request);
            }
            abort(404, 'Purchase Order tidak ditemukan.');
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return ResponseHelper::validationError($e->errors());
            }

            return back()->withErrors($e->errors());
        } catch (AuthorizationException $e) {
            if ($request->expectsJson()) {
                return ResponseHelper::forbidden($e->getMessage(), null, $request);
            }
            abort(403, $e->getMessage());
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal mengirim penawaran harga.', $e, $request);
        }
    }

    /**
     * Agree to the price and lock the purchase order.
     */
    public function setujui(Request $request, string $uuid): JsonResponse|RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user instanceof User) {
            return ResponseHelper::unauthorized('Sesi tidak valid.');
        }

        try {
            $po = $this->service->setujuiHarga($user, $uuid);

            if ($request->expectsJson()) {
                return ResponseHelper::success('Harga berhasil disepakati dan PO telah dikunci.', new PurchaseOrderResource($po));
            }

            return back()->with('success', 'Harga berhasil disepakati dan PO telah dikunci.');
        } catch (ModelNotFoundException) {
            if ($request->expectsJson()) {
                return ResponseHelper::notFound('Purchase Order tidak ditemukan.', null, $request);
            }
            abort(404, 'Purchase Order tidak ditemukan.');
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return ResponseHelper::validationError($e->errors());
            }

            return back()->withErrors($e->errors());
        } catch (AuthorizationException $e) {
            if ($request->expectsJson()) {
                return ResponseHelper::forbidden($e->getMessage(), null, $request);
            }
            abort(403, $e->getMessage());
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal menyetujui harga purchase order.', $e, $request);
        }
    }

    /**
     * Cancel the purchase order during negotiation.
     */
    public function batalkan(Request $request, string $uuid): JsonResponse|RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user instanceof User) {
            return ResponseHelper::unauthorized('Sesi tidak valid.');
        }

        try {
            $validated = $request->validate([
                'alasan' => ['required', 'string', 'min:5', 'max:500'],
            ], [
                'alasan.required' => 'Alasan pembatalan Purchase Order wajib diisi.',
                'alasan.min' => 'Alasan pembatalan minimal 5 karakter.',
                'alasan.max' => 'Alasan pembatalan maksimal 500 karakter.',
            ]);

            $po = $this->service->batalkanPo($user, $uuid, $validated['alasan']);

            if ($request->expectsJson()) {
                return ResponseHelper::success('Purchase Order berhasil dibatalkan.', new PurchaseOrderResource($po));
            }

            return back()->with('success', 'Purchase Order berhasil dibatalkan.');
        } catch (ModelNotFoundException) {
            if ($request->expectsJson()) {
                return ResponseHelper::notFound('Purchase Order tidak ditemukan.', null, $request);
            }
            abort(404, 'Purchase Order tidak ditemukan.');
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return ResponseHelper::validationError($e->errors());
            }

            return back()->withErrors($e->errors());
        } catch (AuthorizationException $e) {
            if ($request->expectsJson()) {
                return ResponseHelper::forbidden($e->getMessage(), null, $request);
            }
            abort(403, $e->getMessage());
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal membatalkan purchase order.', $e, $request);
        }
    }
}
