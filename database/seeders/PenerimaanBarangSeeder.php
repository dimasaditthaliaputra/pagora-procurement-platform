<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\StatusItemPenerimaan;
use App\Enums\StatusPengiriman;
use App\Enums\StatusPurchaseOrder;
use App\Models\PenerimaanBarang;
use App\Models\PenerimaanBarangItem;
use App\Models\Pengiriman;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo seeder: hanya local/testing.
 */
class PenerimaanBarangSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $qc = User::where('email', 'qc@pagora.test')->firstOrFail();

        $poList = PurchaseOrder::whereIn('status', [
            StatusPurchaseOrder::PartiallyReceived->value,
            StatusPurchaseOrder::DiterimaGudang->value,
            StatusPurchaseOrder::Ditagih->value,
            StatusPurchaseOrder::Lunas->value,
            StatusPurchaseOrder::Sengketa->value,
        ])->with(['pengiriman', 'poItems'])->get();

        foreach ($poList as $po) {
            if ($po->penerimaanBarang()->exists()) {
                continue;
            }

            $shipment = $po->pengiriman->first();
            if ($shipment === null) {
                $shipment = Pengiriman::create([
                    'purchase_order_id' => $po->id,
                    'route' => 'Darat',
                    'carrier_name' => 'Armada Vendor',
                    'vehicle_plate_number' => 'N 8001 PG',
                    'estimated_arrival_date' => now()->subDay()->toDateString(),
                    'status' => StatusPengiriman::Arrived->value,
                    'reported_by' => $qc->id,
                ]);
            }

            $isSengketa = in_array($po->status, [
                StatusPurchaseOrder::Sengketa,
                StatusPurchaseOrder::PartiallyReceived,
            ], true);

            $penerimaan = PenerimaanBarang::create([
                'purchase_order_id' => $po->id,
                'shipment_id' => $shipment->id,
                'inspected_by' => $qc->id,
                'matched_plate_number' => $shipment->vehicle_plate_number,
                'inspected_at' => now()->subDay(),
                'notes' => $isSengketa ? 'Sebagian barang kondisi rusak pada saat pemeriksaan di dermaga gudang.' : null,
            ]);

            foreach ($po->poItems as $poItem) {
                PenerimaanBarangItem::create([
                    'goods_receipt_id' => $penerimaan->id,
                    'purchase_order_item_id' => $poItem->id,
                    'received_quantity' => $isSengketa ? $poItem->jumlah_pesanan * 0.8 : $poItem->jumlah_pesanan,
                    'status' => $isSengketa ? StatusItemPenerimaan::Rejected->value : StatusItemPenerimaan::Accepted->value,
                    'notes' => $isSengketa ? 'Kondisi barang tidak sesuai spesifikasi — kemasan rusak.' : null,
                    'rejection_photos' => $isSengketa ? ['goods-receipts/rejections/sengketa-foto-1.jpg'] : null,
                ]);
            }
        }
    }
}
