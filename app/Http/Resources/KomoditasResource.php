<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Komoditas;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Komoditas
 */
class KomoditasResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'slug' => $this->whenNotNull($this->slug),
            'name' => $this->name,
            'sku' => $this->sku,
            'commodity_category_uuid' => $this->whenLoaded('category', fn () => $this->category?->uuid),
            'category_name' => $this->whenLoaded('category', fn () => $this->category?->name),
            'unit' => $this->unit,
            'default_shrinkage_tolerance' => $this->default_shrinkage_tolerance !== null ? (float) $this->default_shrinkage_tolerance : null,
            'api_reference_code' => $this->api_reference_code,
            'specification' => $this->specification,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'can' => [
                'update' => (bool) $request->user()?->can('update', $this->resource),
                'delete' => (bool) $request->user()?->can('delete', $this->resource),
            ],
        ];
    }
}
