<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Komoditas;
use App\Repositories\Contracts\CommodityCategoryRepositoryInterface;
use App\Repositories\Contracts\KomoditasRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class KomoditasService
{
    /**
     * Standard units for food and raw material commodities.
     *
     * @var list<string>
     */
    public const SATUAN_OPTIONS = [
        'kg',
        'ton',
        'sak',
        'karung',
        'liter',
        'gram',
    ];

    public function __construct(
        protected KomoditasRepositoryInterface $repository,
        protected CommodityCategoryRepositoryInterface $categoryRepository
    ) {}

    /**
     * Paginate commodities.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return $this->repository->paginate($filters, $perPage);
    }

    /**
     * Find a commodity by UUID or throw ModelNotFoundException.
     *
     * @throws ModelNotFoundException
     */
    public function findByUuid(string $uuid): Komoditas
    {
        $komoditas = $this->repository->findByUuid($uuid);

        if (! $komoditas) {
            throw (new ModelNotFoundException('Komoditas tidak ditemukan.'))->setModel(Komoditas::class, [$uuid]);
        }

        return $komoditas;
    }

    /**
     * Create a new commodity.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Komoditas
    {
        return DB::transaction(function () use ($data): Komoditas {
            $data = $this->resolveCategory($data);
            $data['slug'] = $this->generateUniqueSlug((string) ($data['name'] ?? ''));
            $data['uuid'] = (string) Str::uuid();

            if (! isset($data['is_active'])) {
                $data['is_active'] = true;
            }

            $data['default_shrinkage_tolerance'] = array_key_exists('default_shrinkage_tolerance', $data)
                ? $this->normalizeShrinkageTolerance($data['default_shrinkage_tolerance'])
                : 0.0;

            return $this->repository->create($data);
        });
    }

    /**
     * Update an existing commodity.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function update(string $uuid, array $data): Komoditas
    {
        $komoditas = $this->findByUuid($uuid);

        return DB::transaction(function () use ($komoditas, $data, $uuid): Komoditas {
            $data = $this->resolveCategory($data);

            if (isset($data['name']) && $data['name'] !== $komoditas->name) {
                $data['slug'] = $this->generateUniqueSlug((string) $data['name'], $uuid);
            }

            if (array_key_exists('default_shrinkage_tolerance', $data)) {
                $data['default_shrinkage_tolerance'] = $this->normalizeShrinkageTolerance($data['default_shrinkage_tolerance']);
            }

            $this->repository->update($komoditas, $data);

            return $komoditas->fresh();
        });
    }

    /**
     * Replace `commodity_category_uuid` with `commodity_category_id`.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolveCategory(array $data): array
    {
        if (! array_key_exists('commodity_category_uuid', $data)) {
            return $data;
        }

        $uuid = $data['commodity_category_uuid'];
        unset($data['commodity_category_uuid']);

        $data['commodity_category_id'] = $uuid ? $this->categoryRepository->findByUuid((string) $uuid)?->id : null;

        return $data;
    }

    /**
     * Guard and normalize the shrinkage tolerance to ensure decimal dot format and range.
     *
     * @throws ValidationException
     */
    private function normalizeShrinkageTolerance(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if (str_contains($trimmed, ',') || ! preg_match('/^(?:100(?:\.0{1,2})?|\d{1,2}(?:\.\d{1,2})?)$/', $trimmed)) {
                throw ValidationException::withMessages([
                    'default_shrinkage_tolerance' => 'Batas susut hanya menerima format angka desimal dengan titik (contoh: 2.5).',
                ]);
            }

            return (float) $trimmed;
        }

        if (is_numeric($value)) {
            $num = (float) $value;
            if ($num < 0 || $num > 100) {
                throw ValidationException::withMessages([
                    'default_shrinkage_tolerance' => 'Batas susut harus berada dalam rentang 0 hingga 100 persen.',
                ]);
            }

            return round($num, 2);
        }

        throw ValidationException::withMessages([
            'default_shrinkage_tolerance' => 'Format batas susut tidak valid.',
        ]);
    }

    /**
     * Delete a commodity by UUID.
     *
     * @throws ModelNotFoundException
     */
    public function delete(string $uuid): bool
    {
        $komoditas = $this->findByUuid($uuid);

        return $this->repository->delete($komoditas);
    }

    /**
     * Generate unique slug handling potential conflicts and name changes.
     */
    public function generateUniqueSlug(string $name, ?string $ignoreUuid = null): string
    {
        $baseSlug = Str::slug($name);
        if ($baseSlug === '') {
            $baseSlug = 'komoditas-'.Str::lower(Str::random(6));
        }

        $slug = $baseSlug;
        $counter = 1;

        while ($this->repository->slugExists($slug, $ignoreUuid)) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * Get available unit options.
     *
     * @return list<string>
     */
    public function getSatuanOptions(): array
    {
        return self::SATUAN_OPTIONS;
    }

    /**
     * Get active category options for selects.
     *
     * @return list<array{uuid: string, name: string}>
     */
    public function getCategoryOptions(): array
    {
        return $this->categoryRepository->getActive()
            ->map(fn ($category): array => ['uuid' => $category->uuid, 'name' => $category->name])
            ->all();
    }
}
