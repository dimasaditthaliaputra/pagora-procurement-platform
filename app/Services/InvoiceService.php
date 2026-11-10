<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PemicuSengketa;
use App\Enums\StatusItemPenerimaan;
use App\Enums\StatusMatching;
use App\Enums\StatusPurchaseOrder;
use App\Enums\StatusSengketa;
use App\Models\Invoice;
use App\Models\LogSengketa;
use App\Models\PoItem;
use App\Models\PurchaseOrder;
use App\Models\Sengketa;
use App\Models\User;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    private const INVOICE_DISK = 'local';

    private const INVOICE_DIRECTORY = 'vendors/invoices';

    public function __construct(
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly FileStorageService $fileStorage,
        private readonly ?NotificationService $notificationService = null,
    ) {}

    /**
     * Get the authenticated vendor's invoices.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateForVendor(
        User $actor,
        array $filters = [],
        int $perPage = 10
    ): LengthAwarePaginator {
        return $this->invoiceRepository->paginateForVendor(
            $this->getVendorId($actor),
            $filters,
            $perPage
        );
    }

    /**
     * Get purchase orders that still have accepted QC quantities available.
     *
     * @return Collection<int, PurchaseOrder>
     */
    public function getPurchaseOrdersForInvoice(User $actor): Collection
    {
        return $this->invoiceRepository
            ->getPurchaseOrdersForVendorWithAvailableItems(
                $this->getVendorId($actor)
            );
    }

    /**
     * Find an invoice belonging to the authenticated vendor.
     */
    public function findInvoiceForVendor(
        User $actor,
        string $invoiceUuid
    ): Invoice {
        $invoice = $this->invoiceRepository->findByUuid(
            $invoiceUuid,
            $this->getVendorId($actor)
        );

        if ($invoice === null) {
            throw (new ModelNotFoundException())->setModel(
                Invoice::class
            );
        }

        return $invoice;
    }

    public function findPurchaseOrderForVendor(
        User $actor,
        string $purchaseOrderUuid
    ): PurchaseOrder {
        $purchaseOrder = $this->invoiceRepository->findPurchaseOrderForVendor(
            $purchaseOrderUuid,
            $this->getVendorId($actor)
        );

        if ($purchaseOrder === null) {
            throw (new ModelNotFoundException())->setModel(
                PurchaseOrder::class
            );
        }

        return $purchaseOrder;
    }

    /**
     * Preview accepted QC items that have not been invoiced.
     *
     * @return array{
     *     purchase_order: PurchaseOrder,
     *     items: Collection<int, PoItem>,
     *     total_amount: string
     * }
     */
    public function preview(
        User $actor,
        string $purchaseOrderUuid
    ): array {
        $purchaseOrder = $this->findPurchaseOrderForVendor(
            $actor,
            $purchaseOrderUuid
        );

        $items = $this->invoiceRepository->getAvailableItems($purchaseOrder);

        return [
            'purchase_order' => $purchaseOrder,
            'items' => $items,
            'total_amount' => $this->calculateTotal($items),
        ];
    }

    /**
     * Issue an invoice for all accepted and not-yet-invoiced quantities.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): Invoice
    {
        $vendorId = $this->getVendorId($actor);
        $purchaseOrderUuid = (string) ($data['purchase_order_uuid'] ?? '');

        if ($purchaseOrderUuid === '') {
            throw ValidationException::withMessages([
                'purchase_order_uuid' => 'Purchase Order wajib dipilih.',
            ]);
        }

        $purchaseOrder = $this->invoiceRepository->findPurchaseOrderForVendor(
            $purchaseOrderUuid,
            $vendorId
        );

        if ($purchaseOrder === null) {
            throw ValidationException::withMessages([
                'purchase_order_uuid' => 'Purchase Order tidak ditemukan.',
            ]);
        }

        $file = $data['file_invoice'] ?? null;

        if ($file !== null && ! $file instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'file_invoice' => 'File invoice tidak valid.',
            ]);
        }

        $invoiceNumber = trim(
            (string) ($data['nomor_invoice_vendor'] ?? '')
        );

        if ($invoiceNumber === '') {
            throw ValidationException::withMessages([
                'nomor_invoice_vendor' => 'Nomor invoice wajib diisi.',
            ]);
        }

        $invoiceDate = $data['tanggal_invoice'] ?? null;

        if (blank($invoiceDate)) {
            throw ValidationException::withMessages([
                'tanggal_invoice' => 'Tanggal invoice wajib diisi.',
            ]);
        }

        // Store the file before the database transaction.
        $filePath = $file instanceof UploadedFile
            ? $this->fileStorage->store(
                $file,
                self::INVOICE_DIRECTORY,
                self::INVOICE_DISK,
                uuidName: true
            )
            : null;

        return $this->fileStorage->rollbackOnFailure(
            self::INVOICE_DISK,
            $filePath,
            fn (): Invoice => DB::transaction(function () use (
                $purchaseOrder,
                $vendorId,
                $invoiceNumber,
                $invoiceDate,
                $filePath
            ): Invoice {
                // Serialize invoice creation for the same purchase order.
                PurchaseOrder::query()
                    ->whereKey($purchaseOrder->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                // Recalculate available quantities inside the transaction.
                $availableItems = $this->invoiceRepository->getAvailableItems(
                    $purchaseOrder
                );

                if ($availableItems->isEmpty()) {
                    throw ValidationException::withMessages([
                        'purchase_order_uuid' => 'Tidak ada barang lolos QC yang belum ditagihkan.',
                    ]);
                }

                $invoiceItems = [];

                foreach ($availableItems as $item) {
                    $quantity = (float) $item->getAttribute(
                        'jumlah_tersedia_ditagihkan'
                    );

                    $unitPrice = (float) $item->harga_satuan_po;

                    if ($quantity <= 0 || $unitPrice < 0) {
                        continue;
                    }

                    $subtotal = round($quantity * $unitPrice, 2);

                    $invoiceItems[] = [
                        'purchase_order_item_id' => $item->getKey(),
                        'quantity' => number_format(
                            $quantity,
                            3,
                            '.',
                            ''
                        ),
                        'unit_price' => number_format(
                            $unitPrice,
                            2,
                            '.',
                            ''
                        ),
                        'subtotal' => number_format(
                            $subtotal,
                            2,
                            '.',
                            ''
                        ),
                    ];
                }

                if ($invoiceItems === []) {
                    throw ValidationException::withMessages([
                        'purchase_order_uuid' => 'Tidak ada barang yang dapat ditagihkan.',
                    ]);
                }

                $totalAmount = array_sum(array_map(
                    fn (array $item): float => (float) $item['subtotal'],
                    $invoiceItems
                ));

                return $this->invoiceRepository->createWithItems(
                    [
                        'purchase_order_id' => $purchaseOrder->getKey(),
                        'vendor_id' => $vendorId,
                        'nomor_invoice_vendor' => $invoiceNumber,
                        'jumlah_tagihan' => number_format(
                            $totalAmount,
                            2,
                            '.',
                            ''
                        ),
                        'file_invoice' => $filePath,
                        'tanggal_invoice' => $invoiceDate,
                        'status_matching' => 'pending',
                    ],
                    $invoiceItems
                );
            })
        );
    }

    /**
     * Get the authenticated buyer tenant's invoices with filters and pagination.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateForTenant(
        User $actor,
        array $filters = [],
        int $perPage = 10
    ): LengthAwarePaginator {
        return $this->invoiceRepository->paginateForTenant(
            $this->getTenantId($actor),
            $filters,
            $perPage
        );
    }

    /**
     * Get aggregate matching status counts for the buyer tenant.
     *
     * @return array{total: int, pending: int, matched: int, mismatched: int}
     */
    public function getCountsForTenant(User $actor): array
    {
        return $this->invoiceRepository->getMatchingCountsForTenant(
            $this->getTenantId($actor)
        );
    }

    /**
     * Find an invoice scoped to the authenticated buyer tenant.
     */
    public function findInvoiceForTenant(
        User $actor,
        string $invoiceUuid
    ): Invoice {
        $invoice = $this->invoiceRepository->findByUuidForTenant(
            $invoiceUuid,
            $this->getTenantId($actor)
        );

        if ($invoice === null) {
            throw (new ModelNotFoundException())->setModel(Invoice::class);
        }

        return $invoice;
    }

    /**
     * Perform 3-Way Matching comparison among PO, Goods Receipt, and Invoice.
     * Tolerance = 0 per BR-12 and PRD Decision Q-31.
     *
     * @return array{
     *     is_matched: bool,
     *     discrepancy_types: list<string>,
     *     items: list<array<string, mixed>>,
     *     summary: array<string, mixed>
     * }
     */
    public function calculateThreeWayMatching(Invoice $invoice): array
    {
        $invoice->loadMissing([
            'purchaseOrder.poItems.komoditas',
            'purchaseOrder.poItems.penerimaanBarangItems.goodsReceipt',
            'items.purchaseOrderItem.komoditas',
            'items.purchaseOrderItem.penerimaanBarangItems',
        ]);

        $itemsResult = [];
        $discrepancyTypes = [];
        $mismatchNotes = [];

        $totalPoAmount = 0.0;
        $totalReceivedAmount = 0.0;
        $totalInvoicedAmount = 0.0;

        foreach ($invoice->items as $item) {
            $poItem = $item->purchaseOrderItem;
            $commodity = $poItem?->komoditas;
            $commodityName = $commodity?->name ?? 'Komoditas';
            $unit = $commodity?->unit ?? 'kg';

            $invQty = (float) $item->quantity;
            $invUnitPrice = (float) $item->unit_price;
            $invSubtotal = (float) $item->subtotal;

            $poQty = (float) ($poItem?->jumlah_pesanan ?? 0);
            $poUnitPrice = (float) ($poItem?->harga_satuan_po ?? 0);
            $poSubtotal = (float) ($poItem?->subtotal ?? 0);

            // Filter accepted QC items
            $acceptedReceiptItems = $poItem?->penerimaanBarangItems?->filter(function ($pbi) {
                $statusVal = is_object($pbi->status) ? $pbi->status->value : $pbi->status;

                return in_array($statusVal, [StatusItemPenerimaan::Accepted->value, 'accepted', 'diterima'], true);
            }) ?? collect();

            $acceptedQty = (float) $acceptedReceiptItems->sum(function ($pbi) {
                return (float) ($pbi->received_quantity ?? $pbi->jumlah_diterima ?? 0);
            });

            $rejectedReceiptItems = $poItem?->penerimaanBarangItems?->filter(function ($pbi) {
                $statusVal = is_object($pbi->status) ? $pbi->status->value : $pbi->status;

                return in_array($statusVal, [StatusItemPenerimaan::Rejected->value, 'rejected', 'ditolak'], true);
            }) ?? collect();

            $rejectedQty = (float) $rejectedReceiptItems->sum(function ($pbi) {
                return (float) ($pbi->received_quantity ?? $pbi->jumlah_diterima ?? 0);
            });

            $itemNotes = [];
            $qtyMatched = true;
            $priceMatched = true;
            $subtotalMatched = true;

            // 1. Quantity comparison: Invoiced qty must equal accepted received qty, and cannot exceed PO qty
            if ($invQty > $acceptedQty + 0.0001) {
                $qtyMatched = false;
                $msg = "Kuantitas ditagih ({$invQty} {$unit}) melebihi kuantitas yang diterima di gudang ({$acceptedQty} {$unit}) untuk {$commodityName}.";
                $itemNotes[] = $msg;
                $mismatchNotes[] = $msg;
                $discrepancyTypes['quantity_mismatch'] = true;
            } elseif ($invQty > $poQty + 0.0001) {
                $qtyMatched = false;
                $msg = "Kuantitas ditagih ({$invQty} {$unit}) melebihi kuantitas kesepakatan PO ({$poQty} {$unit}) untuk {$commodityName}.";
                $itemNotes[] = $msg;
                $mismatchNotes[] = $msg;
                $discrepancyTypes['quantity_mismatch'] = true;
            } elseif (abs($invQty - $acceptedQty) >= 0.0001) {
                $qtyMatched = false;
                $msg = "Kuantitas ditagih ({$invQty} {$unit}) tidak sama dengan kuantitas yang diterima di gudang ({$acceptedQty} {$unit}) untuk {$commodityName}.";
                $itemNotes[] = $msg;
                $mismatchNotes[] = $msg;
                $discrepancyTypes['quantity_mismatch'] = true;
            }

            // 2. Price comparison: Invoiced unit price must equal agreed PO unit price
            if (abs($invUnitPrice - $poUnitPrice) >= 0.01) {
                $priceMatched = false;
                $msg = 'Harga satuan ditagih (Rp '.number_format($invUnitPrice, 0, ',', '.').') tidak sesuai dengan harga kesepakatan PO (Rp '.number_format($poUnitPrice, 0, ',', '.').") untuk {$commodityName}.";
                $itemNotes[] = $msg;
                $mismatchNotes[] = $msg;
                $discrepancyTypes['amount_mismatch'] = true;
            }

            // 3. Subtotal comparison: Invoiced subtotal must equal rounded (invQty * poUnitPrice)
            $expectedSubtotal = round($invQty * $poUnitPrice, 2);
            if (abs($invSubtotal - $expectedSubtotal) >= 0.01) {
                $subtotalMatched = false;
                $msg = 'Subtotal ditagih (Rp '.number_format($invSubtotal, 0, ',', '.').') tidak sesuai dengan perhitungan kuantitas x harga PO (Rp '.number_format($expectedSubtotal, 0, ',', '.').") untuk {$commodityName}.";
                $itemNotes[] = $msg;
                $mismatchNotes[] = $msg;
                $discrepancyTypes['amount_mismatch'] = true;
            }

            $itemMatched = $qtyMatched && $priceMatched && $subtotalMatched;

            $totalPoAmount += $poSubtotal;
            $totalReceivedAmount += round($acceptedQty * $poUnitPrice, 2);
            $totalInvoicedAmount += $invSubtotal;

            $itemsResult[] = [
                'purchase_order_item_id' => $poItem?->id,
                'po_item_uuid' => $poItem?->uuid,
                'commodity_name' => $commodityName,
                'unit' => $unit,
                'po_quantity' => $poQty,
                'received_quantity' => $acceptedQty,
                'rejected_quantity' => $rejectedQty,
                'invoiced_quantity' => $invQty,
                'po_unit_price' => $poUnitPrice,
                'invoiced_unit_price' => $invUnitPrice,
                'po_subtotal' => $poSubtotal,
                'invoiced_subtotal' => $invSubtotal,
                'quantity_matched' => $qtyMatched,
                'price_matched' => $priceMatched,
                'subtotal_matched' => $subtotalMatched,
                'is_matched' => $itemMatched,
                'notes' => $itemNotes,
            ];
        }

        $headerTotal = (float) $invoice->jumlah_tagihan;
        $headerTotalMatched = abs($headerTotal - $totalInvoicedAmount) < 0.01;
        if (! $headerTotalMatched) {
            $msg = 'Total jumlah tagihan pada invoice (Rp '.number_format($headerTotal, 0, ',', '.').') berbeda dengan total rincian barang (Rp '.number_format($totalInvoicedAmount, 0, ',', '.').').';
            $mismatchNotes[] = $msg;
            $discrepancyTypes['amount_mismatch'] = true;
        }

        $isOverallMatched = $headerTotalMatched && collect($itemsResult)->every(fn ($i) => $i['is_matched']);

        return [
            'is_matched' => $isOverallMatched,
            'discrepancy_types' => array_keys($discrepancyTypes),
            'items' => $itemsResult,
            'summary' => [
                'total_po_items' => count($itemsResult),
                'total_po_amount' => $totalPoAmount,
                'total_received_amount' => $totalReceivedAmount,
                'total_invoiced_amount' => $totalInvoicedAmount,
                'invoice_header_amount' => $headerTotal,
                'header_total_matched' => $headerTotalMatched,
                'mismatch_notes' => $mismatchNotes,
            ],
        ];
    }

    /**
     * Execute 3-Way Matching verification for the given invoice (Buyer side).
     *
     * @return array{
     *     status: string,
     *     message: string,
     *     is_matched: bool,
     *     matching_result: array<string, mixed>,
     *     dispute: ?Sengketa
     * }
     */
    public function verify(User $actor, string $invoiceUuid): array
    {
        $invoice = $this->findInvoiceForTenant($actor, $invoiceUuid);

        Gate::forUser($actor)->authorize('verify', $invoice);

        $currentStatus = is_object($invoice->status_matching)
            ? $invoice->status_matching->value
            : $invoice->status_matching;

        if ($currentStatus !== StatusMatching::Pending->value && $currentStatus !== 'pending') {
            throw ValidationException::withMessages([
                'invoice' => 'Tagihan ini sudah pernah diverifikasi sebelumnya.',
            ]);
        }

        $matchingResult = $this->calculateThreeWayMatching($invoice);
        $isMatched = $matchingResult['is_matched'];
        $po = $invoice->purchaseOrder;

        return DB::transaction(function () use ($invoice, $po, $actor, $matchingResult, $isMatched): array {
            $dispute = null;

            if ($isMatched) {
                $this->invoiceRepository->updateMatching($invoice, [
                    'status_matching' => StatusMatching::Matched->value,
                    'matching_result' => $matchingResult,
                    'diverifikasi_oleh' => $actor->id,
                    'diverifikasi_pada' => now(),
                ]);

                // Transisi status PO ke ready_to_pay (Siap Dibayar) per PRD UC-17 step 5
                $po->update([
                    'status' => StatusPurchaseOrder::ReadyToPay->value,
                ]);

                // Notifikasi in-app jika service tersedia
                if ($this->notificationService !== null) {
                    $vendorUsers = User::query()->where('vendor_id', $invoice->vendor_id)->get();
                    $this->notificationService->sendToMany(
                        $vendorUsers,
                        'invoice',
                        'Tagihan Disetujui (3-Way Matching)',
                        "Invoice {$invoice->nomor_invoice_vendor} untuk PO {$po->nomor_po} telah diverifikasi dan Sesuai (3-Way Matching cocok).",
                        route('invoice.show', $invoice->uuid)
                    );

                    $this->notificationService->send(
                        $actor,
                        'invoice',
                        'Verifikasi 3-Way Matching Berhasil',
                        "Invoice {$invoice->nomor_invoice_vendor} telah berhasil diverifikasi dan Sesuai. PO {$po->nomor_po} kini berstatus Siap Dibayar.",
                        route('verifikasi-tagihan.show', $invoice->uuid)
                    );
                }

                return [
                    'status' => 'matched',
                    'message' => "Verifikasi 3-Way Matching berhasil. Invoice {$invoice->nomor_invoice_vendor} Sesuai dan PO {$po->nomor_po} berstatus Siap Dibayar.",
                    'is_matched' => true,
                    'matching_result' => $matchingResult,
                    'dispute' => null,
                ];
            }

            // Gagal / Mismatched: Buka Sengketa + Log Sengketa otomatis
            $this->invoiceRepository->updateMatching($invoice, [
                'status_matching' => StatusMatching::Mismatched->value,
                'matching_result' => $matchingResult,
                'diverifikasi_oleh' => $actor->id,
                'diverifikasi_pada' => now(),
            ]);

            // Status PO tidak berubah, namun PO dibekukan oleh sengketa (PRD BR-14, UC-17 step 4a)
            $triggerType = in_array('quantity_mismatch', $matchingResult['discrepancy_types'], true)
                ? PemicuSengketa::QuantityMismatch->value
                : PemicuSengketa::AmountMismatch->value;

            $firstMismatchItem = collect($matchingResult['items'])->first(fn ($i) => ! $i['is_matched']);
            $poItemId = $firstMismatchItem['purchase_order_item_id'] ?? null;

            $notesSummary = implode('; ', $matchingResult['summary']['mismatch_notes']);
            $reason = "Ketidaksesuaian verifikasi 3-Way Matching pada Invoice {$invoice->nomor_invoice_vendor}: {$notesSummary}";

            $dispute = Sengketa::create([
                'uuid' => (string) Str::uuid(),
                'purchase_order_id' => $po->id,
                'purchase_order_item_id' => $poItemId,
                'invoice_id' => $invoice->id,
                'trigger_type' => $triggerType,
                'reason' => $reason,
                'dibuka_oleh' => $actor->id,
                'status' => StatusSengketa::Terbuka->value,
                'tanggal_dibuka' => now(),
                'tanggal_selesai' => null,
            ]);

            LogSengketa::create([
                'uuid' => (string) Str::uuid(),
                'sengketa_id' => $dispute->id,
                'user_id' => $actor->id,
                'type' => 'status_change',
                'isi_pesan' => "Sistem membuka sengketa otomatis akibat ketidaksesuaian 3-Way Matching pada Invoice {$invoice->nomor_invoice_vendor}: {$reason}",
                'file_bukti' => null,
            ]);

            if ($this->notificationService !== null) {
                $vendorUsers = User::query()->where('vendor_id', $invoice->vendor_id)->get();
                $this->notificationService->sendToMany(
                    $vendorUsers,
                    'dispute',
                    'Tagihan Tidak Sesuai (Sengketa Terbuka)',
                    "Invoice {$invoice->nomor_invoice_vendor} tidak lolos 3-Way Matching. Sengketa pengadaan telah dibuka secara otomatis.",
                    route('log-sengketa.show', $dispute->uuid)
                );

                $this->notificationService->send(
                    $actor,
                    'dispute',
                    'Sengketa 3-Way Matching Terbuka',
                    "Ketidaksesuaian terdeteksi pada Invoice {$invoice->nomor_invoice_vendor}. Sengketa pengadaan telah dibuka secara otomatis.",
                    route('log-sengketa.show', $dispute->uuid)
                );
            }

            return [
                'status' => 'mismatched',
                'message' => 'Hasil 3-Way Matching menunjukkan ketidaksesuaian. Sengketa pengadaan telah dibuka secara otomatis.',
                'is_matched' => false,
                'matching_result' => $matchingResult,
                'dispute' => $dispute,
            ];
        });
    }

    /**
     * Get the vendor ID from the authenticated user.
     */
    private function getVendorId(User $actor): int
    {
        $vendorId = $actor->vendor_id;

        if (! $vendorId) {
            abort(403, 'Akun ini tidak memiliki akses sebagai Vendor.');
        }

        return (int) $vendorId;
    }

    /**
     * Get the tenant ID from the authenticated buyer user.
     */
    private function getTenantId(User $actor): int
    {
        $tenantId = $actor->tenant_id;

        if (! $tenantId) {
            abort(403, 'Akun ini tidak memiliki akses sebagai Buyer / Tenant.');
        }

        return (int) $tenantId;
    }

    /**
     * Calculate the total amount of available invoice items.
     *
     * @param  Collection<int, PoItem>  $items
     */
    private function calculateTotal(Collection $items): string
    {
        $total = 0;

        foreach ($items as $item) {
            $quantity = (float) $item->getAttribute(
                'jumlah_tersedia_ditagihkan'
            );

            $unitPrice = (float) $item->harga_satuan_po;

            $total += round($quantity * $unitPrice, 2);
        }

        return number_format($total, 2, '.', '');
    }
}
