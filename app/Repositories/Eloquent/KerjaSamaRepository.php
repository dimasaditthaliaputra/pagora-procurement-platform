<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\StatusKerjaSama;
use App\Models\KerjaSama;
use App\Repositories\Contracts\KerjaSamaRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class KerjaSamaRepository implements KerjaSamaRepositoryInterface
{
    public function __construct(
        protected KerjaSama $model
    ) {}

    /**
     * Find a partnership by tenant and vendor IDs.
     */
    public function findByTenantAndVendor(
        int $tenantId,
        int $vendorId
    ): ?KerjaSama {
        return $this->model->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('vendor_id', $vendorId)
            ->first();
    }

    /**
     * Create a new partnership request.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): KerjaSama
    {
        return $this->model->newQuery()->create($data);
    }

    /**
     * Get pending invitations received by a vendor.
     */
    public function paginateIncomingForVendor(
        int $vendorId,
        int $perPage = 12
    ): LengthAwarePaginator {
        return $this->model->newQuery()
            ->with('perusahaanBuyer')
            ->where('vendor_id', $vendorId)
            ->where('requested_by', 'tenant')
            ->where('status', StatusKerjaSama::Pending)
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Find an invitation belonging to a vendor.
     *
     * Includes previously responded invitations so the service
     * can reject duplicate responses with a validation error.
     */
    public function findIncomingByUuidForVendor(
        string $uuid,
        int $vendorId
    ): ?KerjaSama {
        return $this->model->newQuery()
            ->with('perusahaanBuyer')
            ->where('uuid', $uuid)
            ->where('vendor_id', $vendorId)
            ->where('requested_by', 'tenant')
            ->first();
    }

    /**
     * Update a partnership.
     *
     * @param array<string, mixed> $data
     */
    public function update(
        KerjaSama $kerjaSama,
        array $data
    ): KerjaSama {
        $kerjaSama->update($data);

        return $kerjaSama->refresh();
    }
}
