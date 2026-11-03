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

        return [
            'uuid' => $this->uuid,
            'nomor_po' => $this->nomor_po,
            'tanggal_po' => $this->tanggal_po?->format('Y-m-d') ?? (string) $this->tanggal_po,
            'catatan' => $this->catatan,
            'status' => $statusValue,
            'status_label' => $statusLabel,
            'is_editable' => $statusValue === 'draft',
            'harga_pagu_acuan' => $isVendorUser ? null : (float) ($this->harga_pagu_acuan ?? $totalPagu),
            'dibuat_oleh' => $this->pembuat?->name ?? 'User',
            'vendor' => [
                'uuid' => $this->vendor?->uuid,
                'nama_perusahaan' => $this->vendor?->company_name ?? 'Vendor',
                'contact_email' => $this->vendor?->contact_email,
                'phone' => $this->vendor?->phone,
                'address' => $this->vendor?->address,
            ],
            'items' => $items->values()->all(),
            'total_po' => (float) $totalPo,
            'total_pagu' => $totalPagu,
            'locked_at' => $this->locked_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'can' => [
                'update' => (bool) $user?->can('update', $this->resource),
                'delete' => (bool) $user?->can('delete', $this->resource),
                'konfirmasi' => (bool) $user?->can('konfirmasi', $this->resource),
            ],
        ];
    }
}
