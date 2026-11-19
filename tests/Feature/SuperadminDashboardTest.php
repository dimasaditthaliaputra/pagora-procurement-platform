<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\VerificationStatus;
use App\Models\CommodityCategory;
use App\Models\DokumenLegalitasVendor;
use App\Models\Komoditas;
use App\Models\PerusahaanBuyer;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SuperadminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);

        $this->superadmin = User::factory()->create([
            'name' => 'Superadmin Utama',
            'email' => 'superadmin@pagora.test',
            'role_id' => Role::where('name', RoleName::Superadmin->value)->value('id'),
            'is_active' => true,
        ]);

        $approvedTenant = PerusahaanBuyer::factory()->create([
            'company_name' => 'PT Pangan Makmur Sejahtera',
            'verification_status' => VerificationStatus::Approved->value,
            'is_active' => true,
        ]);

        PerusahaanBuyer::factory()->create([
            'company_name' => 'PT Tenant Menunggu Verifikasi',
            'verification_status' => VerificationStatus::Pending->value,
            'is_active' => false,
        ]);

        $this->owner = User::factory()->create([
            'name' => 'Owner Tenant',
            'email' => 'owner@pagora.test',
            'role_id' => Role::where('name', RoleName::Owner->value)->value('id'),
            'tenant_id' => $approvedTenant->id,
            'is_active' => true,
        ]);

        $pendingVendor = Vendor::create([
            'company_name' => 'CV Pemasok Baru Pending',
            'slug' => 'cv-pemasok-baru-'.Str::lower(Str::random(5)),
            'business_entity' => 'CV',
            'contact_email' => 'pending@vendor.test',
            'phone' => '081234567890',
            'verification_status' => VerificationStatus::Pending->value,
            'is_active' => true,
        ]);

        DokumenLegalitasVendor::create([
            'uuid' => (string) Str::uuid(),
            'vendor_id' => $pendingVendor->id,
            'document_type' => 'npwp',
            'document_number' => '12.345.678.9-012.000',
            'file_path' => 'legalitas/npwp-test.pdf',
            'verification_status' => VerificationStatus::Pending->value,
        ]);

        Vendor::create([
            'company_name' => 'PT Vendor Diblokir Global',
            'slug' => 'pt-vendor-diblokir-'.Str::lower(Str::random(5)),
            'business_entity' => 'PT',
            'contact_email' => 'blocked@vendor.test',
            'phone' => '081234567899',
            'verification_status' => VerificationStatus::Approved->value,
            'is_active' => false,
            'block_reason' => 'Pelanggaran dokumen legalitas.',
            'blocked_by' => $this->superadmin->id,
            'blocked_at' => now(),
        ]);

        $category = CommodityCategory::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Beras & Biji-bijian',
            'description' => 'Komoditas pangan pokok',
            'is_active' => true,
        ]);

        Komoditas::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Beras IR-64 Premium',
            'slug' => 'beras-ir-64-premium',
            'sku' => 'BRS-IR64',
            'commodity_category_id' => $category->id,
            'unit' => 'kg',
            'default_shrinkage_tolerance' => 1.5,
            'api_reference_code' => 'BI-BERAS-01',
            'is_active' => true,
        ]);
    }

    public function test_superadmin_receives_platform_governance_dashboard_metrics(): void
    {
        $this->actingAs($this->superadmin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('is_superadmin', true)
                ->where('superadmin_dashboard.summary.tenants.total', 2)
                ->where('superadmin_dashboard.summary.tenants.pending', 1)
                ->where('superadmin_dashboard.summary.tenants.approved', 1)
                ->where('superadmin_dashboard.summary.vendors.total', 2)
                ->where('superadmin_dashboard.summary.vendors.pending', 1)
                ->where('superadmin_dashboard.summary.vendors.blocked', 1)
                ->where('superadmin_dashboard.summary.vendors.pending_documents', 1)
                ->where('superadmin_dashboard.summary.commodities.total', 1)
                ->where('superadmin_dashboard.summary.commodities.with_api_code', 1)
                ->where('superadmin_dashboard.summary.commodities.categories_total', 1)
                ->has('superadmin_dashboard.pending_tenants', 1)
                ->where('superadmin_dashboard.pending_tenants.0.company_name', 'PT Tenant Menunggu Verifikasi')
                ->missing('superadmin_dashboard.pending_tenants.0.id')
                ->has('superadmin_dashboard.pending_vendors', 1)
                ->where('superadmin_dashboard.pending_vendors.0.company_name', 'CV Pemasok Baru Pending')
                ->missing('superadmin_dashboard.pending_vendors.0.id')
                ->has('superadmin_dashboard.blocked_vendors', 1)
                ->where('superadmin_dashboard.blocked_vendors.0.company_name', 'PT Vendor Diblokir Global')
                ->has('superadmin_dashboard.role_distribution', 6)
                ->has('superadmin_dashboard.category_distribution', 1)
                ->has('superadmin_dashboard.monthly_growth', 6)
                ->has('superadmin_dashboard.system_integrations')
            );
    }

    public function test_non_superadmin_does_not_receive_superadmin_dashboard_payload(): void
    {
        $this->actingAs($this->owner)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('is_superadmin', false)
                ->where('superadmin_dashboard', null)
            );
    }
}
