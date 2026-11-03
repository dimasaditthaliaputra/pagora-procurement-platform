<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PerusahaanBuyer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PerusahaanBuyer
 */
class CalonMitraResource extends JsonResource
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
            'company_name' => $this->company_name,
            'tax_number' => $this->tax_number,
            'address' => $this->address,
            'verification_status' => $this->verification_status->value,
            'is_active' => (bool) $this->is_active,
            'verified_at' => $this->verified_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
