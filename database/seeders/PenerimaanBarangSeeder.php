<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\StatusPurchaseOrder;
use App\Models\PenerimaanBarang;
use App\Models\PenerimaanBarangItem;
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
            StatusPurchaseOrder::DiterimaGudang->value,
            StatusPurchaseOrder::Ditagih->value,
            StatusPurchaseOrder::Lunas->value,
            StatusPurchaseOrder::Sengketa->value,
        ])->with(['pengiriman', 'poItems'])->get();

        foreach ($poList as $po) {
            if ($po->penerimaanBarang()->exists()) {
                continue;
            }

            $isSengketa = $po->status === StatusPurchaseOrder::Sengketa;

            $penerimaan = PenerimaanBarang::create([
                'purchase_order_id' => $po->id,
                'check_by' => $qc->id,
                'no_polisi_dicocokkan' => $po->pengiriman->first()?->no_polisi_armada,
                'tanggal_pemeriksaan' => now()->subDay(),
                'catatan' => $isSengketa ? 'Sebagian barang kondisi rusak.' : null,
            ]);

            foreach ($po->poItems as $poItem) {
                PenerimaanBarangItem::create([
                    'penerimaan_barang_id' => $penerimaan->id,
                    'po_item_id' => $poItem->id,
                    'jumlah_diterima' => $isSengketa ? $poItem->jumlah_pesanan * 0.8 : $poItem->jumlah_pesanan,
                    'status' => $isSengketa ? 'ditolak_sebagian' : 'diterima',
                    'catatan' => $isSengketa ? 'Kondisi barang tidak sesuai spesifikasi — kemasan rusak.' : null,
                    'foto_bukti_penolakan' => $isSengketa ? 'penerimaan/bukti/sengketa-foto-1.jpg' : null,
                ]);
            }
        }
    }
}
