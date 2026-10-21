<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PerusahaanBuyer;
use Illuminate\Database\Seeder;

/**
 * Demo seeder: hanya local/testing.
 */
class PerusahaanBuyerSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        PerusahaanBuyer::updateOrCreate(
            ['nama_perusahaan' => 'PT Pagora Nusantara'],
            [
                'nomor_npwp' => '01.234.567.8-012.000',
                'file_url' => null,
                'alamat' => 'Jl. Industri Raya No. 1, Surabaya, Jawa Timur',
                'tipe_paket' => 'enterprise',
                'status_aktif' => true,
            ]
        );
    }
}
