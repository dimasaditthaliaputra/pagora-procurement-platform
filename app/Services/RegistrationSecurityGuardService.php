<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\VerificationStatus;
use App\Helpers\NpwpHelper;
use App\Models\PerusahaanBuyer;
use App\Models\Vendor;
use Illuminate\Validation\ValidationException;

/**
 * Service to guard onboarding registrations against:
 * 1. Blacklisted / suspended identity fraud (Pre-Registration Guard across all legal documents).
 * 2. Duplicate / spam pending registrations for the exact same company entity.
 * 3. Cross-entity duplication detection for verification administrators (all legal documents).
 */
class RegistrationSecurityGuardService
{
    /**
     * Pre-registration guard to check blacklist and pending duplicates across all legal documents.
     * Throws ValidationException if a violation is detected.
     *
     * @param  'vendor'|'tenant'  $entityType
     *
     * @throws ValidationException
     */
    public function validatePreRegistration(
        string $entityType,
        string $companyName,
        ?string $taxNumber = null,
        ?string $nib = null,
        ?string $ktp = null,
        ?string $sppkp = null,
        ?string $akta = null,
        ?string $skKemenkumham = null
    ): void {
        $normalizedNpwp = filled($taxNumber) ? NpwpHelper::normalize((string) $taxNumber) : null;
        $cleanNib = filled($nib) ? trim((string) $nib) : null;
        $cleanKtp = filled($ktp) ? trim((string) $ktp) : null;
        $cleanSppkp = filled($sppkp) ? trim((string) $sppkp) : null;
        $cleanAkta = filled($akta) ? trim((string) $akta) : null;
        $cleanSk = filled($skKemenkumham) ? trim((string) $skKemenkumham) : null;
        $cleanCompanyName = trim($companyName);

        // 1. Blacklist / Suspended Guard for all documents
        $this->checkBlacklist(
            taxNumber: $normalizedNpwp,
            nib: $cleanNib,
            ktp: $cleanKtp,
            targetFieldPrefix: $entityType,
            sppkp: $cleanSppkp,
            akta: $cleanAkta,
            skKemenkumham: $cleanSk
        );

        // 2. Pending Duplicate / Spam Guard
        $this->checkPendingSpam(
            entityType: $entityType,
            companyName: $cleanCompanyName,
            taxNumber: $normalizedNpwp,
            nib: $cleanNib,
            sppkp: $cleanSppkp,
            akta: $cleanAkta,
            skKemenkumham: $cleanSk
        );
    }

    /**
     * Ensure documents do not belong to blacklisted or suspended entities.
     *
     * @throws ValidationException
     */
    public function checkBlacklist(
        ?string $taxNumber,
        ?string $nib,
        ?string $ktp,
        string $targetFieldPrefix = 'vendor',
        ?string $sppkp = null,
        ?string $akta = null,
        ?string $skKemenkumham = null
    ): void {
        $npwpField = $targetFieldPrefix === 'vendor' ? 'tax_number' : 'nomor_npwp';

        // Check NPWP
        if (filled($taxNumber)) {
            $this->checkDocumentBlacklist('npwp', $taxNumber, $npwpField);
        }

        // Check NIB
        if (filled($nib)) {
            $this->checkDocumentBlacklist('nib', $nib, 'nomor_nib');
        }

        // Check KTP / NIK Penanggung Jawab
        if (filled($ktp)) {
            $this->checkDocumentBlacklist('ktp', $ktp, 'nomor_ktp');
        }

        // Check SPPKP
        if (filled($sppkp)) {
            $this->checkDocumentBlacklist('sppkp', $sppkp, 'nomor_sppkp');
        }

        // Check Akta Pendirian
        if (filled($akta)) {
            $this->checkDocumentBlacklist('akta', $akta, 'nomor_akta');
        }

        // Check SK Kemenkumham
        if (filled($skKemenkumham)) {
            $this->checkDocumentBlacklist('sk_kemenkumham', $skKemenkumham, 'nomor_sk_kemenkumham');
        }
    }

