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
        $roleName = $this->role?->name;
        $perusahaan = null;

        if ($this->perusahaanBuyer !== null) {
            $perusahaan = [
                'uuid' => $this->perusahaanBuyer->uuid,
                'nama' => $this->perusahaanBuyer->company_name,
                'tipe' => 'buyer',
            ];
        } elseif ($this->vendor !== null) {
            $perusahaan = [
                'uuid' => $this->vendor->uuid,
                'nama' => $this->vendor->company_name,
                'tipe' => 'vendor',
            ];
        }

        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->avatar_url,
            'role' => [
                'name' => $roleName,
                'label' => $this->roleName()?->label() ?? ucfirst((string) ($roleName ?? '-')),
                'description' => $this->role?->description,
            ],
            'perusahaan' => $perusahaan,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'can' => [
                'update' => (bool) $request->user()?->can('update', $this->resource),
                'delete' => (bool) $request->user()?->can('delete', $this->resource),
            ],
        ];
    }
}
