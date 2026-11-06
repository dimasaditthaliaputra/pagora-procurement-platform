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
            'status_matching' => is_object($this->status_matching) ? $this->status_matching->value : $this->status_matching,
            'matching_result' => $this->matching_result,
            'diverifikasi_pada' => $this->diverifikasi_pada?->toISOString(),

            'verifikator' => $this->whenLoaded(
                'verifikator',
                fn () => [
                    'uuid' => $this->verifikator?->uuid,
                    'name' => $this->verifikator?->name,
                ]
            ),

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
                    'contact_email' => $this->vendor?->contact_email,
                    'phone' => $this->vendor?->phone,
                ]
            ),

            'items' => InvoiceItemResource::collection(
                $this->whenLoaded('items')
            ),

            'sengketa' => $this->whenLoaded(
                'sengketa',
                fn () => $this->sengketa->map(fn ($s) => [
                    'uuid' => $s->uuid,
                    'trigger_type' => $s->trigger_type,
                    'trigger_label' => $s->triggerLabel(),
                    'reason' => $s->reason,
                    'status' => is_object($s->status) ? $s->status->value : $s->status,
                    'tanggal_dibuka' => $s->tanggal_dibuka?->toISOString(),
                ])
            ),

            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
