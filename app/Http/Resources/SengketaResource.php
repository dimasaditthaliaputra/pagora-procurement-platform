<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\StatusItemPenerimaan;
use App\Models\PenerimaanBarangItem;
use App\Models\Sengketa;
use App\Services\PenerimaanBarangService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Explicit API Resource for Procurement Dispute & Log Sengketa (`UC-18`, `FR-31`, `BR-02`, `RULES` §5.1 & §8.1).
 *
 * Exposes UUIDs only (never internal auto-increment `id` or `*_id`).
 *
 * @mixin Sengketa
 */
class SengketaResource extends JsonResource
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

        $po = $this->relationLoaded('purchaseOrder') ? $this->purchaseOrder : null;
        $vendor = ($po !== null && $po->relationLoaded('vendor')) ? $po->vendor : null;
        $tenant = ($po !== null && $po->relationLoaded('perusahaanBuyer')) ? $po->perusahaanBuyer : null;
        $poItem = $this->relationLoaded('poItem') ? $this->poItem : null;
        $commodity = ($poItem !== null && $poItem->relationLoaded('komoditas')) ? $poItem->komoditas : null;
        $invoice = $this->relationLoaded('invoice') ? $this->invoice : null;
        $opener = $this->relationLoaded('pembuka') ? $this->pembuka : null;

        $triggerEnum = $this->triggerEnum();
        $shortCode = '#'.Str::upper(substr($this->uuid, 0, 8));

        if ($poItem !== null) {
            $unit = $commodity?->unit ?? 'kg';
            $qtyFormatted = rtrim(rtrim(number_format((float) $poItem->jumlah_pesanan, 3, ',', '.'), '0'), ',');
            $objectSummary = ($commodity?->name ?? 'Item Komoditas')." ({$qtyFormatted} {$unit})";
        } elseif ($invoice !== null) {
            $objectSummary = 'Invoice '.$invoice->nomor_invoice_vendor;
        } else {
            $objectSummary = 'Dokumen Purchase Order';
        }

        $logsCount = isset($this->logs_count)
            ? (int) $this->logs_count
            : ($this->relationLoaded('logs') ? $this->logs->count() : 0);

        $payload = [
            'uuid' => $this->uuid,
            'short_code' => $shortCode,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'trigger_type' => $this->trigger_type ?? 'qc_rejected',
            'trigger_type_label' => $this->triggerLabel(),
            'trigger_description' => $triggerEnum?->description(),
            'reason' => $this->reason,
            'opened_at' => $this->tanggal_dibuka?->toISOString(),
            'resolved_at' => $this->tanggal_selesai?->toISOString(),
            'opened_by_name' => $opener?->name ?? 'Sistem Otomatis',
            'opened_by_role_label' => $opener?->roleName()?->label() ?? 'Sistem',
            'logs_count' => $logsCount,
            'po_uuid' => $po?->uuid,
            'po_number' => $po?->nomor_po ?? '-',
            'po_date' => $po?->tanggal_po?->format('Y-m-d'),
            'po_status' => $po?->status?->value,
            'po_status_label' => $po?->status?->label() ?? '-',
            'vendor_uuid' => $vendor?->uuid,
            'vendor_name' => $vendor?->company_name ?? '-',
            'vendor_email' => $vendor?->contact_email,
            'object_summary' => $objectSummary,
            'export_url' => route('log-sengketa.export', $this->uuid),
            'can' => [
                'view' => $user?->can('view', $this->resource) ?? false,
                'export' => $user?->can('export', $this->resource) ?? false,
                'review' => $user?->can('review', $this->resource) ?? false,
                'view_po' => $po !== null && ($user?->can('view', $po) ?? false),
            ],
        ];

        // Full detail payload when deep relations are loaded (`Show` view)
        if ($this->relationLoaded('logs')) {
            $tenantId = $po !== null ? (int) $po->perusahaan_buyer_id : null;

            // Purchase Order detail
            $latestShipment = ($po !== null && $po->relationLoaded('pengiriman'))
                ? $po->pengiriman->first()
                : null;

            $poItemsPayload = ($po !== null && $po->relationLoaded('poItems'))
                ? $po->poItems->map(static function ($item): array {
                    $com = $item->relationLoaded('komoditas') ? $item->komoditas : null;

                    return [
                        'uuid' => $item->uuid,
                        'commodity_uuid' => $com?->uuid,
                        'commodity_name' => $com?->name ?? 'Komoditas',
                        'unit' => $com?->unit ?? 'kg',
                        'quantity' => (float) $item->jumlah_pesanan,
                        'unit_price' => $item->harga_satuan_po !== null ? (float) $item->harga_satuan_po : null,
                        'subtotal' => $item->subtotal !== null ? (float) $item->subtotal : null,
                        'status_item' => $item->status_item,
                    ];
                })->values()->all()
                : [];

            $poPayload = $po !== null ? [
                'uuid' => $po->uuid,
                'po_number' => $po->nomor_po,
                'po_date' => $po->tanggal_po?->format('Y-m-d'),
                'status' => $po->status->value,
                'status_label' => $po->status->label(),
                'notes' => $po->catatan,
                'locked_at' => $po->locked_at?->toISOString(),
                'created_by_name' => ($po->relationLoaded('pembuat') ? $po->pembuat?->name : null) ?? '-',
                'tenant_name' => $tenant?->company_name ?? '-',
                'vendor_name' => $vendor?->company_name ?? '-',
                'vendor_email' => $vendor?->contact_email,
                'vendor_phone' => $vendor?->phone,
                'total_amount' => (float) ($po->relationLoaded('poItems') ? $po->poItems->sum('subtotal') : 0),
                'items' => $poItemsPayload,
                'latest_shipment' => $latestShipment !== null ? [
                    'uuid' => $latestShipment->uuid,
                    'route' => $latestShipment->route,
                    'carrier_name' => $latestShipment->carrier_name,
                    'vehicle_plate_number' => $latestShipment->vehicle_plate_number,
                    'estimated_arrival_date' => $latestShipment->estimated_arrival_date?->format('Y-m-d'),
                    'status' => $latestShipment->status->value,
                    'status_label' => $latestShipment->status->label(),
                ] : null,
            ] : null;

            // Internal ceiling price reference strictly visible only to Tenant actors (`BR-02`)
            if ($poPayload !== null && $isTenantActor) {
                $poPayload['ceiling_price_reference'] = $po?->harga_pagu_acuan !== null
                    ? (float) $po->harga_pagu_acuan
                    : null;
            }

            // Disputed PO Item & QC Inspection Evidence
            $disputedItemPayload = null;
            if ($poItem !== null) {
                $orderedQty = round((float) $poItem->jumlah_pesanan, 3);
                $tolerance = PenerimaanBarangService::resolveEffectiveShrinkageTolerance($commodity, $tenantId);

                /** @var PenerimaanBarangItem|null $receiptItem */
                $receiptItem = null;
                if ($poItem->relationLoaded('penerimaanBarangItems')) {
                    $receiptItem = $poItem->penerimaanBarangItems
                        ->first(static fn (PenerimaanBarangItem $ri): bool => ($ri->status instanceof StatusItemPenerimaan ? $ri->status->value : (string) $ri->status) === 'rejected')
                        ?? $poItem->penerimaanBarangItems->sortByDesc('id')->first();
                }

                $qcInspectionPayload = null;
                if ($receiptItem !== null) {
                    $receivedQty = round((float) $receiptItem->received_quantity, 3);
                    $shortageQty = max(0.0, round($orderedQty - $receivedQty, 3));
                    $shrinkagePct = $orderedQty > 0 ? round(($shortageQty / $orderedQty) * 100, 2) : 0.0;
                    $receipt = $receiptItem->relationLoaded('goodsReceipt') ? $receiptItem->goodsReceipt : null;

                    $rawPhotos = is_array($receiptItem->rejection_photos) ? array_values($receiptItem->rejection_photos) : [];
                    $photos = [];
                    foreach ($rawPhotos as $idx => $path) {
                        if (is_string($path) && $path !== '') {
                            $photos[] = [
                                'index' => $idx,
                                'filename' => basename($path),
                                'url' => route('log-sengketa.qc-photo', [
                                    'uuid' => $this->uuid,
                                    'itemUuid' => $receiptItem->uuid,
                                    'index' => $idx,
                                ]),
                            ];
                        }
                    }

                    $qcInspectionPayload = [
                        'receipt_uuid' => $receipt?->uuid,
                        'receipt_item_uuid' => $receiptItem->uuid,
                        'received_quantity' => $receivedQty,
                        'shortage_quantity' => $shortageQty,
                        'shrinkage_percentage' => $shrinkagePct,
                        'exceeds_tolerance' => $shrinkagePct > $tolerance,
                        'inspector_name' => ($receipt !== null && $receipt->relationLoaded('inspector') ? $receipt->inspector?->name : null) ?? '-',
                        'inspected_at' => $receipt?->inspected_at?->toISOString(),
                        'matched_plate_number' => $receipt?->matched_plate_number,
                        'notes' => $receiptItem->notes,
                        'photos' => $photos,
                    ];
                }

                $disputedItemPayload = [
                    'uuid' => $poItem->uuid,
                    'commodity_uuid' => $commodity?->uuid,
                    'commodity_name' => $commodity?->name ?? 'Komoditas',
                    'unit' => $commodity?->unit ?? 'kg',
                    'ordered_quantity' => $orderedQty,
                    'unit_price' => $poItem->harga_satuan_po !== null ? (float) $poItem->harga_satuan_po : 0.0,
                    'subtotal' => $poItem->subtotal !== null ? (float) $poItem->subtotal : 0.0,
                    'shrinkage_tolerance' => $tolerance,
                    'qc_inspection' => $qcInspectionPayload,
                ];
            }

            // Disputed Invoice (3-Way Matching)
            $disputedInvoicePayload = null;
            if ($invoice !== null) {
                $matchingStatus = (string) ($invoice->status_matching ?? 'pending');
                $matchingLabel = match ($matchingStatus) {
                    'matched' => 'Sesuai (Matched)',
                    'mismatched' => 'Tidak Sesuai (Mismatched)',
                    default => 'Menunggu Verifikasi',
                };

                $disputedInvoicePayload = [
                    'uuid' => $invoice->uuid,
                    'vendor_invoice_number' => $invoice->nomor_invoice_vendor,
                    'invoice_date' => $invoice->tanggal_invoice?->format('Y-m-d'),
                    'total_amount' => (float) $invoice->jumlah_tagihan,
                    'matching_status' => $matchingStatus,
                    'matching_status_label' => $matchingLabel,
                    'verified_by_name' => ($invoice->relationLoaded('verifikator') ? $invoice->verifikator?->name : null),
                    'verified_at' => $invoice->diverifikasi_pada?->toISOString(),
                ];
            }

            // Smart Negotiation Chat history on the related PO (`UC-18` step 2, `FR-31`)
            $negotiationMessages = ($po !== null && $po->relationLoaded('chatNegosiasi'))
                ? $po->chatNegosiasi->map(static function ($msg): array {
                    $sender = $msg->relationLoaded('pengirim') ? $msg->pengirim : null;
                    $roleEnum = $sender?->roleName();

                    return [
                        'uuid' => $msg->uuid,
                        'sender_name' => $sender?->name ?? 'Pengguna',
                        'sender_role_label' => $roleEnum?->label() ?? 'Pengguna',
                        'is_vendor_sender' => $sender?->isVendor() ?? false,
                        'message' => $msg->pesan_teks,
                        'offer_amount' => $msg->nominal_tawar !== null ? (float) $msg->nominal_tawar : null,
                        'created_at' => $msg->created_at?->toISOString(),
                    ];
                })->values()->all()
                : [];

            // Immutable Log Sengketa timeline
            $disputeUuid = $this->uuid;
            $logsPayload = $this->logs->map(static function ($log) use ($disputeUuid): array {
                $author = $log->relationLoaded('penulis') ? $log->penulis : null;
                $hasAttachment = is_string($log->file_bukti) && $log->file_bukti !== '';
                $ext = $hasAttachment ? strtolower(pathinfo((string) $log->file_bukti, PATHINFO_EXTENSION)) : '';
                $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
                $type = (string) ($log->type ?? 'note');

                $typeLabel = match ($type) {
                    'status_change' => 'Perubahan Status',
                    'evidence' => 'Bukti Tambahan',
                    'chat_snapshot' => 'Snapshot Negosiasi',
                    'resolution' => 'Penyelesaian',
                    default => 'Catatan Tinjauan / Mediasi',
                };

                return [
                    'uuid' => $log->uuid,
                    'type' => $type,
                    'type_label' => $typeLabel,
                    'message' => $log->isi_pesan,
                    'author_name' => $author?->name ?? 'Sistem Otomatis',
                    'author_role_label' => $author?->roleName()?->label() ?? 'Sistem',
                    'has_attachment' => $hasAttachment,
                    'attachment_name' => $hasAttachment ? basename((string) $log->file_bukti) : null,
                    'attachment_is_image' => $isImage,
                    'attachment_url' => $hasAttachment
                        ? route('log-sengketa.attachment', [
                            'uuid' => $disputeUuid,
                            'logUuid' => $log->uuid,
                        ])
                        : null,
                    'created_at' => $log->created_at?->toISOString(),
                ];
            })->values()->all();

            $payload['purchase_order'] = $poPayload;
            $payload['disputed_item'] = $disputedItemPayload;
            $payload['disputed_invoice'] = $disputedInvoicePayload;
            $payload['negotiation_messages'] = $negotiationMessages;
            $payload['logs'] = $logsPayload;
        }

        return $payload;
    }
}
