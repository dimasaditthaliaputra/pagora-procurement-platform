<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RoleName;
use App\Enums\StatusKerjaSama;
use App\Models\KerjaSama;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\KerjaSamaRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class VendorInvitationService
{
    public function __construct(
        private readonly KerjaSamaRepositoryInterface $kerjaSamaRepository,
        private readonly NotificationService $notificationService,
    ) {}

    public function paginateIncoming(
        int $vendorId,
        int $perPage = 12
    ): LengthAwarePaginator {
        return $this->kerjaSamaRepository->paginateIncomingForVendor(
            $vendorId,
            $perPage
        );
    }

    public function findIncoming(
        string $uuid,
        int $vendorId,
        ?User $user = null
    ): ?KerjaSama {
        $partnership = $this->kerjaSamaRepository
            ->findIncomingByUuidForVendor($uuid, $vendorId);

        if ($partnership && $user) {
            Gate::forUser($user)->authorize('view', $partnership);
        }

        return $partnership;
    }

    public function respond(
        User $user,
        string $uuid,
        string $action,
        ?string $rejectionReason = null
    ): KerjaSama {
        return DB::transaction(function () use (
            $user,
            $uuid,
            $action,
            $rejectionReason
        ): KerjaSama {
            $partnership = $this->findIncoming(
                $uuid,
                (int) $user->vendor_id,
                $user
            );

            if (! $partnership) {
                throw new ModelNotFoundException(
                    'Undangan kerja sama tidak ditemukan.'
                );
            }

            Gate::forUser($user)->authorize('respond', $partnership);

            if ($partnership->status !== StatusKerjaSama::Pending) {
                throw ValidationException::withMessages([
                    'status' => 'Undangan kerja sama ini sudah ditanggapi.',
                ]);
            }

            if (! in_array($action, ['accept', 'reject'], true)) {
                throw ValidationException::withMessages([
                    'action' => 'Tindakan tidak valid.',
                ]);
            }

            $status = $action === 'accept'
                ? StatusKerjaSama::Agreed
                : StatusKerjaSama::Rejected;

            $updated = $this->kerjaSamaRepository->update(
                $partnership,
                [
                    'status' => $status,
                    'rejection_reason' => $action === 'reject'
                        ? $rejectionReason
                        : null,
                ]
            );

            $buyerRoleIds = Role::query()
                ->whereIn('name', [
                    RoleName::Owner->value,
                    RoleName::Manajemen->value,
                ])
                ->pluck('id');

            $buyerUsers = User::query()
                ->where('tenant_id', $updated->tenant_id)
                ->whereIn('role_id', $buyerRoleIds)
                ->get();

            $this->notificationService->sendToMany(
                $buyerUsers,
                'partnership',
                $action === 'accept'
                    ? 'Undangan Kerja Sama Diterima'
                    : 'Undangan Kerja Sama Ditolak',
                $action === 'accept'
                    ? 'Vendor menyetujui undangan kerja sama Anda.'
                    : 'Vendor menolak undangan kerja sama Anda.',
                '/kerja-sama',
                [
                    'partnership_uuid' => $updated->uuid,
                    'status' => $updated->status->value,
                ]
            );

            return $updated;
        });
    }
}