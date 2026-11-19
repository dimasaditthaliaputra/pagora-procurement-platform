<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PrediksiHarga;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Contains Harga Pagu: only for Tenant pages (BR-02), never for Vendors.
 *
 * @mixin PrediksiHarga
 */
class PrediksiHargaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'prediction_date' => $this->prediction_date?->toDateString(),
            'predicted_price' => (float) $this->predicted_price,
            'recommended_ceiling_price' => (float) $this->recommended_ceiling_price,
            'calculated_at' => $this->calculated_at?->toIso8601String(),
        ];
    }
}
