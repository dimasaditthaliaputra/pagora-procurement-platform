<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\DokumenLegalitasVendor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface DokumenLegalitasVendorRepositoryInterface
{
    /**
     * Get paginated vendors that submitted legal documents, each with all of its documents.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateVendors(array $filters, int $perPage = 10): LengthAwarePaginator;

    /**
     * Get every legal document of a vendor.
     *
     * @return Collection<int, DokumenLegalitasVendor>
     */
    public function getByVendorId(int $vendorId): Collection;

    /**
     * Find a legal document by UUID.
     */
    public function findByUuid(string $uuid): ?DokumenLegalitasVendor;

    /**
     * Create a new vendor legal document record.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): DokumenLegalitasVendor;

    /**
     * Save changes to a legal document.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(
        DokumenLegalitasVendor $document,
        array $data
    ): bool;
}
