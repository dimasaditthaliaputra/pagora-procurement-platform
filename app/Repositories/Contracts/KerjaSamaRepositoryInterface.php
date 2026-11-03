<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\KerjaSama;

interface KerjaSamaRepositoryInterface
{
    /**
     * Find a partnership by tenant and vendor IDs.
     */
    public function findByTenantAndVendor(int $tenantId, int $vendorId): ?KerjaSama;

    /**
     * Create a new partnership request.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): KerjaSama;
}
