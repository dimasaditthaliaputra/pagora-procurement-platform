<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NegotiationMessageType;
use App\Enums\NegotiationStatus;
use App\Enums\RoleName;
use App\Enums\StatusPurchaseOrder;
use App\Models\ChatNegosiasi;
use App\Models\PoItem;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Repositories\Contracts\ChatNegosiasiRepositoryInterface;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Smart Negotiation Chat (UC-12, UC-13, FR-19..21, BR-06, BR-08).
 *
 * Purchasing proposes a unit price per item, the Vendor accepts or counters, Purchasing accepts
 * a counter, proposes again, or drops the item. Once every remaining item is agreed the PO is
 * locked as agreed; when every item is dropped the PO is cancelled. Messages are immutable.
 */
class NegotiationService
{
    /** NFR-12: a message reaches the other party within 5 seconds. */
    public const POLL_INTERVAL_MS = 3000;

    public const POLL_BATCH_LIMIT = 100;

    public const SIDE_TENANT = 'tenant';

    public const SIDE_VENDOR = 'vendor';

    public function __construct(
        protected PurchaseOrderRepositoryInterface $purchaseOrders,
        protected ChatNegosiasiRepositoryInterface $messages,
        protected UserRepositoryInterface $users,
        protected NotificationService $notifications,
        protected PricePredictionService $prices,
    ) {}

    /**
     * Everything the negotiation page needs on first load.
     *
     * @return array{purchase_order: PurchaseOrder, side: string, messages: Collection<int, ChatNegosiasi>, ceilings: array<int, float>}
     *
     * @throws ModelNotFoundException|AuthorizationException
     */
    public function room(User $actor, string $uuid): array
    {
        $purchaseOrder = $this->findForUser($actor, $uuid, 'negosiasi');
        $side = $this->sideOf($actor, $purchaseOrder);

        return [
            'purchase_order' => $purchaseOrder,
            'side' => $side,
            'messages' => $this->messages->forPurchaseOrder($purchaseOrder->id),
            'ceilings' => $side === self::SIDE_TENANT ? $this->ceilingsFor($purchaseOrder) : [],
        ];
    }

    /**
     * Polling: messages written after `$afterUuid` plus the current item and PO state.
     *
     * @return array{purchase_order: PurchaseOrder, side: string, messages: Collection<int, ChatNegosiasi>}
     *
     * @throws ModelNotFoundException|AuthorizationException
     */
    public function updates(User $actor, string $uuid, ?string $afterUuid): array
    {
        $purchaseOrder = $this->findForUser($actor, $uuid, 'negosiasi');

        return [
            'purchase_order' => $purchaseOrder,
            'side' => $this->sideOf($actor, $purchaseOrder),
            'messages' => $this->messages->forPurchaseOrder($purchaseOrder->id, $afterUuid, self::POLL_BATCH_LIMIT),
        ];
    }

    /**
     * @throws ModelNotFoundException|AuthorizationException
     */
    public function sendMessage(User $actor, string $uuid, string $text): ChatNegosiasi
    {
        $purchaseOrder = $this->findForUser($actor, $uuid, 'tawar');

        $message = $this->messages->create([
            'purchase_order_id' => $purchaseOrder->id,
            'sender_id' => $actor->id,
            'type' => NegotiationMessageType::Text,
            'message' => $text,
        ]);

        $this->notifyCounterpart($actor, $purchaseOrder, 'Pesan baru', "Pesan baru di negosiasi PO {$purchaseOrder->nomor_po}.");

        return $this->messages->withDetails($message);
    }

