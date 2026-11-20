<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\StatusPurchaseOrder;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Repositories\Contracts\CreditNoteRepositoryInterface;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\SengketaRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Pays a matched invoice through the payment gateway (UC-17 steps 5–7, FR-29, FR-39, BR-13, BR-26).
 * The amount is the bill minus confirmed credit notes minus what was already paid; Midtrans takes whole rupiah.
 */
class PaymentService
{
    /** Remaining balance below this (Rp) counts as fully paid, absorbing the whole-rupiah rounding. */
    private const SETTLED_TOLERANCE = 1;

    public function __construct(
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly PaymentRepositoryInterface $paymentRepository,
        private readonly CreditNoteRepositoryInterface $creditNoteRepository,
        private readonly SengketaRepositoryInterface $sengketaRepository,
        private readonly MidtransClient $midtrans,
        private readonly ?NotificationService $notificationService = null,
    ) {}

    /**
     * Unpaid part of the invoice: bill − confirmed credit notes − paid payments (BR-26).
     */
    public function remainingAmount(Invoice $invoice): float
    {
        return max(0.0, round(
            (float) $invoice->jumlah_tagihan
            - $this->creditNoteRepository->sumConfirmedForInvoice((int) $invoice->id)
            - $this->paymentRepository->sumPaidForInvoice((int) $invoice->id),
            2
        ));
    }

    public function isFullyPaid(Invoice $invoice): bool
    {
        return $this->remainingAmount($invoice) < self::SETTLED_TOLERANCE;
    }

    /**
     * Whether the actor may start a payment now: the policy plus an unpaid balance.
     */
    public function canPay(User $actor, Invoice $invoice): bool
    {
        return Gate::forUser($actor)->allows('pay', $invoice) && ! $this->isFullyPaid($invoice);
    }

    /**
     * Start (or resume) the gateway payment of an invoice and return it with its Snap token.
     *
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     * @throws ValidationException
     * @throws MidtransException
     */
    public function initiate(User $actor, string $invoiceUuid): Payment
    {
        $invoice = $actor->tenant_id !== null
            ? $this->invoiceRepository->findByUuidForTenant($invoiceUuid, (int) $actor->tenant_id)
            : null;

        if ($invoice === null) {
            throw (new ModelNotFoundException)->setModel(Invoice::class);
        }

        Gate::forUser($actor)->authorize('pay', $invoice);

        return DB::transaction(function () use ($actor, $invoice): Payment {
            $locked = $this->invoiceRepository->lockForPurchaseOrder((int) $invoice->purchase_order_id, $invoice->uuid) ?? $invoice;

            // BR-13: the gateway opens only for a matched invoice with no unresolved dispute.
            if ($this->sengketaRepository->hasOpenForInvoice((int) $locked->id)) {
                throw ValidationException::withMessages([
                    'invoice' => 'Tagihan ini masih memiliki sengketa yang belum Selesai.',
                ]);
            }

            $remaining = $this->remainingAmount($locked);

            if ($remaining < self::SETTLED_TOLERANCE) {
                throw ValidationException::withMessages(['invoice' => 'Tagihan ini sudah lunas.']);
            }

            $pending = $this->paymentRepository->findPendingForInvoice((int) $locked->id);

            if ($pending !== null) {
                return $pending;
            }

            $amount = (int) round($remaining);
            $number = $this->paymentRepository->getNextNumber((int) now()->year);
            // Random suffix: a reset database must not reuse an order id that Midtrans already knows.
            $orderId = $number.'-'.Str::upper(Str::random(6));

            $snap = $this->midtrans->createSnapTransaction([
                'transaction_details' => ['order_id' => $orderId, 'gross_amount' => $amount],
                'item_details' => [[
                    'id' => $invoice->uuid,
                    'price' => $amount,
                    'quantity' => 1,
                    'name' => Str::limit("Pelunasan invoice {$invoice->nomor_invoice_vendor}", 50, ''),
                ]],
                'customer_details' => [
                    'first_name' => Str::limit((string) $actor->name, 255, ''),
                    'email' => $actor->email,
                ],
            ]);

            return $this->paymentRepository->create([
                'invoice_id' => $locked->id,
                'payment_number' => $number,
                'gateway_order_id' => $orderId,
                'amount' => $amount,
                'payment_status' => PaymentStatus::Pending->value,
                'snap_token' => $snap['token'],
                'created_by' => $actor->id,
            ]);
        });
    }

