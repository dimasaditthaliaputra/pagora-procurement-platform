<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatusKerjaSama;
use App\Models\KerjaSama;
use App\Models\PerusahaanBuyer;
use App\Models\Vendor;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnershipTest extends TestCase
{
    use RefreshDatabase;

    private function createVendor(): Vendor
    {
        return Vendor::create([
            'company_name' => 'PT Mitra Uji',
            'slug' => 'pt-mitra-uji',
            'contact_email' => 'mitra@uji.test',
            'phone' => '081234567890',
            'address' => 'Jl. Uji No. 1',
            'kategori_komoditas' => 'Pangan Pokok',
            'verification_status' => 'approved',
            'is_active' => true,
        ]);
    }

    public function test_partnership_defaults_to_tenant_requested_and_casts_status(): void
    {
        $partnership = KerjaSama::create([
            'tenant_id' => PerusahaanBuyer::factory()->create()->id,
            'vendor_id' => $this->createVendor()->id,
            'status' => StatusKerjaSama::Pending,
        ])->fresh();

        $this->assertSame('tenant', $partnership->requested_by);
        $this->assertSame(StatusKerjaSama::Pending, $partnership->status);
        $this->assertSame('Menunggu', $partnership->status->label());
    }

    public function test_tenant_and_vendor_can_only_have_one_partnership(): void
    {
        $tenant = PerusahaanBuyer::factory()->create();
        $vendor = $this->createVendor();

        KerjaSama::create(['tenant_id' => $tenant->id, 'vendor_id' => $vendor->id, 'status' => StatusKerjaSama::Pending]);

        $this->expectException(QueryException::class);

        KerjaSama::create(['tenant_id' => $tenant->id, 'vendor_id' => $vendor->id, 'status' => StatusKerjaSama::Agreed]);
    }
}
