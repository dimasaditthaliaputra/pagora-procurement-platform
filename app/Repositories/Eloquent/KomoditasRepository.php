<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Komoditas;
use App\Models\TenantCommodity;
use App\Repositories\Contracts\KomoditasRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
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
        'name',
        'sku',
        'unit',
        'is_active',
        'created_at',
    ];

    public function __construct(
        protected Komoditas $model
    ) {}

    /**
     * Paginate commodities with search, filters, and sorting.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? null, self::SORTABLE, true)
            ? (string) $filters['sort']
            : 'created_at';

        $direction = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $driver = DB::getDriverName();
        $like = $driver === 'pgsql' ? 'ilike' : 'like';

        return $this->model->newQuery()
            ->with('category:id,uuid,name')
            ->when(! empty($filters['search']), function ($query) use ($filters, $like): void {
                $term = '%'.trim((string) $filters['search']).'%';
                $query->where(function ($q) use ($term, $like): void {
                    $q->where('name', $like, $term)
                        ->orWhere('sku', $like, $term)
                        ->orWhere('api_reference_code', $like, $term);
                });
            })
            ->when(! empty($filters['commodity_category_uuid']) && $filters['commodity_category_uuid'] !== 'all', function ($query) use ($filters): void {
                $query->whereHas('category', fn ($q) => $q->where('uuid', $filters['commodity_category_uuid']));
            })
            ->when(isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== 'all', function ($query) use ($filters): void {
                $status = filter_var($filters['status'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($status !== null) {
                    $query->where('is_active', $status);
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
        return $this->model->newQuery()->with('category:id,uuid,name')->where('uuid', $uuid)->first();
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

        if (! isset($data['is_active'])) {
            $data['is_active'] = true;
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

    /**
     * Active commodities enabled for a Tenant, ordered by name.
     *
     * @return Collection<int, Komoditas>
     */
    public function getActiveForTenant(int $tenantId): Collection
    {
        $enabledIds = TenantCommodity::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->pluck('commodity_id');

        return $this->model->newQuery()
            ->where('is_active', true)
            ->when($enabledIds->isNotEmpty(), fn ($query) => $query->whereIn('id', $enabledIds))
            ->orderBy('name')
            ->get();
    }

    /**
     * Active commodities mapped to a Bank Indonesia commodity code.
     *
     * @return Collection<int, Komoditas>
     */
    public function getActiveWithApiReference(): Collection
    {
        return $this->model->newQuery()
            ->where('is_active', true)
            ->whereNotNull('api_reference_code')
            ->where('api_reference_code', '!=', '')
            ->orderBy('name')
            ->get();
    }

    /**
     * Bank Indonesia codes already mapped to a (non-deleted) commodity.
     *
     * @return list<string>
     */
    public function getMappedApiReferenceCodes(): array
    {
        return $this->model->newQuery()
            ->whereNotNull('api_reference_code')
            ->where('api_reference_code', '!=', '')
            ->pluck('api_reference_code')
            ->values()
            ->all();
    }

    /**
     * Check if a (non-deleted) commodity already uses this name, case-insensitively.
     */
    public function nameExists(string $name): bool
    {
        return $this->model->newQuery()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->exists();
    }
}
