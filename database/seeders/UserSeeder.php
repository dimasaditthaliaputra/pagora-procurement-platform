<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PerusahaanBuyer;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo seeder: hanya local/testing.
 * Semua akun demo pakai password: password
 * role_id mengikuti RoleSeeder: 1 superadmin, 2 purchasing, 3 qc_gudang, 4 vendor, 5 manajemen, 6 owner.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $buyer = PerusahaanBuyer::where('nama_perusahaan', 'PT Pagora Nusantara')->firstOrFail();
        $vendor1 = Vendor::where('email_kontak', 'kontak@mitra-agro.test')->firstOrFail();
        $vendor2 = Vendor::where('email_kontak', 'info@sumber-pangan.test')->firstOrFail();
        $vendor3 = Vendor::where('email_kontak', 'berkah@tani.test')->firstOrFail();

        $accounts = [
            ['email' => 'superadmin@pagora.test', 'name' => 'Super Administrator',         'role_id' => 1, 'tenant_id' => null,       'vendor_id' => null,         'is_active' => true],
            ['email' => 'manajemen@pagora.test',  'name' => 'Admin Manajemen',             'role_id' => 5, 'tenant_id' => $buyer->id, 'vendor_id' => null,         'is_active' => true],
            ['email' => 'owner@pagora.test',      'name' => 'Owner Perusahaan',            'role_id' => 6, 'tenant_id' => $buyer->id, 'vendor_id' => null,         'is_active' => true],
            ['email' => 'buyer@pagora.test',      'name' => 'Staff Purchasing',            'role_id' => 2, 'tenant_id' => $buyer->id, 'vendor_id' => null,         'is_active' => true],
            ['email' => 'qc@pagora.test',         'name' => 'Petugas QC Gudang',           'role_id' => 3, 'tenant_id' => $buyer->id, 'vendor_id' => null,         'is_active' => true],
            ['email' => 'vendor1@pagora.test',    'name' => 'Budi Santoso (Mitra Agro)',   'role_id' => 4, 'tenant_id' => null,       'vendor_id' => $vendor1->id, 'is_active' => true],
            ['email' => 'vendor2@pagora.test',    'name' => 'Siti Rahayu (Sumber Pangan)', 'role_id' => 4, 'tenant_id' => null,       'vendor_id' => $vendor2->id, 'is_active' => true],
            ['email' => 'vendor3@pagora.test',    'name' => 'Ahmad Fauzi (Berkah Tani)',   'role_id' => 4, 'tenant_id' => null,       'vendor_id' => $vendor3->id, 'is_active' => true],
            ['email' => 'blokir@pagora.test',     'name' => 'Akun Nonaktif (Demo)',        'role_id' => 4, 'tenant_id' => null,       'vendor_id' => $vendor3->id, 'is_active' => false],
        ];

        foreach ($accounts as $data) {
            User::updateOrCreate(
                ['email' => $data['email']],
                // Demo accounts count as verified so they skip the activation OTP and can reset passwords.
                $data + ['password' => Hash::make('password'), 'email_verified_at' => now()]
            );
        }
    }
}
