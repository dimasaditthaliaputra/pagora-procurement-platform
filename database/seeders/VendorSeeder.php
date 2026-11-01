<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Vendor;
use Illuminate\Database\Seeder;

/**
 * Demo seeder: hanya local/testing.
 * Idempotent: updateOrCreate dengan kunci 'contact_email'.
 */
class VendorSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $vendors = [
            [
                'slug' => 'cv-mitra-agro-sejahtera',
                'company_name' => 'CV Mitra Agro Sejahtera',
                'contact_email' => 'kontak@mitra-agro.test',
                'phone' => '0341-111222',
                'address' => 'Jl. Pahlawan No. 10, Malang, Jawa Timur',
                'kategori_komoditas' => 'Pangan Pokok',
                'verification_status' => 'approved',
            ],
            [
                'slug' => 'pt-sumber-pangan-utama',
                'company_name' => 'PT Sumber Pangan Utama',
                'contact_email' => 'info@sumber-pangan.test',
                'phone' => '024-333444',
                'address' => 'Jl. Diponegoro No. 25, Semarang, Jawa Tengah',
                'kategori_komoditas' => 'Bumbu & Rempah',
                'verification_status' => 'approved',
            ],
            [
                'slug' => 'ud-berkah-tani-mandiri',
                'company_name' => 'UD Berkah Tani Mandiri',
                'contact_email' => 'berkah@tani.test',
                'phone' => '0354-555666',
                'address' => 'Jl. Merdeka No. 5, Kediri, Jawa Timur',
                'kategori_komoditas' => 'Bumbu & Rempah',
                'verification_status' => 'pending',
            ],
        ];

        foreach ($vendors as $data) {
            Vendor::updateOrCreate(
                ['contact_email' => $data['contact_email']],
                $data + ['is_active' => true]
            );
        }
    }
}
