<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\VerificationStatus;
use App\Mail\TenantApprovalMail;
use App\Mail\TenantRejectionMail;
use App\Models\PerusahaanBuyer;
use App\Models\User;
use App\Repositories\Contracts\PerusahaanBuyerRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class VerifikasiTenantService
{
    public function __construct(
        protected PerusahaanBuyerRepositoryInterface $repository
    ) {}

    /**
     * Get paginated tenant companies with search and verification filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return $this->repository->paginate($filters, $perPage);
    }

    /**
     * Get aggregate verification status counts.
     *
     * @return array{total: int, pending: int, approved: int, rejected: int}
     */
    public function getStatusCounts(): array
    {
        return $this->repository->getStatusCounts();
    }

    /**
     * Find a tenant company by UUID.
     */
    public function findByUuid(string $uuid): PerusahaanBuyer
    {
        $tenant = $this->repository->findByUuid($uuid);

        if ($tenant === null) {
            throw (new ModelNotFoundException('Data tenant tidak ditemukan.'))->setModel(PerusahaanBuyer::class, [$uuid]);
        }

        return $tenant;
    }

    /**
     * Find a tenant company the actor is authorized to view.
     *
     * @throws ModelNotFoundException
     * @throws AuthorizationException
     */
    public function findViewableByUuid(User $actor, string $uuid): PerusahaanBuyer
    {
        $tenant = $this->findByUuid($uuid);
        Gate::forUser($actor)->authorize('view', $tenant);

        return $tenant;
    }

    /**
     * Verify (approve or reject) a tenant company's registration and legal document.
     */
    public function verify(
        string $uuid,
        string $action,
        User $verifier,
        ?string $rejectionReason = null
    ): PerusahaanBuyer {
        if (! in_array($action, ['approve', 'reject'], true)) {
            throw ValidationException::withMessages([
                'aksi' => 'Aksi verifikasi tidak valid.',
            ]);
        }

        if ($action === 'reject' && trim((string) $rejectionReason) === '') {
            throw ValidationException::withMessages([
                'rejection_reason' => 'Alasan penolakan wajib diisi.',
            ]);
        }

        $tenant = DB::transaction(function () use (
            $uuid,
            $action,
            $verifier,
            $rejectionReason
        ): PerusahaanBuyer {
            $tenant = $this->repository->findByUuid($uuid);

            if ($tenant === null) {
                throw (new ModelNotFoundException('Data tenant tidak ditemukan.'))->setModel(PerusahaanBuyer::class, [$uuid]);
            }

            Gate::forUser($verifier)->authorize('verify', $tenant);

            if ($tenant->verification_status !== VerificationStatus::Pending) {
                throw ValidationException::withMessages([
                    'verification_status' => 'Akun tenant ini sudah diverifikasi sebelumnya dan tidak dapat diproses ulang.',
                ]);
            }

            $approved = $action === 'approve';

            $this->repository->update($tenant, [
                'verification_status' => $approved
                    ? VerificationStatus::Approved
                    : VerificationStatus::Rejected,
                'verified_by' => $verifier->id,
                'verified_at' => now(),
                'verification_note' => $approved
                    ? null
                    : trim((string) $rejectionReason),
            ]);

            return $tenant->fresh(['users', 'verifiedByUser']);
        });

        // Send email notification to tenant owner
        $owner = $tenant->users->first(fn ($u) => $u->role?->name === 'owner') ?? $tenant->users->first();

        if ($owner && filled($owner->email)) {
            try {
                if ($action === 'approve') {
                    Mail::to($owner->email)->send(
                        new TenantApprovalMail(
                            ownerName: $owner->name,
                            companyName: $tenant->company_name,
                        )
                    );
                } else {
                    Mail::to($owner->email)->send(
                        new TenantRejectionMail(
                            ownerName: $owner->name,
                            companyName: $tenant->company_name,
                            rejectionReason: (string) $rejectionReason,
                        )
                    );
                }
            } catch (Throwable $e) {
                Log::error('Gagal mengirim email verifikasi tenant', [
                    'tenant_uuid' => $tenant->uuid,
                    'action' => $action,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $tenant;
    }
}
