<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Vendor;
use App\Repositories\Contracts\VendorRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendorService
{
    public function __construct(
        protected VendorRepositoryInterface $vendorRepository
    ) {}

    /**
     * Get paginated vendors with filters and sorting.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return $this->vendorRepository->paginate($filters, $perPage);
    }

    /**
     * Find a vendor by UUID or fail.
     *
     * @throws ModelNotFoundException
     */
    public function findByUuid(string $uuid): Vendor
    {
        $vendor = $this->vendorRepository->findByUuid($uuid);

        if ($vendor === null) {
            throw new ModelNotFoundException('Data vendor tidak ditemukan.');
        }

        return $vendor;
    }

    /**
     * Block a vendor and deactivate its associated user accounts.
     *
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function blokir(string $uuid, string $alasan, User $actor): Vendor
    {
        $vendor = $this->findByUuid($uuid);

        if (! $vendor->status_aktif) {
            throw ValidationException::withMessages([
                'alasan_pemblokiran' => 'Vendor ini sudah dalam status diblokir.',
            ]);
        }

        return DB::transaction(function () use ($vendor, $alasan, $actor): Vendor {
            $this->vendorRepository->update($vendor, [
                'status_aktif' => false,
                'alasan_pemblokiran' => trim($alasan),
                'blocked_by' => $actor->id,
                'diblokir_pada' => now(),
            ]);

            // Deactivate all user logins belonging to this vendor
            $vendor->users()->update([
                'status_aktif' => false,
            ]);

            return $vendor->fresh(['blockedByUser']);
        });
    }

    /**
     * Unblock a vendor and reactivate its associated user accounts.
     *
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function bukaBlokir(string $uuid, User $actor): Vendor
    {
        $vendor = $this->findByUuid($uuid);

        if ($vendor->status_aktif) {
            throw ValidationException::withMessages([
                'status_aktif' => 'Vendor ini sudah dalam status aktif.',
            ]);
        }

        return DB::transaction(function () use ($vendor): Vendor {
            $this->vendorRepository->update($vendor, [
                'status_aktif' => true,
                'alasan_pemblokiran' => null,
                'blocked_by' => null,
                'diblokir_pada' => null,
            ]);

            // Reactivate user logins belonging to this vendor
            $vendor->users()->update([
                'status_aktif' => true,
            ]);

            return $vendor->fresh(['blockedByUser']);
        });
    }
}
