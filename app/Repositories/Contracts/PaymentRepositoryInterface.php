<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Payment;

/**
 * Gateway payments of invoices (FR-39, BR-26).
 */
interface PaymentRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Payment;

    /**
     * Find a payment by gateway order id and lock its row (callbacks may arrive twice).
     */
    public function lockByGatewayOrderId(string $orderId): ?Payment;

    /**
     * The invoice's payment awaiting the gateway, if any.
     */
    public function findPendingForInvoice(int $invoiceId): ?Payment;

    /**
     * Total of `paid` payments of an invoice.
     */
    public function sumPaidForInvoice(int $invoiceId): float;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Payment $payment, array $attributes): Payment;

    public function getNextNumber(int $year): string;

    /**
     * Whether any invoice of the purchase order still has an unpaid balance or is not matched (BR-26).
     */
    public function hasUnsettledInvoice(int $purchaseOrderId): bool;
}
