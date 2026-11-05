<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\StatusItemPenerimaan;
use App\Enums\StatusPengiriman;
use App\Models\PenerimaanBarang;
use App\Models\PenerimaanBarangItem;
use App\Models\Sengketa;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Explicit resource mapping for Goods Receipt / QC Inspection (`goods_receipts` — RULES §5.1, §11.1, BR-02).
 * Never exposes auto-increment `id`s or internal Tenant ceiling prices (`harga_pagu`).
 *
 * @mixin PenerimaanBarang
 */
class PenerimaanBarangResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $po = $this->relationLoaded('purchaseOrder') ? $this->purchaseOrder : null;
        $shipment = $this->relationLoaded('shipment') ? $this->shipment : null;
        $inspector = $this->relationLoaded('inspector') ? $this->inspector : null;

        $acceptedCount = 0;
        $rejectedCount = 0;

        $mappedItems = $this->relationLoaded('items')
            ? $this->items->map(function (PenerimaanBarangItem $item) use (&$acceptedCount, &$rejectedCount): array {
                $statusEnum = $item->status instanceof StatusItemPenerimaan
                    ? $item->status
                    : StatusItemPenerimaan::tryFrom((string) $item->status);

                $statusValue = $statusEnum?->value ?? (string) $item->status;
                $statusLabel = $statusEnum?->label() ?? ucfirst(str_replace('_', ' ', $statusValue));

                if ($statusEnum === StatusItemPenerimaan::Rejected) {
                    $rejectedCount++;
                } else {
                    $acceptedCount++;
                }

                $poItem = $item->relationLoaded('poItem') ? $item->poItem : null;
                $commodity = ($poItem !== null && $poItem->relationLoaded('komoditas')) ? $poItem->komoditas : null;

                $rawPhotos = is_array($item->rejection_photos) ? array_values($item->rejection_photos) : [];
                $photoUrls = [];
                foreach ($rawPhotos as $idx => $path) {
                    if (is_string($path) && $path !== '') {
                        $photoUrls[] = [
                            'index' => $idx,
                            'url' => route('inspeksi-qc.photo', [
                                'itemUuid' => $item->uuid,
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
                        ? $dispute->logs->map(fn ($log): array => [
                            'uuid' => $log->uuid,
                            'type' => $log->type ?? 'note',
                            'message' => $log->isi_pesan,
                            'has_attachment' => is_string($log->file_bukti) && $log->file_bukti !== '',
                            'author_name' => ($log->relationLoaded('penulis') ? $log->penulis?->name : null) ?? 'Sistem',
                            'created_at' => $log->created_at?->toISOString(),
                        ])->values()->all()
                        : [];

                    $disputePayload = [
                        'uuid' => $dispute->uuid,
                        'trigger_type' => $dispute->trigger_type,
                        'reason' => $dispute->reason,
                        'status' => $dispute->status->value,
                        'status_label' => $dispute->status->label(),
                        'opened_at' => $dispute->tanggal_dibuka?->toISOString(),
                        'resolved_at' => $dispute->tanggal_selesai?->toISOString(),
                        'logs' => $disputeLogs,
                    ];
                }

                return [
                    'uuid' => $item->uuid,
                    'po_item_uuid' => $poItem?->uuid,
                    'commodity_uuid' => $commodity?->uuid,
                    'commodity_name' => $commodity?->name ?? 'Komoditas',
                    'unit' => $commodity?->unit ?? 'kg',
                    'ordered_quantity' => (float) ($poItem?->jumlah_pesanan ?? 0),
                    'received_quantity' => (float) $item->received_quantity,
                    'unit_price' => (float) ($poItem?->harga_satuan_po ?? 0),
                    'subtotal' => (float) ($poItem?->subtotal ?? 0),
                    'status' => $statusValue,
                    'status_label' => $statusLabel,
                    'notes' => $item->notes,
                    'photos_count' => count($photoUrls),
                    'photos' => $photoUrls,
                    'dispute' => $disputePayload,
                ];
            })->values()->all()
            : [];

        $hasRejection = $rejectedCount > 0;
        $overallResult = $hasRejection ? 'rejected' : 'accepted';
        $overallResultLabel = $hasRejection ? 'Terdapat Penolakan (Sengketa QC)' : 'Disetujui Lengkap (Sesuai)';

        $shipmentStatusEnum = $shipment?->status instanceof StatusPengiriman
            ? $shipment->status
            : ($shipment !== null ? StatusPengiriman::tryFrom((string) $shipment->status) : null);

        $poStatusValue = $po?->status?->value ?? (string) ($po?->status ?? '');
        $poStatusLabel = ($po !== null && method_exists($po->status, 'label'))
            ? $po->status->label()
            : ucfirst(str_replace('_', ' ', $poStatusValue));

        return [
            'uuid' => $this->uuid,
            'matched_plate_number' => $this->matched_plate_number,
            'inspected_at' => $this->inspected_at?->toISOString(),
            'notes' => $this->notes,
            'overall_result' => $overallResult,
            'overall_result_label' => $overallResultLabel,
            'accepted_count' => $acceptedCount,
            'rejected_count' => $rejectedCount,
            'items_count' => count($mappedItems),
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
                'status' => $shipmentStatusEnum?->value ?? (string) $shipment->status,
                'status_label' => $shipmentStatusEnum?->label() ?? 'Tiba di Tujuan',
                'is_reshipment' => $shipment->dispute_id !== null,
            ] : null,
            'purchase_order' => $po !== null ? [
                'uuid' => $po->uuid,
                'po_number' => $po->nomor_po,
                'po_date' => $po->tanggal_po?->format('Y-m-d') ?? (string) $po->tanggal_po,
                'status' => $poStatusValue,
                'status_label' => $poStatusLabel,
                'notes' => $po->catatan,
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
            'items' => $mappedItems,
            'created_at' => $this->created_at?->toISOString(),
            'can' => [
                'view' => (bool) $user?->can('view', $this->resource),
                'view_purchase_order' => $po !== null && (bool) $user?->can('view', $po),
                'view_shipment' => $shipment !== null && (bool) $user?->can('view', $shipment),
            ],
        ];
    }
}

