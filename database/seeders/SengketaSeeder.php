<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\StatusSengketa;
use App\Models\Invoice;
use App\Models\LogSengketa;
use App\Models\PurchaseOrder;
use App\Models\Sengketa;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo seeder: hanya local/testing.
 */
class SengketaSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $userBuyer = User::where('email', 'buyer@pagora.test')->firstOrFail();
        $userManajemen = User::where('email', 'manajemen@pagora.test')->firstOrFail();

        $po = PurchaseOrder::where('nomor_po', 'PO-2026-0009')->firstOrFail();
        $invoice = Invoice::where('purchase_order_id', $po->id)->first();

        if (! $invoice || Sengketa::where('invoice_id', $invoice->id)->exists()) {
            return;
        }

        $sengketa = Sengketa::create([
            'invoice_id' => $invoice->id,
            'dibuka_oleh' => $userBuyer->id,
            'status' => StatusSengketa::DalamMediasi->value,
            'tanggal_dibuka' => now()->subDays(2),
            'tanggal_selesai' => null,
        ]);

        $logs = [
            [$userBuyer, 'Buyer membuka sengketa: nominal invoice tidak sesuai dengan barang yang diterima di gudang.'],
            [$userManajemen, 'Sengketa diambil alih oleh manajemen. Status diubah ke Dalam Mediasi.'],
        ];

        foreach ($logs as [$user, $pesan]) {
            LogSengketa::create([
                'sengketa_id' => $sengketa->id,
                'user_id' => $user->id,
                'isi_pesan' => $pesan,
                'file_bukti' => null,
            ]);
        }
    }
}
