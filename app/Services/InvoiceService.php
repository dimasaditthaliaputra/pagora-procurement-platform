<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Invoice;
use App\Models\PoItem;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    private const INVOICE_DISK = 'local';

    private const INVOICE_DIRECTORY = 'vendors/invoices';

    public function __construct(
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly FileStorageService $fileStorage
    ) {}

    /**
     * Get the authenticated vendor's invoices.
     *
     * @param array<string, mixed> $filters
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
     * @param array<string, mixed> $data
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
                        'purchase_order_uuid' =>
                            'Tidak ada barang lolos QC yang belum ditagihkan.',
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
                        'purchase_order_uuid' =>
                            'Tidak ada barang yang dapat ditagihkan.',
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
     * Calculate the total amount of available invoice items.
     *
     * @param Collection<int, PoItem> $items
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
