<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PerusahaanBuyer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PerusahaanBuyer
 */
class VerifikasiTenantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $owner = $this->relationLoaded('users')
            ? ($this->users->first(fn ($u) => $u->role?->name === 'owner') ?? $this->users->first())
            : null;

        return [
            'uuid' => $this->uuid,
            'company_name' => $this->company_name,
            'tax_number' => $this->tax_number,
            'address' => $this->address,
            'verification_status' => $this->verification_status->value,
            'verification_note' => $this->verification_note,
            'has_legal_document' => filled($this->legal_document_path),
            'legal_document_url' => filled($this->legal_document_path)
                ? route('master.tenant.verifikasi-dokumen.download', $this->uuid)
                : null,
            'verified_at' => $this->verified_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'owner' => $owner ? [
                'name' => $owner->name,
                'email' => $owner->email,
            ] : null,
            'verifier' => $this->relationLoaded('verifiedByUser') && $this->verifiedByUser ? [
                'name' => $this->verifiedByUser->name,
                'email' => $this->verifiedByUser->email,
            ] : null,
        ];
    }
}
