<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\StatusPengiriman;
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
            StatusPurchaseOrder::PartiallyReceived->value,
            StatusPurchaseOrder::DiterimaGudang->value,
            StatusPurchaseOrder::Ditagih->value,
            StatusPurchaseOrder::Lunas->value,
            StatusPurchaseOrder::Sengketa->value,
        ])->with('vendor')->get();

        foreach ($poList as $po) {
            if ($po->pengiriman()->exists()) {
                continue;
            }

            $isVendor2 = $po->vendor->contact_email === 'info@sumber-pangan.test';

            Pengiriman::create([
                'purchase_order_id' => $po->id,
                'route' => 'Darat',
                'carrier_name' => $isVendor2 ? 'JNE Cargo' : 'Wahana Express',
                'vehicle_plate_number' => $isVendor2 ? 'S 1234 AB' : 'L 5678 CD',
                'estimated_arrival_date' => now()->subDays(2)->toDateString(),
                'status' => $po->status === StatusPurchaseOrder::DalamPengiriman
                    ? StatusPengiriman::InTransit->value
                    : StatusPengiriman::Arrived->value,
                'reported_by' => $isVendor2 ? $userVendor2->id : $userVendor1->id,
            ]);
        }
    }
}
