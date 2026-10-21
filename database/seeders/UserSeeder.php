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
 * role_id mengikuti RoleSeeder: 1 superadmin, 2 buyer, 3 qc_gudang, 4 vendor, 5 manajemen, 6 owner.
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
            ['email' => 'superadmin@pagora.test', 'nama_lengkap' => 'Super Administrator',         'role_id' => 1, 'perusahaan_id' => null,       'vendor_id' => null,         'status_aktif' => true],
            ['email' => 'manajemen@pagora.test',  'nama_lengkap' => 'Admin Manajemen',             'role_id' => 5, 'perusahaan_id' => $buyer->id, 'vendor_id' => null,         'status_aktif' => true],
            ['email' => 'owner@pagora.test',      'nama_lengkap' => 'Owner Perusahaan',            'role_id' => 6, 'perusahaan_id' => $buyer->id, 'vendor_id' => null,         'status_aktif' => true],
            ['email' => 'buyer@pagora.test',      'nama_lengkap' => 'Staff Buyer',                 'role_id' => 2, 'perusahaan_id' => $buyer->id, 'vendor_id' => null,         'status_aktif' => true],
            ['email' => 'qc@pagora.test',         'nama_lengkap' => 'Petugas QC Gudang',           'role_id' => 3, 'perusahaan_id' => $buyer->id, 'vendor_id' => null,         'status_aktif' => true],
            ['email' => 'vendor1@pagora.test',    'nama_lengkap' => 'Budi Santoso (Mitra Agro)',   'role_id' => 4, 'perusahaan_id' => null,       'vendor_id' => $vendor1->id, 'status_aktif' => true],
            ['email' => 'vendor2@pagora.test',    'nama_lengkap' => 'Siti Rahayu (Sumber Pangan)', 'role_id' => 4, 'perusahaan_id' => null,       'vendor_id' => $vendor2->id, 'status_aktif' => true],
            ['email' => 'vendor3@pagora.test',    'nama_lengkap' => 'Ahmad Fauzi (Berkah Tani)',   'role_id' => 4, 'perusahaan_id' => null,       'vendor_id' => $vendor3->id, 'status_aktif' => true],
            ['email' => 'blokir@pagora.test',     'nama_lengkap' => 'Akun Nonaktif (Demo)',        'role_id' => 4, 'perusahaan_id' => null,       'vendor_id' => $vendor3->id, 'status_aktif' => false],
        ];

        foreach ($accounts as $data) {
            User::updateOrCreate(
                ['email' => $data['email']],
                $data + ['password' => Hash::make('password')]
            );
        }
    }
}
