<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\Invoice\IndexInvoiceRequest;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\User;
use App\Services\InvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class InvoiceController extends Controller
{
    public function __construct(
        protected readonly InvoiceService $service
    ) {}

    /**
     * Display the authenticated vendor's invoices.
     */
    public function index(IndexInvoiceRequest $request): Response|JsonResponse|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ResponseHelper::unauthorized('Sesi tidak valid.');
        }

        try {
            $filters = $request->validated();
            $perPage = (int) $request->input('per_page', 10);

            $paginator = $this->service->paginateForVendor(
                $user,
                $filters,
                $perPage
            );

            $invoices = InvoiceResource::collection($paginator);

            if ($request->expectsJson()) {
                return ResponseHelper::success(
                    'Daftar invoice berhasil diambil.',
                    ['items' => $invoices]
                );
            }

            return Inertia::render('Invoice/Index', [
                'invoices' => $invoices,
                'filters' => $filters,
            ]);
        } catch (Throwable $e) {
            return ResponseHelper::serverError(
                'Gagal memuat daftar invoice.',
                $e,
                $request
            );
        }
    }

    /**
     * Show the invoice creation form.
     */
    public function create(Request $request): Response|JsonResponse|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ResponseHelper::unauthorized('Sesi tidak valid.');
        }

        try {
            $purchaseOrders = $this->service->getPurchaseOrdersForInvoice($user);

            return Inertia::render('Invoice/Create', [
                'purchase_orders' => $purchaseOrders,
            ]);
        } catch (Throwable $e) {
            return ResponseHelper::serverError(
                'Gagal memuat formulir penerbitan invoice.',
                $e,
                $request
            );
        }
    }

    /**
     * Preview accepted QC items available for invoicing.
     */
    public function preview(
        Request $request,
        string $uuid
    ): Response|JsonResponse|RedirectResponse {
        $user = $request->user();

        if (! $user instanceof User) {
            return ResponseHelper::unauthorized('Sesi tidak valid.');
        }

        try {
            $preview = $this->service->preview($user, $uuid);

            if ($request->expectsJson()) {
                return ResponseHelper::success(
                    'Preview invoice berhasil diambil.',
                    $preview
                );
            }

            return Inertia::render('Invoice/Preview', [
                'preview' => $preview,
            ]);
        } catch (ModelNotFoundException) {
            abort(404, 'Purchase Order tidak ditemukan.');
        } catch (Throwable $e) {
            return ResponseHelper::serverError(
                'Gagal memuat preview invoice.',
                $e,
                $request
            );
        }
    }

    /**
     * Store a newly issued vendor invoice.
     */
    public function store(StoreInvoiceRequest $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ResponseHelper::unauthorized('Sesi tidak valid.');
        }

        try {
            $invoice = $this->service->create(
                $user,
                $request->validated()
            );

            return ResponseHelper::created(
                "Invoice {$invoice->nomor_invoice_vendor} berhasil diterbitkan.",
                $invoice,
                route('invoice.show', $invoice->uuid)
            );
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return ResponseHelper::validationError($e->errors());
            }

            return back()->withErrors($e->errors())->withInput();
        } catch (Throwable $e) {
            return ResponseHelper::serverError(
                'Gagal menerbitkan invoice.',
                $e,
                $request
            );
        }
    }

    /**
     * Display an invoice belonging to the authenticated vendor.
     */
    public function show(Request $request, string $uuid): Response|JsonResponse|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ResponseHelper::unauthorized('Sesi tidak valid.');
        }

        try {
            $invoice = new InvoiceResource(
                $this->service->findInvoiceForVendor($user, $uuid)
            );

            if ($request->expectsJson()) {
                return ResponseHelper::success(
                    'Detail invoice berhasil diambil.',
                    $invoice
                );
            }

            return Inertia::render('Invoice/Show', [
                'invoice' => $invoice,
            ]);
        } catch (ModelNotFoundException) {
            abort(404, 'Invoice tidak ditemukan.');
        } catch (Throwable $e) {
            return ResponseHelper::serverError(
                'Gagal memuat detail invoice.',
                $e,
                $request
            );
        }
    }

    /**
     * Download the authenticated vendor's invoice as PDF.
     */
    public function downloadPdf(Request $request, string $uuid): HttpResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401, 'Sesi tidak valid.');
        }

        try {
            $invoice = $this->service->findInvoiceForVendor($user, $uuid);

            $invoice->loadMissing([
                'vendor',
                'purchaseOrder',
                'items.purchaseOrderItem.komoditas',
            ]);

            $pdf = Pdf::loadView('pdf.invoice', [
                'invoice' => $invoice,
            ])->setPaper('a4', 'portrait');

            return $pdf->download(
                'invoice-' . $invoice->nomor_invoice_vendor . '.pdf'
            );
        } catch (ModelNotFoundException) {
            abort(404, 'Invoice tidak ditemukan.');
        }
    }
}
