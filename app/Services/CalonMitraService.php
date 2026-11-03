<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StatusKerjaSama;
use App\Models\KerjaSama;
use App\Models\PerusahaanBuyer;
use App\Repositories\Contracts\KerjaSamaRepositoryInterface;
use App\Repositories\Contracts\PerusahaanBuyerRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CalonMitraService
{
    public function __construct(
        private readonly PerusahaanBuyerRepositoryInterface $buyerRepository,
        private readonly KerjaSamaRepositoryInterface $kerjaSamaRepository
    ) {}

    /**
     * Return paginated list of eligible partner companies.
     *
     * @param array<string, mixed> $filters
     */
    public function paginate(int $vendorId, array $filters, int $perPage = 12): LengthAwarePaginator
    {
        return $this->buyerRepository->paginateCalonMitra(
            $vendorId,
            $filters,
            $perPage
        );
    }

    /**
     * Find an eligible Buyer company by UUID.
     */
    public function findEligibleBuyer(string $uuid): ?PerusahaanBuyer
    {
        $buyer = $this->buyerRepository->findByUuid($uuid);

        if (
            ! $buyer ||
            ! $buyer->is_active ||
            $buyer->verification_status->value !== 'approved'
        ) {
            return null;
        }

        return $buyer;
    }

    /**
     * Submit a partnership request from Vendor to Buyer.
     */
    public function submitPartnership(int $vendorId, string $buyerUuid): KerjaSama
    {
        return DB::transaction(function () use ($vendorId, $buyerUuid): KerjaSama {
            $buyer = $this->findEligibleBuyer($buyerUuid);

            if (! $buyer) {
                throw ValidationException::withMessages([
                    'buyer' => 'Perusahaan Buyer tidak ditemukan atau belum memenuhi syarat.',
                ]);
            }

            $existing = $this->kerjaSamaRepository->findByTenantAndVendor(
                $buyer->id,
                $vendorId
            );

            if ($existing) {
                throw ValidationException::withMessages([
                    'partnership' => 'Pengajuan kerja sama dengan perusahaan ini sudah pernah dibuat.',
                ]);
            }

            return $this->kerjaSamaRepository->create([
                'tenant_id' => $buyer->id,
                'vendor_id' => $vendorId,
                'requested_by' => 'vendor',
                'status' => StatusKerjaSama::Pending,
            ]);
        });
    }
}
