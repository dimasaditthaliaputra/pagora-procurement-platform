<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CreditNoteStatus;
use App\Enums\DisputeResolutionType;
use App\Enums\PurchaseReturnStatus;
use App\Enums\StatusItemPenerimaan;
use App\Enums\StatusPurchaseOrder;
use App\Enums\StatusSengketa;
use App\Models\CreditNote;
use App\Models\PenerimaanBarangItem;
use App\Models\PurchaseReturn;
use App\Models\Sengketa;
use App\Models\User;
use App\Repositories\Contracts\CreditNoteRepositoryInterface;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Repositories\Contracts\PurchaseReturnRepositoryInterface;
use App\Repositories\Contracts\SengketaRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Dispute settlement (UC-18 step 4–7, FR-38, FR-40, FR-41, FR-42, BR-15, BR-16, BR-17, BR-27).
 *
 * Owner/Manajemen propose a settlement (reshipment, return, or credit note), the Vendor of the PO confirms
 * or refuses it, and only then may Owner/Manajemen set the dispute to `resolved`. The PO status is never
 * touched here: it simply stops being frozen once its dispute is resolved (BR-14).
 */
class DisputeResolutionService
{
    public function __construct(
        protected readonly SengketaRepositoryInterface $sengketaRepository,
        protected readonly CreditNoteRepositoryInterface $creditNoteRepository,
        protected readonly PurchaseReturnRepositoryInterface $purchaseReturnRepository,
        protected readonly InvoiceRepositoryInterface $invoiceRepository,
        protected readonly NotificationService $notificationService,
    ) {}

    /**
     * What still blocks `resolved`, for Owner/Manajemen on the dispute page; null for everyone else.
     *
     * @return array{resolve_blocker: string|null}
     */
    public function context(User $actor, Sengketa $sengketa): array
    {
        return [
            'resolve_blocker' => Gate::forUser($actor)->allows('resolve', $sengketa) ? $this->resolutionBlocker($sengketa) : null,
        ];
    }

    /**
     * Data for the settlement form page (UC-18 step 4).
     *
     * @return array<string, mixed>
     *
     * @throws AuthorizationException
     */
    public function proposalForm(User $actor, Sengketa $sengketa): array
    {
        Gate::forUser($actor)->authorize('proposeResolution', $sengketa);

        return [
            'resolution_options' => DisputeResolutionType::options(),
            ...$this->formOptions($sengketa),
        ];
    }

    /**
     * Choices for the settlement form of a fully loaded dispute (see `SengketaRepository::findByUuidForActor`).
     *
     * @return array{
     *     creditable_invoices: list<array{uuid: string, number: string, total_amount: float, credited_amount: float, remaining_amount: float}>,
     *     returnable_items: list<array{receipt_item_uuid: string, commodity_name: string, unit: string, rejected_quantity: float, returned_quantity: float, remaining_quantity: float}>,
     *     can_reship: bool
     * }
     */
    public function formOptions(Sengketa $sengketa): array
    {
        $purchaseOrder = $sengketa->purchaseOrder;

        $creditableInvoices = $purchaseOrder->invoices->map(function ($invoice): array {
            $total = (float) $invoice->jumlah_tagihan;
            $credited = (float) $invoice->creditNotes
                ->filter(fn (CreditNote $note): bool => in_array($note->status, [CreditNoteStatus::AwaitingVendorConfirmation, CreditNoteStatus::Confirmed], true))
                ->sum('amount');

            return [
                'uuid' => $invoice->uuid,
                'number' => $invoice->nomor_invoice_vendor,
                'total_amount' => $total,
                'credited_amount' => $credited,
                'remaining_amount' => round(max(0.0, $total - $credited), 2),
            ];
        })->values()->all();

        $rejectedLines = $this->rejectedReceiptLines($sengketa);
        $returned = $this->purchaseReturnRepository->returnedQuantityByReceiptItem(
            $rejectedLines->pluck('id')->map(fn ($id): int => (int) $id)->all()
        );

        $returnableItems = $rejectedLines->map(function (PenerimaanBarangItem $line) use ($returned): array {
            $rejected = (float) $line->received_quantity;
            $alreadyReturned = $returned[(int) $line->id] ?? 0.0;
            $commodity = $line->poItem?->komoditas;

            return [
                'receipt_item_uuid' => $line->uuid,
                'commodity_name' => $commodity?->name ?? 'Komoditas',
                'unit' => $commodity?->unit ?? 'kg',
                'rejected_quantity' => $rejected,
                'returned_quantity' => $alreadyReturned,
                'remaining_quantity' => round(max(0.0, $rejected - $alreadyReturned), 3),
            ];
        })->values()->all();

        return [
            'creditable_invoices' => $creditableInvoices,
            'returnable_items' => $returnableItems,
            'can_reship' => $purchaseOrder->status === StatusPurchaseOrder::PartiallyReceived,
        ];
    }

