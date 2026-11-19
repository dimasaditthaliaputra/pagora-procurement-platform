<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\CreditNote;

/**
 * Credit notes (FR-41, BR-16). A `confirmed` credit note is immutable (BR-17).
 */
interface CreditNoteRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): CreditNote;

    /**
     * Find a credit note billed to the given Vendor, optionally locking the row.
     */
    public function findByUuidForVendor(int $vendorId, string $uuid, bool $lock = false): ?CreditNote;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(CreditNote $creditNote, array $attributes): CreditNote;

    /**
     * Total of `confirmed` credit notes of an invoice: what is deducted from the bill (BR-26).
     */
    public function sumConfirmedForInvoice(int $invoiceId): float;

    /**
     * Total of credit notes still awaiting the Vendor or confirmed, used for the BR-16 ceiling.
     */
    public function sumActiveForInvoice(int $invoiceId): float;

    public function hasAwaitingForDispute(int $disputeId): bool;

    /**
     * Whether the Vendor confirmed at least one document of the dispute.
     */
    public function hasConfirmedForDispute(int $disputeId): bool;

    public function getNextNumber(int $year): string;
}
