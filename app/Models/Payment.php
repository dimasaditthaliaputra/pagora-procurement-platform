<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One gateway payment of an invoice (FR-39). An invoice may have several, e.g. a retry after a failure (BR-26).
 */
#[Fillable([
    'uuid',
    'invoice_id',
    'payment_number',
    'gateway_order_id',
    'payment_date',
    'amount',
    'payment_method',
    'payment_status',
    'reference_number',
    'snap_token',
    'proof_file',
    'created_by',
])]
#[Hidden(['id', 'invoice_id', 'created_by', 'snap_token'])]
class Payment extends Model
{
    use HasFactory, HasUuids;

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'payment_status' => PaymentStatus::class,
            'payment_date' => 'datetime',
            'amount' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
