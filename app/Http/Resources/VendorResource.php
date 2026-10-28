<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Vendor
 */
class VendorResource extends JsonResource
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
            'slug' => $this->slug,
            'nama_perusahaan' => $this->nama_perusahaan,
            'email_kontak' => $this->email_kontak,
            'no_telepon' => $this->no_telepon,
            'alamat' => $this->alamat,
            'kategori_komoditas' => $this->kategori_komoditas,
            'status_verifikasi' => $this->status_verifikasi,
            'status_aktif' => (bool) $this->status_aktif,
            'alasan_pemblokiran' => $this->alasan_pemblokiran,
            'diblokir_pada' => $this->diblokir_pada?->toISOString(),
            'blocked_by_user' => $this->blockedByUser ? [
                'uuid' => $this->blockedByUser->uuid,
                'nama_lengkap' => $this->blockedByUser->nama_lengkap,
                'email' => $this->blockedByUser->email,
            ] : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