    /**
     * Check if a specific document number is associated with any suspended or blocked entity.
     *
     * @throws ValidationException
     */
    public function checkDocumentBlacklist(string $canonicalType, ?string $documentNumber, string $errorField): void
    {
        if (! filled($documentNumber)) {
            return;
        }

        $docTypes = $this->getMatchingDocumentTypes($canonicalType);

        // 1. Check in suspended/blacklisted vendors (is_active = false or blocked_at is not null)
        $suspendedVendor = Vendor::query()
            ->where(function ($q): void {
                $q->where('is_active', false)
                    ->orWhereNotNull('blocked_at');
            })
            ->where(function ($q) use ($canonicalType, $documentNumber, $docTypes): void {
                if ($canonicalType === 'npwp') {
                    $q->where('tax_number', $documentNumber)
                        ->orWhereHas('legalDocuments', function ($dq) use ($docTypes, $documentNumber): void {
                            $dq->whereIn('document_type', $docTypes)
                                ->where('document_number', $documentNumber);
                        });
                } else {
                    $q->whereHas('legalDocuments', function ($dq) use ($docTypes, $documentNumber): void {
                        $dq->whereIn('document_type', $docTypes)
                            ->where('document_number', $documentNumber);
                    });
                }
            })
            ->exists();

        if ($suspendedVendor) {
            $this->throwBlacklistException($errorField);
        }

        // 2. Check in suspended/blacklisted tenants (is_active = false)
        $suspendedTenant = PerusahaanBuyer::query()
            ->where('is_active', false)
            ->where(function ($q) use ($canonicalType, $documentNumber, $docTypes): void {
                if ($canonicalType === 'npwp') {
                    $q->where('tax_number', $documentNumber)
                        ->orWhereHas('legalDocuments', function ($dq) use ($docTypes, $documentNumber): void {
                            $dq->whereIn('document_type', $docTypes)
                                ->where('document_number', $documentNumber);
                        });
                } else {
                    $q->whereHas('legalDocuments', function ($dq) use ($docTypes, $documentNumber): void {
                        $dq->whereIn('document_type', $docTypes)
                            ->where('document_number', $documentNumber);
                    });
                }
            })
            ->exists();

        if ($suspendedTenant) {
            $this->throwBlacklistException($errorField);
        }
    }

