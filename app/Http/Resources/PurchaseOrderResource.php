<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseOrder
 */
class PurchaseOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isVendorUser = $user?->isVendor() ?? false;

        $items = $this->poItems->map(function ($item) use ($isVendorUser) {
            $komoditas = $item->komoditas;
            // Dummy reference ceiling price if available or standard fallback
            $hargaPagu = 15000.0;
            $subtotal = (float) $item->subtotal;

            return [
                'uuid' => $item->uuid,
                'komoditas_uuid' => $komoditas?->uuid,
                'nama_komoditas' => $komoditas?->name ?? 'Komoditas',
                'satuan' => $komoditas?->unit ?? 'kg',
                'jumlah_pesanan' => (float) $item->jumlah_pesanan,
                'harga_satuan_po' => (float) $item->harga_satuan_po,
                'harga_tawar_vendor' => $item->harga_tawar_vendor !== null ? (float) $item->harga_tawar_vendor : null,
                'subtotal' => $subtotal,
                'harga_pagu' => $isVendorUser ? null : $hargaPagu,
                'status_item' => $item->status_item,
            ];
        });

        $totalPo = $items->sum('subtotal');
        $totalPagu = $isVendorUser ? null : $items->sum(fn ($i) => $i['jumlah_pesanan'] * ($i['harga_pagu'] ?? 0));

        $statusValue = $this->status?->value ?? (string) $this->status;
        $statusLabel = method_exists($this->status, 'label')
            ? $this->status->label()
            : ucfirst($statusValue);

        $chat = $this->relationLoaded('chatNegosiasi')
            ? $this->chatNegosiasi->map(function ($chat) use ($user) {
                $pengirim = $chat->pengirim;
                $isVendor = $pengirim?->vendor_id !== null;

                return [
                    'uuid' => $chat->uuid,
                    'pesan_teks' => $chat->pesan_teks,
                    'nominal_tawar' => $chat->nominal_tawar !== null ? (float) $chat->nominal_tawar : null,
                    'created_at' => $chat->created_at?->toISOString(),
                    'pengirim' => [
                        'uuid' => $pengirim?->uuid,
                        'nama' => $pengirim?->name ?? 'Pengguna',
                        'peran' => $isVendor ? 'vendor' : 'buyer',
                        'role' => $pengirim?->role?->name ?? 'user',
                        'role_label' => $pengirim?->roleName()?->label() ?? 'Pengguna',
                        'is_me' => $user !== null && (int) $user->id === (int) $chat->pengirim_id,
                    ],
                ];
            })->values()->all()
            : [];

        $shipments = $this->relationLoaded('pengiriman')
            ? $this->pengiriman->map(function ($shipment): array {
                $shipStatusValue = $shipment->status?->value ?? (string) $shipment->status;
                $shipStatusLabel = method_exists($shipment->status, 'label')
                    ? $shipment->status->label()
                    : ucfirst(str_replace('_', ' ', $shipStatusValue));
                $reporter = $shipment->relationLoaded('reporter') ? $shipment->reporter : null;

                return [
                    'uuid' => $shipment->uuid,
                    'route' => $shipment->route,
                    'carrier_name' => $shipment->carrier_name,
                    'vehicle_plate_number' => $shipment->vehicle_plate_number,
                    'estimated_arrival_date' => $shipment->estimated_arrival_date?->format('Y-m-d') ?? (string) $shipment->estimated_arrival_date,
                    'status' => $shipStatusValue,
                    'status_label' => $shipStatusLabel,
                    'is_reshipment' => $shipment->dispute_id !== null,
                    'reported_by_name' => $reporter?->name ?? 'Vendor',
                    'created_at' => $shipment->created_at?->toISOString(),
                ];
            })->values()->all()
            : [];

        return [
            'uuid' => $this->uuid,
            'nomor_po' => $this->nomor_po,
            'tanggal_po' => $this->tanggal_po?->format('Y-m-d') ?? (string) $this->tanggal_po,
            'catatan' => $this->catatan,
            'alasan_pembatalan' => $this->alasan_pembatalan,
            'status' => $statusValue,
            'status_label' => $statusLabel,
            'is_editable' => $statusValue === 'draft',
            'quantity_locked' => $this->isQuantityLocked(),
            'current_role' => $isVendorUser ? 'vendor' : 'buyer',
            'harga_pagu_acuan' => $isVendorUser ? null : (float) ($this->harga_pagu_acuan ?? $totalPagu),
            'dibuat_oleh' => $this->pembuat?->name ?? 'User',
            'tenant' => [
                'uuid' => $this->tenant?->uuid,
                'nama_perusahaan' => $this->tenant?->company_name ?? 'Tenant',
                'tax_number' => $this->tenant?->tax_number,
                'address' => $this->tenant?->address,
            ],
            'vendor' => [
                'uuid' => $this->vendor?->uuid,
                'nama_perusahaan' => $this->vendor?->company_name ?? 'Vendor',
                'contact_email' => $this->vendor?->contact_email,
                'phone' => $this->vendor?->phone,
                'address' => $this->vendor?->address,
            ],
            'items' => $items->values()->all(),
            'shipments' => $shipments,
            'chat' => $chat,
            'total_po' => (float) $totalPo,
            'total_pagu' => $totalPagu,
            'locked_at' => $this->locked_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'can' => [
                'update' => (bool) $user?->can('update', $this->resource),
                'delete' => (bool) $user?->can('delete', $this->resource),
                'konfirmasi' => (bool) $user?->can('konfirmasi', $this->resource),
                'negosiasi' => (bool) $user?->can('negosiasi', $this->resource),
                'tawar' => (bool) $user?->can('tawar', $this->resource),
                'setujui' => (bool) $user?->can('setujui', $this->resource),
                'batalkan' => (bool) $user?->can('batalkan', $this->resource),
                'kirim' => (bool) $user?->can('kirim', $this->resource),
            ],
        ];
    }
}
