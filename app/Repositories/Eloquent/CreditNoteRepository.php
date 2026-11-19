<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\CreditNoteStatus;
use App\Models\CreditNote;
use App\Repositories\Contracts\CreditNoteRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use LogicException;

class CreditNoteRepository implements CreditNoteRepositoryInterface
{
    public function __construct(
        protected CreditNote $model
    ) {}

    public function create(array $attributes): CreditNote
    {
        return $this->model->newQuery()->create(['uuid' => (string) Str::uuid(), ...$attributes]);
    }

    public function findByUuidForVendor(int $vendorId, string $uuid, bool $lock = false): ?CreditNote
    {
        return $this->model->newQuery()
            ->where('uuid', $uuid)
            ->whereHas('invoice', fn (Builder $q) => $q->where('vendor_id', $vendorId))
            ->with(['invoice', 'sengketa.purchaseOrder'])
            ->when($lock, fn (Builder $q) => $q->lockForUpdate())
            ->first();
    }

    public function update(CreditNote $creditNote, array $attributes): CreditNote
    {
        if ($creditNote->getOriginal('status') === CreditNoteStatus::Confirmed) {
            throw new LogicException('Credit note yang telah dikonfirmasi bersifat permanen dan tidak dapat diubah.');
        }

        $creditNote->fill($attributes)->save();

        return $creditNote;
    }

    public function sumConfirmedForInvoice(int $invoiceId): float
    {
        return (float) $this->model->newQuery()
            ->where('invoice_id', $invoiceId)
            ->where('status', CreditNoteStatus::Confirmed->value)
            ->sum('amount');
    }

    public function sumActiveForInvoice(int $invoiceId): float
    {
        return (float) $this->model->newQuery()
            ->where('invoice_id', $invoiceId)
            ->whereIn('status', [CreditNoteStatus::AwaitingVendorConfirmation->value, CreditNoteStatus::Confirmed->value])
            ->sum('amount');
    }

    public function hasAwaitingForDispute(int $disputeId): bool
    {
        return $this->model->newQuery()
            ->where('dispute_id', $disputeId)
            ->where('status', CreditNoteStatus::AwaitingVendorConfirmation->value)
            ->exists();
    }

    public function hasConfirmedForDispute(int $disputeId): bool
    {
        return $this->model->newQuery()
            ->where('dispute_id', $disputeId)
            ->where('status', CreditNoteStatus::Confirmed->value)
            ->exists();
    }

    public function getNextNumber(int $year): string
    {
        return NumberSequence::next($this->model->newQuery(), 'credit_note_number', sprintf('CN-%04d-', $year));
    }
}
