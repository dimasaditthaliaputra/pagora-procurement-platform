<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Collection;

interface PermissionRepositoryInterface
{
    /**
     * All permissions ordered by module and catalog order.
     *
     * @return Collection<int, Permission>
     */
    public function allOrdered(): Collection;

    /**
     * Permissions matching the given names.
     *
     * @param  list<string>  $names
     * @return Collection<int, Permission>
     */
    public function findByNames(array $names): Collection;
}
