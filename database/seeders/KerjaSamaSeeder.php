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

        $buyer = PerusahaanBuyer::where('company_name', 'PT Pagora Nusantara')->firstOrFail();

        $data = [
            ['email' => 'kontak@mitra-agro.test',  'status' => StatusKerjaSama::Agreed, 'reason' => null],
            ['email' => 'info@sumber-pangan.test', 'status' => StatusKerjaSama::Agreed, 'reason' => null],
            ['email' => 'berkah@tani.test',        'status' => StatusKerjaSama::Pending, 'reason' => null],
        ];

        foreach ($data as $d) {
            $vendor = Vendor::where('contact_email', $d['email'])->firstOrFail();

            KerjaSama::updateOrCreate(
                ['tenant_id' => $buyer->id, 'vendor_id' => $vendor->id],
                [
                    'requested_by' => 'tenant',
                    'status' => $d['status']->value,
                    'rejection_reason' => $d['reason'],
                ]
            );
        }
    }
}
