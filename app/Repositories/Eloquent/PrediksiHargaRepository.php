<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\PrediksiHarga;
use App\Repositories\Contracts\PrediksiHargaRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PrediksiHargaRepository implements PrediksiHargaRepositoryInterface
{
    public function __construct(
        protected PrediksiHarga $model
    ) {}

    public function createBatch(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $this->model->newQuery()->insert(array_map(
            fn (array $row): array => ['uuid' => (string) Str::uuid(), ...$row],
            $rows
        ));
    }

    public function latestBatch(int $commodityId): Collection
    {
        return $this->latestBatchQuery($commodityId)->orderBy('prediction_date')->get();
    }

    public function latestCeiling(int $commodityId): ?PrediksiHarga
    {
        return $this->latestBatchQuery($commodityId)
            ->orderByDesc('recommended_ceiling_price')
            ->orderBy('prediction_date')
            ->first();
    }

    /**
     * @return Builder<PrediksiHarga>
     */
    private function latestBatchQuery(int $commodityId): Builder
    {
        $latestRun = $this->model->newQuery()
            ->where('commodity_id', $commodityId)
            ->max('calculated_at');

        return $this->model->newQuery()
            ->where('commodity_id', $commodityId)
            ->when($latestRun === null, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when($latestRun !== null, fn (Builder $query) => $query->where('calculated_at', $latestRun));
    }
}
