<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PerusahaanBuyer;
use App\Services\RegistrationSecurityGuardService;
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

        $duplicateWarnings = app(RegistrationSecurityGuardService::class)->detectTenantDuplicateDocuments($this->resource);

        return [
            'uuid' => $this->uuid,
            'company_name' => $this->company_name,
            'tax_number' => $this->tax_number,
            'phone' => $this->phone,
            'address' => $this->address,
            'verification_status' => $this->verification_status->value,
            'verification_note' => $this->verification_note,
            'has_duplicate_documents' => count($duplicateWarnings) > 0,
            'duplicate_warnings' => $duplicateWarnings,
            'has_legal_document' => filled($this->legal_document_path) || ($this->relationLoaded('legalDocuments') && $this->legalDocuments->isNotEmpty()),
            'legal_document_url' => filled($this->legal_document_path)
                ? route('master.tenant.verifikasi-dokumen.download', $this->uuid)
                : null,
            'legal_documents' => $this->relationLoaded('legalDocuments')
                ? $this->legalDocuments->map(fn ($doc) => [
                    'uuid' => $doc->uuid,
                    'document_type' => $doc->document_type,
                    'document_number' => $doc->document_number,
                    'verification_status' => $doc->verification_status->value,
                    'file_url' => route('master.tenant.verifikasi-dokumen.download', $doc->uuid),
                ])->values()->all()
                : [],
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
