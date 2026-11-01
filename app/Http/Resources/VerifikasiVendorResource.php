<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One vendor registration together with all of its legal documents (UC-21).
 *
 * @mixin Vendor
 */
class VerifikasiVendorResource extends JsonResource
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
            'contact_email' => $this->contact_email,
            'phone' => $this->phone,
            'address' => $this->address,
            'kategori_komoditas' => $this->kategori_komoditas,
            'verification_status' => $this->verification_status->value,
            'created_at' => $this->created_at?->toISOString(),
            'documents' => DokumenLegalitasVendorResource::collection($this->whenLoaded('legalDocuments')),
        ];
    }
}
