<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\PrediksiHarga;
use Illuminate\Support\Collection;

/**
 * Prediction rows are referenced by purchase orders, so only create and read exist.
 */
interface PrediksiHargaRepositoryInterface
{
    /**
     * Insert one forecast run (all rows share `calculated_at`).
     *
     * @param  list<array{commodity_id: int, prediction_date: string, predicted_price: float, recommended_ceiling_price: float, calculated_at: mixed}>  $rows
     */
    public function createBatch(array $rows): void;

    /**
     * Rows of the most recent forecast run for a commodity, ordered by prediction date.
     *
     * @return Collection<int, PrediksiHarga>
     */
    public function latestBatch(int $commodityId): Collection;

    /**
     * Row holding the highest recommended ceiling price in the most recent forecast run.
     */
    public function latestCeiling(int $commodityId): ?PrediksiHarga;
}
