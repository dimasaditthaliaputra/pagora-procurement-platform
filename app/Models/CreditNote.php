<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CreditNoteStatus;
use Database\Factories\CreditNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bill reduction produced by a dispute (FR-41, BR-16). Immutable once `confirmed` (BR-17).
 */
#[Fillable([
    'uuid',
    'credit_note_number',
    'invoice_id',
    'dispute_id',
    'credit_date',
    'amount',
    'reason',
    'status',
    'vendor_confirmed_by',
    'vendor_confirmed_at',
    'created_by',
])]
#[Hidden(['id', 'invoice_id', 'dispute_id', 'vendor_confirmed_by', 'created_by'])]
class CreditNote extends Model
{
    /** @use HasFactory<CreditNoteFactory> */
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
            'status' => CreditNoteStatus::class,
            'credit_date' => 'date',
            'amount' => 'decimal:2',
            'vendor_confirmed_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function sengketa(): BelongsTo
    {
        return $this->belongsTo(Sengketa::class, 'dispute_id');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function vendorConfirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_confirmed_by');
    }
}
