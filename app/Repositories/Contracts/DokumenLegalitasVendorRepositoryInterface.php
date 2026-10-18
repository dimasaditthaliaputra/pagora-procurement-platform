<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\DokumenLegalitasVendor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DokumenLegalitasVendorRepositoryInterface
{
    /**
     * Get paginated vendor legal documents.
     *
     * @param array<string, mixed> $filters
     */
    public function paginate(array $filters, int $perPage = 10): LengthAwarePaginator;

    /**
     * Find a legal document by UUID.
     */
    public function findByUuid(string $uuid): ?DokumenLegalitasVendor;

    /**
     * Create a new vendor legal document record.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): DokumenLegalitasVendor;

    /**
     * Save changes to a legal document.
     *
     * @param array<string, mixed> $data
     */
    public function update(
        DokumenLegalitasVendor $document,
        array $data
    ): bool;
}
