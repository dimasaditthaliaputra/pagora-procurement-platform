<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\DokumenLegalitasVendor;
use App\Repositories\Contracts\DokumenLegalitasVendorRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DokumenLegalitasVendorRepository implements DokumenLegalitasVendorRepositoryInterface
{
    public function __construct(
        protected DokumenLegalitasVendor $model
    ) {}

    /**
     * Get paginated vendor legal documents.
     *
     * @param array<string, mixed> $filters
     */
    public function paginate(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return $this->model
            ->newQuery()
            ->with('vendor')
            ->when(
                !empty($filters['status_verifikasi']),
                fn ($query) => $query->where(
                    'status_verifikasi',
                    $filters['status_verifikasi']
                )
            )
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findByUuid(string $uuid): ?DokumenLegalitasVendor
    {
        return $this->model
            ->newQuery()
            ->with('vendor')
            ->where('uuid', $uuid)
            ->first();
    }

    /**
     * Create a new vendor legal document record.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): DokumenLegalitasVendor
    {
        return $this->model->newQuery()->create($data);
    }

    /**
     * Save changes to a legal document.
     *
     * @param array<string, mixed> $data
     */
    public function update(
        DokumenLegalitasVendor $document,
        array $data
    ): bool {
        return $document->update($data);
    }
}
