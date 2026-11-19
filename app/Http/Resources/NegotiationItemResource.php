<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\NegotiationStatus;
use App\Models\PoItem;
use App\Services\NegotiationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Negotiation state of one PO item. Estimate and Harga Pagu are Tenant-internal (BR-02):
 * they are only added for the Tenant side.
 *
 * @mixin PoItem
 */
class NegotiationItemResource extends JsonResource
{
    public function __construct(
        PoItem $resource,
        private readonly string $side,
        private readonly ?float $ceilingPrice = null,
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $quantity = (float) $this->jumlah_pesanan;
        $proposed = $this->harga_satuan_po !== null ? (float) $this->harga_satuan_po : null;
        $agreed = $this->status_item === NegotiationStatus::Agreed;

        $data = [
            'uuid' => $this->uuid,
            'name' => $this->komoditas?->name,
            'unit' => $this->komoditas?->unit,
            'quantity' => $quantity,
            'status' => $this->status_item?->value,
            'status_label' => $this->status_item?->label(),
            // Purchasing's current proposal, or the final price once agreed.
            'proposed_price' => $proposed,
            'vendor_offer_price' => $this->harga_tawar_vendor !== null ? (float) $this->harga_tawar_vendor : null,
            'agreed_price' => $agreed ? $proposed : null,
            'subtotal' => $proposed !== null ? round($quantity * $proposed, 2) : null,
            'can' => NegotiationService::itemActions($this->side, $this->resource),
        ];

        if ($this->side === NegotiationService::SIDE_TENANT) {
            $data['estimated_unit_price'] = (float) $this->estimated_unit_price;
            $data['ceiling_price'] = $this->ceilingPrice;
        }

        return $data;
    }
}
