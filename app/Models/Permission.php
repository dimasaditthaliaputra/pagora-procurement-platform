<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RoleName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $module
 * @property string $action
 * @property string $label
 * @property string|null $description
 * @property list<string> $assignable_roles
 * @property int $sort_order
 */
#[Fillable([
    'uuid',
    'name',
    'module',
    'action',
    'label',
    'description',
    'assignable_roles',
    'sort_order',
])]
#[Hidden(['id', 'pivot'])]
class Permission extends Model
{
    use HasUuids;

    protected $table = 'permissions';

    /**
     * Columns that store UUIDs.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assignable_roles' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Roles that currently hold this permission.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'permission_role');
    }

    /**
     * Determine whether this permission may be given to the role.
     */
    public function isAssignableTo(RoleName $role): bool
    {
        return in_array($role->value, $this->assignable_roles, true);
    }
}
