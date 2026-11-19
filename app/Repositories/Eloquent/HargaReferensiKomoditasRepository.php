<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\HargaReferensiKomoditas;
use App\Repositories\Contracts\HargaReferensiKomoditasRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class HargaReferensiKomoditasRepository implements HargaReferensiKomoditasRepositoryInterface
{
    public function __construct(
        protected HargaReferensiKomoditas $model
    ) {}

    public function latestDate(int $commodityId, string $source): ?CarbonInterface
    {
        $date = $this->model->newQuery()
            ->where('commodity_id', $commodityId)
            ->where('source', $source)
            ->max('date');

        return $date ? Carbon::parse($date)->startOfDay() : null;
    }

    public function upsertMany(int $commodityId, string $source, array $points): int
    {
        if ($points === []) {
            return 0;
        }

        $now = now();
        $rows = array_map(fn (array $point): array => [
            'uuid' => (string) Str::uuid(),
            'commodity_id' => $commodityId,
            'date' => $point['date'],
            'price' => $point['price'],
            'source' => $source,
            'created_at' => $now,
        ], $points);

        return $this->model->newQuery()->upsert($rows, ['commodity_id', 'date', 'source'], ['price']);
    }

    public function history(int $commodityId, CarbonInterface $from): Collection
    {
        return $this->preferredSource($commodityId)
            ->where('date', '>=', $from->toDateString())
            ->orderBy('date')
            ->get(['uuid', 'commodity_id', 'date', 'price', 'source']);
    }

    public function latest(int $commodityId): ?HargaReferensiKomoditas
    {
        return $this->preferredSource($commodityId)->orderByDesc('date')->first();
    }

    /**
     * Demo rows (`seeder`) are only used while no Bank Indonesia data exists yet,
     * so the two series are never mixed on one chart.
     *
     * @return Builder<HargaReferensiKomoditas>
     */
    private function preferredSource(int $commodityId): Builder
    {
        $hasBankIndonesia = $this->model->newQuery()
            ->where('commodity_id', $commodityId)
            ->where('source', HargaReferensiKomoditas::SOURCE_BANK_INDONESIA)
            ->exists();

        return $this->model->newQuery()
            ->where('commodity_id', $commodityId)
            ->when($hasBankIndonesia, fn (Builder $query) => $query->where('source', HargaReferensiKomoditas::SOURCE_BANK_INDONESIA));
    }
}
