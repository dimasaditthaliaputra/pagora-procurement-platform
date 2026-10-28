<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Vendor;
use App\Repositories\Contracts\VendorRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class VendorRepository implements VendorRepositoryInterface
{
    private const SORTABLE = [
        'nama_perusahaan',
        'email_kontak',
        'kategori_komoditas',
        'status_verifikasi',
        'status_aktif',
        'created_at',
        'diblokir_pada',
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
                    $subQuery->where('nama_perusahaan', 'like', "%{$trimmed}%")
                        ->orWhere('email_kontak', 'like', "%{$trimmed}%")
                        ->orWhere('no_telepon', 'like', "%{$trimmed}%")
                        ->orWhere('kategori_komoditas', 'like', "%{$trimmed}%");
                });
            })
            ->when(
                isset($filters['status_aktif']) && $filters['status_aktif'] !== '' && $filters['status_aktif'] !== 'all',
                function (Builder $query) use ($filters): void {
                    $val = filter_var($filters['status_aktif'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($val !== null) {
                        $query->where('status_aktif', $val);
                    }
                }
            )
            ->when(
                ! empty($filters['status_verifikasi']) && $filters['status_verifikasi'] !== 'all',
                fn (Builder $query) => $query->where('status_verifikasi', $filters['status_verifikasi'])
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
        return $this->model->newQuery()->where('email_kontak', $email)->first();
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
}
