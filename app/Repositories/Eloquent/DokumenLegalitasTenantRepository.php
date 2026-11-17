<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\DokumenLegalitasTenant;
use App\Repositories\Contracts\DokumenLegalitasTenantRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class DokumenLegalitasTenantRepository implements DokumenLegalitasTenantRepositoryInterface
{
    public function __construct(
        protected DokumenLegalitasTenant $model
    ) {}

    /**
     * Get every legal document of a tenant.
     *
     * @return Collection<int, DokumenLegalitasTenant>
     */
    public function getByTenantId(int $tenantId): Collection
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->latest('id')
            ->get();
    }

    /**
     * Find a legal document by UUID.
     */
    public function findByUuid(string $uuid): ?DokumenLegalitasTenant
    {
        return $this->model
            ->newQuery()
            ->where('uuid', $uuid)
            ->first();
    }

    /**
     * Create a new tenant legal document record.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): DokumenLegalitasTenant
    {
        return $this->model->newQuery()->create($data);
    }

    /**
     * Save changes to a legal document.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(
        DokumenLegalitasTenant $document,
        array $data
    ): bool {
        return $document->update($data);
    }
}
