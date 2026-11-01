<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\VerificationStatus;
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
            ['company_name' => 'PT Pagora Nusantara'],
            [
                'tax_number' => '01.234.567.8-012.000',
                'legal_document_path' => null,
                'address' => 'Jl. Industri Raya No. 1, Surabaya, Jawa Timur',
                'verification_status' => VerificationStatus::Approved,
                'is_active' => true,
            ]
        );
    }
}
