<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Vendor;
use Illuminate\Database\Seeder;

/**
 * Demo seeder: hanya local/testing.
 * Idempotent: updateOrCreate dengan kunci 'email_kontak'.
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
                'nama_perusahaan' => 'CV Mitra Agro Sejahtera',
                'email_kontak' => 'kontak@mitra-agro.test',
                'no_telepon' => '0341-111222',
                'alamat' => 'Jl. Pahlawan No. 10, Malang, Jawa Timur',
                'kategori_komoditas' => 'Pangan Pokok',
                'status_verifikasi' => 'approved',
            ],
            [
                'slug' => 'pt-sumber-pangan-utama',
                'nama_perusahaan' => 'PT Sumber Pangan Utama',
                'email_kontak' => 'info@sumber-pangan.test',
                'no_telepon' => '024-333444',
                'alamat' => 'Jl. Diponegoro No. 25, Semarang, Jawa Tengah',
                'kategori_komoditas' => 'Bumbu & Rempah',
                'status_verifikasi' => 'approved',
            ],
            [
                'slug' => 'ud-berkah-tani-mandiri',
                'nama_perusahaan' => 'UD Berkah Tani Mandiri',
                'email_kontak' => 'berkah@tani.test',
                'no_telepon' => '0354-555666',
                'alamat' => 'Jl. Merdeka No. 5, Kediri, Jawa Timur',
                'kategori_komoditas' => 'Bumbu & Rempah',
                'status_verifikasi' => 'pending',
            ],
        ];

        foreach ($vendors as $data) {
            Vendor::updateOrCreate(
                ['email_kontak' => $data['email_kontak']],
                $data + ['status_aktif' => true]
            );
        }
    }
}
