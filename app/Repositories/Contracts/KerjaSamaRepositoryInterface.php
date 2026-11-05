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

    public function paginateIncomingForVendor(
    int $vendorId,
    int $perPage = 12
    ): \Illuminate\Contracts\Pagination\LengthAwarePaginator;

    public function findIncomingByUuidForVendor(
        string $uuid,
        int $vendorId
    ): ?\App\Models\KerjaSama;

    public function update(
        \App\Models\KerjaSama $kerjaSama,
        array $data
    ): \App\Models\KerjaSama;
}
