<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\InvoiceItem
 */
class InvoiceItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'subtotal' => $this->subtotal,

            'purchase_order_item' => $this->whenLoaded(
                'purchaseOrderItem',
                fn () => [
                    'uuid' => $this->purchaseOrderItem?->uuid,
                    'jumlah_pesanan' => $this->purchaseOrderItem?->jumlah_pesanan,
                    'harga_satuan_po' => $this->purchaseOrderItem?->harga_satuan_po,
                    'komoditas' => $this->when(
                        $this->purchaseOrderItem?->relationLoaded('komoditas'),
                        fn () => [
                            'name' => $this->purchaseOrderItem?->komoditas?->name,
                            'unit' => $this->purchaseOrderItem?->komoditas?->unit,
                        ]
                    ),
                ]
            ),
        ];
    }
}
