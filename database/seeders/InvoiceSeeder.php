<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\StatusPurchaseOrder;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo seeder: hanya local/testing.
 * Invoice PO sengketa sengaja di-mark-up agar 3-way matching gagal.
 */
class InvoiceSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $userBuyer = User::where('email', 'buyer@pagora.test')->firstOrFail();

        $poList = PurchaseOrder::whereIn('status', [
            StatusPurchaseOrder::Ditagih->value,
            StatusPurchaseOrder::Lunas->value,
            StatusPurchaseOrder::Sengketa->value,
        ])->with('poItems')->get();

        foreach ($poList as $po) {
            if ($po->invoices()->exists()) {
                continue;
            }

            $isLunas = $po->status === StatusPurchaseOrder::Lunas;
            $isSengketa = $po->status === StatusPurchaseOrder::Sengketa;
            $totalPo = (float) $po->poItems->sum('subtotal');

            Invoice::create([
                'purchase_order_id' => $po->id,
                'nomor_invoice_vendor' => 'INV-2026-'.str_pad((string) $po->id, 4, '0', STR_PAD_LEFT),
                'jumlah_tagihan' => $isSengketa ? round($totalPo * 1.15, 2) : $totalPo,
                'file_invoice' => null,
                'tanggal_invoice' => now()->subDays(2)->toDateString(),
                'status_matching' => match (true) {
                    $isSengketa => 'gagal',
                    $isLunas => 'cocok',
                    default => 'pending',
                },
                'diverifikasi_oleh' => $isLunas ? $userBuyer->id : null,
                'diverifikasi_pada' => $isLunas ? now()->subDay() : null,
            ]);
        }
    }
}
