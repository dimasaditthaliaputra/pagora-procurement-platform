<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\Invoice\IndexVerifikasiTagihanRequest;
use App\Http\Requests\Invoice\VerifyInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class VerifikasiTagihanController extends Controller
{
    public function __construct(
        protected readonly InvoiceService $service
    ) {}

    /**
     * Display the list of invoices for the buyer company (Tenant).
     */
    public function index(IndexVerifikasiTagihanRequest $request): Response|JsonResponse|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ResponseHelper::unauthorized('Sesi tidak valid.');
        }

        try {
            $filters = $request->validated();
            $perPage = (int) $request->input('per_page', 10);

            $paginator = $this->service->paginateForTenant(
                $user,
                $filters,
                $perPage
            );

            $counts = $this->service->getCountsForTenant($user);
            $invoices = InvoiceResource::collection($paginator);

            if ($request->expectsJson()) {
                return ResponseHelper::success(
                    'Daftar tagihan berhasil diambil.',
                    [
                        'items' => $invoices,
                        'counts' => $counts,
                    ]
                );
            }

            return Inertia::render('VerifikasiTagihan/Index', [
                'invoices' => $invoices,
                'counts' => $counts,
                'filters' => $filters,
            ]);
        } catch (AuthorizationException $e) {
            return ResponseHelper::forbidden($e->getMessage(), null, $request);
        } catch (Throwable $e) {
            return ResponseHelper::serverError(
                'Gagal memuat daftar verifikasi tagihan.',
                $e,
                $request
            );
        }
    }

    /**
     * Display the 3-Way Matching detail for an invoice.
     */
    public function show(Request $request, string $uuid): Response|JsonResponse|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ResponseHelper::unauthorized('Sesi tidak valid.');
        }

        if (! $user->can('verifyAny', Invoice::class)) {
            return ResponseHelper::forbidden('Anda tidak memiliki izin mengakses verifikasi tagihan.', null, $request);
        }

        try {
            $invoice = $this->service->findInvoiceForTenant($user, $uuid);

            if (! $user->can('view', $invoice)) {
                return ResponseHelper::forbidden('Anda tidak memiliki akses ke tagihan ini.', null, $request);
            }

            $threeWayMatching = $this->service->calculateThreeWayMatching($invoice);

            $resource = new InvoiceResource($invoice);

            if ($request->expectsJson()) {
                return ResponseHelper::success(
                    'Detail verifikasi tagihan berhasil diambil.',
                    [
                        'invoice' => $resource,
                        'matching_evaluation' => $threeWayMatching,
                    ]
                );
            }

            return Inertia::render('VerifikasiTagihan/Show', [
                'invoice' => $resource,
                'matching_evaluation' => $threeWayMatching,
            ]);
        } catch (ModelNotFoundException) {
            return ResponseHelper::notFound('Tagihan tidak ditemukan.', null, $request);
        } catch (AuthorizationException $e) {
            return ResponseHelper::forbidden($e->getMessage(), null, $request);
        } catch (Throwable $e) {
            return ResponseHelper::serverError(
                'Gagal memuat detail verifikasi tagihan.',
                $e,
                $request
            );
        }
    }

    /**
     * Execute 3-Way Matching verification and approve or flag invoice.
     */
    public function verify(VerifyInvoiceRequest $request, string $uuid): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ResponseHelper::unauthorized('Sesi tidak valid.');
        }

        try {
            $result = $this->service->verify($user, $uuid);

            return ResponseHelper::success(
                $result['message'],
                $result,
                route('verifikasi-tagihan.show', $uuid)
            );
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return ResponseHelper::validationError($e->errors());
            }

            return back()->withErrors($e->errors())->withInput();
        } catch (AuthorizationException $e) {
            return ResponseHelper::forbidden($e->getMessage(), null, $request);
        } catch (ModelNotFoundException) {
            return ResponseHelper::notFound('Tagihan tidak ditemukan.', null, $request);
        } catch (Throwable $e) {
            return ResponseHelper::serverError(
                'Gagal memproses verifikasi 3-Way Matching tagihan.',
                $e,
                $request
            );
        }
    }
}

