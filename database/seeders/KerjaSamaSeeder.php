<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\StatusKerjaSama;
use App\Models\KerjaSama;
use App\Models\PerusahaanBuyer;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

/**
 * Demo seeder: hanya local/testing.
 */
class KerjaSamaSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $buyer = PerusahaanBuyer::where('nama_perusahaan', 'PT Pagora Nusantara')->firstOrFail();

        $data = [
            ['email' => 'kontak@mitra-agro.test',  'status' => StatusKerjaSama::Aktif, 'catatan' => null],
            ['email' => 'info@sumber-pangan.test', 'status' => StatusKerjaSama::Aktif, 'catatan' => null],
            ['email' => 'berkah@tani.test',        'status' => StatusKerjaSama::Diajukan, 'catatan' => null],
        ];

        foreach ($data as $d) {
            $vendor = Vendor::where('email_kontak', $d['email'])->firstOrFail();

            KerjaSama::updateOrCreate(
                ['id_perusahaan' => $buyer->id, 'id_vendor' => $vendor->id],
                [
                    'status_kerjasama' => $d['status']->value,
                    'catatan_penolakan' => $d['catatan'],
                ]
            );
        }
    }
}
