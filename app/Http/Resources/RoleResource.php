<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'label' => $this->roleName()?->label() ?? ucfirst($this->name),
            'description' => $this->description,
            'users_count' => (int) ($this->users_count ?? 0),
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('name')->values()),
            'can' => [
                'update' => (bool) $request->user()?->can('update', $this->resource),
            ],
        ];
    }
}