    /**
     * Owner/Manajemen choose the settlement and issue its document for the Vendor to confirm (UC-18 step 4–5).
     *
     * @param  array{
     *     resolution_type: string,
     *     resolution_notes?: string|null,
     *     invoice_uuid?: string|null,
     *     amount?: numeric-string|float|null,
     *     document_date?: string|null,
     *     items?: list<array{receipt_item_uuid: string, quantity: numeric-string|float, notes?: string|null}>
     * }  $data
     *
     * @throws AuthorizationException|ModelNotFoundException|ValidationException
     */
    public function propose(User $actor, string $uuid, array $data): Sengketa
    {
        $type = DisputeResolutionType::from($data['resolution_type']);
        $notes = trim((string) ($data['resolution_notes'] ?? ''));

        $sengketa = DB::transaction(function () use ($actor, $uuid, $type, $notes, $data): Sengketa {
            $sengketa = $this->lockForActor($actor, $uuid);
            Gate::forUser($actor)->authorize('proposeResolution', $sengketa);

            if ($this->hasAwaitingDocument($sengketa)) {
                throw ValidationException::withMessages([
                    'resolution_type' => 'Masih ada dokumen penyelesaian yang menunggu konfirmasi Vendor.',
                ]);
            }

            if ($sengketa->resolution_type !== null && $sengketa->resolution_type !== $type && $sengketa->vendor_confirmed_at !== null) {
                throw ValidationException::withMessages([
                    'resolution_type' => "Vendor sudah mengonfirmasi penyelesaian {$sengketa->resolution_type->label()}; jenis penyelesaian tidak dapat diganti.",
                ]);
            }

            $detail = match ($type) {
                DisputeResolutionType::Reshipment => $this->assertReshipmentAllowed($sengketa),
                DisputeResolutionType::CreditNote => $this->issueCreditNote($actor, $sengketa, $data, $notes),
                DisputeResolutionType::Return => $this->issuePurchaseReturn($actor, $sengketa, $data, $notes),
            };

            // A new type (or a reshipment asked again) needs a fresh Vendor confirmation; extra credit notes keep the earlier one.
            $resetsConfirmation = $sengketa->resolution_type !== $type || $type === DisputeResolutionType::Reshipment;

            $this->sengketaRepository->update($sengketa, [
                'resolution_type' => $type,
                'resolution_notes' => $notes !== '' ? $notes : $sengketa->resolution_notes,
                'status' => StatusSengketa::AwaitingResponse,
                ...($resetsConfirmation ? ['vendor_confirmed_by' => null, 'vendor_confirmed_at' => null] : []),
            ]);

            $this->log($sengketa, $actor, 'resolution', "Penyelesaian diajukan: {$type->label()}. {$detail}".($notes !== '' ? " Catatan: {$notes}" : '').' Menunggu konfirmasi Vendor.');

            return $sengketa;
        });

        $this->notify($actor, $sengketa, 'Penyelesaian Sengketa Diajukan', "{$actor->name} mengajukan penyelesaian {$type->label()} untuk sengketa PO {$this->poNumber($sengketa)}. Mohon konfirmasi.");

        return $sengketa;
    }

