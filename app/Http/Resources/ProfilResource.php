<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class ProfilResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $roleName = $this->role?->nama_role;

        return [
            'uuid' => $this->uuid,
            'nama_lengkap' => $this->nama_lengkap,
            'email' => $this->email,
            'avatar_url' => $this->avatar_url,
            'role' => match ($roleName) {
                'buyer' => 'Buyer',
                'qc_gudang' => 'QC Gudang',
                'superadmin' => 'Superadmin',
                'manajemen' => 'Manajemen',
                'owner' => 'Owner',
                'vendor' => 'Vendor',
                default => ucfirst((string) ($roleName ?? '-')),
            },
            'organisasi' => $this->vendor?->nama_perusahaan ?? $this->perusahaanBuyer?->nama_perusahaan,
            'punya_password' => $this->password !== null,
            'terhubung_google' => $this->google_id !== null,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
