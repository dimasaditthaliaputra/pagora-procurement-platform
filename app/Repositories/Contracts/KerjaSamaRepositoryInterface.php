<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\KerjaSama;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface KerjaSamaRepositoryInterface
{
    /**
     * Find a partnership by tenant and vendor IDs.
     */
    public function findByTenantAndVendor(int $tenantId, int $vendorId): ?KerjaSama;

    /**
     * Create a new partnership request.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): KerjaSama;

    public function paginateIncomingForVendor(
        int $vendorId,
        int $perPage = 12
    ): LengthAwarePaginator;

    public function findIncomingByUuidForVendor(
        string $uuid,
        int $vendorId
    ): ?KerjaSama;

    public function update(
        KerjaSama $kerjaSama,
        array $data
    ): KerjaSama;
}