    /**
     * The Vendor accepts or refuses a proposed reshipment (UC-18 step 5, 5a).
     *
     * @throws AuthorizationException|ModelNotFoundException|ValidationException
     */
    public function decideReshipment(User $actor, string $uuid, bool $accepted, ?string $reason = null): Sengketa
    {
        $sengketa = DB::transaction(function () use ($actor, $uuid, $accepted, $reason): Sengketa {
            $sengketa = $this->lockForActor($actor, $uuid);
            Gate::forUser($actor)->authorize('respond', $sengketa);

            if ($sengketa->resolution_type !== DisputeResolutionType::Reshipment || $sengketa->vendor_confirmed_at !== null) {
                throw ValidationException::withMessages([
                    'resolution' => 'Tidak ada pengajuan pengiriman ulang yang menunggu konfirmasi Anda.',
                ]);
            }

            $this->sengketaRepository->update($sengketa, $accepted
                ? ['vendor_confirmed_by' => $actor->id, 'vendor_confirmed_at' => now(), 'status' => StatusSengketa::InMediation]
                : ['resolution_type' => null, 'status' => StatusSengketa::InMediation]);

            $this->log($sengketa, $actor, 'resolution', $accepted
                ? 'Vendor menyetujui penyelesaian Pengiriman Ulang dan akan mengirim ulang item yang ditolak.'
                : 'Vendor menolak penyelesaian Pengiriman Ulang.'.$this->reasonSuffix($reason));

            return $sengketa;
        });

        $this->notify($actor, $sengketa, $accepted ? 'Pengiriman Ulang Disetujui Vendor' : 'Pengiriman Ulang Ditolak Vendor', "{$actor->name} ".($accepted ? 'menyetujui' : 'menolak')." pengiriman ulang untuk sengketa PO {$this->poNumber($sengketa)}.");

        return $sengketa;
    }

    /**
     * The Vendor confirms or refuses a credit note; only a confirmed one reduces the bill (BR-16).
     *
     * @throws AuthorizationException|ModelNotFoundException
     */
    public function decideCreditNote(User $actor, string $creditNoteUuid, bool $accepted, ?string $reason = null): CreditNote
    {
        $creditNote = DB::transaction(function () use ($actor, $creditNoteUuid, $accepted, $reason): CreditNote {
            $creditNote = $this->creditNoteRepository->findByUuidForVendor((int) $actor->vendor_id, $creditNoteUuid, lock: true)
                ?? throw new ModelNotFoundException('Credit note tidak ditemukan.');
            Gate::forUser($actor)->authorize($accepted ? 'confirm' : 'reject', $creditNote);

            $sengketa = $this->lockForActor($actor, $creditNote->sengketa->uuid);

            $this->creditNoteRepository->update($creditNote, $accepted
                ? ['status' => CreditNoteStatus::Confirmed, 'vendor_confirmed_by' => $actor->id, 'vendor_confirmed_at' => now()]
                : ['status' => CreditNoteStatus::Rejected]);

            $this->sengketaRepository->update($sengketa, $accepted
                ? ['vendor_confirmed_by' => $actor->id, 'vendor_confirmed_at' => now(), 'status' => StatusSengketa::InMediation]
                : ['status' => StatusSengketa::InMediation]);

            $amount = $this->rupiah((float) $creditNote->amount);
            $this->log($sengketa, $actor, 'resolution', $accepted
                ? "Vendor mengonfirmasi credit note {$creditNote->credit_note_number} sebesar {$amount}."
                : "Vendor menolak credit note {$creditNote->credit_note_number} sebesar {$amount}.".$this->reasonSuffix($reason));

            return $creditNote;
        });

        $this->notify($actor, $creditNote->sengketa, $accepted ? 'Credit Note Dikonfirmasi Vendor' : 'Credit Note Ditolak Vendor', "{$actor->name} ".($accepted ? 'mengonfirmasi' : 'menolak')." credit note {$creditNote->credit_note_number}.");

        return $creditNote;
    }

