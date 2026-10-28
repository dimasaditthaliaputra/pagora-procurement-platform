<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Vendor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface VendorRepositoryInterface
{
    /**
     * Get paginated vendors with filters and sorting.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 10): LengthAwarePaginator;

    /**
     * Create a new vendor record.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Vendor;

    /**
     * Find a vendor by its email kontak.
     */
    public function findByEmail(string $email): ?Vendor;

    /**
     * Find a vendor by its UUID.
     */
    public function findByUuid(string $uuid): ?Vendor;

    /**
     * Update an existing vendor.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Vendor $vendor, array $data): bool;
}