    /**
     * Purchasing proposes a unit price, or the Vendor counters with another one.
     *
     * @throws ModelNotFoundException|AuthorizationException|ValidationException
     */
    public function offer(User $actor, string $uuid, string $itemUuid, float $unitPrice, ?string $note): void
    {
        $purchaseOrder = $this->findForUser($actor, $uuid, 'tawar');
        $side = $this->sideOf($actor, $purchaseOrder);

        $itemName = DB::transaction(function () use ($actor, $purchaseOrder, $side, $itemUuid, $unitPrice, $note): string {
            $locked = $this->lockOpen($purchaseOrder);
            $item = $this->itemOf($locked, $itemUuid);

            if (! self::itemActions($side, $item)['offer']) {
                throw $this->staleItem();
            }

            $this->purchaseOrders->updateItem($item, $side === self::SIDE_TENANT
                ? [
                    'harga_satuan_po' => $unitPrice,
                    'subtotal' => $this->subtotal($item, $unitPrice),
                    'status_item' => NegotiationStatus::Pending,
                ]
                : [
                    'harga_tawar_vendor' => $unitPrice,
                    'status_item' => NegotiationStatus::Countered,
                ]);

            $this->messages->create([
                'purchase_order_id' => $locked->id,
                'purchase_order_item_id' => $item->id,
                'sender_id' => $actor->id,
                'type' => NegotiationMessageType::Offer,
                'offer_amount' => $unitPrice,
                'message' => $note,
            ]);

            return (string) $item->komoditas?->name;
        });

        $who = $side === self::SIDE_TENANT ? 'Purchasing' : 'Vendor';
        $this->notifyCounterpart(
            $actor,
            $purchaseOrder,
            'Tawaran harga baru',
            "{$who} mengajukan {$this->rupiah($unitPrice)}/satuan untuk {$itemName} di PO {$purchaseOrder->nomor_po}."
        );
    }

    /**
     * Accept the other party's latest price for an item.
     *
     * @throws ModelNotFoundException|AuthorizationException|ValidationException
     */
    public function accept(User $actor, string $uuid, string $itemUuid): void
    {
        $purchaseOrder = $this->findForUser($actor, $uuid, 'setujui');
        $side = $this->sideOf($actor, $purchaseOrder);

        [$itemName, $outcome] = DB::transaction(function () use ($actor, $purchaseOrder, $side, $itemUuid): array {
            $locked = $this->lockOpen($purchaseOrder);
            $item = $this->itemOf($locked, $itemUuid);

            if (! self::itemActions($side, $item)['accept']) {
                throw $this->staleItem();
            }

            $finalPrice = (float) ($side === self::SIDE_TENANT ? $item->harga_tawar_vendor : $item->harga_satuan_po);

            $this->purchaseOrders->updateItem($item, [
                'harga_satuan_po' => $finalPrice,
                'subtotal' => $this->subtotal($item, $finalPrice),
                'status_item' => NegotiationStatus::Agreed,
            ]);

            $this->messages->create([
                'purchase_order_id' => $locked->id,
                'purchase_order_item_id' => $item->id,
                'sender_id' => $actor->id,
                'type' => NegotiationMessageType::Acceptance,
                'offer_amount' => $finalPrice,
            ]);

            return [(string) $item->komoditas?->name, $this->settle($actor, $locked)];
        });

        $this->notifyCounterpart($actor, $purchaseOrder, 'Harga item disepakati', "Harga {$itemName} di PO {$purchaseOrder->nomor_po} telah disepakati.");
        $this->notifyOutcome($purchaseOrder, $outcome);
    }

    /**
     * Purchasing drops an item that could not be agreed (Q-24, BR-06).
     *
     * @throws ModelNotFoundException|AuthorizationException|ValidationException
     */
    public function removeItem(User $actor, string $uuid, string $itemUuid, ?string $reason): void
    {
        $purchaseOrder = $this->findForUser($actor, $uuid, 'keluarkanItem');

        [$itemName, $outcome] = DB::transaction(function () use ($actor, $purchaseOrder, $itemUuid, $reason): array {
            $locked = $this->lockOpen($purchaseOrder);
            $item = $this->itemOf($locked, $itemUuid);

            if (! self::itemActions(self::SIDE_TENANT, $item)['remove']) {
                throw $this->staleItem();
            }

            $this->purchaseOrders->updateItem($item, ['status_item' => NegotiationStatus::Removed]);

            $this->messages->create([
                'purchase_order_id' => $locked->id,
                'purchase_order_item_id' => $item->id,
                'sender_id' => $actor->id,
                'type' => NegotiationMessageType::ItemRemoved,
                'message' => $reason,
            ]);

            return [(string) $item->komoditas?->name, $this->settle($actor, $locked)];
        });

        $this->notifyCounterpart($actor, $purchaseOrder, 'Item dikeluarkan', "{$itemName} dikeluarkan dari PO {$purchaseOrder->nomor_po}.");
        $this->notifyOutcome($purchaseOrder, $outcome);
    }

