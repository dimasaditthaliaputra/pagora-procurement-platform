<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Invoice
 */
class InvoiceResource extends JsonResource
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
            'nomor_invoice_vendor' => $this->nomor_invoice_vendor,
            'tanggal_invoice' => $this->tanggal_invoice?->format('Y-m-d'),
            'jumlah_tagihan' => $this->jumlah_tagihan,
            'file_invoice' => $this->file_invoice,
            'status_matching' => $this->status_matching,
            'matching_result' => $this->matching_result,

            'purchase_order' => $this->whenLoaded(
                'purchaseOrder',
                fn () => [
                    'uuid' => $this->purchaseOrder?->uuid,
                    'nomor_po' => $this->purchaseOrder?->nomor_po,
                    'status' => $this->purchaseOrder?->status,
                ]
            ),

            'vendor' => $this->whenLoaded(
                'vendor',
                fn () => [
                    'uuid' => $this->vendor?->uuid,
                    'company_name' => $this->vendor?->company_name,
                ]
            ),

            'items' => InvoiceItemResource::collection(
                $this->whenLoaded('items')
            ),

            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
