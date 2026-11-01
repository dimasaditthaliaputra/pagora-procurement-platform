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
            'company_name' => $this->company_name,
            'contact_email' => $this->contact_email,
            'phone' => $this->phone,
            'address' => $this->address,
            'kategori_komoditas' => $this->kategori_komoditas,
            'verification_status' => $this->verification_status->value,
            'is_active' => (bool) $this->is_active,
            'block_reason' => $this->block_reason,
            'blocked_at' => $this->blocked_at?->toISOString(),
            'blocked_by_user' => $this->blockedByUser ? [
                'uuid' => $this->blockedByUser->uuid,
                'name' => $this->blockedByUser->name,
                'email' => $this->blockedByUser->email,
            ] : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