    /**
     * Opening entry of the chat when Purchasing submits the draft (UC-12 step 5). No prices:
     * estimates stay internal (BR-02); Purchasing proposes per item in the chat.
     */
    public function recordStart(User $actor, PurchaseOrder $purchaseOrder): void
    {
        $this->messages->create([
            'purchase_order_id' => $purchaseOrder->id,
            'sender_id' => $actor->id,
            'type' => NegotiationMessageType::System,
            'message' => 'Purchase Order diajukan untuk negosiasi. Purchasing akan mengajukan harga per item.',
        ]);
    }

    /**
     * Audit entry when Purchasing cancels the PO during negotiation (UC-12 alternative 7b).
     */
    public function recordCancellation(User $actor, PurchaseOrder $purchaseOrder, string $reason): void
    {
        $this->messages->create([
            'purchase_order_id' => $purchaseOrder->id,
            'sender_id' => $actor->id,
            'type' => NegotiationMessageType::System,
            'message' => 'Purchase Order dibatalkan. Alasan: '.$reason,
        ]);
    }

    /**
     * Which negotiation actions a side may take on an item right now.
     *
     * @return array{offer: bool, accept: bool, remove: bool}
     */
    public static function itemActions(string $side, PoItem $item): array
    {
        $status = $item->status_item;
        $tenantProposed = $item->harga_satuan_po !== null;

        if ($side === self::SIDE_TENANT) {
            return [
                'offer' => $status?->isOpen() ?? false,
                'accept' => $status === NegotiationStatus::Countered && $item->harga_tawar_vendor !== null,
                'remove' => $status?->isOpen() ?? false,
            ];
        }

        return [
            'offer' => ($status === NegotiationStatus::Pending && $tenantProposed) || $status === NegotiationStatus::Countered,
            'accept' => $status === NegotiationStatus::Pending && $tenantProposed,
            'remove' => false,
        ];
    }

    public function sideOf(User $actor, PurchaseOrder $purchaseOrder): string
    {
        return $actor->vendor_id !== null && (int) $actor->vendor_id === (int) $purchaseOrder->vendor_id
            ? self::SIDE_VENDOR
            : self::SIDE_TENANT;
    }

    /**
     * @throws ModelNotFoundException|AuthorizationException
     */
    private function findForUser(User $actor, string $uuid, string $ability): PurchaseOrder
    {
        $purchaseOrder = $this->purchaseOrders->findByUuid(
            $uuid,
            $actor->tenant_id !== null ? (int) $actor->tenant_id : null,
            $actor->vendor_id !== null ? (int) $actor->vendor_id : null,
        ) ?? throw (new ModelNotFoundException)->setModel(PurchaseOrder::class, [$uuid]);

        Gate::forUser($actor)->authorize('view', $purchaseOrder);
        Gate::forUser($actor)->authorize($ability, $purchaseOrder);

        return $purchaseOrder;
    }

    /**
     * @throws ValidationException
     */
    private function lockOpen(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        $locked = $this->purchaseOrders->lockForNegotiation($purchaseOrder->id);

        // Another request may have closed the negotiation since authorization.
        if ($locked->status !== StatusPurchaseOrder::Negosiasi) {
            throw ValidationException::withMessages([
                'status' => 'Negosiasi untuk Purchase Order ini sudah ditutup.',
            ]);
        }

        return $locked;
    }

    /**
     * @throws ModelNotFoundException
     */
    private function itemOf(PurchaseOrder $purchaseOrder, string $itemUuid): PoItem
    {
        return $purchaseOrder->poItems->firstWhere('uuid', $itemUuid)
            ?? throw (new ModelNotFoundException)->setModel(PoItem::class, [$itemUuid]);
    }

    private function staleItem(): ValidationException
    {
        return ValidationException::withMessages([
            'item' => 'Status item sudah berubah. Muat ulang halaman untuk melihat tawaran terbaru.',
        ]);
    }