    /**
     * Prevent double submit / spam when an identical company name and legal document is still pending.
     *
     * @throws ValidationException
     */
    public function checkPendingSpam(
        string $entityType,
        string $companyName,
        ?string $taxNumber = null,
        ?string $nib = null,
        ?string $sppkp = null,
        ?string $akta = null,
        ?string $skKemenkumham = null
    ): void {
        $hasDocs = filled($taxNumber) || filled($nib) || filled($sppkp) || filled($akta) || filled($skKemenkumham);
        if (! filled($companyName) || ! $hasDocs) {
            return;
        }

        $loweredName = strtolower(trim($companyName));

        if ($entityType === 'vendor') {
            $pendingExists = Vendor::query()
                ->where('verification_status', VerificationStatus::Pending)
                ->whereRaw('LOWER(TRIM(company_name)) = ?', [$loweredName])
                ->where(function ($q) use ($taxNumber, $nib, $sppkp, $akta, $skKemenkumham): void {
                    if (filled($taxNumber)) {
                        $q->where('tax_number', $taxNumber)
                            ->orWhereHas('legalDocuments', function ($dq) use ($taxNumber): void {
                                $dq->where('document_type', 'npwp')
                                    ->where('document_number', $taxNumber);
                            });
                    }
                    if (filled($nib)) {
                        $q->orWhereHas('legalDocuments', function ($dq) use ($nib): void {
                            $dq->where('document_type', 'nib')
                                ->where('document_number', $nib);
                        });
                    }
                    if (filled($sppkp)) {
                        $q->orWhereHas('legalDocuments', function ($dq) use ($sppkp): void {
                            $dq->whereIn('document_type', ['sppkp', 'dokumen_sppkp'])
                                ->where('document_number', $sppkp);
                        });
                    }
                    if (filled($akta)) {
                        $q->orWhereHas('legalDocuments', function ($dq) use ($akta): void {
                            $dq->whereIn('document_type', ['akta', 'akta_pendirian'])
                                ->where('document_number', $akta);
                        });
                    }
                    if (filled($skKemenkumham)) {
                        $q->orWhereHas('legalDocuments', function ($dq) use ($skKemenkumham): void {
                            $dq->where('document_type', 'sk_kemenkumham')
                                ->where('document_number', $skKemenkumham);
                        });
                    }
                })
                ->exists();

            if ($pendingExists) {
                throw ValidationException::withMessages([
                    'company_name' => 'Pendaftaran untuk entitas perusahaan ini dengan nomor dokumen legalitas terkait saat ini sedang dalam proses verifikasi oleh tim administrator. Mohon menunggu hasil verifikasi atau periksa email bisnis Anda.',
                ]);
            }
        } else {
            $pendingExists = PerusahaanBuyer::query()
                ->where('verification_status', VerificationStatus::Pending)
                ->whereRaw('LOWER(TRIM(company_name)) = ?', [$loweredName])
                ->where(function ($q) use ($taxNumber, $nib, $sppkp, $akta, $skKemenkumham): void {
                    if (filled($taxNumber)) {
                        $q->where('tax_number', $taxNumber)
                            ->orWhereHas('legalDocuments', function ($dq) use ($taxNumber): void {
                                $dq->where('document_type', 'npwp')
                                    ->where('document_number', $taxNumber);
                            });
                    }
                    if (filled($nib)) {
                        $q->orWhereHas('legalDocuments', function ($dq) use ($nib): void {
                            $dq->where('document_type', 'nib')
                                ->where('document_number', $nib);
                        });
                    }
                    if (filled($sppkp)) {
                        $q->orWhereHas('legalDocuments', function ($dq) use ($sppkp): void {
                            $dq->whereIn('document_type', ['sppkp', 'dokumen_sppkp'])
                                ->where('document_number', $sppkp);
                        });
                    }
                    if (filled($akta)) {
                        $q->orWhereHas('legalDocuments', function ($dq) use ($akta): void {
                            $dq->whereIn('document_type', ['akta', 'akta_pendirian'])
                                ->where('document_number', $akta);
                        });
                    }
                    if (filled($skKemenkumham)) {
                        $q->orWhereHas('legalDocuments', function ($dq) use ($skKemenkumham): void {
                            $dq->where('document_type', 'sk_kemenkumham')
                                ->where('document_number', $skKemenkumham);
                        });
                    }
                })
                ->exists();

            if ($pendingExists) {
                throw ValidationException::withMessages([
                    'nama_perusahaan' => 'Pendaftaran untuk entitas perusahaan ini dengan nomor dokumen legalitas terkait saat ini sedang dalam proses verifikasi oleh tim administrator. Mohon menunggu hasil verifikasi atau periksa email bisnis Anda.',
                ]);
            }
        }
    }

    /**
     * Detect duplicate legal documents for a vendor compared to other active/pending vendors or tenants.
     * Checks all legal document types: NPWP, NIB, KTP, SPPKP, Akta Pendirian, SK Kemenkumham, SIUP.
     *
     * @return list<array{document_type: string, document_label: string, document_number: string, matches: list<array{entity_type: string, company_name: string, verification_status: string, is_active: bool, registered_at: ?string}>}>
     */
    public function detectVendorDuplicateDocuments(Vendor $vendor): array
    {
        $warnings = [];

        // 1. Check NPWP from vendor tax_number column
        if (filled($vendor->tax_number)) {
            $npwp = NpwpHelper::normalize((string) $vendor->tax_number);
            $matches = $this->findDocumentMatches('npwp', $npwp, 'vendor', (int) $vendor->id);
            if (count($matches) > 0) {
                $warnings[] = [
                    'document_type' => 'npwp',
                    'document_label' => $this->getDocumentLabel('npwp'),
                    'document_number' => (string) $vendor->tax_number,
                    'matches' => $matches,
                ];
            }
        }

        // 2. Check all attached legal documents
        $documents = $vendor->relationLoaded('legalDocuments')
            ? $vendor->legalDocuments
            : $vendor->legalDocuments()->get();

        foreach ($documents as $doc) {
            $rawType = (string) $doc->document_type;
            $number = trim((string) ($doc->document_number ?? ''));

            if (! filled($number)) {
                continue;
            }

            $canonicalType = $this->resolveCanonicalType($rawType);
            $label = $this->getDocumentLabel($canonicalType);

            // Prevent duplicate warning report if already added
            $alreadyAdded = collect($warnings)->contains(
                fn ($w) => $w['document_type'] === $canonicalType && $w['document_number'] === $number
            );
            if ($alreadyAdded) {
                continue;
            }

            $matches = $this->findDocumentMatches($canonicalType, $number, 'vendor', (int) $vendor->id);
            if (count($matches) > 0) {
                $warnings[] = [
                    'document_type' => $canonicalType,
                    'document_label' => $label,
                    'document_number' => $number,
                    'matches' => $matches,
                ];
            }
        }

        return $warnings;
    }

