<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\DokumenLegalitasTenant;
use Illuminate\Database\Eloquent\Collection;

interface DokumenLegalitasTenantRepositoryInterface
{
    /**
     * Get every legal document of a tenant.
     *
     * @return Collection<int, DokumenLegalitasTenant>
     */
    public function getByTenantId(int $tenantId): Collection;

    /**
     * Find a legal document by UUID.
     */
    public function findByUuid(string $uuid): ?DokumenLegalitasTenant;

    /**
     * Create a new tenant legal document record.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): DokumenLegalitasTenant;

    /**
     * Save changes to a legal document.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(
        DokumenLegalitasTenant $document,
        array $data
    ): bool;
}
