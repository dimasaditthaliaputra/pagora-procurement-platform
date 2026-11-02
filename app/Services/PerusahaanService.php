<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\NpwpHelper;
use App\Models\PerusahaanBuyer;
use App\Models\User;
use App\Models\Vendor;
use App\Repositories\Contracts\PerusahaanBuyerRepositoryInterface;
use App\Repositories\Contracts\VendorRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class PerusahaanService
{
    public function __construct(
        protected readonly PerusahaanBuyerRepositoryInterface $perusahaanBuyerRepository,
        protected readonly VendorRepositoryInterface $vendorRepository
    ) {}

    /**
     * Retrieve the company profile payload for the authenticated Owner or Vendor.
     *
     * @return array<string, mixed>|null
     */
    public function getPerusahaanForUser(User $user): ?array
    {
        if ($user->isOwner() && $user->tenant_id !== null) {
            /** @var PerusahaanBuyer|null $tenant */
            $tenant = $user->perusahaanBuyer;

            if (! $tenant) {
                return null;
            }

            Gate::forUser($user)->authorize('view', $tenant);

            $tenant->loadMissing(['verifiedByUser']);

            return [
                'tipe' => 'tenant',
                'uuid' => $tenant->uuid,
                'company_name' => $tenant->company_name,
                'tax_number' => $tenant->tax_number,
                'address' => $tenant->address,
                'verification_status' => $tenant->verification_status?->value ?? (string) $tenant->verification_status,
                'verification_note' => $tenant->verification_note,
                'has_legal_document' => filled($tenant->legal_document_path),
                'legal_document_url' => filled($tenant->legal_document_path)
                    ? route('master.tenant.verifikasi-dokumen.download', $tenant->uuid)
                    : null,
                'verified_at' => $tenant->verified_at?->toISOString(),
                'verified_by' => $tenant->verifiedByUser?->name,
                'created_at' => $tenant->created_at?->toISOString(),
            ];
        }

        if ($user->isVendor() && $user->vendor_id !== null) {
            /** @var Vendor|null $vendor */
            $vendor = $user->vendor;

            if (! $vendor) {
                return null;
            }

            Gate::forUser($user)->authorize('view', $vendor);

            $vendor->loadMissing(['legalDocuments']);

            return [
                'tipe' => 'vendor',
                'uuid' => $vendor->uuid,
                'company_name' => $vendor->company_name,
                'slug' => $vendor->slug,
                'tax_number' => $vendor->tax_number,
                'contact_email' => $vendor->contact_email,
                'phone' => $vendor->phone,
                'address' => $vendor->address,
                'kategori_komoditas' => $vendor->kategori_komoditas,
                'verification_status' => $vendor->verification_status?->value ?? (string) $vendor->verification_status,
                'is_active' => (bool) $vendor->is_active,
                'created_at' => $vendor->created_at?->toISOString(),
                'documents' => $vendor->legalDocuments->map(fn ($doc) => [
                    'uuid' => $doc->uuid,
                    'document_type' => $doc->document_type,
                    'document_number' => $doc->document_number,
                    'verification_status' => $doc->verification_status?->value ?? (string) $doc->verification_status,
                    'file_url' => filled($doc->file_path) ? route('master.vendor.verifikasi-dokumen.download', $doc->uuid) : null,
                ])->all(),
            ];
        }

        return null;
    }

    /**
     * Update the company profile for the authenticated Owner or Vendor.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws AuthorizationException
     */
    public function updateForUser(User $user, array $data): array
    {
        if ($user->isOwner() && $user->tenant_id !== null) {
            /** @var PerusahaanBuyer|null $tenant */
            $tenant = $user->perusahaanBuyer;

            if (! $tenant) {
                throw new AuthorizationException('Data perusahaan tenant tidak ditemukan.');
            }

            Gate::forUser($user)->authorize('update', $tenant);

            $normalizedTaxNumber = NpwpHelper::normalize((string) $data['tax_number']);

            $this->perusahaanBuyerRepository->update($tenant, [
                'company_name' => trim((string) $data['company_name']),
                'tax_number' => $normalizedTaxNumber,
                'address' => trim((string) $data['address']),
            ]);

            return $this->getPerusahaanForUser($user->fresh()) ?? [];
        }

        if ($user->isVendor() && $user->vendor_id !== null) {
            /** @var Vendor|null $vendor */
            $vendor = $user->vendor;

            if (! $vendor) {
                throw new AuthorizationException('Data perusahaan vendor tidak ditemukan.');
            }

            Gate::forUser($user)->authorize('update', $vendor);

            $normalizedTaxNumber = NpwpHelper::normalize((string) $data['tax_number']);
            $newCompanyName = trim((string) $data['company_name']);

            $updatePayload = [
                'company_name' => $newCompanyName,
                'tax_number' => $normalizedTaxNumber,
                'contact_email' => strtolower(trim((string) $data['contact_email'])),
                'phone' => isset($data['phone']) && trim((string) $data['phone']) !== ''
                    ? trim((string) $data['phone'])
                    : null,
                'address' => trim((string) $data['address']),
            ];

            if ($vendor->company_name !== $newCompanyName) {
                $baseSlug = Str::slug($newCompanyName);
                $updatePayload['slug'] = $baseSlug.'-'.Str::lower(Str::random(6));
            }

            $this->vendorRepository->update($vendor, $updatePayload);

            return $this->getPerusahaanForUser($user->fresh()) ?? [];
        }

        throw new AuthorizationException('Akun Anda tidak memiliki izin untuk memperbarui data perusahaan.');
    }
}
