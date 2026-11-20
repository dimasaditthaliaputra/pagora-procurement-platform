<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\Payment\MidtransNotificationRequest;
use App\Http\Requests\Payment\PayInvoiceRequest;
use App\Models\User;
use App\Services\MidtransClient;
use App\Services\MidtransException;
use App\Services\PaymentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Invoice payment through Midtrans Snap (UC-17 steps 5–6, FR-29, FR-39).
 */
class PembayaranController extends Controller
{
    public function __construct(
        protected readonly PaymentService $service,
        protected readonly MidtransClient $midtrans,
    ) {}

    /**
     * Create (or resume) the payment of an invoice and return the Snap token for the popup.
     */
    public function store(PayInvoiceRequest $request, string $uuid): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ResponseHelper::unauthorized('Sesi tidak valid.');
        }

        try {
            $payment = $this->service->initiate($user, $uuid);

            return ResponseHelper::success('Pembayaran siap diproses.', [
                'payment_uuid' => $payment->uuid,
                'payment_number' => $payment->payment_number,
                'snap_token' => $payment->snap_token,
                'snap_url' => $this->midtrans->snapScriptUrl(),
                'client_key' => $this->midtrans->clientKey(),
            ], null, $request);
        } catch (ValidationException $e) {
            return $this->validationFailure($request, $e);
        } catch (AuthorizationException $e) {
            return ResponseHelper::forbidden($e->getMessage(), null, $request);
        } catch (ModelNotFoundException) {
            return ResponseHelper::notFound('Tagihan tidak ditemukan.', null, $request);
        } catch (MidtransException $e) {
            report($e);

            return ResponseHelper::serverError('Gateway pembayaran sedang tidak dapat dihubungi. Silakan coba lagi.', $e, $request);
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memulai pembayaran.', $e, $request);
        }
    }

    /**
     * Refresh the pending payment from Midtrans after the Snap popup closes.
     */
    public function sync(PayInvoiceRequest $request, string $uuid): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ResponseHelper::unauthorized('Sesi tidak valid.');
        }

        try {
            $payment = $this->service->syncPending($user, $uuid);

            return ResponseHelper::success(
                'Status pembayaran diperbarui.',
                ['payment_status' => $payment?->payment_status->value],
                route('verifikasi-tagihan.show', $uuid),
                $request
            );
        } catch (AuthorizationException $e) {
            return ResponseHelper::forbidden($e->getMessage(), null, $request);
        } catch (ModelNotFoundException) {
            return ResponseHelper::notFound('Tagihan tidak ditemukan.', null, $request);
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memperbarui status pembayaran.', $e, $request);
        }
    }

    /**
     * Midtrans HTTP notification. Public route: the signature replaces login and CSRF (NFR-03, NFR-04).
     */
    public function notification(MidtransNotificationRequest $request): JsonResponse
    {
        try {
            $this->service->handleNotification($request->validated());

            return response()->json(['message' => 'OK']);
        } catch (AuthorizationException) {
            return response()->json(['message' => 'Tanda tangan tidak valid.'], 403);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Pembayaran tidak ditemukan.'], 404);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Gagal memproses notifikasi.'], 500);
        }
    }

    private function validationFailure(Request $request, ValidationException $e): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return ResponseHelper::validationError($e->errors());
        }

        return back()->withErrors($e->errors());
    }
}
