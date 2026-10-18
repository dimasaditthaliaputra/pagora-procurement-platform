<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Vendor;
use App\Repositories\Contracts\VendorRepositoryInterface;

class VendorRepository implements VendorRepositoryInterface
{
    public function __construct(
        protected Vendor $model
    ) {}

    /**
     * Create a new vendor record.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Vendor
    {
        return $this->model->newQuery()->create($data);
    }

    /**
     * Find a vendor by its email kontak.
     */
    public function findByEmail(string $email): ?Vendor
    {
        return $this->model->newQuery()->where('email_kontak', $email)->first();
    }

    /**
     * Find a vendor by its UUID.
     */
    public function findByUuid(string $uuid): ?Vendor
    {
        return $this->model->newQuery()->where('uuid', $uuid)->first();
    }
}
