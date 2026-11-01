<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\CommodityCategory;
use App\Repositories\Contracts\CommodityCategoryRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class CommodityCategoryRepository implements CommodityCategoryRepositoryInterface
{
    public function __construct(
        protected CommodityCategory $model
    ) {}

    /**
     * Get active categories ordered by name.
     *
     * @return Collection<int, CommodityCategory>
     */
    public function getActive(): Collection
    {
        return $this->model->newQuery()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'uuid', 'name']);
    }

    /**
     * Find a category by its UUID.
     */
    public function findByUuid(string $uuid): ?CommodityCategory
    {
        return $this->model->newQuery()->where('uuid', $uuid)->first();
    }
}
