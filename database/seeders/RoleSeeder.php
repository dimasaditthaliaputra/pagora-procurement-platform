<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'id' => 1,
                'name' => 'superadmin',
                'description' => 'Akses penuh ke seluruh sistem, master data, konfigurasi, dan audit trail.',
            ],
            [
                'id' => 2,
                'name' => 'purchasing',
                'description' => 'Tim Purchasing / Finance: pantau Harga Pagu, kelola PO & negosiasi, verifikasi tagihan (3-Way Matching), pelunasan, dan buka sengketa.',
            ],
            [
                'id' => 3,
                'name' => 'qc_gudang',
                'description' => 'Petugas inspeksi docking gudang: memeriksa fisik, unggah bukti foto, dan rilis GRN.',
            ],
            [
                'id' => 4,
                'name' => 'vendor',
                'description' => 'Pemasok bahan baku mitra: guided bidding, pelaporan armada ekspedisi, dan pengajuan invoice.',
            ],
            [
                'id' => 5,
                'name' => 'manajemen',
                'description' => 'Admin internal Tenant: aktivasi komoditas & toleransi susut, kelola kerja sama, undang & blokir vendor per Tenant, tinjau dan tetapkan sengketa Selesai.',
            ],
            [
                'id' => 6,
                'name' => 'owner',
                'description' => 'Pemilik perusahaan Tenant: manajemen perusahaan & anggota, seluruh aktivitas internal Tenant, dan menetapkan sengketa Selesai.',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['id' => $role['id']],
                [
                    'name' => $role['name'],
                    'description' => $role['description'],
                ]
            );
        }

        $this->call(PermissionSeeder::class);
    }
}
