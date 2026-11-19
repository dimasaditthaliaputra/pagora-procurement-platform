<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ChatNegosiasi;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One negotiation chat entry. Offer amounts are unit prices both parties already exchanged,
 * so the payload is the same for Tenant and Vendor.
 *
 * @mixin ChatNegosiasi
 */
class ChatNegosiasiResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $sender = $this->pengirim;
        $item = $this->item;

        return [
            'uuid' => $this->uuid,
            'type' => $this->type?->value,
            'message' => $this->message,
            'offer_amount' => $this->offer_amount !== null ? (float) $this->offer_amount : null,
            'item' => $item ? [
                'uuid' => $item->uuid,
                'name' => $item->komoditas?->name,
                'unit' => $item->komoditas?->unit,
            ] : null,
            'sender' => [
                'name' => $sender?->name ?? 'Pengguna',
                'side' => $sender?->vendor_id !== null ? 'vendor' : 'tenant',
                'role_label' => $sender?->roleName()?->label(),
                'is_me' => $sender !== null && $request->user()?->id === $sender->id,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
