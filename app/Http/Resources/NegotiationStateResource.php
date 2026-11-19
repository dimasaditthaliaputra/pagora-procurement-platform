<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\StatusPurchaseOrder;
use App\Models\PoItem;
use App\Models\PurchaseOrder;
use App\Services\NegotiationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * PO header, items, and permissions of the negotiation room. Sent on page load and on every poll.
 *
 * @mixin PurchaseOrder
 */
class NegotiationStateResource extends JsonResource
{
    /**
     * @param  array<int, float>  $ceilings  Harga Pagu per commodity id (Tenant side only).
     */
    public function __construct(
        PurchaseOrder $resource,
        private readonly string $side,
        private readonly array $ceilings = [],
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $items = $this->poItems->map(fn (PoItem $item): array => (new NegotiationItemResource(
            $item,
            $this->side,
            $this->ceilings[$item->komoditas_id] ?? null,
        ))->toArray($request));

        return [
            'uuid' => $this->uuid,
            'nomor_po' => $this->nomor_po,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'is_open' => $this->status === StatusPurchaseOrder::Negosiasi,
            'locked_at' => $this->locked_at?->toIso8601String(),
            'alasan_pembatalan' => $this->alasan_pembatalan,
            'side' => $this->side,
            'counterparty' => $this->side === NegotiationService::SIDE_VENDOR
                ? $this->tenant?->company_name
                : $this->vendor?->company_name,
            'items' => $items->values()->all(),
            'total_agreed' => round((float) $items->sum(fn (array $item): float => $item['agreed_price'] !== null ? (float) $item['subtotal'] : 0.0), 2),
            'can' => [
                'send_message' => (bool) $user?->can('tawar', $this->resource),
                'cancel' => (bool) $user?->can('batalkan', $this->resource),
            ],
        ];
    }
}