    /**
     * The Vendor confirms or refuses a purchase return (BR-27).
     *
     * @throws AuthorizationException|ModelNotFoundException
     */
    public function decidePurchaseReturn(User $actor, string $returnUuid, bool $accepted, ?string $reason = null): PurchaseReturn
    {
        $purchaseReturn = DB::transaction(function () use ($actor, $returnUuid, $accepted, $reason): PurchaseReturn {
            $purchaseReturn = $this->purchaseReturnRepository->findByUuidForVendor((int) $actor->vendor_id, $returnUuid, lock: true)
                ?? throw new ModelNotFoundException('Dokumen retur tidak ditemukan.');
            Gate::forUser($actor)->authorize($accepted ? 'confirm' : 'reject', $purchaseReturn);

            $sengketa = $this->lockForActor($actor, $purchaseReturn->sengketa->uuid);

            $this->purchaseReturnRepository->update($purchaseReturn, $accepted
                ? ['status' => PurchaseReturnStatus::Confirmed, 'vendor_confirmed_by' => $actor->id, 'vendor_confirmed_at' => now()]
                : ['status' => PurchaseReturnStatus::Rejected]);

            $this->sengketaRepository->update($sengketa, $accepted
                ? ['vendor_confirmed_by' => $actor->id, 'vendor_confirmed_at' => now(), 'status' => StatusSengketa::InMediation]
                : ['status' => StatusSengketa::InMediation]);

            $this->log($sengketa, $actor, 'resolution', $accepted
                ? "Vendor mengonfirmasi retur {$purchaseReturn->return_number}; barang yang ditolak dikembalikan dan tidak ditagihkan."
                : "Vendor menolak retur {$purchaseReturn->return_number}.".$this->reasonSuffix($reason));

            return $purchaseReturn;
        });

        $this->notify($actor, $purchaseReturn->sengketa, $accepted ? 'Retur Dikonfirmasi Vendor' : 'Retur Ditolak Vendor', "{$actor->name} ".($accepted ? 'mengonfirmasi' : 'menolak')." retur {$purchaseReturn->return_number}.");

        return $purchaseReturn;
    }

    /**
     * The Vendor received the returned goods back (`confirmed` → `completed`, the one change BR-17 allows).
     *
     * @throws AuthorizationException|ModelNotFoundException
     */
    public function completePurchaseReturn(User $actor, string $returnUuid): PurchaseReturn
    {
        $purchaseReturn = DB::transaction(function () use ($actor, $returnUuid): PurchaseReturn {
            $purchaseReturn = $this->purchaseReturnRepository->findByUuidForVendor((int) $actor->vendor_id, $returnUuid, lock: true)
                ?? throw new ModelNotFoundException('Dokumen retur tidak ditemukan.');
            Gate::forUser($actor)->authorize('complete', $purchaseReturn);

            $this->purchaseReturnRepository->update($purchaseReturn, ['status' => PurchaseReturnStatus::Completed]);
            $this->log($purchaseReturn->sengketa, $actor, 'resolution', "Vendor menandai retur {$purchaseReturn->return_number} Selesai: barang retur sudah diterima kembali.");

            return $purchaseReturn;
        });

        $this->notify($actor, $purchaseReturn->sengketa, 'Retur Selesai', "Barang retur {$purchaseReturn->return_number} sudah diterima kembali oleh Vendor.");

        return $purchaseReturn;
    }

    /**
     * Owner/Manajemen verify the settlement and set the dispute to `resolved` (UC-18 step 6–7, BR-15).
     *
     * @throws AuthorizationException|ModelNotFoundException|ValidationException
     */
    public function resolve(User $actor, string $uuid, ?string $note = null): Sengketa
    {
        $sengketa = DB::transaction(function () use ($actor, $uuid, $note): Sengketa {
            $sengketa = $this->lockForActor($actor, $uuid);
            Gate::forUser($actor)->authorize('resolve', $sengketa);

            $blocker = $this->resolutionBlocker($sengketa);
            if ($blocker !== null) {
                throw ValidationException::withMessages(['resolution' => $blocker]);
            }

            $this->sengketaRepository->update($sengketa, [
                'status' => StatusSengketa::Resolved,
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
            ]);

            $note = trim((string) $note);
            $this->log($sengketa, $actor, 'resolution', "Sengketa ditetapkan Selesai dengan penyelesaian {$sengketa->resolution_type->label()}.".($note !== '' ? " Catatan verifikasi: {$note}" : ''));

            return $sengketa;
        });

        $this->notify($actor, $sengketa, 'Sengketa Selesai', "Sengketa PO {$this->poNumber($sengketa)} ditetapkan Selesai ({$sengketa->resolution_type->label()}).");

        return $sengketa;
    }

