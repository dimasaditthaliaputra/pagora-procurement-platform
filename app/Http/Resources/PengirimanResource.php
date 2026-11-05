<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\StatusPengiriman;
use App\Models\Pengiriman;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Explicit resource mapping for Self-Reporting Logistics (`shipments` — RULES §5.3, §11.1, BR-02).
 * Never exposes internal auto-increment `id`s or Tenant ceiling prices (`harga_pagu`).
 *
 * @mixin Pengiriman
 */
class PengirimanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $statusEnum = $this->status instanceof StatusPengiriman
            ? $this->status
            : StatusPengiriman::tryFrom((string) $this->status);

        $statusValue = $statusEnum?->value ?? (string) $this->status;
        $statusLabel = $statusEnum?->label() ?? ucfirst(str_replace('_', ' ', $statusValue));

        $po = $this->relationLoaded('purchaseOrder') ? $this->purchaseOrder : null;
        $reporter = $this->relationLoaded('reporter') ? $this->reporter : null;
        $dispute = $this->relationLoaded('dispute') ? $this->dispute : null;

        $poItems = ($po !== null && $po->relationLoaded('poItems'))
            ? $po->poItems->map(fn ($item): array => [
                'uuid' => $item->uuid,
                'commodity_uuid' => $item->relationLoaded('komoditas') ? $item->komoditas?->uuid : null,
                'commodity_name' => ($item->relationLoaded('komoditas') ? $item->komoditas?->name : null) ?? 'Komoditas',
                'unit' => ($item->relationLoaded('komoditas') ? $item->komoditas?->unit : null) ?? 'kg',
                'quantity' => (float) $item->jumlah_pesanan,
                'unit_price' => (float) $item->harga_satuan_po,
                'subtotal' => (float) $item->subtotal,
            ])->values()->all()
            : [];

        $totalAmount = array_reduce(
            $poItems,
            fn (float $carry, array $row): float => $carry + (float) $row['subtotal'],
            0.0
        );

        $poStatusValue = $po?->status?->value ?? (string) ($po?->status ?? '');
        $poStatusLabel = ($po !== null && method_exists($po->status, 'label'))
            ? $po->status->label()
            : ucfirst(str_replace('_', ' ', $poStatusValue));

        return [
            'uuid' => $this->uuid,
            'route' => $this->route,
            'carrier_name' => $this->carrier_name,
            'vehicle_plate_number' => $this->vehicle_plate_number,
            'estimated_arrival_date' => $this->estimated_arrival_date?->format('Y-m-d') ?? (string) $this->estimated_arrival_date,
            'status' => $statusValue,
            'status_label' => $statusLabel,
            'is_reshipment' => $this->dispute_id !== null,
            'dispute_uuid' => $dispute?->uuid,
            'reporter' => [
                'uuid' => $reporter?->uuid,
                'name' => $reporter?->name ?? 'Vendor',
            ],
            'purchase_order' => $po !== null ? [
                'uuid' => $po->uuid,
                'po_number' => $po->nomor_po,
                'po_date' => $po->tanggal_po?->format('Y-m-d') ?? (string) $po->tanggal_po,
                'status' => $poStatusValue,
                'status_label' => $poStatusLabel,
                'notes' => $po->catatan,
                'is_locked' => $po->locked_at !== null || $po->isQuantityLocked(),
                'quantity_locked' => $po->isQuantityLocked(),
                'locked_at' => $po->locked_at?->toISOString(),
                'total_amount' => $totalAmount,
                'items_count' => count($poItems),
                'items' => $poItems,
                'tenant' => $po->relationLoaded('tenant') && $po->tenant !== null ? [
                    'uuid' => $po->tenant->uuid,
                    'company_name' => $po->tenant->company_name,
                    'address' => $po->tenant->address,
                ] : null,
                'vendor' => $po->relationLoaded('vendor') && $po->vendor !== null ? [
                    'uuid' => $po->vendor->uuid,
                    'company_name' => $po->vendor->company_name,
                    'contact_email' => $po->vendor->contact_email,
                    'phone' => $po->vendor->phone,
                    'address' => $po->vendor->address,
                ] : null,
            ] : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'can' => [
                'view' => (bool) $user?->can('view', $this->resource),
                'view_purchase_order' => $po !== null && (bool) $user?->can('view', $po),
            ],
        ];
    }
}
