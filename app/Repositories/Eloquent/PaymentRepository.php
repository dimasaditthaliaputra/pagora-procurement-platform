<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\CreditNoteStatus;
use App\Enums\PaymentStatus;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Payment;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use Illuminate\Support\Str;

class PaymentRepository implements PaymentRepositoryInterface
{
    public function __construct(
        protected Payment $model
    ) {}

    public function create(array $attributes): Payment
    {
        return $this->model->newQuery()->create(['uuid' => (string) Str::uuid(), ...$attributes]);
    }

    public function lockByGatewayOrderId(string $orderId): ?Payment
    {
        return $this->model->newQuery()
            ->where('gateway_order_id', $orderId)
            ->with('invoice.purchaseOrder')
            ->lockForUpdate()
            ->first();
    }

    public function findPendingForInvoice(int $invoiceId): ?Payment
    {
        return $this->model->newQuery()
            ->where('invoice_id', $invoiceId)
            ->where('payment_status', PaymentStatus::Pending->value)
            ->whereNotNull('snap_token')
            ->latest('id')
            ->first();
    }

    public function sumPaidForInvoice(int $invoiceId): float
    {
        return (float) $this->model->newQuery()
            ->where('invoice_id', $invoiceId)
            ->where('payment_status', PaymentStatus::Paid->value)
            ->sum('amount');
    }

    public function update(Payment $payment, array $attributes): Payment
    {
        $payment->fill($attributes)->save();

        return $payment;
    }

    public function getNextNumber(int $year): string
    {
        return NumberSequence::next($this->model->newQuery(), 'payment_number', sprintf('PAY-%04d-', $year));
    }

    public function hasUnsettledInvoice(int $purchaseOrderId): bool
    {
        return Invoice::query()
            ->where('purchase_order_id', $purchaseOrderId)
            ->with(['creditNotes', 'payments'])
            ->get()
            ->contains(function (Invoice $invoice): bool {
                $credited = $invoice->creditNotes
                    ->filter(fn (CreditNote $note): bool => $note->status === CreditNoteStatus::Confirmed)
                    ->sum('amount');
                $paid = $invoice->payments
                    ->filter(fn (Payment $payment): bool => $payment->payment_status === PaymentStatus::Paid)
                    ->sum('amount');

                return ! $invoice->isMatched() || ((float) $invoice->jumlah_tagihan - $credited - $paid) >= 1;
            });
    }
}
