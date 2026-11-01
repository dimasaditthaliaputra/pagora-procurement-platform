<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\VerificationStatus;
use App\Mail\VendorApprovalMail;
use App\Models\DokumenLegalitasVendor;
use App\Models\User;
use App\Models\Vendor;
use App\Repositories\Contracts\DokumenLegalitasVendorRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class VerifikasiDokumenService
{
    public function __construct(
        protected DokumenLegalitasVendorRepositoryInterface $repository
    ) {}

    /**
     * Get paginated vendors with all of their legal documents.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(
        array $filters,
        int $perPage = 10
    ): LengthAwarePaginator {
        return $this->repository->paginateVendors($filters, $perPage);
    }

    /**
     * Find a legal document by UUID.
     */
    public function findByUuid(string $uuid): DokumenLegalitasVendor
    {
        $document = $this->repository->findByUuid($uuid);

        if ($document === null) {
            throw (new ModelNotFoundException(
                'Dokumen legalitas tidak ditemukan.'
            ))->setModel(DokumenLegalitasVendor::class, [$uuid]);
        }

        return $document;
    }

    /**
     * Find a legal document the actor is allowed to open.
     *
     * @throws ModelNotFoundException
     * @throws AuthorizationException
     */
    public function findViewableByUuid(User $actor, string $uuid): DokumenLegalitasVendor
    {
        $document = $this->findByUuid($uuid);
        Gate::forUser($actor)->authorize('view', $document);

        return $document;
    }

    /**
     * Approve or reject a vendor legal document.
     */
    public function verify(
        string $uuid,
        string $action,
        User $verifier,
        ?string $rejectionReason = null
    ): DokumenLegalitasVendor {
        if (! in_array($action, ['approve', 'reject'], true)) {
            throw ValidationException::withMessages([
                'aksi' => 'Aksi verifikasi tidak valid.',
            ]);
        }

        if (
            $action === 'reject'
            && trim((string) $rejectionReason) === ''
        ) {
            throw ValidationException::withMessages([
                'rejection_reason' => 'Alasan penolakan wajib diisi.',
            ]);
        }

        [$document, $allApproved] = DB::transaction(function () use (
            $uuid,
            $action,
            $verifier,
            $rejectionReason
        ): array {
            $document = $this->repository->findByUuid($uuid);

            if ($document === null) {
                throw (new ModelNotFoundException(
                    'Dokumen legalitas tidak ditemukan.'
                ))->setModel(DokumenLegalitasVendor::class, [$uuid]);
            }

            Gate::forUser($verifier)->authorize('verify', $document);

            if ($document->verification_status !== VerificationStatus::Pending) {
                throw ValidationException::withMessages([
                    'verification_status' => 'Dokumen ini sudah diverifikasi dan tidak dapat diproses ulang.',
                ]);
            }

            $vendor = $document->vendor;

            if ($vendor === null) {
                throw (new ModelNotFoundException(
                    'Data vendor tidak ditemukan.'
                ))->setModel('Vendor');
            }

            $approved = $action === 'approve';

            $this->repository->update($document, [
                'verification_status' => $approved
                    ? VerificationStatus::Approved
                    : VerificationStatus::Rejected,
                'verified_by' => $verifier->id,
                'verified_at' => now(),
                'rejection_reason' => $approved
                    ? null
                    : trim((string) $rejectionReason),
            ]);

            $allApproved = $this->syncVendorStatus($vendor);

            return [$document->fresh(['vendor']), $allApproved];
        });

        if ($allApproved) {
            $vendor = $document->vendor;

            if ($vendor !== null && filled($vendor->contact_email)) {
                Mail::to($vendor->contact_email)->send(
                    new VendorApprovalMail(
                        vendorName: $vendor->company_name,
                        companyName: $vendor->company_name,
                    )
                );
            }
        }

        return $document;
    }

    /**
     * Derive the vendor status from all of its documents: it stays pending while any document
     * is unprocessed, becomes rejected if any is rejected, and approved only when all are approved.
     *
     * @return bool true when every document is approved (the vendor can be notified)
     */
    private function syncVendorStatus(Vendor $vendor): bool
    {
        $statuses = $this->repository->getByVendorId($vendor->getKey())
            ->map(fn (DokumenLegalitasVendor $document): VerificationStatus => $document->verification_status);

        if ($statuses->contains(VerificationStatus::Pending)) {
            $vendor->verification_status = VerificationStatus::Pending;
        } elseif ($statuses->contains(VerificationStatus::Rejected)) {
            $vendor->verification_status = VerificationStatus::Rejected;
        } else {
            $vendor->verification_status = VerificationStatus::Approved;
        }

        $vendor->save();

        return $vendor->verification_status === VerificationStatus::Approved;
    }
}
