<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DokumenLegalitasVendor;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

/**
 * Demo seeder: hanya local/testing.
 */
class DokumenLegalitasVendorSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $vendors = Vendor::whereIn('email_kontak', [
            'kontak@mitra-agro.test',
            'info@sumber-pangan.test',
            'berkah@tani.test',
        ])->get()->keyBy('email_kontak');

        $superadmin = User::where('email', 'superadmin@pagora.test')->firstOrFail();

        $dokumen = [
            ['vendor' => 'kontak@mitra-agro.test',  'jenis' => 'npwp', 'nomor' => '02.111.222.3-456.000', 'status' => 'approved', 'file' => 'vendor/dokumen/vendor1-npwp.pdf'],
            ['vendor' => 'kontak@mitra-agro.test',  'jenis' => 'siup', 'nomor' => 'SIUP/001/2024',          'status' => 'approved', 'file' => 'vendor/dokumen/vendor1-siup.pdf'],
            ['vendor' => 'info@sumber-pangan.test', 'jenis' => 'npwp', 'nomor' => '03.333.444.5-789.000', 'status' => 'approved', 'file' => 'vendor/dokumen/vendor2-npwp.pdf'],
            ['vendor' => 'info@sumber-pangan.test', 'jenis' => 'siup', 'nomor' => 'SIUP/002/2024',          'status' => 'approved', 'file' => 'vendor/dokumen/vendor2-siup.pdf'],
            ['vendor' => 'berkah@tani.test',        'jenis' => 'npwp', 'nomor' => '04.555.666.7-012.000', 'status' => 'pending',  'file' => 'vendor/dokumen/vendor3-npwp.pdf'],
            ['vendor' => 'berkah@tani.test',        'jenis' => 'siup', 'nomor' => 'SIUP/003/2024',          'status' => 'pending',  'file' => 'vendor/dokumen/vendor3-siup.pdf'],
        ];

        foreach ($dokumen as $d) {
            $approved = $d['status'] === 'approved';

            DokumenLegalitasVendor::updateOrCreate(
                ['vendor_id' => $vendors[$d['vendor']]->id, 'jenis_dokumen' => $d['jenis']],
                [
                    'nomor_dokumen' => $d['nomor'],
                    'file_url' => $d['file'],
                    'status_verifikasi' => $d['status'],
                    'verif_by' => $approved ? $superadmin->id : null,
                    'diverifikasi_pada' => $approved ? now()->subDays(30) : null,
                ]
            );
        }
    }
}