    /**
     * Detect duplicate legal documents for a tenant compared to other active/pending tenants or vendors.
     * Checks all legal document types: NPWP, NIB, KTP, SPPKP, Akta Pendirian, SK Kemenkumham, SIUP.
     *
     * @return list<array{document_type: string, document_label: string, document_number: string, matches: list<array{entity_type: string, company_name: string, verification_status: string, is_active: bool, registered_at: ?string}>}>
     */
    public function detectTenantDuplicateDocuments(PerusahaanBuyer $tenant): array
    {
        $warnings = [];

        // 1. Check NPWP from tenant tax_number column
        if (filled($tenant->tax_number)) {
            $npwp = NpwpHelper::normalize((string) $tenant->tax_number);
            $matches = $this->findDocumentMatches('npwp', $npwp, 'tenant', (int) $tenant->id);
            if (count($matches) > 0) {
                $warnings[] = [
                    'document_type' => 'npwp',
                    'document_label' => $this->getDocumentLabel('npwp'),
                    'document_number' => (string) $tenant->tax_number,
                    'matches' => $matches,
                ];
            }
        }

        // 2. Check all attached legal documents
        $documents = $tenant->relationLoaded('legalDocuments')
            ? $tenant->legalDocuments
            : $tenant->legalDocuments()->get();

        foreach ($documents as $doc) {
            $rawType = (string) $doc->document_type;
            $number = trim((string) ($doc->document_number ?? ''));

            if (! filled($number)) {
                continue;
            }

            $canonicalType = $this->resolveCanonicalType($rawType);
            $label = $this->getDocumentLabel($canonicalType);

            // Prevent duplicate warning report if already added
            $alreadyAdded = collect($warnings)->contains(
                fn ($w) => $w['document_type'] === $canonicalType && $w['document_number'] === $number
            );
            if ($alreadyAdded) {
                continue;
            }

            $matches = $this->findDocumentMatches($canonicalType, $number, 'tenant', (int) $tenant->id);
            if (count($matches) > 0) {
                $warnings[] = [
                    'document_type' => $canonicalType,
                    'document_label' => $label,
                    'document_number' => $number,
                    'matches' => $matches,
                ];
            }
        }

        return $warnings;
    }

