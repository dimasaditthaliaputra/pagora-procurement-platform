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
                'nama_role' => 'superadmin',
                'deskripsi' => 'Akses penuh ke seluruh sistem, master data, konfigurasi, dan audit trail.',
            ],
            [
                'id' => 2,
                'nama_role' => 'buyer',
                'deskripsi' => 'Tim purchasing manufaktur: memantau harga acuan BI, menerbitkan PO, dan negosiasi.',
            ],
            [
                'id' => 3,
                'nama_role' => 'qc_gudang',
                'deskripsi' => 'Petugas inspeksi docking gudang: memeriksa fisik, unggah bukti foto, dan rilis GRN.',
            ],
            [
                'id' => 4,
                'nama_role' => 'vendor',
                'deskripsi' => 'Pemasok bahan baku mitra: guided bidding, pelaporan armada ekspedisi, dan pengajuan invoice.',
            ],
            [
                'id' => 5,
                'nama_role' => 'manajemen',
                'deskripsi' => 'Admin internal perusahaan buyer: verifikasi vendor rekanan, tinjau sengketa, kelola staf buyer/QC.',
            ],
            [
                'id' => 6,
                'nama_role' => 'owner',
                'deskripsi' => 'Pemilik perusahaan buyer: pantau dasbor, PO, anggaran, invoice, dan sengketa (read-only).',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['id' => $role['id']],
                [
                    'nama_role' => $role['nama_role'],
                    'deskripsi' => $role['deskripsi'],
                ]
            );
        }
    }
}
