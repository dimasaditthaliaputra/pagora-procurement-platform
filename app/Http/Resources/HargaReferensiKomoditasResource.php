<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\HargaReferensiKomoditas;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin HargaReferensiKomoditas
 */
class HargaReferensiKomoditasResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'date' => $this->date?->toDateString(),
            'price' => (float) $this->price,
            'source' => $this->source,
        ];
    }
}
