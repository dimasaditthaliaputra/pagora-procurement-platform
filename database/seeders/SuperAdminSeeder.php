<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@pagora.id'],
            [
                'uuid' => (string) Str::uuid(),
                'nama_lengkap' => 'Super Administrator PAGORA',
                'password' => Hash::make('password'),
                'role_id' => 1, // ID Role Superadmin
                'vendor_id' => null,
                'status_aktif' => true,
            ]
        );
    }
}
