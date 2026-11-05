<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\StatusItemPenerimaan;
use App\Models\PenerimaanBarangItem;
use App\Models\Sengketa;
use App\Services\PenerimaanBarangService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Explicit resource mapping for a QC Damage Report item (`goods_receipt_items` with `status = 'rejected'` —
 * UC-15 «extend» Melaporkan Kerusakan Barang, RULES §5.1, §8.1, BR-02).
 *
 * @mixin PenerimaanBarangItem
 */
class DamageReportItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isTenantActor = $user !== null && $user->tenant_id !== null;

        $receipt = $this->relationLoaded('goodsReceipt') ? $this->goodsReceipt : null;
        $po = ($receipt !== null && $receipt->relationLoaded('purchaseOrder')) ? $receipt->purchaseOrder : null;
        $shipment = ($receipt !== null && $receipt->relationLoaded('shipment')) ? $receipt->shipment : null;
        $inspector = ($receipt !== null && $receipt->relationLoaded('inspector')) ? $receipt->inspector : null;
        $poItem = $this->relationLoaded('poItem') ? $this->poItem : null;
        $commodity = ($poItem !== null && $poItem->relationLoaded('komoditas')) ? $poItem->komoditas : null;

        $statusEnum = $this->status instanceof StatusItemPenerimaan
            ? $this->status
            : StatusItemPenerimaan::tryFrom((string) $this->status);
        $statusValue = $statusEnum?->value ?? (string) $this->status;
        $statusLabel = $statusEnum?->label() ?? 'Ditolak';

        $orderedQty = round((float) ($poItem?->jumlah_pesanan ?? 0), 3);
        $receivedQty = round((float) $this->received_quantity, 3);
        $shortageQty = max(0.0, round($orderedQty - $receivedQty, 3));
        $shrinkagePercent = $orderedQty > 0
            ? round(($shortageQty / $orderedQty) * 100, 2)
            : 0.0;

        $tenantId = $po !== null ? (int) $po->perusahaan_buyer_id : null;
        $effectiveTolerance = PenerimaanBarangService::resolveEffectiveShrinkageTolerance($commodity, $tenantId);

        $rawPhotos = is_array($this->rejection_photos) ? array_values($this->rejection_photos) : [];
        $photoUrls = [];
        foreach ($rawPhotos as $idx => $path) {
            if (is_string($path) && $path !== '') {
                $photoUrls[] = [
                    'index' => $idx,
                    'url' => route('inspeksi-qc.photo', [
                        'itemUuid' => $this->uuid,
                        'index' => $idx,
                    ]),
                ];
            }
        }

        /** @var Sengketa|null $dispute */
        $dispute = null;
        if ($poItem !== null && $poItem->relationLoaded('sengketa')) {
            $dispute = $poItem->sengketa->sortByDesc('id')->first();
        }

        $disputePayload = null;
        if ($dispute !== null) {
            $disputeLogs = $dispute->relationLoaded('logs')
                ? $dispute->logs->map(function ($log): array {
                    $hasAttachment = is_string($log->file_bukti) && $log->file_bukti !== '';

                    return [
                        'uuid' => $log->uuid,
                        'type' => $log->type ?? 'note',
                        'message' => $log->isi_pesan,
                        'has_attachment' => $hasAttachment,
                        'attachment_url' => $hasAttachment
                            ? route('inspeksi-qc.dispute-log.photo', ['logUuid' => $log->uuid])
                            : null,
                        'author_name' => ($log->relationLoaded('penulis') ? $log->penulis?->name : null) ?? 'Sistem',
                        'created_at' => $log->created_at?->toISOString(),
                    ];
                })->values()->all()
                : [];

            $disputePayload = [
                'uuid' => $dispute->uuid,
                'trigger_type' => $dispute->trigger_type ?? 'qc_rejected',
                'trigger_type_label' => $dispute->triggerLabel(),
                'reason' => $dispute->reason,
                'status' => $dispute->status->value,
                'status_label' => $dispute->status->label(),
                'opened_at' => $dispute->tanggal_dibuka?->toISOString(),
                'resolved_at' => $dispute->tanggal_selesai?->toISOString(),
                'logs_count' => count($disputeLogs),
                'logs' => $disputeLogs,
            ];
        }

        $poStatusValue = $po?->status?->value ?? (string) ($po?->status ?? '');
        $poStatusLabel = ($po !== null && method_exists($po->status, 'label'))
            ? $po->status->label()
            : ucfirst(str_replace('_', ' ', $poStatusValue));

        $payload = [
            'uuid' => $this->uuid,
            'goods_receipt_uuid' => $receipt?->uuid,
            'po_item_uuid' => $poItem?->uuid,
            'commodity_uuid' => $commodity?->uuid,
            'commodity_name' => $commodity?->name ?? 'Komoditas',
            'unit' => $commodity?->unit ?? 'kg',
            'ordered_quantity' => $orderedQty,
            'received_quantity' => $receivedQty,
            'shortage_quantity' => $shortageQty,
            'shrinkage_percent' => $shrinkagePercent,
            'unit_price' => (float) ($poItem?->harga_satuan_po ?? 0),
            'subtotal' => (float) ($poItem?->subtotal ?? 0),
            'status' => $statusValue,
            'status_label' => $statusLabel,
            'notes' => $this->notes,
            'photos_count' => count($photoUrls),
            'photos' => $photoUrls,
            'inspected_at' => $receipt?->inspected_at?->toISOString(),
            'matched_plate_number' => $receipt?->matched_plate_number,
            'inspector' => [
                'uuid' => $inspector?->uuid,
                'name' => $inspector?->name ?? 'QC Gudang',
            ],
            'shipment' => $shipment !== null ? [
                'uuid' => $shipment->uuid,
                'route' => $shipment->route,
                'carrier_name' => $shipment->carrier_name,
                'vehicle_plate_number' => $shipment->vehicle_plate_number,
                'estimated_arrival_date' => $shipment->estimated_arrival_date?->format('Y-m-d') ?? (string) $shipment->estimated_arrival_date,
                'is_reshipment' => $shipment->dispute_id !== null,
            ] : null,
            'purchase_order' => $po !== null ? [
                'uuid' => $po->uuid,
                'po_number' => $po->nomor_po,
                'po_date' => $po->tanggal_po?->format('Y-m-d') ?? (string) $po->tanggal_po,
                'status' => $poStatusValue,
                'status_label' => $poStatusLabel,
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
            'dispute' => $disputePayload,
            'created_at' => $this->created_at?->toISOString(),
            'can' => [
                'view' => $receipt !== null && (bool) $user?->can('view', $receipt),
                'view_receipt' => $receipt !== null && (bool) $user?->can('view', $receipt),
                'view_purchase_order' => $po !== null && (bool) $user?->can('view', $po),
                'view_shipment' => $shipment !== null && (bool) $user?->can('view', $shipment),
                'report_additional_evidence' => $receipt !== null
                    && $dispute !== null
                    && (bool) $user?->can('reportDamageEvidence', [$receipt, $dispute]),
            ],
        ];

        // Internal Tenant shrinkage tolerance configuration is shared only with Tenant actors (BR-02 / RULES §8.1).
        if ($isTenantActor) {
            $payload['shrinkage_tolerance'] = $effectiveTolerance;
            $payload['exceeds_shrinkage_tolerance'] = $shrinkagePercent > $effectiveTolerance;
        }

        return $payload;
    }
}
