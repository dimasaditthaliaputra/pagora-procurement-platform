<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Vendor;

interface VendorRepositoryInterface
{
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
}
