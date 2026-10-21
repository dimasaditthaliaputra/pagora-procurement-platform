<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\StatusPurchaseOrder;
use App\Models\Pengiriman;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo seeder: hanya local/testing.
 */
class PengirimanSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $userVendor1 = User::where('email', 'vendor1@pagora.test')->firstOrFail();
        $userVendor2 = User::where('email', 'vendor2@pagora.test')->firstOrFail();

        $poList = PurchaseOrder::whereIn('status', [
            StatusPurchaseOrder::DalamPengiriman->value,
            StatusPurchaseOrder::DiterimaGudang->value,
            StatusPurchaseOrder::Ditagih->value,
            StatusPurchaseOrder::Lunas->value,
            StatusPurchaseOrder::Sengketa->value,
        ])->with('vendor')->get();

        foreach ($poList as $po) {
            if ($po->pengiriman()->exists()) {
                continue;
            }

            $isVendor2 = $po->vendor->email_kontak === 'info@sumber-pangan.test';

            Pengiriman::create([
                'purchase_order_id' => $po->id,
                'jalur_pengiriman' => 'Darat',
                'nama_armada_ekspedisi' => $isVendor2 ? 'JNE Cargo' : 'Wahana Express',
                'no_polisi_armada' => $isVendor2 ? 'S 1234 AB' : 'L 5678 CD',
                'perkiraan_tanggal_tiba' => now()->subDays(2)->toDateString(),
                'status' => $po->status === StatusPurchaseOrder::DalamPengiriman ? 'dalam_perjalanan' : 'tiba',
                'dilaporkan_oleh' => $isVendor2 ? $userVendor2->id : $userVendor1->id,
            ]);
        }
    }
}
