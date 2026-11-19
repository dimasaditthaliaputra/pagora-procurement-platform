<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\ChatNegosiasi;
use Illuminate\Support\Collection;

/**
 * Negotiation messages are an immutable audit trail (BR-17): create and read only.
 */
interface ChatNegosiasiRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): ChatNegosiasi;

    /**
     * Load the relations the chat resource needs (sender, role, item), so a just-created
     * message can be returned without lazy loading.
     */
    public function withDetails(ChatNegosiasi $message): ChatNegosiasi;

    /**
     * Messages of a purchase order, oldest first. With `$afterUuid`, only messages written after it
     * (used by polling); an unknown cursor returns the latest `$limit` messages instead.
     *
     * @return Collection<int, ChatNegosiasi>
     */
    public function forPurchaseOrder(int $purchaseOrderId, ?string $afterUuid = null, int $limit = 200): Collection;
}
