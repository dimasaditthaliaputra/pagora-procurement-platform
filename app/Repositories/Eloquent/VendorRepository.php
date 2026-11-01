<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Vendor;
use App\Repositories\Contracts\VendorRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class VendorRepository implements VendorRepositoryInterface
{
    private const SORTABLE = [
        'company_name',
        'contact_email',
        'kategori_komoditas',
        'verification_status',
        'is_active',
        'created_at',
        'blocked_at',
    ];

    public function __construct(
        protected Vendor $model
    ) {}

    /**
     * Get paginated vendors with filters and sorting.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? null, self::SORTABLE, true)
            ? (string) $filters['sort']
            : 'created_at';

        $direction = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc'
            ? 'asc'
            : 'desc';

        return $this->model->newQuery()
            ->with(['blockedByUser'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $trimmed = trim($search);
                $query->where(function (Builder $subQuery) use ($trimmed): void {
                    $subQuery->where('company_name', 'like', "%{$trimmed}%")
                        ->orWhere('contact_email', 'like', "%{$trimmed}%")
                        ->orWhere('phone', 'like', "%{$trimmed}%")
                        ->orWhere('kategori_komoditas', 'like', "%{$trimmed}%");
                });
            })
            ->when(
                isset($filters['is_active']) && $filters['is_active'] !== '' && $filters['is_active'] !== 'all',
                function (Builder $query) use ($filters): void {
                    $val = filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($val !== null) {
                        $query->where('is_active', $val);
                    }
                }
            )
            ->when(
                ! empty($filters['verification_status']) && $filters['verification_status'] !== 'all',
                fn (Builder $query) => $query->where('verification_status', $filters['verification_status'])
            )
            ->orderBy($sort, $direction)
            ->paginate(min(max($perPage, 1), 100))
            ->withQueryString();
    }

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
        return $this->model->newQuery()->where('contact_email', $email)->first();
    }

    /**
     * Find a vendor by its UUID.
     */
    public function findByUuid(string $uuid): ?Vendor
    {
        return $this->model->newQuery()
            ->with(['blockedByUser'])
            ->where('uuid', $uuid)
            ->first();
    }

    /**
     * Update an existing vendor.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Vendor $vendor, array $data): bool
    {
        return $vendor->update($data);
    }

    /**
     * Retrieve all vendors ordered by company name.
     *
     * @return Collection<int, Vendor>
     */
    public function getAllOrdered(): Collection
    {
        return $this->model->newQuery()
            ->select(['id', 'uuid', 'company_name', 'is_active'])
            ->orderBy('company_name')
            ->get();
    }
}
