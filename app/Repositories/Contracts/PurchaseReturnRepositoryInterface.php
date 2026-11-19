<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\PurchaseReturn;

/**
 * Purchase returns (FR-42, BR-27). Once `confirmed`, only the move to `completed` is allowed (BR-17).
 */
interface PurchaseReturnRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $items
     */
    public function createWithItems(array $attributes, array $items): PurchaseReturn;

    /**
     * Find a return of one of the Vendor's purchase orders, optionally locking the row.
     */
    public function findByUuidForVendor(int $vendorId, string $uuid, bool $lock = false): ?PurchaseReturn;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(PurchaseReturn $purchaseReturn, array $attributes): PurchaseReturn;

    /**
     * Quantity already on a return that was not refused, keyed by goods receipt item id.
     *
     * @param  list<int>  $receiptItemIds
     * @return array<int, float>
     */
    public function returnedQuantityByReceiptItem(array $receiptItemIds): array;

    public function hasAwaitingForDispute(int $disputeId): bool;

    /**
     * Whether the Vendor confirmed at least one document of the dispute.
     */
    public function hasConfirmedForDispute(int $disputeId): bool;

    public function getNextNumber(int $year): string;
}