    /**
     * Close the negotiation when no item is open any more (FR-21, BR-06).
     *
     * @return StatusPurchaseOrder|null The new PO status, or null while items are still open.
     */
    private function settle(User $actor, PurchaseOrder $purchaseOrder): ?StatusPurchaseOrder
    {
        // The locked PO's items were updated in place, so this already reflects the current action.
        $items = $purchaseOrder->poItems;

        if ($items->contains(fn (PoItem $item): bool => $item->status_item?->isOpen() ?? false)) {
            return null;
        }

        $agreed = $items->where('status_item', NegotiationStatus::Agreed);

        if ($agreed->isEmpty()) {
            $reason = 'Seluruh item dikeluarkan karena harga tidak disepakati.';
            $this->purchaseOrders->update($purchaseOrder, [
                'status' => StatusPurchaseOrder::Dibatalkan->value,
                'alasan_pembatalan' => $reason,
            ]);
            $this->recordCancellation($actor, $purchaseOrder, $reason);

            return StatusPurchaseOrder::Dibatalkan;
        }

        $this->purchaseOrders->update($purchaseOrder, [
            'status' => StatusPurchaseOrder::Disepakati->value,
            'locked_at' => now(),
        ]);

        $this->messages->create([
            'purchase_order_id' => $purchaseOrder->id,
            'sender_id' => $actor->id,
            'type' => NegotiationMessageType::System,
            'message' => "Semua item disepakati dengan total {$this->rupiah((float) $agreed->sum('subtotal'))}. Dokumen PO dan harga dikunci; chat kini hanya-baca.",
        ]);

        return StatusPurchaseOrder::Disepakati;
    }

    /**
     * Current Harga Pagu per commodity, for the Purchasing side only (BR-02).
     *
     * @return array<int, float>
     */
    private function ceilingsFor(PurchaseOrder $purchaseOrder): array
    {
        return $purchaseOrder->poItems
            ->pluck('komoditas_id')
            ->unique()
            ->mapWithKeys(fn (int $commodityId): array => [
                $commodityId => $this->prices->latestCeiling($commodityId)?->recommended_ceiling_price,
            ])
            ->filter()
            ->map(fn ($price): float => (float) $price)
            ->all();
    }

    private function subtotal(PoItem $item, float $unitPrice): float
    {
        return round((float) $item->jumlah_pesanan * $unitPrice, 2);
    }

    private function rupiah(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    private function notifyOutcome(PurchaseOrder $purchaseOrder, ?StatusPurchaseOrder $outcome): void
    {
        if ($outcome === StatusPurchaseOrder::Disepakati) {
            $this->notifyBothSides($purchaseOrder, 'PO disepakati', "Semua item PO {$purchaseOrder->nomor_po} telah disepakati. Dokumen PO dikunci.");
        } elseif ($outcome === StatusPurchaseOrder::Dibatalkan) {
            $this->notifyBothSides($purchaseOrder, 'PO dibatalkan', "PO {$purchaseOrder->nomor_po} dibatalkan karena seluruh item dikeluarkan dari negosiasi.");
        }
    }

    private function notifyCounterpart(User $actor, PurchaseOrder $purchaseOrder, string $title, string $message): void
    {
        $recipients = $this->sideOf($actor, $purchaseOrder) === self::SIDE_TENANT
            ? $this->vendorRecipients($purchaseOrder)
            : $this->tenantRecipients($purchaseOrder);

        $this->send($recipients, $purchaseOrder, $title, $message);
    }

    private function notifyBothSides(PurchaseOrder $purchaseOrder, string $title, string $message): void
    {
        $this->send($this->tenantRecipients($purchaseOrder)->merge($this->vendorRecipients($purchaseOrder)), $purchaseOrder, $title, $message);
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    private function send(Collection $recipients, PurchaseOrder $purchaseOrder, string $title, string $message): void
    {
        $this->notifications->sendToMany(
            $recipients,
            'negotiation',
            "{$title}: {$purchaseOrder->nomor_po}",
            $message,
            "/purchase-order/{$purchaseOrder->uuid}/negosiasi",
            ['po_uuid' => $purchaseOrder->uuid, 'nomor_po' => $purchaseOrder->nomor_po],
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function tenantRecipients(PurchaseOrder $purchaseOrder): Collection
    {
        return $this->users->getActiveByTenantAndRoles(
            (int) $purchaseOrder->perusahaan_buyer_id,
            [RoleName::Owner->value, RoleName::Purchasing->value],
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function vendorRecipients(PurchaseOrder $purchaseOrder): Collection
    {
        return $this->users->getActiveByVendor((int) $purchaseOrder->vendor_id);
    }
}
