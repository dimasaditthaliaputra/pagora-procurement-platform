<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Komoditas;
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
            'nama_komoditas',
            'sku',
            'kategori',
            'satuan',
            'batas_susut',
            'kode_referensi_api',
            'status',
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

            if (! isset($data['status'])) {
                $data['status'] = true;
            }

            if (array_key_exists('batas_susut', $data)) {
                $data['batas_susut'] = $this->normalizeBatasSusut($data['batas_susut']);
            } else {
                $data['batas_susut'] = 0.0;
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
     * @throws ValidationException
     */
    public function update(string $uuid, array $data): Komoditas
    {
        $komoditas = $this->findByUuid($uuid);

        return DB::transaction(function () use ($komoditas, $data, $uuid): Komoditas {
            if (isset($data['nama_komoditas']) && $data['nama_komoditas'] !== $komoditas->nama_komoditas) {
                $data['slug'] = $this->generateUniqueSlug((string) $data['nama_komoditas'], $uuid);
            }

            if (array_key_exists('batas_susut', $data)) {
                $data['batas_susut'] = $this->normalizeBatasSusut($data['batas_susut']);
            }

            $this->repository->update($komoditas, $data);

            return $komoditas->fresh();
        });
    }

    /**
     * Guard and normalize batas_susut to ensure decimal dot format and range.
     *
     * @throws ValidationException
     */
    private function normalizeBatasSusut(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if (str_contains($trimmed, ',') || ! preg_match('/^(?:100(?:\.0{1,2})?|\d{1,2}(?:\.\d{1,2})?)$/', $trimmed)) {
                throw ValidationException::withMessages([
                    'batas_susut' => 'Batas susut hanya menerima format angka desimal dengan titik (contoh: 2.5).',
                ]);
            }

            return (float) $trimmed;
        }

        if (is_numeric($value)) {
            $num = (float) $value;
            if ($num < 0 || $num > 100) {
                throw ValidationException::withMessages([
                    'batas_susut' => 'Batas susut harus berada dalam rentang 0 hingga 100 persen.',
                ]);
            }

            return round($num, 2);
        }

        throw ValidationException::withMessages([
            'batas_susut' => 'Format batas susut tidak valid.',
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
     * Get available category options.
     *
     * @return list<string>
     */
    public function getKategoriOptions(): array
    {
        return self::KATEGORI_OPTIONS;
    }
}
