<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PurchaseOrder;
use App\Services\PenerimaanBarangService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Explicit resource mapping for an `"In Transit"` (`dalam_pengiriman`) Purchase Order card
 * ready for QC Gudang inspection (`UC-15`, `BR-02`, `BR-23`, `RULES` §5.1).
 *
 * @mixin PurchaseOrder
 */
class InspectableOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return app(PenerimaanBarangService::class)->formatInspectablePurchaseOrder($this->resource);
    }
}
