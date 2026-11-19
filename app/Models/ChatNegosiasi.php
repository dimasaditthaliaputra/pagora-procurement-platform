<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NegotiationMessageType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Negotiation chat entry (`negotiation_messages`). Immutable audit trail (NFR-07, BR-17):
 * blocked here and by a PostgreSQL trigger.
 */
#[Fillable([
    'uuid',
    'sender_id',
    'purchase_order_id',
    'purchase_order_item_id',
    'type',
    'message',
    'offer_amount',
])]
#[Hidden(['id', 'sender_id', 'purchase_order_id', 'purchase_order_item_id'])]
class ChatNegosiasi extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'negotiation_messages';

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
     * Get route key name for model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => NegotiationMessageType::class,
            'offer_amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Records are immutable: block updates at the application level.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new \LogicException('ChatNegosiasi bersifat immutable dan tidak dapat diubah.');
    }

    /**
     * Records are immutable: block deletes at the application level.
     */
    public function delete(): ?bool
    {
        throw new \LogicException('ChatNegosiasi bersifat immutable dan tidak dapat dihapus.');
    }

    public function pengirim(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(PoItem::class, 'purchase_order_item_id');
    }
}
