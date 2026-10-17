<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Komoditas;
use App\Repositories\Contracts\KomoditasRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    /**
     * Standard categories for food procurement commodities.
     *
     * @var list<string>
     */
    public const KATEGORI_OPTIONS = [
        'Beras & Padi',
        'Biji-bijian & Jagung',
        'Minyak & Lemak',
        'Gula & Pemanis',
        'Bumbu & Rempah',
        'Sayuran & Buah',
        'Daging & Unggas',
        'Tepung & Olahan',
        'Lainnya',
    ];

    public function __construct(
        protected KomoditasRepositoryInterface $repository
    ) {}

    /**
     * Paginate commodities.
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
            'slug',
            'nama_komoditas',
            'sku',
            'kategori',
            'satuan',
            'kode_referensi_api',
            'status_aktif',
            'created_at',
        ]
    ): LengthAwarePaginator {
        return $this->repository->paginate($filters, $perPage, $columns);
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
            $nama = (string) ($data['nama_komoditas'] ?? '');
            $data['slug'] = $this->generateUniqueSlug($nama);
            $data['uuid'] = (string) Str::uuid();

            if (! isset($data['status_aktif'])) {
                $data['status_aktif'] = true;
            }

            return $this->repository->create($data);
        });
    }

    /**
     * Update an existing commodity.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ModelNotFoundException
     */
    public function update(string $uuid, array $data): Komoditas
    {
        $komoditas = $this->findByUuid($uuid);

        return DB::transaction(function () use ($komoditas, $data, $uuid): Komoditas {
            if (isset($data['nama_komoditas']) && $data['nama_komoditas'] !== $komoditas->nama_komoditas) {
                $data['slug'] = $this->generateUniqueSlug((string) $data['nama_komoditas'], $uuid);
            }

            $this->repository->update($komoditas, $data);

            return $komoditas->fresh();
        });
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
     * Get available category options.
     *
     * @return list<string>
     */
    public function getKategoriOptions(): array
    {
        return self::KATEGORI_OPTIONS;
    }
}
