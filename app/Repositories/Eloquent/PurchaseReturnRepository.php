<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\PurchaseReturnStatus;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Repositories\Contracts\PurchaseReturnRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use LogicException;

class PurchaseReturnRepository implements PurchaseReturnRepositoryInterface
{
    public function __construct(
        protected PurchaseReturn $model,
        protected PurchaseReturnItem $itemModel,
    ) {}

    public function createWithItems(array $attributes, array $items): PurchaseReturn
    {
        $purchaseReturn = $this->model->newQuery()->create(['uuid' => (string) Str::uuid(), ...$attributes]);

        foreach ($items as $item) {
            $purchaseReturn->items()->create(['uuid' => (string) Str::uuid(), ...$item]);
        }

        return $purchaseReturn;
    }

    public function findByUuidForVendor(int $vendorId, string $uuid, bool $lock = false): ?PurchaseReturn
    {
        return $this->model->newQuery()
            ->where('uuid', $uuid)
            ->whereHas('purchaseOrder', fn (Builder $q) => $q->where('vendor_id', $vendorId))
            ->with(['purchaseOrder', 'sengketa'])
            ->when($lock, fn (Builder $q) => $q->lockForUpdate())
            ->first();
    }

    public function update(PurchaseReturn $purchaseReturn, array $attributes): PurchaseReturn
    {
        $original = $purchaseReturn->getOriginal('status');
        $isCompleting = $original === PurchaseReturnStatus::Confirmed
            && array_keys($attributes) === ['status']
            && $attributes['status'] === PurchaseReturnStatus::Completed;

        if ($original?->isConfirmed() && ! $isCompleting) {
            throw new LogicException('Retur yang telah dikonfirmasi bersifat permanen; hanya dapat ditandai Selesai.');
        }

        $purchaseReturn->fill($attributes)->save();

        return $purchaseReturn;
    }

    public function returnedQuantityByReceiptItem(array $receiptItemIds): array
    {
        if ($receiptItemIds === []) {
            return [];
        }

        return $this->itemModel->newQuery()
            ->whereIn('goods_receipt_item_id', $receiptItemIds)
            ->whereHas('purchaseReturn', fn (Builder $q) => $q->where('status', '!=', PurchaseReturnStatus::Rejected->value))
            ->selectRaw('goods_receipt_item_id, SUM(quantity) AS total')
            ->groupBy('goods_receipt_item_id')
            ->pluck('total', 'goods_receipt_item_id')
            ->map(fn ($total): float => (float) $total)
            ->all();
    }

    public function hasAwaitingForDispute(int $disputeId): bool
    {
        return $this->model->newQuery()
            ->where('dispute_id', $disputeId)
            ->where('status', PurchaseReturnStatus::AwaitingVendorConfirmation->value)
            ->exists();
    }

    public function hasConfirmedForDispute(int $disputeId): bool
    {
        return $this->model->newQuery()
            ->where('dispute_id', $disputeId)
            ->whereIn('status', [PurchaseReturnStatus::Confirmed->value, PurchaseReturnStatus::Completed->value])
            ->exists();
    }

    public function getNextNumber(int $year): string
    {
        return NumberSequence::next($this->model->newQuery(), 'return_number', sprintf('RTR-%04d-', $year));
    }
}
