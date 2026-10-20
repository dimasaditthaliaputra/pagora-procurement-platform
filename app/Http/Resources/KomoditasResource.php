<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Komoditas;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Komoditas
 */
class KomoditasResource extends JsonResource
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
            'slug' => $this->whenNotNull($this->slug),
            'nama_komoditas' => $this->nama_komoditas,
            'sku' => $this->sku,
            'kategori' => $this->kategori,
            'satuan' => $this->satuan,
            'kode_referensi_api' => $this->kode_referensi_api,
            'spesifikasi' => $this->spesifikasi,
            'status_aktif' => (bool) $this->status_aktif,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
