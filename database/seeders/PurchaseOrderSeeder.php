<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\StatusPurchaseOrder;
use App\Models\ChatNegosiasi;
use App\Models\Komoditas;
use App\Models\PerusahaanBuyer;
use App\Models\PoItem;
use App\Models\PrediksiHarga;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

/**
 * Demo seeder: hanya local/testing.
 * Satu PO per status agar setiap tampilan status bisa diuji.
 */
class PurchaseOrderSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $buyer = PerusahaanBuyer::where('nama_perusahaan', 'PT Pagora Nusantara')->firstOrFail();
        $userBuyer = User::where('email', 'buyer@pagora.test')->firstOrFail();
        $userVendor1 = User::where('email', 'vendor1@pagora.test')->firstOrFail();
        $vendor1 = Vendor::where('email_kontak', 'kontak@mitra-agro.test')->firstOrFail();
        $vendor2 = Vendor::where('email_kontak', 'info@sumber-pangan.test')->firstOrFail();

        $beras = Komoditas::where('sku', 'BERAS')->firstOrFail();
        $bawangMerah = Komoditas::where('sku', 'BAWANG_MERAH')->firstOrFail();

        $skenario = [
            ['PO-2026-0001', StatusPurchaseOrder::Draft,             $vendor1, $beras,       500,  12500, 10, 'PO draft untuk pengujian tampilan awal.'],
            ['PO-2026-0002', StatusPurchaseOrder::MenungguTanggapan, $vendor1, $beras,       1000, 12600, 8,  'PO sudah dikirim ke vendor, menunggu respons.'],
            ['PO-2026-0003', StatusPurchaseOrder::Negosiasi,         $vendor1, $bawangMerah, 200,  29000, 7,  'Negosiasi harga bawang merah sedang berlangsung.'],
            ['PO-2026-0004', StatusPurchaseOrder::Disepakati,        $vendor1, $beras,       2000, 12750, 6,  'Harga sepakat, PO terkunci.'],
            ['PO-2026-0005', StatusPurchaseOrder::DalamPengiriman,   $vendor1, $beras,       1500, 12800, 5,  'Barang dalam perjalanan.'],
            ['PO-2026-0006', StatusPurchaseOrder::DiterimaGudang,    $vendor2, $bawangMerah, 300,  28500, 4,  'Barang sudah diperiksa QC.'],
            ['PO-2026-0007', StatusPurchaseOrder::Ditagih,           $vendor2, $beras,       1000, 12900, 3,  'Invoice sudah terbit, menunggu verifikasi buyer.'],
            ['PO-2026-0008', StatusPurchaseOrder::Lunas,             $vendor2, $beras,       500,  13000, 15, 'PO selesai, pembayaran lunas.'],
            ['PO-2026-0009', StatusPurchaseOrder::Sengketa,          $vendor1, $bawangMerah, 100,  30000, 12, 'Terdapat selisih pada 3-way matching.'],
            ['PO-2026-0010', StatusPurchaseOrder::Dibatalkan,        $vendor1, $beras,       250,  12500, 20, 'PO dibatalkan oleh buyer.'],
        ];

        foreach ($skenario as [$nomor, $status, $vendor, $komoditas, $jumlah, $harga, $hariLalu, $catatan]) {
            $prediksi = PrediksiHarga::where('komoditas_id', $komoditas->id)->latest('tanggal_prediksi')->first();

            $po = PurchaseOrder::updateOrCreate(
                ['nomor_po' => $nomor],
                [
                    'vendor_id' => $vendor->id,
                    'created_by' => $userBuyer->id,
                    'prediksi_harga_id' => $prediksi?->id,
                    'perusahaan_buyer_id' => $buyer->id,
                    'harga_pagu_acuan' => $prediksi?->harga_pagu_rekomendasi,
                    'status' => $status->value,
                    'tanggal_po' => now()->subDays($hariLalu)->toDateString(),
                    'catatan' => $catatan,
                ]
            );

            if (! $po->poItems()->exists()) {
                PoItem::create([
                    'purchase_order_id' => $po->id,
                    'komoditas_id' => $komoditas->id,
                    'jumlah_pesanan' => $jumlah,
                    'harga_satuan_po' => $harga,
                    'subtotal' => $jumlah * $harga,
                    'harga_tawar_vendor' => $status === StatusPurchaseOrder::Negosiasi ? 28500 : null,
                    'status_item' => $status === StatusPurchaseOrder::Draft ? 'menunggu' : 'disepakati',
                ]);
            }

            if ($status === StatusPurchaseOrder::Negosiasi && ! $po->chatNegosiasi()->exists()) {
                $chats = [
                    [$userBuyer, 'Selamat pagi, kami tertarik dengan penawaran Anda. Apakah harga bisa lebih kompetitif?', null],
                    [$userVendor1, 'Harga terbaik kami untuk volume ini adalah Rp 28.500/kg.', 28500],
                    [$userBuyer, 'Kami tawar Rp 28.000/kg.', 28000],
                ];

                foreach ($chats as [$pengirim, $pesan, $nominal]) {
                    ChatNegosiasi::create([
                        'pengirim_id' => $pengirim->id,
                        'purchase_order_id' => $po->id,
                        'pesan_teks' => $pesan,
                        'nominal_tawar' => $nominal,
                    ]);
                }
            }
        }
    }
}
