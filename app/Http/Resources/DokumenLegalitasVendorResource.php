<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DokumenLegalitasVendor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DokumenLegalitasVendor
 */
class DokumenLegalitasVendorResource extends JsonResource
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
            'document_type' => $this->document_type,
            'document_number' => $this->document_number,
            'verification_status' => $this->verification_status->value,
            'rejection_reason' => $this->rejection_reason,
            'verified_at' => $this->verified_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'file_url' => route(
                'master.vendor.verifikasi-dokumen.download',
                $this->uuid
            ),
            'vendor' => $this->relationLoaded('vendor') && $this->vendor ? [
                'uuid' => $this->vendor->uuid,
                'company_name' => $this->vendor->company_name,
                'contact_email' => $this->vendor->contact_email,
                'phone' => $this->vendor->phone,
                'kategori_komoditas' => $this->vendor->kategori_komoditas,
                'address' => $this->vendor->address,
                'verification_status' => $this->vendor->verification_status->value,
            ] : null,
            'verifier' => $this->relationLoaded('verifier') && $this->verifier ? [
                'uuid' => $this->verifier->uuid,
                'name' => $this->verifier->name,
                'email' => $this->verifier->email,
            ] : null,
        ];
    }
}
