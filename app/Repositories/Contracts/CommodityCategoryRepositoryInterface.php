<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\CommodityCategory;
use Illuminate\Database\Eloquent\Collection;

interface CommodityCategoryRepositoryInterface
{
    /**
     * Get active categories ordered by name.
     *
     * @return Collection<int, CommodityCategory>
     */
    public function getActive(): Collection;

    /**
     * Find a category by its UUID.
     */
    public function findByUuid(string $uuid): ?CommodityCategory;
}
