<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Urutan mengikuti ketergantungan FK — jangan ubah tanpa memeriksa §5 design-db.md.
     * Reference seeders (RoleSeeder, KomoditasSeeder) jalan di semua environment.
     * Demo seeders bungkus environment check secara internal.
     */
    public function run(): void
    {
        $this->call([
            // 1–2: Reference seeders (all environments)
            RoleSeeder::class,
            KomoditasSeeder::class,

            // 3–14: Demo seeders (local/testing only — tiap seeder cek sendiri)
            PerusahaanBuyerSeeder::class,
            VendorSeeder::class,
            UserSeeder::class,
            DokumenLegalitasVendorSeeder::class,
            KerjaSamaSeeder::class,
            HargaReferensiKomoditasSeeder::class,
            PrediksiHargaSeeder::class,
            PurchaseOrderSeeder::class,
            PengirimanSeeder::class,
            PenerimaanBarangSeeder::class,
            InvoiceSeeder::class,
            SengketaSeeder::class,
        ]);
    }
}
