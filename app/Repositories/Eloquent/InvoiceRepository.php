<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PoItem;
use App\Models\PurchaseOrder;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvoiceRepository implements InvoiceRepositoryInterface
{
    public function __construct(
        protected Invoice $model
    ) {}

    /**
     * Paginate invoices belonging to a vendor.
     *
     * @param array<string, mixed> $filters
     */
    public function paginateForVendor(
        int $vendorId,
        array $filters,
        int $perPage = 10
    ): LengthAwarePaginator {
        $sort = in_array(
            $filters['sort'] ?? null,
            ['nomor_invoice_vendor', 'tanggal_invoice', 'jumlah_tagihan', 'created_at'],
            true
        ) ? (string) $filters['sort'] : 'created_at';

        $direction = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc'
            ? 'asc'
            : 'desc';

        return $this->model->newQuery()
            ->where('vendor_id', $vendorId)
            ->with([
                'purchaseOrder:id,uuid,nomor_po,status',
                'items.purchaseOrderItem.komoditas:id,uuid,name,unit',
            ])
            ->when(! empty($filters['search']), function ($query) use ($filters): void {
                $term = '%'.trim((string) $filters['search']).'%';

                $query->where(function ($q) use ($term): void {
                    $q->where('nomor_invoice_vendor', 'ilike', $term)
                        ->orWhereHas('purchaseOrder', function ($po) use ($term): void {
                            $po->where('nomor_po', 'ilike', $term);
                        });
                });
            })
            ->when(
                ! empty($filters['status_matching']) &&
                $filters['status_matching'] !== 'all',
                fn ($query) => $query->where(
                    'status_matching',
                    $filters['status_matching']
                )
            )
            ->orderBy($sort, $direction)
            ->paginate(min($perPage, 100))
            ->withQueryString();
    }

    /**
     * Find a purchase order belonging to a vendor.
     */
    public function findPurchaseOrderForVendor(
        string $uuid,
        int $vendorId
    ): ?PurchaseOrder {
        return PurchaseOrder::query()
            ->where('uuid', $uuid)
            ->where('vendor_id', $vendorId)
            ->with([
                'tenant:id,uuid,company_name',
                'poItems.komoditas:id,uuid,name,unit',
            ])
            ->first();
    }

    /**
     * Get PO items with accepted QC quantities and invoiced quantities.
     *
     * @return Collection<int, PoItem>
     */
    public function getAvailableItems(PurchaseOrder $purchaseOrder): Collection
    {
        $items = PoItem::query()
            ->where('purchase_order_id', $purchaseOrder->id)
            ->whereHas('penerimaanBarangItems', function ($query) use ($purchaseOrder): void {
                $query->whereIn('status', ['diterima', 'ditolak_sebagian'])
                    ->whereHas('penerimaanBarang', function ($receipt) use ($purchaseOrder): void {
                        $receipt->where('purchase_order_id', $purchaseOrder->id);
                    });
            })
            ->with([
                'komoditas:id,uuid,name,unit',
                'penerimaanBarangItems' => function ($query) use ($purchaseOrder): void {
                    $query->whereIn('status', ['diterima', 'ditolak_sebagian'])
                        ->whereHas('penerimaanBarang', function ($receipt) use ($purchaseOrder): void {
                            $receipt->where('purchase_order_id', $purchaseOrder->id);
                        });
                },
                'invoiceItems',
            ])
            ->get();

        return $items->map(function (PoItem $item): PoItem {
            $acceptedQuantity = (float) $item->penerimaanBarangItems->sum('jumlah_diterima');
            $invoicedQuantity = (float) $item->invoiceItems->sum('quantity');

            $item->setAttribute(
                'jumlah_lolos_qc',
                number_format($acceptedQuantity, 3, '.', '')
            );

            $item->setAttribute(
                'jumlah_sudah_ditagihkan',
                number_format($invoicedQuantity, 3, '.', '')
            );

            $item->setAttribute(
                'jumlah_tersedia_ditagihkan',
                number_format(
                    max(0, $acceptedQuantity - $invoicedQuantity),
                    3,
                    '.',
                    ''
                )
            );

            return $item;
        })->filter(
            fn (PoItem $item): bool =>
                (float) $item->getAttribute('jumlah_tersedia_ditagihkan') > 0
        )->values();
    }

    /**
     * Find an invoice by UUID, scoped to its vendor.
     */
    public function findByUuid(
        string $uuid,
        int $vendorId
    ): ?Invoice {
        return $this->model->newQuery()
            ->where('uuid', $uuid)
            ->where('vendor_id', $vendorId)
            ->with([
                'purchaseOrder:id,uuid,nomor_po,status',
                'vendor:id,uuid,company_name',
                'items.purchaseOrderItem.komoditas:id,uuid,name,unit',
            ])
            ->first();
    }

    /**
     * Create an invoice with its invoice items.
     *
     * @param array<string, mixed> $attributes
     * @param list<array<string, mixed>> $items
     */
    public function createWithItems(
        array $attributes,
        array $items
    ): Invoice {
        return DB::transaction(function () use ($attributes, $items): Invoice {
            $invoice = $this->model->newQuery()->create($attributes);

            foreach ($items as $item) {
                InvoiceItem::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'invoice_id' => $invoice->id,
                    'purchase_order_item_id' => $item['purchase_order_item_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['subtotal'],
                ]);
            }

            return $invoice->load([
                'purchaseOrder',
                'items.purchaseOrderItem.komoditas',
            ]);
        });
    }

    /**
     * Get purchase orders belonging to a vendor that have available items.
     *
     * @return Collection<int, PurchaseOrder>
     */
    public function getPurchaseOrdersForVendorWithAvailableItems(
        int $vendorId
    ): Collection {
        return PurchaseOrder::query()
            ->where('vendor_id', $vendorId)
            ->whereHas('poItems.penerimaanBarangItems', function ($query): void {
                $query->whereIn('status', ['diterima', 'ditolak_sebagian'])
                    ->whereHas('penerimaanBarang', function ($receipt): void {
                        $receipt->whereColumn(
                            'penerimaan_barang.purchase_order_id',
                            'purchase_order.id'
                        );
                    });
            })
            ->with([
                'tenant:id,uuid,company_name',
                'poItems.komoditas:id,uuid,name,unit',
            ])
            ->orderByDesc('created_at')
            ->get()
            ->filter(
                fn (PurchaseOrder $purchaseOrder): bool =>
                    $this->getAvailableItems($purchaseOrder)->isNotEmpty()
            )
            ->values();
    }
}
