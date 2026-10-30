<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\UndanganVendor;
use App\Repositories\Contracts\UndanganVendorRepositoryInterface;

class UndanganVendorRepository implements UndanganVendorRepositoryInterface
{
    public function __construct(
        protected readonly UndanganVendor $model
    ) {}

    public function create(array $data): UndanganVendor
    {
        return $this->model->create($data);
    }

    public function findByUuid(string $uuid): ?UndanganVendor
    {
        return $this->model->where('uuid', $uuid)->first();
    }

    public function findByToken(string $token): ?UndanganVendor
    {
        return $this->model->where('token', $token)->first();
    }

    public function findByEmail(string $email): ?UndanganVendor
    {
        return $this->model->where('email', strtolower(trim($email)))
            ->latest('id')
            ->first();
    }

    public function update(UndanganVendor $undangan, array $data): bool
    {
        return $undangan->update($data);
    }
}