    /**
     * Why the dispute cannot be resolved yet, or null when it can (BR-15).
     */
    public function resolutionBlocker(Sengketa $sengketa): ?string
    {
        if ($sengketa->resolution_type === null) {
            return 'Tentukan dan ajukan jenis penyelesaian terlebih dahulu.';
        }

        if ($this->hasAwaitingDocument($sengketa)) {
            return 'Masih ada dokumen penyelesaian yang menunggu konfirmasi Vendor.';
        }

        $confirmed = match ($sengketa->resolution_type) {
            DisputeResolutionType::Reshipment => $sengketa->vendor_confirmed_at !== null,
            DisputeResolutionType::CreditNote => $this->creditNoteRepository->hasConfirmedForDispute((int) $sengketa->id),
            DisputeResolutionType::Return => $this->purchaseReturnRepository->hasConfirmedForDispute((int) $sengketa->id),
        };

        return $confirmed && $sengketa->vendor_confirmed_at !== null
            ? null
            : 'Penyelesaian belum dikonfirmasi oleh Vendor.';
    }

    /**
     * @return string Summary of the reshipment for the dispute log.
     */
    private function assertReshipmentAllowed(Sengketa $sengketa): string
    {
        if ($sengketa->purchaseOrder->status !== StatusPurchaseOrder::PartiallyReceived) {
            throw ValidationException::withMessages([
                'resolution_type' => 'Pengiriman ulang hanya untuk PO berstatus Diterima Sebagian (ada item yang ditolak QC).',
            ]);
        }

        return 'Vendor diminta mengirim ulang item yang ditolak QC.';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return string Summary of the credit note for the dispute log.
     */
    private function issueCreditNote(User $actor, Sengketa $sengketa, array $data, string $notes): string
    {
        $invoice = $this->invoiceRepository->lockForPurchaseOrder((int) $sengketa->purchase_order_id, (string) ($data['invoice_uuid'] ?? ''));
        if ($invoice === null) {
            throw ValidationException::withMessages(['invoice_uuid' => 'Pilih invoice dari Purchase Order ini.']);
        }

        $amount = round((float) ($data['amount'] ?? 0), 2);
        $remaining = round((float) $invoice->jumlah_tagihan - $this->creditNoteRepository->sumActiveForInvoice((int) $invoice->id), 2);
        if ($amount > $remaining) {
            throw ValidationException::withMessages([
                'amount' => 'Nominal credit note melebihi sisa nilai invoice ('.$this->rupiah(max(0.0, $remaining)).').',
            ]);
        }

        $creditNote = $this->creditNoteRepository->create([
            'credit_note_number' => $this->creditNoteRepository->getNextNumber((int) now()->format('Y')),
            'invoice_id' => $invoice->id,
            'dispute_id' => $sengketa->id,
            'credit_date' => $data['document_date'] ?? now()->toDateString(),
            'amount' => $amount,
            'reason' => $notes !== '' ? $notes : $sengketa->reason,
            'status' => CreditNoteStatus::AwaitingVendorConfirmation,
            'created_by' => $actor->id,
        ]);

        return "Credit note {$creditNote->credit_note_number} sebesar {$this->rupiah($amount)} atas invoice {$invoice->nomor_invoice_vendor}.";
    }

    /**
     * @param  array<string, mixed>  $data
     * @return string Summary of the return for the dispute log.
     */
    private function issuePurchaseReturn(User $actor, Sengketa $sengketa, array $data, string $notes): string
    {
        $lines = $this->rejectedReceiptLines($sengketa)->keyBy('uuid');
        $returned = $this->purchaseReturnRepository->returnedQuantityByReceiptItem(
            $lines->pluck('id')->map(fn ($id): int => (int) $id)->values()->all()
        );

        $items = [];
        foreach (array_values($data['items'] ?? []) as $index => $row) {
            /** @var PenerimaanBarangItem|null $line */
            $line = $lines->get((string) $row['receipt_item_uuid']);
            if ($line === null) {
                throw ValidationException::withMessages(["items.{$index}.receipt_item_uuid" => 'Item tidak termasuk barang yang ditolak QC pada sengketa ini.']);
            }

            $quantity = round((float) $row['quantity'], 3);
            $remaining = round((float) $line->received_quantity - ($returned[(int) $line->id] ?? 0.0), 3);
            if ($quantity > $remaining) {
                throw ValidationException::withMessages(["items.{$index}.quantity" => "Kuantitas retur melebihi sisa barang yang ditolak ({$remaining})."]);
            }

            $items[] = [
                'purchase_order_item_id' => $line->purchase_order_item_id,
                'goods_receipt_item_id' => $line->id,
                'quantity' => $quantity,
                'notes' => isset($row['notes']) && trim((string) $row['notes']) !== '' ? trim((string) $row['notes']) : null,
            ];
        }

        $purchaseReturn = $this->purchaseReturnRepository->createWithItems([
            'return_number' => $this->purchaseReturnRepository->getNextNumber((int) now()->format('Y')),
            'purchase_order_id' => $sengketa->purchase_order_id,
            'dispute_id' => $sengketa->id,
            'return_date' => $data['document_date'] ?? now()->toDateString(),
            'reason' => $notes !== '' ? $notes : $sengketa->reason,
            'status' => PurchaseReturnStatus::AwaitingVendorConfirmation,
            'created_by' => $actor->id,
        ], $items);

        return "Retur {$purchaseReturn->return_number} untuk ".count($items).' item yang ditolak QC.';
    }

    /**
     * QC-rejected receipt lines this dispute may return: the disputed item only, or every rejected line of the PO.
     *
     * @return Collection<int, PenerimaanBarangItem>
     */
    private function rejectedReceiptLines(Sengketa $sengketa): Collection
    {
        $purchaseOrder = $sengketa->purchaseOrder;
        $purchaseOrder->loadMissing('penerimaanBarang.items.poItem.komoditas');

        return $purchaseOrder->penerimaanBarang
            ->flatMap(fn ($receipt) => $receipt->items)
            ->filter(fn (PenerimaanBarangItem $line): bool => $line->status === StatusItemPenerimaan::Rejected
                && ($sengketa->purchase_order_item_id === null || (int) $line->purchase_order_item_id === (int) $sengketa->purchase_order_item_id))
            ->values();
    }

    private function hasAwaitingDocument(Sengketa $sengketa): bool
    {
        return $this->creditNoteRepository->hasAwaitingForDispute((int) $sengketa->id)
            || $this->purchaseReturnRepository->hasAwaitingForDispute((int) $sengketa->id);
    }

    /**
     * @throws ModelNotFoundException
     */
    private function lockForActor(User $actor, string $uuid): Sengketa
    {
        return $this->sengketaRepository->lockByUuidForActor($actor, $uuid)
            ?? throw new ModelNotFoundException('Data sengketa tidak ditemukan.');
    }

    private function log(Sengketa $sengketa, User $actor, string $type, string $message): void
    {
        $this->sengketaRepository->appendLog([
            'dispute_id' => (int) $sengketa->id,
            'user_id' => (int) $actor->id,
            'type' => $type,
            'message' => $message,
        ]);
    }

    private function notify(User $actor, Sengketa $sengketa, string $title, string $message): void
    {
        try {
            $this->notificationService->sendToMany(
                $this->sengketaRepository->getStakeholderRecipients($sengketa, (int) $actor->id),
                'dispute',
                $title,
                $message,
                route('log-sengketa.show', $sengketa->uuid, false),
                ['dispute_uuid' => $sengketa->uuid],
            );
        } catch (Throwable) {
            // A failed notification must never undo the settlement.
        }
    }

    private function poNumber(Sengketa $sengketa): string
    {
        return $sengketa->purchaseOrder?->nomor_po ?? 'PO';
    }

    private function reasonSuffix(?string $reason): string
    {
        $reason = trim((string) $reason);

        return $reason !== '' ? " Alasan: {$reason}" : '';
    }

    private function rupiah(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
