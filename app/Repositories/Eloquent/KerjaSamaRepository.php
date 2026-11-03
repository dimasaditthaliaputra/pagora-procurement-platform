<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\KerjaSama;
use App\Repositories\Contracts\KerjaSamaRepositoryInterface;

class KerjaSamaRepository implements KerjaSamaRepositoryInterface
{
    public function __construct(
        protected KerjaSama $model
    ) {}

    /**
     * Find a partnership by tenant and vendor IDs.
     */
    public function findByTenantAndVendor(int $tenantId, int $vendorId): ?KerjaSama
    {
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
}
