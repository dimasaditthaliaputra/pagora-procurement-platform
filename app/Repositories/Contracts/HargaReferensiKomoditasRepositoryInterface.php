<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\HargaReferensiKomoditas;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface HargaReferensiKomoditasRepositoryInterface
{
    /**
     * Latest stored price date for a commodity from the given source.
     */
    public function latestDate(int $commodityId, string $source): ?CarbonInterface;

    /**
     * Insert or update daily prices keyed by (commodity, date, source).
     *
     * @param  list<array{date: string, price: float}>  $points
     */
    public function upsertMany(int $commodityId, string $source, array $points): int;

    /**
     * Daily prices since `$from`, oldest first. Bank Indonesia data wins over seeded demo data.
     *
     * @return Collection<int, HargaReferensiKomoditas>
     */
    public function history(int $commodityId, CarbonInterface $from): Collection;

    /**
     * Most recent stored price for a commodity, preferring Bank Indonesia data.
     */
    public function latest(int $commodityId): ?HargaReferensiKomoditas;
}