    /**
     * Apply a Midtrans HTTP notification after verifying its signature (NFR-04).
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function handleNotification(array $payload): Payment
    {
        if (! $this->midtrans->isValidSignature($payload)) {
            throw new AuthorizationException('Tanda tangan notifikasi tidak valid.');
        }

        return DB::transaction(function () use ($payload): Payment {
            $payment = $this->paymentRepository->lockByGatewayOrderId((string) $payload['order_id']);

            if ($payment === null) {
                throw (new ModelNotFoundException)->setModel(Payment::class);
            }

            if ((int) round((float) $payload['gross_amount']) !== (int) round((float) $payment->amount)) {
                throw ValidationException::withMessages(['gross_amount' => 'Jumlah notifikasi tidak sama dengan pembayaran.']);
            }

            return $this->applyGatewayStatus($payment, $payload);
        });
    }

    /**
     * Ask Midtrans for the current status of the invoice's pending payment. Used right after the Snap popup closes,
     * so the invoice is up to date even when the notification URL is not reachable (e.g. local development).
     *
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     */
    public function syncPending(User $actor, string $invoiceUuid): ?Payment
    {
        $invoice = $actor->tenant_id !== null
            ? $this->invoiceRepository->findByUuidForTenant($invoiceUuid, (int) $actor->tenant_id)
            : null;

        if ($invoice === null) {
            throw (new ModelNotFoundException)->setModel(Invoice::class);
        }

        Gate::forUser($actor)->authorize('pay', $invoice);

        $pending = $this->paymentRepository->findPendingForInvoice((int) $invoice->id);

        if ($pending === null) {
            return null;
        }

        try {
            $status = $this->midtrans->transactionStatus($pending->gateway_order_id);
        } catch (MidtransException) {
            // Midtrans has no record yet: the buyer closed Snap before choosing a method.
            return $pending;
        }

        return DB::transaction(function () use ($pending, $status): Payment {
            $locked = $this->paymentRepository->lockByGatewayOrderId($pending->gateway_order_id) ?? $pending;

            return $this->applyGatewayStatus($locked, $status);
        });
    }

    /**
     * @param  array<string, mixed>  $data  notification or status payload
     */
    private function applyGatewayStatus(Payment $payment, array $data): Payment
    {
        // A paid payment never changes again, so a repeated or late notification is a no-op.
        if ($payment->payment_status === PaymentStatus::Paid) {
            return $payment;
        }

        $status = $this->mapStatus($data);

        if ($status === PaymentStatus::Pending) {
            return $this->paymentRepository->update($payment, ['payment_method' => $data['payment_type'] ?? $payment->payment_method]);
        }

        if ($status === PaymentStatus::Failed) {
            $this->paymentRepository->update($payment, [
                'payment_status' => PaymentStatus::Failed->value,
                'payment_method' => $data['payment_type'] ?? $payment->payment_method,
                'reference_number' => $data['transaction_id'] ?? $payment->reference_number,
            ]);
            $this->notifyOutcome($payment, 'Pembayaran Gagal', "Pembayaran {$payment->payment_number} gagal atau kedaluwarsa. Silakan coba lagi.");

            return $payment;
        }

        $this->paymentRepository->update($payment, [
            'payment_status' => PaymentStatus::Paid->value,
            'payment_date' => $this->parseTime($data['settlement_time'] ?? $data['transaction_time'] ?? null),
            'payment_method' => $data['payment_type'] ?? $payment->payment_method,
            'reference_number' => $data['transaction_id'] ?? $payment->reference_number,
        ]);

        $invoice = $payment->invoice;
        $po = $invoice->purchaseOrder;

        // UC-17 step 7: every invoice of the PO is paid → the PO is Lunas.
        if ($po->status === StatusPurchaseOrder::ReadyToPay && ! $this->paymentRepository->hasUnsettledInvoice((int) $po->id)) {
            $po->update(['status' => StatusPurchaseOrder::Lunas->value]);
        }

        $this->notifyOutcome($payment, 'Pembayaran Berhasil', "Pembayaran {$payment->payment_number} untuk invoice {$invoice->nomor_invoice_vendor} telah diterima.");

        return $payment;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function mapStatus(array $data): PaymentStatus
    {
        return match ($data['transaction_status'] ?? null) {
            'settlement' => PaymentStatus::Paid,
            'capture' => ($data['fraud_status'] ?? 'accept') === 'accept' ? PaymentStatus::Paid : PaymentStatus::Pending,
            'deny', 'cancel', 'expire', 'failure' => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };
    }

    private function parseTime(mixed $value): Carbon
    {
        try {
            return is_string($value) && $value !== '' ? Carbon::parse($value, 'Asia/Jakarta') : now();
        } catch (Throwable) {
            return now();
        }
    }

    private function notifyOutcome(Payment $payment, string $title, string $message): void
    {
        if ($this->notificationService === null) {
            return;
        }

        $invoice = $payment->invoice;

        $this->notificationService->sendToMany(
            User::query()->where('vendor_id', $invoice->vendor_id)->get(),
            'invoice',
            $title,
            $message,
            route('invoice.show', $invoice->uuid)
        );

        $payer = User::query()->find($payment->created_by);

        if ($payer !== null) {
            $this->notificationService->send($payer, 'invoice', $title, $message, route('verifikasi-tagihan.show', $invoice->uuid));
        }
    }
}
