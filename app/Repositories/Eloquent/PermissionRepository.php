<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Permission;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class PermissionRepository implements PermissionRepositoryInterface
{
    public function __construct(
        private readonly Permission $model
    ) {}

    /**
     * All permissions ordered by module and catalog order.
     *
     * @return Collection<int, Permission>
     */
    public function allOrdered(): Collection
    {
        return $this->model->newQuery()->orderBy('sort_order')->get();
    }

    /**
     * Permissions matching the given names.
     *
     * @param  list<string>  $names
     * @return Collection<int, Permission>
     */
    public function findByNames(array $names): Collection
    {
        return $this->model->newQuery()->whereIn('name', $names)->get();
    }
}
