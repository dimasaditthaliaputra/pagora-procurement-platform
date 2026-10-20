<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Komoditas;
use App\Repositories\Contracts\KomoditasRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KomoditasRepository implements KomoditasRepositoryInterface
{
    /**
     * Whitelist of sortable column names.
     *
     * @var list<string>
     */
    private const SORTABLE = [
        'nama_komoditas',
        'sku',
        'kategori',
        'satuan',
        'status_aktif',
        'created_at',
    ];

    public function __construct(
        protected Komoditas $model
    ) {}

    /**
     * Paginate commodities with search, filters, and sorting.
     *
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $columns
     */
    public function paginate(
        array $filters,
        int $perPage = 10,
        array $columns = [
            'id',
            'uuid',
            'nama_komoditas',
            'sku',
            'kategori',
            'satuan',
            'kode_referensi_api',
            'status_aktif',
            'created_at',
        ]
    ): LengthAwarePaginator {
        $sort = in_array($filters['sort'] ?? null, self::SORTABLE, true)
            ? (string) $filters['sort']
            : 'created_at';

        $direction = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $driver = DB::getDriverName();
        $like = $driver === 'pgsql' ? 'ilike' : 'like';

        return $this->model->newQuery()
            ->select($columns)
            ->when(! empty($filters['search']), function ($query) use ($filters, $like): void {
                $term = '%'.trim((string) $filters['search']).'%';
                $query->where(function ($q) use ($term, $like): void {
                    $q->where('nama_komoditas', $like, $term)
                        ->orWhere('sku', $like, $term)
                        ->orWhere('kode_referensi_api', $like, $term);
                });
            })
            ->when(! empty($filters['kategori']) && $filters['kategori'] !== 'all', function ($query) use ($filters): void {
                $query->where('kategori', $filters['kategori']);
            })
            ->when(isset($filters['status_aktif']) && $filters['status_aktif'] !== '' && $filters['status_aktif'] !== 'all', function ($query) use ($filters): void {
                $status = filter_var($filters['status_aktif'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($status !== null) {
                    $query->where('status_aktif', $status);
                }
            })
            ->orderBy($sort, $direction)
            ->paginate(min(max($perPage, 1), 100))
            ->withQueryString();
    }

    /**
     * Find a commodity by its UUID.
     */
    public function findByUuid(string $uuid): ?Komoditas
    {
        return $this->model->newQuery()->where('uuid', $uuid)->first();
    }

    /**
     * Find a commodity by its slug.
     */
    public function findBySlug(string $slug): ?Komoditas
    {
        return $this->model->newQuery()->where('slug', $slug)->first();
    }

    /**
     * Find a commodity by its SKU.
     */
    public function findBySku(string $sku): ?Komoditas
    {
        return $this->model->newQuery()->where('sku', $sku)->first();
    }

    /**
     * Create a new commodity record.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Komoditas
    {
        if (empty($data['uuid'])) {
            $data['uuid'] = (string) Str::uuid();
        }

        if (! isset($data['status_aktif'])) {
            $data['status_aktif'] = true;
        }

        return $this->model->newQuery()->create($data);
    }

    /**
     * Update commodity attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Komoditas $komoditas, array $data): bool
    {
        return $komoditas->update($data);
    }

    /**
     * Soft delete a commodity.
     */
    public function delete(Komoditas $komoditas): bool
    {
        return (bool) $komoditas->delete();
    }

    /**
     * Check if a slug already exists, optionally ignoring a specific UUID.
     */
    public function slugExists(string $slug, ?string $ignoreUuid = null): bool
    {
        return $this->model->newQuery()
            ->where('slug', $slug)
            ->when($ignoreUuid, fn ($q) => $q->where('uuid', '!=', $ignoreUuid))
            ->exists();
    }

    /**
     * Check if an SKU already exists, optionally ignoring a specific UUID.
     */
    public function skuExists(string $sku, ?string $ignoreUuid = null): bool
    {
        return $this->model->newQuery()
            ->where('sku', $sku)
            ->when($ignoreUuid, fn ($q) => $q->where('uuid', '!=', $ignoreUuid))
            ->exists();
    }
}
