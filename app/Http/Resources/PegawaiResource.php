<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class PegawaiResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $roleName = $this->role?->nama_role;
        $perusahaan = null;

        if ($this->perusahaanBuyer !== null) {
            $perusahaan = [
                'uuid' => $this->perusahaanBuyer->uuid,
                'nama' => $this->perusahaanBuyer->nama_perusahaan,
                'tipe' => 'buyer',
            ];
        } elseif ($this->vendor !== null) {
            $perusahaan = [
                'uuid' => $this->vendor->uuid,
                'nama' => $this->vendor->nama_perusahaan,
                'tipe' => 'vendor',
            ];
        }

        return [
            'uuid' => $this->uuid,
            'nama_lengkap' => $this->nama_lengkap,
            'email' => $this->email,
            'avatar' => $this->avatar_url,
            'role' => [
                'name' => $roleName,
                'label' => match ($roleName) {
                    'buyer' => 'Buyer',
                    'qc_gudang' => 'QC Gudang',
                    'superadmin' => 'Superadmin',
                    'manajemen' => 'Manajemen',
                    'owner' => 'Owner',
                    'vendor' => 'Vendor',
                    default => ucfirst((string) ($roleName ?? '-')),
                },
                'deskripsi' => $this->role?->deskripsi,
            ],
            'perusahaan' => $perusahaan,
            'status_aktif' => (bool) $this->status_aktif,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