    /**
     * Find matching registered entities (other than the current one) sharing the document number.
     *
     * @return list<array{entity_type: string, company_name: string, verification_status: string, is_active: bool, registered_at: ?string}>
     */
    private function findDocumentMatches(
        string $documentType,
        string $documentNumber,
        string $currentEntityType,
        int $currentEntityId
    ): array {
        $matches = [];
        $matchingTypes = $this->getMatchingDocumentTypes($documentType);

        // 1. Check in Vendors
        $vendorQuery = Vendor::query()
            ->where(function ($q) use ($documentType, $documentNumber, $matchingTypes): void {
                if ($documentType === 'npwp') {
                    $q->where('tax_number', $documentNumber)
                        ->orWhereHas('legalDocuments', function ($dq) use ($matchingTypes, $documentNumber): void {
                            $dq->whereIn('document_type', $matchingTypes)
                                ->where('document_number', $documentNumber);
                        });
                } else {
                    $q->whereHas('legalDocuments', function ($dq) use ($matchingTypes, $documentNumber): void {
                        $dq->whereIn('document_type', $matchingTypes)
                            ->where('document_number', $documentNumber);
                    });
                }
            });

        if ($currentEntityType === 'vendor') {
            $vendorQuery->where('id', '!=', $currentEntityId);
        }

        $otherVendors = $vendorQuery->get();
        foreach ($otherVendors as $v) {
            $matches[] = [
                'entity_type' => 'vendor',
                'company_name' => $v->company_name,
                'verification_status' => $v->verification_status instanceof VerificationStatus
                    ? $v->verification_status->value
                    : (string) $v->verification_status,
                'is_active' => (bool) $v->is_active,
                'registered_at' => $v->created_at?->toISOString(),
            ];
        }

        // 2. Check in Tenants
        $tenantQuery = PerusahaanBuyer::query()
            ->where(function ($q) use ($documentType, $documentNumber, $matchingTypes): void {
                if ($documentType === 'npwp') {
                    $q->where('tax_number', $documentNumber)
                        ->orWhereHas('legalDocuments', function ($dq) use ($matchingTypes, $documentNumber): void {
                            $dq->whereIn('document_type', $matchingTypes)
                                ->where('document_number', $documentNumber);
                        });
                } else {
                    $q->whereHas('legalDocuments', function ($dq) use ($matchingTypes, $documentNumber): void {
                        $dq->whereIn('document_type', $matchingTypes)
                            ->where('document_number', $documentNumber);
                    });
                }
            });

        if ($currentEntityType === 'tenant') {
            $tenantQuery->where('id', '!=', $currentEntityId);
        }

        $otherTenants = $tenantQuery->get();
        foreach ($otherTenants as $t) {
            $matches[] = [
                'entity_type' => 'tenant',
                'company_name' => $t->company_name,
                'verification_status' => $t->verification_status instanceof VerificationStatus
                    ? $t->verification_status->value
                    : (string) $t->verification_status,
                'is_active' => (bool) $t->is_active,
                'registered_at' => $t->created_at?->toISOString(),
            ];
        }

        return $matches;
    }

    /**
     * Map canonical type to possible document_type column values in the database.
     *
     * @return list<string>
     */
    public function getMatchingDocumentTypes(string $canonicalType): array
    {
        return match ($canonicalType) {
            'ktp' => ['ktp', 'ktp_penanggung_jawab'],
            'sppkp' => ['sppkp', 'dokumen_sppkp'],
            'akta' => ['akta', 'akta_pendirian'],
            'sk_kemenkumham' => ['sk_kemenkumham'],
            'nib' => ['nib'],
            'siup' => ['siup'],
            'npwp' => ['npwp'],
            default => [$canonicalType],
        };
    }

    /**
     * Resolve any raw document type string to its canonical document category.
     */
    public function resolveCanonicalType(string $type): string
    {
        $lower = strtolower(trim($type));
        if (str_starts_with($lower, 'ktp')) {
            return 'ktp';
        }
        if (str_contains($lower, 'akta')) {
            return 'akta';
        }
        if (str_contains($lower, 'sppkp')) {
            return 'sppkp';
        }
        if (str_contains($lower, 'kemenkumham')) {
            return 'sk_kemenkumham';
        }
        if ($lower === 'nib') {
            return 'nib';
        }
        if ($lower === 'siup') {
            return 'siup';
        }
        if ($lower === 'npwp') {
            return 'npwp';
        }

        return $lower;
    }

    /**
     * Return human-readable Indonesian label for a document type.
     */
    public function getDocumentLabel(string $canonicalType): string
    {
        return match ($canonicalType) {
            'npwp' => 'NPWP Perusahaan',
            'nib' => 'NIB (Nomor Induk Berusaha)',
            'ktp' => 'KTP / NIK Penanggung Jawab',
            'sppkp' => 'SPPKP (Surat Pengukuhan Pengusaha Kena Pajak)',
            'akta' => 'Akta Pendirian Perusahaan',
            'sk_kemenkumham' => 'SK Pengesahan Kemenkumham',
            'siup' => 'SIUP (Surat Izin Usaha Perdagangan)',
            default => strtoupper($canonicalType),
        };
    }

    /**
     * Throw standardized validation error for blacklisted/suspended document.
     *
     * @throws ValidationException
     */
    private function throwBlacklistException(string $field): void
    {
        throw ValidationException::withMessages([
            $field => 'Nomor identitas atau dokumen legalitas ini tidak dapat digunakan untuk pendaftaran karena terindikasi memiliki riwayat penangguhan/pemblokiran akun dalam sistem. Silakan hubungi tim administrator PAGORA.',
        ]);
    }
}
