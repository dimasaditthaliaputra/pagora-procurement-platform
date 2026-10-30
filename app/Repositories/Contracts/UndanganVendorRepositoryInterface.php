<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\UndanganVendor;

interface UndanganVendorRepositoryInterface
{
    /**
     * Create a new vendor invitation.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): UndanganVendor;

    /**
     * Find an invitation by its UUID.
     */
    public function findByUuid(string $uuid): ?UndanganVendor;

    /**
     * Find an invitation by its unique token.
     */
    public function findByToken(string $token): ?UndanganVendor;

    /**
     * Find the latest invitation for a given email address.
     */
    public function findByEmail(string $email): ?UndanganVendor;

    /**
     * Update an invitation record.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(UndanganVendor $undangan, array $data): bool;
}
