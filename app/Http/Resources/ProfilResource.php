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
        $roleName = $this->role?->name;

        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_url' => $this->avatar_url,
            'role' => match ($roleName) {
                'purchasing' => 'Purchasing / Finance',
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
