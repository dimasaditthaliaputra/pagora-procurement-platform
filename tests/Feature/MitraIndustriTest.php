<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\VerificationStatus;
use App\Models\DokumenLegalitasVendor;
use App\Models\PerusahaanBuyer;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MitraIndustriTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    private User $manajemen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);

        $this->superadmin = User::factory()->create([
            'role_id' => 1, // Superadmin
            'name' => 'Super Administrator',
            'email' => 'superadmin@pagora.test',
            'is_active' => true,
        ]);

        $this->manajemen = User::factory()->create([
            'role_id' => 5, // Manajemen (Tenant)
            'name' => 'Staff Manajemen',
            'email' => 'manajemen@pagora.test',
            'is_active' => true,
        ]);
    }

    private function createTenant(array $attributes = []): PerusahaanBuyer
    {
        return PerusahaanBuyer::factory()->create(array_merge([
            'company_name' => 'PT Buyer Mitra Industri',
            'tax_number' => '01.234.567.8-901.000',
            'address' => 'Jl. Jenderal Sudirman No. 10, Jakarta Pusat',
            'verification_status' => VerificationStatus::Pending,
        ], $attributes));
    }

    private function createVendor(array $attributes = []): Vendor
    {
        $name = $attributes['company_name'] ?? 'PT Rekanan Vendor Sukses';
        $slug = Str::slug($name).'-'.Str::lower(Str::random(6));

        return Vendor::create(array_merge([
            'company_name' => $name,
            'slug' => $slug,
            'contact_email' => 'vendor@rekanan.test',
            'phone' => '081234567890',
            'kategori_komoditas' => 'Beras & Jagung',
            'verification_status' => VerificationStatus::Approved,
            'is_active' => true,
        ], $attributes));
    }

    public function test_unauthenticated_user_cannot_access_mitra_industri(): void
    {
        $response = $this->get('/manajemen/mitra-industri');
        $response->assertRedirect('/login');
    }

    public function test_non_superadmin_cannot_access_mitra_industri(): void
    {
        $response = $this->actingAs($this->manajemen)->get('/manajemen/mitra-industri');
        $response->assertForbidden();
    }

    public function test_superadmin_can_access_mitra_industri_tenant_tab(): void
    {
        $this->createTenant();

        $response = $this->actingAs($this->superadmin)->get('/manajemen/mitra-industri?tab=tenant');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Master/MitraIndustri/Index')
            ->where('active_tab', 'tenant')
            ->has('tenants.data', 1)
            ->has('tenant_stats')
        );
    }

    public function test_superadmin_can_access_mitra_industri_vendor_verifikasi_tab(): void
    {
        $vendor = $this->createVendor(['verification_status' => VerificationStatus::Pending]);
        DokumenLegalitasVendor::create([
            'vendor_id' => $vendor->id,
            'document_type' => 'nib',
            'document_number' => 'NIB-12345678',
            'file_path' => 'legalitas/nib.pdf',
            'verification_status' => VerificationStatus::Pending,
        ]);

        $response = $this->actingAs($this->superadmin)->get('/manajemen/mitra-industri?tab=vendor&vendor_section=verifikasi');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Master/MitraIndustri/Index')
            ->where('active_tab', 'vendor')
            ->where('active_vendor_section', 'verifikasi')
            ->has('vendor_docs.data', 1)
            ->has('vendor_stats', fn ($stats) => $stats
                ->where('total_vendors', 1)
                ->where('active_vendors', 1)
                ->where('blocked_vendors', 0)
                ->where('pending_review_vendors', 1)
            )
        );
    }

    public function test_superadmin_can_access_mitra_industri_vendor_rekanan_tab(): void
    {
        $this->createVendor();

        $response = $this->actingAs($this->superadmin)->get('/manajemen/mitra-industri?tab=vendor&vendor_section=rekanan');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Master/MitraIndustri/Index')
            ->where('active_tab', 'vendor')
            ->where('active_vendor_section', 'rekanan')
            ->has('vendor_rekanan.data', 1)
        );
    }

    public function test_superadmin_can_verify_tenant_via_mitra_industri(): void
    {
        $tenant = $this->createTenant();

        $response = $this->actingAs($this->superadmin)->post(
            "/manajemen/mitra-industri/tenant/{$tenant->uuid}/verifikasi",
            [
                'aksi' => 'approve',
            ]
        );

        $response->assertSessionHas('success');
        $tenant->refresh();
        $this->assertSame(VerificationStatus::Approved, $tenant->verification_status);
    }

    public function test_superadmin_can_verify_vendor_doc_via_mitra_industri(): void
    {
        $vendor = $this->createVendor(['verification_status' => VerificationStatus::Pending]);
        $doc = DokumenLegalitasVendor::create([
            'vendor_id' => $vendor->id,
            'document_type' => 'nib',
            'document_number' => 'NIB-99998888',
            'file_path' => 'legalitas/nib.pdf',
            'verification_status' => VerificationStatus::Pending,
        ]);

        $response = $this->actingAs($this->superadmin)->post(
            "/manajemen/mitra-industri/vendor/{$doc->uuid}/verifikasi",
            [
                'aksi' => 'approve',
            ]
        );

        $response->assertSessionHas('success');
        $doc->refresh();
        $this->assertSame(VerificationStatus::Approved, $doc->verification_status);
    }

    public function test_superadmin_can_block_and_unblock_vendor_via_mitra_industri(): void
    {
        $vendor = $this->createVendor(['is_active' => true]);

        // Blokir
        $response = $this->actingAs($this->superadmin)->post(
            "/manajemen/mitra-industri/vendor/{$vendor->uuid}/blokir",
            [
                'block_reason' => 'Vendor melanggar ketentuan operasional platform.',
            ]
        );

        $response->assertSessionHas('success');
        $vendor->refresh();
        $this->assertFalse($vendor->is_active);
        $this->assertSame('Vendor melanggar ketentuan operasional platform.', $vendor->block_reason);

        // Buka Blokir
        $responseUnblock = $this->actingAs($this->superadmin)->post(
            "/manajemen/mitra-industri/vendor/{$vendor->uuid}/buka-blokir"
        );

        $responseUnblock->assertSessionHas('success');
        $vendor->refresh();
        $this->assertTrue($vendor->is_active);
    }
}
