<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PemicuSengketa;
use App\Enums\RoleName;
use App\Enums\StatusItemPenerimaan;
use App\Enums\StatusKerjaSama;
use App\Enums\StatusMatching;
use App\Enums\StatusPurchaseOrder;
use App\Enums\StatusSengketa;
use App\Enums\VerificationStatus;
use App\Models\CommodityCategory;
use App\Models\DokumenLegalitasVendor;
use App\Models\Invoice;
use App\Models\KerjaSama;
use App\Models\Komoditas;
use App\Models\PenerimaanBarang;
use App\Models\PenerimaanBarangItem;
use App\Models\Pengiriman;
use App\Models\PerusahaanBuyer;
use App\Models\PoItem;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Sengketa;
use App\Models\TenantCommodity;
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
        $currentMonthKey = now()->format('Y-m');
        $fiveMonthsAgoKey = now()->startOfMonth()->subMonths(5)->format('Y-m');

        $this->actingAs($this->superadmin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('is_superadmin', true)
                ->where('superadmin_dashboard.summary.tenants.total', 2)
                ->where('superadmin_dashboard.summary.tenants.pending', 1)
                ->where('superadmin_dashboard.summary.tenants.approved', 1)
                ->where('superadmin_dashboard.summary.tenants.rejected', 0)
                ->where('superadmin_dashboard.summary.tenants.active', 1)
                ->where('superadmin_dashboard.summary.vendors.total', 2)
                ->where('superadmin_dashboard.summary.vendors.pending', 1)
                ->where('superadmin_dashboard.summary.vendors.approved', 1)
                ->where('superadmin_dashboard.summary.vendors.rejected', 0)
                ->where('superadmin_dashboard.summary.vendors.blocked', 1)
                ->where('superadmin_dashboard.summary.vendors.active', 0)
                ->where('superadmin_dashboard.summary.vendors.pending_documents', 1)
                ->where('superadmin_dashboard.summary.commodities.total', 1)
                ->where('superadmin_dashboard.summary.commodities.active', 1)
                ->where('superadmin_dashboard.summary.commodities.inactive', 0)
                ->where('superadmin_dashboard.summary.commodities.with_api_code', 1)
                ->where('superadmin_dashboard.summary.commodities.categories_total', 1)
                ->where('superadmin_dashboard.summary.commodities.categories_active', 1)
                ->where('superadmin_dashboard.summary.users.total', 2)
                ->where('superadmin_dashboard.summary.users.active', 2)
                ->where('superadmin_dashboard.summary.users.inactive', 0)
                ->where('superadmin_dashboard.summary.ecosystem.partnerships_total', 0)
                ->where('superadmin_dashboard.summary.ecosystem.partnerships_agreed', 0)
                ->where('superadmin_dashboard.summary.ecosystem.partnerships_pending', 0)
                ->where('superadmin_dashboard.summary.ecosystem.purchase_orders_total', 0)
                ->where('superadmin_dashboard.summary.ecosystem.disputes_open', 0)
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
                ->where('superadmin_dashboard.monthly_growth.0.month_key', $fiveMonthsAgoKey)
                ->where('superadmin_dashboard.monthly_growth.0.tenants_count', 0)
                ->where('superadmin_dashboard.monthly_growth.0.vendors_count', 0)
                ->where('superadmin_dashboard.monthly_growth.0.purchase_orders_count', 0)
                ->where('superadmin_dashboard.monthly_growth.5.month_key', $currentMonthKey)
                ->where('superadmin_dashboard.monthly_growth.5.tenants_count', 2)
                ->where('superadmin_dashboard.monthly_growth.5.vendors_count', 2)
                ->where('superadmin_dashboard.monthly_growth.5.purchase_orders_count', 0)
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
                ->where('is_manajemen', false)
                ->where('manajemen_dashboard', null)
            );
    }

    public function test_manajemen_receives_tenant_scoped_dashboard_metrics_and_excludes_other_tenants(): void
    {
        $tenantA = PerusahaanBuyer::where('company_name', 'PT Pangan Makmur Sejahtera')->firstOrFail();
        $tenantB = PerusahaanBuyer::factory()->create([
            'company_name' => 'PT Tenant Lain Terisolasi',
            'verification_status' => VerificationStatus::Approved->value,
            'is_active' => true,
        ]);

        $manajemenA = User::factory()->create([
            'name' => 'Manajemen Tenant A',
            'email' => 'manajemen-a@pagora.test',
            'role_id' => Role::where('name', RoleName::Manajemen->value)->value('id'),
            'tenant_id' => $tenantA->id,
            'is_active' => true,
        ]);

        $vendor = Vendor::create([
            'company_name' => 'PT Mitra Pemasok Utama',
            'slug' => 'pt-mitra-pemasok-utama-'.Str::lower(Str::random(5)),
            'business_entity' => 'PT',
            'contact_email' => 'mitra@vendor.test',
            'phone' => '081234560001',
            'verification_status' => VerificationStatus::Approved->value,
            'is_active' => true,
        ]);

        KerjaSama::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantA->id,
            'vendor_id' => $vendor->id,
            'requested_by' => 'tenant',
            'status' => StatusKerjaSama::Agreed->value,
        ]);

        // Partnership belonging to Tenant B (must not be counted for Tenant A)
        KerjaSama::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantB->id,
            'vendor_id' => $vendor->id,
            'requested_by' => 'vendor',
            'status' => StatusKerjaSama::Pending->value,
        ]);

        $commodity = Komoditas::firstOrFail();
        TenantCommodity::create([
            'tenant_id' => $tenantA->id,
            'commodity_id' => $commodity->id,
            'is_active' => true,
            'shrinkage_tolerance' => 2.25,
        ]);

        $poA = PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-MJM-A001',
            'perusahaan_buyer_id' => $tenantA->id,
            'vendor_id' => $vendor->id,
            'created_by' => $this->owner->id,
            'status' => StatusPurchaseOrder::DalamPengiriman->value,
            'tanggal_po' => now()->toDateString(),
        ]);

        $poB = PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-MJM-B001',
            'perusahaan_buyer_id' => $tenantB->id,
            'vendor_id' => $vendor->id,
            'created_by' => $this->owner->id,
            'status' => StatusPurchaseOrder::DalamPengiriman->value,
            'tanggal_po' => now()->toDateString(),
        ]);

        Sengketa::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $poA->id,
            'trigger_type' => PemicuSengketa::GoodsMismatch->value,
            'reason' => 'Kemasan beras sobek saat tiba di gudang A.',
            'opened_by' => $this->owner->id,
            'status' => StatusSengketa::Open->value,
            'opened_at' => now(),
        ]);

        // Dispute belonging to Tenant B (must not leak to Tenant A)
        Sengketa::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $poB->id,
            'trigger_type' => PemicuSengketa::GoodsMismatch->value,
            'reason' => 'Sengketa milik Tenant B.',
            'opened_by' => $this->owner->id,
            'status' => StatusSengketa::Open->value,
            'opened_at' => now(),
        ]);

        $currentMonthKey = now()->format('Y-m');
        $fiveMonthsAgoKey = now()->startOfMonth()->subMonths(5)->format('Y-m');

        $this->actingAs($manajemenA)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('is_superadmin', false)
                ->where('superadmin_dashboard', null)
                ->where('is_manajemen', true)
                ->where('manajemen_dashboard.company.company_name', 'PT Pangan Makmur Sejahtera')
                ->where('manajemen_dashboard.summary.disputes.total', 1)
                ->where('manajemen_dashboard.summary.disputes.open', 1)
                ->where('manajemen_dashboard.summary.disputes.in_mediation', 0)
                ->where('manajemen_dashboard.summary.disputes.resolved', 0)
                ->where('manajemen_dashboard.summary.partnerships.total', 1)
                ->where('manajemen_dashboard.summary.partnerships.agreed', 1)
                ->where('manajemen_dashboard.summary.partnerships.pending_incoming', 0)
                ->where('manajemen_dashboard.summary.partnerships.pending_outgoing', 0)
                ->where('manajemen_dashboard.summary.partnerships.blocked', 0)
                ->where('manajemen_dashboard.summary.partnerships.invitations_pending', 0)
                ->where('manajemen_dashboard.summary.qc_inspections.total_receipts', 0)
                ->where('manajemen_dashboard.summary.qc_inspections.in_transit_orders', 1)
                ->where('manajemen_dashboard.summary.qc_inspections.accepted_items', 0)
                ->where('manajemen_dashboard.summary.qc_inspections.rejected_items', 0)
                ->where('manajemen_dashboard.summary.commodities.global_active_total', 1)
                ->where('manajemen_dashboard.summary.commodities.tenant_active', 1)
                ->where('manajemen_dashboard.summary.commodities.custom_tolerance_count', 1)
                ->where('manajemen_dashboard.summary.commodities.active_purchase_orders', 1)
                ->has('manajemen_dashboard.active_disputes', 1)
                ->where('manajemen_dashboard.active_disputes.0.po_number', 'PO-MJM-A001')
                ->missing('manajemen_dashboard.active_disputes.0.id')
                ->has('manajemen_dashboard.vendor_partnerships', 1)
                ->where('manajemen_dashboard.vendor_partnerships.0.vendor_name', 'PT Mitra Pemasok Utama')
                ->missing('manajemen_dashboard.vendor_partnerships.0.id')
                ->has('manajemen_dashboard.tenant_commodities', 1)
                ->has('manajemen_dashboard.monthly_trend', 6)
                ->where('manajemen_dashboard.monthly_trend.0.month_key', $fiveMonthsAgoKey)
                ->where('manajemen_dashboard.monthly_trend.0.purchase_orders_count', 0)
                ->where('manajemen_dashboard.monthly_trend.0.qc_inspections_count', 0)
                ->where('manajemen_dashboard.monthly_trend.0.disputes_count', 0)
                ->where('manajemen_dashboard.monthly_trend.5.month_key', $currentMonthKey)
                ->where('manajemen_dashboard.monthly_trend.5.purchase_orders_count', 1)
                ->where('manajemen_dashboard.monthly_trend.5.qc_inspections_count', 0)
                ->where('manajemen_dashboard.monthly_trend.5.disputes_count', 1)
            );
    }

    public function test_owner_receives_executive_tenant_dashboard_and_excludes_other_tenants(): void
    {
        $tenantA = PerusahaanBuyer::where('company_name', 'PT Pangan Makmur Sejahtera')->firstOrFail();
        $tenantB = PerusahaanBuyer::factory()->create([
            'company_name' => 'PT Tenant Kompetitor B',
            'verification_status' => VerificationStatus::Approved->value,
            'is_active' => true,
        ]);

        // Add internal employees to Tenant A
        User::factory()->create([
            'name' => 'Staf Purchasing A',
            'email' => 'purchasing-a@pagora.test',
            'role_id' => Role::where('name', RoleName::Purchasing->value)->value('id'),
            'tenant_id' => $tenantA->id,
            'is_active' => true,
        ]);

        User::factory()->create([
            'name' => 'Staf QC Gudang A',
            'email' => 'qc-a@pagora.test',
            'role_id' => Role::where('name', RoleName::QcGudang->value)->value('id'),
            'tenant_id' => $tenantA->id,
            'is_active' => true,
        ]);

        // Employee in Tenant B (must not be counted in Tenant A)
        User::factory()->create([
            'name' => 'Pegawai Tenant B',
            'email' => 'staff-b@pagora.test',
            'role_id' => Role::where('name', RoleName::Purchasing->value)->value('id'),
            'tenant_id' => $tenantB->id,
            'is_active' => true,
        ]);

        $vendor = Vendor::create([
            'company_name' => 'PT Pemasok Beras Nusantara',
            'slug' => 'pt-pemasok-beras-nusantara-'.Str::lower(Str::random(5)),
            'business_entity' => 'PT',
            'contact_email' => 'beras@vendor.test',
            'phone' => '081234560002',
            'verification_status' => VerificationStatus::Approved->value,
            'is_active' => true,
        ]);

        $commodity = Komoditas::firstOrFail();

        $poA = PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-OWN-A001',
            'perusahaan_buyer_id' => $tenantA->id,
            'vendor_id' => $vendor->id,
            'created_by' => $this->owner->id,
            'status' => StatusPurchaseOrder::Disepakati->value,
            'tanggal_po' => now()->toDateString(),
        ]);

        PoItem::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $poA->id,
            'komoditas_id' => $commodity->id,
            'jumlah_pesanan' => 1000,
            'estimated_unit_price' => 12000,
            'harga_satuan_po' => 12500,
            'subtotal' => 12500000,
        ]);

        Invoice::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $poA->id,
            'vendor_id' => $vendor->id,
            'nomor_invoice_vendor' => 'INV-VND-001',
            'jumlah_tagihan' => 12500000,
            'tanggal_invoice' => now()->toDateString(),
            'status_matching' => StatusMatching::Pending->value,
        ]);

        // PO & Invoice for Tenant B (must not leak to Owner A)
        $poB = PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-OWN-B001',
            'perusahaan_buyer_id' => $tenantB->id,
            'vendor_id' => $vendor->id,
            'created_by' => $this->owner->id,
            'status' => StatusPurchaseOrder::Disepakati->value,
            'tanggal_po' => now()->toDateString(),
        ]);

        Invoice::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $poB->id,
            'vendor_id' => $vendor->id,
            'nomor_invoice_vendor' => 'INV-VND-B99',
            'jumlah_tagihan' => 99000000,
            'tanggal_invoice' => now()->toDateString(),
            'status_matching' => StatusMatching::Pending->value,
        ]);

        $currentMonthKey = now()->format('Y-m');
        $fiveMonthsAgoKey = now()->startOfMonth()->subMonths(5)->format('Y-m');

        $this->actingAs($this->owner)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('is_superadmin', false)
                ->where('superadmin_dashboard', null)
                ->where('is_manajemen', false)
                ->where('manajemen_dashboard', null)
                ->where('is_owner', true)
                ->where('owner_dashboard.company.company_name', 'PT Pangan Makmur Sejahtera')
                ->where('owner_dashboard.summary.procurement.total_orders', 1)
                ->where('owner_dashboard.summary.procurement.draft_and_negotiation', 0)
                ->where('owner_dashboard.summary.procurement.in_execution', 1)
                ->where('owner_dashboard.summary.procurement.invoiced_and_ready_to_pay', 0)
                ->where('owner_dashboard.summary.procurement.completed_paid', 0)
                ->where('owner_dashboard.summary.procurement.disputed_orders', 0)
                ->where('owner_dashboard.summary.procurement.cancelled_orders', 0)
                ->where('owner_dashboard.summary.procurement.total_agreed_value', 12500000)
                ->where('owner_dashboard.summary.invoices.total', 1)
                ->where('owner_dashboard.summary.invoices.pending', 1)
                ->where('owner_dashboard.summary.invoices.matched', 0)
                ->where('owner_dashboard.summary.invoices.mismatched', 0)
                ->where('owner_dashboard.summary.invoices.ready_to_pay_orders', 0)
                ->where('owner_dashboard.summary.invoices.total_invoiced_amount', 12500000)
                ->where('owner_dashboard.summary.invoices.matched_amount', 0)
                ->where('owner_dashboard.summary.employees.total', 3)
                ->where('owner_dashboard.summary.employees.active', 3)
                ->where('owner_dashboard.summary.employees.inactive', 0)
                ->where('owner_dashboard.summary.employees.owner_count', 1)
                ->where('owner_dashboard.summary.employees.purchasing_count', 1)
                ->where('owner_dashboard.summary.employees.qc_gudang_count', 1)
                ->where('owner_dashboard.summary.employees.manajemen_count', 0)
                ->where('owner_dashboard.summary.governance.partnerships_total', 0)
                ->where('owner_dashboard.summary.governance.partnerships_agreed', 0)
                ->where('owner_dashboard.summary.governance.disputes_total', 0)
                ->where('owner_dashboard.summary.governance.disputes_open', 0)
                ->where('owner_dashboard.summary.governance.qc_total_receipts', 0)
                ->where('owner_dashboard.summary.governance.qc_in_transit', 0)
                ->where('owner_dashboard.summary.governance.qc_accepted_items', 0)
                ->where('owner_dashboard.summary.governance.qc_rejected_items', 0)
                ->where('owner_dashboard.summary.governance.tenant_active_commodities', 0)
                ->where('owner_dashboard.summary.governance.global_active_commodities', 1)
                ->has('owner_dashboard.recent_purchase_orders', 1)
                ->where('owner_dashboard.recent_purchase_orders.0.po_number', 'PO-OWN-A001')
                ->missing('owner_dashboard.recent_purchase_orders.0.id')
                ->has('owner_dashboard.recent_invoices', 1)
                ->where('owner_dashboard.recent_invoices.0.invoice_number', 'INV-VND-001')
                ->missing('owner_dashboard.recent_invoices.0.id')
                ->has('owner_dashboard.internal_team', 3)
                ->missing('owner_dashboard.internal_team.0.id')
                ->has('owner_dashboard.monthly_trend', 6)
                ->where('owner_dashboard.monthly_trend.0.month_key', $fiveMonthsAgoKey)
                ->where('owner_dashboard.monthly_trend.0.purchase_orders_count', 0)
                ->where('owner_dashboard.monthly_trend.0.invoices_count', 0)
                ->where('owner_dashboard.monthly_trend.0.qc_inspections_count', 0)
                ->where('owner_dashboard.monthly_trend.0.po_value', 0)
                ->where('owner_dashboard.monthly_trend.5.month_key', $currentMonthKey)
                ->where('owner_dashboard.monthly_trend.5.purchase_orders_count', 1)
                ->where('owner_dashboard.monthly_trend.5.invoices_count', 1)
                ->where('owner_dashboard.monthly_trend.5.qc_inspections_count', 0)
                ->where('owner_dashboard.monthly_trend.5.po_value', 12500000)
            );
    }

    public function test_qc_gudang_receives_inspection_metrics_and_recent_inspections(): void
    {
        $tenant = PerusahaanBuyer::where('company_name', 'PT Pangan Makmur Sejahtera')->firstOrFail();

        $qcUser = User::factory()->create([
            'name' => 'Petugas QC Gudang',
            'email' => 'qc-gudang@pagora.test',
            'role_id' => Role::where('name', RoleName::QcGudang->value)->value('id'),
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $vendor = Vendor::create([
            'company_name' => 'PT Pemasok QC',
            'slug' => 'pt-pemasok-qc-'.Str::lower(Str::random(5)),
            'business_entity' => 'PT',
            'contact_email' => 'qc@vendor.test',
            'phone' => '081234560003',
            'verification_status' => VerificationStatus::Approved->value,
            'is_active' => true,
        ]);

        $commodity = Komoditas::firstOrFail();

        $poInTransit = PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-QC-001',
            'perusahaan_buyer_id' => $tenant->id,
            'vendor_id' => $vendor->id,
            'created_by' => $this->owner->id,
            'status' => StatusPurchaseOrder::DalamPengiriman->value,
            'tanggal_po' => now()->toDateString(),
        ]);

        $poReceived = PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-QC-002',
            'perusahaan_buyer_id' => $tenant->id,
            'vendor_id' => $vendor->id,
            'created_by' => $this->owner->id,
            'status' => StatusPurchaseOrder::DiterimaGudang->value,
            'tanggal_po' => now()->toDateString(),
        ]);

        $poItem = PoItem::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $poReceived->id,
            'komoditas_id' => $commodity->id,
            'jumlah_pesanan' => 500,
            'estimated_unit_price' => 10000,
            'harga_satuan_po' => 10000,
            'subtotal' => 5000000,
        ]);

        $shipment = Pengiriman::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $poReceived->id,
            'route' => 'Darat',
            'carrier_name' => 'PT Ekspedisi Nusantara',
            'vehicle_plate_number' => 'B 1234 XYZ',
            'estimated_arrival_date' => now()->toDateString(),
            'status' => 'arrived',
            'reported_by' => $this->owner->id,
        ]);

        $receipt = PenerimaanBarang::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $poReceived->id,
            'shipment_id' => $shipment->id,
            'inspected_by' => $qcUser->id,
            'inspected_at' => now(),
            'notes' => 'Pemeriksaan fisik sesuai.',
        ]);

        PenerimaanBarangItem::create([
            'uuid' => (string) Str::uuid(),
            'goods_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $poItem->id,
            'received_quantity' => 500,
            'status' => StatusItemPenerimaan::Accepted->value,
        ]);

        $this->actingAs($qcUser)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('is_qc_gudang', true)
                ->where('qc_dashboard.summary.inspections.total_receipts', 1)
                ->where('qc_dashboard.summary.inspections.in_transit_orders', 1)
                ->where('qc_dashboard.summary.inspections.accepted_items', 1)
                ->where('qc_dashboard.summary.inspections.rejected_items', 0)
                ->where('qc_dashboard.summary.inspections.acceptance_rate', 100)
                ->has('qc_dashboard.pending_deliveries', 1)
                ->where('qc_dashboard.pending_deliveries.0.po_number', 'PO-QC-001')
                ->has('qc_dashboard.recent_inspections', 1)
                ->where('qc_dashboard.recent_inspections.0.po_number', 'PO-QC-002')
                ->where('qc_dashboard.recent_inspections.0.accepted_count', 1)
            );
    }

    public function test_vendor_receives_dashboard_excluding_buyer_draft_pos_and_internal_estimated_price(): void
    {
        $tenant = PerusahaanBuyer::where('company_name', 'PT Pangan Makmur Sejahtera')->firstOrFail();

        $vendor = Vendor::create([
            'company_name' => 'PT Vendor Mandiri',
            'slug' => 'pt-vendor-mandiri-'.Str::lower(Str::random(5)),
            'business_entity' => 'PT',
            'contact_email' => 'mandiri@vendor.test',
            'phone' => '081234560004',
            'verification_status' => VerificationStatus::Approved->value,
            'is_active' => true,
        ]);

        $vendorUser = User::factory()->create([
            'name' => 'PIC Vendor Mandiri',
            'email' => 'pic@vendor.test',
            'role_id' => Role::where('name', RoleName::Vendor->value)->value('id'),
            'vendor_id' => $vendor->id,
            'is_active' => true,
        ]);

        KerjaSama::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'vendor_id' => $vendor->id,
            'requested_by' => 'tenant',
            'status' => StatusKerjaSama::Agreed->value,
        ]);

        $commodity = Komoditas::firstOrFail();

        // Draft PO created by Buyer (must NOT appear on Vendor dashboard)
        $draftPo = PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-VND-DRAFT',
            'perusahaan_buyer_id' => $tenant->id,
            'vendor_id' => $vendor->id,
            'created_by' => $this->owner->id,
            'status' => StatusPurchaseOrder::Draft->value,
            'tanggal_po' => now()->toDateString(),
        ]);

        PoItem::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $draftPo->id,
            'komoditas_id' => $commodity->id,
            'jumlah_pesanan' => 100,
            'estimated_unit_price' => 15000,
        ]);

        // Negotiation PO where only estimated_unit_price is filled (must NOT leak estimated_unit_price to Vendor)
        $negPo = PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-VND-NEG',
            'perusahaan_buyer_id' => $tenant->id,
            'vendor_id' => $vendor->id,
            'created_by' => $this->owner->id,
            'status' => StatusPurchaseOrder::Negosiasi->value,
            'tanggal_po' => now()->toDateString(),
        ]);

        PoItem::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $negPo->id,
            'komoditas_id' => $commodity->id,
            'jumlah_pesanan' => 200,
            'estimated_unit_price' => 15000,
            'harga_satuan_po' => null,
            'subtotal' => null,
        ]);

        $this->actingAs($vendorUser)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('is_vendor', true)
                ->where('vendor_dashboard.summary.orders.total', 1)
                ->where('vendor_dashboard.summary.orders.pending_response', 1)
                ->where('vendor_dashboard.summary.partnerships.active', 1)
                ->has('vendor_dashboard.recent_orders', 1)
                ->where('vendor_dashboard.recent_orders.0.po_number', 'PO-VND-NEG')
                ->where('vendor_dashboard.recent_orders.0.total_amount', 0)
                ->has('vendor_dashboard.partnerships', 1)
                ->where('vendor_dashboard.partnerships.0.buyer_name', 'PT Pangan Makmur Sejahtera')
            );
    }

    public function test_superadmin_monthly_growth_accurately_buckets_data_across_historical_months(): void
    {
        $m0Key = now()->startOfMonth()->subMonths(5)->format('Y-m');
        $m1Key = now()->startOfMonth()->subMonths(4)->format('Y-m');
        $m2Key = now()->startOfMonth()->subMonths(3)->format('Y-m');
        $m3Key = now()->startOfMonth()->subMonths(2)->format('Y-m');
        $m4Key = now()->startOfMonth()->subMonths(1)->format('Y-m');
        $m5Key = now()->format('Y-m');

        // Create Tenant in month -3 (index 2)
        $tM2 = PerusahaanBuyer::factory()->create([
            'company_name' => 'PT Tenant Bulan Lalu Dua',
            'verification_status' => VerificationStatus::Approved->value,
            'is_active' => true,
        ]);
        $tM2->created_at = now()->startOfMonth()->subMonths(3)->addDays(5);
        $tM2->save();

        // Create Tenant in month -1 (index 4)
        $tM4 = PerusahaanBuyer::factory()->create([
            'company_name' => 'PT Tenant Bulan Kemarin',
            'verification_status' => VerificationStatus::Approved->value,
            'is_active' => true,
        ]);
        $tM4->created_at = now()->startOfMonth()->subMonths(1)->addDays(2);
        $tM4->save();

        // Create Vendor in month -2 (index 3)
        $vM3 = Vendor::create([
            'company_name' => 'CV Vendor Dua Bulan Lalu',
            'slug' => 'cv-vendor-dua-'.Str::lower(Str::random(5)),
            'business_entity' => 'CV',
            'contact_email' => 'v2@test.com',
            'phone' => '081234567811',
            'verification_status' => VerificationStatus::Approved->value,
            'is_active' => true,
        ]);
        $vM3->created_at = now()->startOfMonth()->subMonths(2)->addDays(10);
        $vM3->save();

        // Create PO in month -3 (index 2)
        $poM2 = PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-SA-M2',
            'perusahaan_buyer_id' => $tM2->id,
            'vendor_id' => $vM3->id,
            'created_by' => $this->owner->id,
            'status' => StatusPurchaseOrder::Disepakati->value,
            'tanggal_po' => now()->startOfMonth()->subMonths(3)->toDateString(),
        ]);
        $poM2->created_at = now()->startOfMonth()->subMonths(3)->addDays(8);
        $poM2->save();

        // Create 2 POs in month -2 (index 3)
        $poM3a = PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-SA-M3A',
            'perusahaan_buyer_id' => $tM2->id,
            'vendor_id' => $vM3->id,
            'created_by' => $this->owner->id,
            'status' => StatusPurchaseOrder::Disepakati->value,
            'tanggal_po' => now()->startOfMonth()->subMonths(2)->toDateString(),
        ]);
        $poM3a->created_at = now()->startOfMonth()->subMonths(2)->addDays(12);
        $poM3a->save();

        $poM3b = PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-SA-M3B',
            'perusahaan_buyer_id' => $tM2->id,
            'vendor_id' => $vM3->id,
            'created_by' => $this->owner->id,
            'status' => StatusPurchaseOrder::DalamPengiriman->value,
            'tanggal_po' => now()->startOfMonth()->subMonths(2)->toDateString(),
        ]);
        $poM3b->created_at = now()->startOfMonth()->subMonths(2)->addDays(14);
        $poM3b->save();

        // Create 1 PO in current month (index 5)
        PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-SA-CURR',
            'perusahaan_buyer_id' => $tM2->id,
            'vendor_id' => $vM3->id,
            'created_by' => $this->owner->id,
            'status' => StatusPurchaseOrder::Draft->value,
            'tanggal_po' => now()->toDateString(),
        ]);

        $this->actingAs($this->superadmin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('is_superadmin', true)
                // Month 0 (5 months ago)
                ->where('superadmin_dashboard.monthly_growth.0.month_key', $m0Key)
                ->where('superadmin_dashboard.monthly_growth.0.tenants_count', 0)
                ->where('superadmin_dashboard.monthly_growth.0.vendors_count', 0)
                ->where('superadmin_dashboard.monthly_growth.0.purchase_orders_count', 0)
                // Month 1 (4 months ago)
                ->where('superadmin_dashboard.monthly_growth.1.month_key', $m1Key)
                ->where('superadmin_dashboard.monthly_growth.1.tenants_count', 0)
                ->where('superadmin_dashboard.monthly_growth.1.vendors_count', 0)
                ->where('superadmin_dashboard.monthly_growth.1.purchase_orders_count', 0)
                // Month 2 (3 months ago)
                ->where('superadmin_dashboard.monthly_growth.2.month_key', $m2Key)
                ->where('superadmin_dashboard.monthly_growth.2.tenants_count', 1)
                ->where('superadmin_dashboard.monthly_growth.2.vendors_count', 0)
                ->where('superadmin_dashboard.monthly_growth.2.purchase_orders_count', 1)
                // Month 3 (2 months ago)
                ->where('superadmin_dashboard.monthly_growth.3.month_key', $m3Key)
                ->where('superadmin_dashboard.monthly_growth.3.tenants_count', 0)
                ->where('superadmin_dashboard.monthly_growth.3.vendors_count', 1)
                ->where('superadmin_dashboard.monthly_growth.3.purchase_orders_count', 2)
                // Month 4 (1 month ago)
                ->where('superadmin_dashboard.monthly_growth.4.month_key', $m4Key)
                ->where('superadmin_dashboard.monthly_growth.4.tenants_count', 1)
                ->where('superadmin_dashboard.monthly_growth.4.vendors_count', 0)
                ->where('superadmin_dashboard.monthly_growth.4.purchase_orders_count', 0)
                // Month 5 (current month: 2 tenants & 2 vendors from setUp + 1 PO created)
                ->where('superadmin_dashboard.monthly_growth.5.month_key', $m5Key)
                ->where('superadmin_dashboard.monthly_growth.5.tenants_count', 2)
                ->where('superadmin_dashboard.monthly_growth.5.vendors_count', 2)
                ->where('superadmin_dashboard.monthly_growth.5.purchase_orders_count', 1)
                // Also verify platform-wide ecosystem totals
                ->where('superadmin_dashboard.summary.ecosystem.purchase_orders_total', 4)
            );
    }

    public function test_manajemen_monthly_trend_and_qc_statistics_across_multiple_months(): void
    {
        $tenantA = PerusahaanBuyer::where('company_name', 'PT Pangan Makmur Sejahtera')->firstOrFail();
        $tenantB = PerusahaanBuyer::factory()->create([
            'company_name' => 'PT Kompetitor Isolasi Multi Bulan',
            'verification_status' => VerificationStatus::Approved->value,
            'is_active' => true,
        ]);

        $manajemenA = User::factory()->create([
            'name' => 'Manajemen Uji Tren',
            'email' => 'manajemen-tren@pagora.test',
            'role_id' => Role::where('name', RoleName::Manajemen->value)->value('id'),
            'tenant_id' => $tenantA->id,
            'is_active' => true,
        ]);

        $vendor = Vendor::create([
            'company_name' => 'PT Mitra Terpercaya Multi Bulan',
            'slug' => 'pt-mitra-multi-'.Str::lower(Str::random(5)),
            'business_entity' => 'PT',
            'contact_email' => 'mitra-multi@vendor.test',
            'phone' => '081234560099',
            'verification_status' => VerificationStatus::Approved->value,
            'is_active' => true,
        ]);

        $commodity = Komoditas::firstOrFail();

        $m0Key = now()->startOfMonth()->subMonths(5)->format('Y-m');
        $m1Key = now()->startOfMonth()->subMonths(4)->format('Y-m');
        $m2Key = now()->startOfMonth()->subMonths(3)->format('Y-m');
        $m3Key = now()->startOfMonth()->subMonths(2)->format('Y-m');
        $m4Key = now()->startOfMonth()->subMonths(1)->format('Y-m');
        $m5Key = now()->format('Y-m');

        // Month -2 (index 3): PO A1 & Dispute A1 for Tenant A
        $poA_m3 = PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-MJM-HIST-01',
            'perusahaan_buyer_id' => $tenantA->id,
            'vendor_id' => $vendor->id,
            'created_by' => $this->owner->id,
            'status' => StatusPurchaseOrder::DalamPengiriman->value,
            'tanggal_po' => now()->startOfMonth()->subMonths(2)->toDateString(),
        ]);
        $poA_m3->created_at = now()->startOfMonth()->subMonths(2)->addDays(5);
        $poA_m3->save();

        $disputeA_m3 = Sengketa::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $poA_m3->id,
            'trigger_type' => PemicuSengketa::GoodsMismatch->value,
            'reason' => 'Sengketa historis 2 bulan lalu.',
            'opened_by' => $this->owner->id,
            'status' => StatusSengketa::Open->value,
            'opened_at' => now()->startOfMonth()->subMonths(2)->addDays(6),
        ]);
        $disputeA_m3->created_at = now()->startOfMonth()->subMonths(2)->addDays(6);
        $disputeA_m3->save();

        // Month -1 (index 4): PO A2 & Goods Receipt A1 for Tenant A
        $poA_m4 = PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-MJM-HIST-02',
            'perusahaan_buyer_id' => $tenantA->id,
            'vendor_id' => $vendor->id,
            'created_by' => $this->owner->id,
            'status' => StatusPurchaseOrder::DiterimaGudang->value,
            'tanggal_po' => now()->startOfMonth()->subMonths(1)->toDateString(),
        ]);
        $poA_m4->created_at = now()->startOfMonth()->subMonths(1)->addDays(4);
        $poA_m4->save();

        $shipmentA1 = Pengiriman::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $poA_m4->id,
            'route' => 'Darat',
            'carrier_name' => 'Logistik Ekspedisi',
            'vehicle_plate_number' => 'B 9999 XYZ',
            'estimated_arrival_date' => now()->startOfMonth()->subMonths(1)->addDays(5)->toDateString(),
            'status' => 'arrived',
            'reported_by' => $this->owner->id,
        ]);
        $shipmentA1->created_at = now()->startOfMonth()->subMonths(1)->addDays(5);
        $shipmentA1->save();

        $receiptA1 = PenerimaanBarang::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $poA_m4->id,
            'shipment_id' => $shipmentA1->id,
            'inspected_by' => $this->owner->id,
            'inspected_at' => now()->startOfMonth()->subMonths(1)->addDays(6),
            'notes' => 'Penerimaan historis bulan lalu.',
        ]);
        $receiptA1->created_at = now()->startOfMonth()->subMonths(1)->addDays(6);
        $receiptA1->save();

        // Month 0 (current month, index 5): PO A3, Dispute A2, Goods Receipt A2 with rejected item
        $poA_m5 = PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-MJM-HIST-03',
            'perusahaan_buyer_id' => $tenantA->id,
            'vendor_id' => $vendor->id,
            'created_by' => $this->owner->id,
            'status' => StatusPurchaseOrder::DiterimaGudang->value,
            'tanggal_po' => now()->toDateString(),
        ]);

        $poItemA = PoItem::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $poA_m5->id,
            'komoditas_id' => $commodity->id,
            'jumlah_pesanan' => 1000,
            'estimated_unit_price' => 10000,
            'harga_satuan_po' => 10000,
            'subtotal' => 10000000,
        ]);

        $disputeA_m5 = Sengketa::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $poA_m5->id,
            'trigger_type' => PemicuSengketa::AmountMismatch->value,
            'reason' => 'Sengketa harga bulan ini dalam mediasi.',
            'opened_by' => $this->owner->id,
            'status' => StatusSengketa::InMediation->value,
            'opened_at' => now(),
        ]);

        $shipmentA2 = Pengiriman::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $poA_m5->id,
            'route' => 'Darat',
            'carrier_name' => 'Logistik Ekspedisi',
            'vehicle_plate_number' => 'B 8888 XYZ',
            'estimated_arrival_date' => now()->toDateString(),
            'status' => 'arrived',
            'reported_by' => $this->owner->id,
        ]);

        $receiptA2 = PenerimaanBarang::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $poA_m5->id,
            'shipment_id' => $shipmentA2->id,
            'inspected_by' => $this->owner->id,
            'inspected_at' => now(),
            'notes' => 'Pemeriksaan dengan penolakan.',
        ]);

        PenerimaanBarangItem::create([
            'uuid' => (string) Str::uuid(),
            'goods_receipt_id' => $receiptA2->id,
            'purchase_order_item_id' => $poItemA->id,
            'received_quantity' => 700,
            'status' => StatusItemPenerimaan::Accepted->value,
        ]);

        PenerimaanBarangItem::create([
            'uuid' => (string) Str::uuid(),
            'goods_receipt_id' => $receiptA2->id,
            'purchase_order_item_id' => $poItemA->id,
            'received_quantity' => 300,
            'status' => StatusItemPenerimaan::Rejected->value,
            'notes' => 'Kemasan rusak dan kotor terkena air hujan.',
            'rejection_photos' => ['photos/reject1.jpg', 'photos/reject2.jpg'],
        ]);

        // Isolasi: Records for Tenant B in the same months (must not leak to Tenant A)
        $poB = PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-MJM-ISO-B',
            'perusahaan_buyer_id' => $tenantB->id,
            'vendor_id' => $vendor->id,
            'created_by' => $this->owner->id,
            'status' => StatusPurchaseOrder::DalamPengiriman->value,
            'tanggal_po' => now()->startOfMonth()->subMonths(2)->toDateString(),
        ]);
        $poB->created_at = now()->startOfMonth()->subMonths(2)->addDays(5);
        $poB->save();

        $disputeB = Sengketa::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $poB->id,
            'trigger_type' => PemicuSengketa::GoodsMismatch->value,
            'reason' => 'Sengketa milik tenant B.',
            'opened_by' => $this->owner->id,
            'status' => StatusSengketa::Open->value,
        ]);
        $disputeB->created_at = now()->startOfMonth()->subMonths(2)->addDays(5);
        $disputeB->save();

        $this->actingAs($manajemenA)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('is_manajemen', true)
                // Summary disputes: 1 open (month -2) + 1 in_mediation (month 0) = 2 total
                ->where('manajemen_dashboard.summary.disputes.total', 2)
                ->where('manajemen_dashboard.summary.disputes.open', 1)
                ->where('manajemen_dashboard.summary.disputes.in_mediation', 1)
                ->where('manajemen_dashboard.summary.disputes.resolved', 0)
                // Summary QC inspections: 2 receipts, 1 accepted item, 1 rejected item
                ->where('manajemen_dashboard.summary.qc_inspections.total_receipts', 2)
                ->where('manajemen_dashboard.summary.qc_inspections.accepted_items', 1)
                ->where('manajemen_dashboard.summary.qc_inspections.rejected_items', 1)
                // Recent QC rejections contains the rejected item with details
                ->has('manajemen_dashboard.recent_qc_rejections', 1)
                ->where('manajemen_dashboard.recent_qc_rejections.0.po_number', 'PO-MJM-HIST-03')
                ->where('manajemen_dashboard.recent_qc_rejections.0.notes', 'Kemasan rusak dan kotor terkena air hujan.')
                ->where('manajemen_dashboard.recent_qc_rejections.0.photos_count', 2)
                ->where('manajemen_dashboard.recent_qc_rejections.0.received_quantity', 300)
                // Monthly trend validation across all 6 months
                ->where('manajemen_dashboard.monthly_trend.0.month_key', $m0Key)
                ->where('manajemen_dashboard.monthly_trend.0.purchase_orders_count', 0)
                ->where('manajemen_dashboard.monthly_trend.0.qc_inspections_count', 0)
                ->where('manajemen_dashboard.monthly_trend.0.disputes_count', 0)
                ->where('manajemen_dashboard.monthly_trend.1.month_key', $m1Key)
                ->where('manajemen_dashboard.monthly_trend.1.purchase_orders_count', 0)
                ->where('manajemen_dashboard.monthly_trend.1.qc_inspections_count', 0)
                ->where('manajemen_dashboard.monthly_trend.1.disputes_count', 0)
                ->where('manajemen_dashboard.monthly_trend.2.month_key', $m2Key)
                ->where('manajemen_dashboard.monthly_trend.2.purchase_orders_count', 0)
                ->where('manajemen_dashboard.monthly_trend.2.qc_inspections_count', 0)
                ->where('manajemen_dashboard.monthly_trend.2.disputes_count', 0)
                // Month 3 (2 months ago): PO=1, QC=0, Dispute=1
                ->where('manajemen_dashboard.monthly_trend.3.month_key', $m3Key)
                ->where('manajemen_dashboard.monthly_trend.3.purchase_orders_count', 1)
                ->where('manajemen_dashboard.monthly_trend.3.qc_inspections_count', 0)
                ->where('manajemen_dashboard.monthly_trend.3.disputes_count', 1)
                // Month 4 (1 month ago): PO=1, QC=1, Dispute=0
                ->where('manajemen_dashboard.monthly_trend.4.month_key', $m4Key)
                ->where('manajemen_dashboard.monthly_trend.4.purchase_orders_count', 1)
                ->where('manajemen_dashboard.monthly_trend.4.qc_inspections_count', 1)
                ->where('manajemen_dashboard.monthly_trend.4.disputes_count', 0)
                // Month 5 (current month): PO=1, QC=1, Dispute=1
                ->where('manajemen_dashboard.monthly_trend.5.month_key', $m5Key)
                ->where('manajemen_dashboard.monthly_trend.5.purchase_orders_count', 1)
                ->where('manajemen_dashboard.monthly_trend.5.qc_inspections_count', 1)
                ->where('manajemen_dashboard.monthly_trend.5.disputes_count', 1)
            );
    }

    public function test_owner_procurement_statistics_status_breakdown_and_monthly_trend_calculation(): void
    {
        $tenant = PerusahaanBuyer::where('company_name', 'PT Pangan Makmur Sejahtera')->firstOrFail();

        $vendor = Vendor::create([
            'company_name' => 'PT Pemasok Lengkap Owner',
            'slug' => 'pt-pemasok-lengkap-'.Str::lower(Str::random(5)),
            'business_entity' => 'PT',
            'contact_email' => 'lengkap@vendor.test',
            'phone' => '081234560777',
            'verification_status' => VerificationStatus::Approved->value,
            'is_active' => true,
        ]);

        $commodity = Komoditas::firstOrFail();

        $m3Key = now()->startOfMonth()->subMonths(2)->format('Y-m');
        $m4Key = now()->startOfMonth()->subMonths(1)->format('Y-m');
        $m5Key = now()->format('Y-m');

        // Month -2: PO 1 Draft (2,000,000) & PO 2 Negosiasi (3,000,000) -> Draft/Neg category = 2
        $po1 = PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-OWN-STAT-01',
            'perusahaan_buyer_id' => $tenant->id,
            'vendor_id' => $vendor->id,
            'created_by' => $this->owner->id,
            'status' => StatusPurchaseOrder::Draft->value,
            'tanggal_po' => now()->startOfMonth()->subMonths(2)->toDateString(),
        ]);
        $po1->created_at = now()->startOfMonth()->subMonths(2)->addDays(2);
        $po1->save();
        PoItem::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po1->id,
            'komoditas_id' => $commodity->id,
            'jumlah_pesanan' => 200,
            'estimated_unit_price' => 10000,
            'subtotal' => 2000000,
        ]);

        $po2 = PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-OWN-STAT-02',
            'perusahaan_buyer_id' => $tenant->id,
            'vendor_id' => $vendor->id,
            'created_by' => $this->owner->id,
            'status' => StatusPurchaseOrder::Negosiasi->value,
            'tanggal_po' => now()->startOfMonth()->subMonths(2)->toDateString(),
        ]);
        $po2->created_at = now()->startOfMonth()->subMonths(2)->addDays(5);
        $po2->save();
        PoItem::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po2->id,
            'komoditas_id' => $commodity->id,
            'jumlah_pesanan' => 300,
            'estimated_unit_price' => 10000,
            'subtotal' => 3000000,
        ]);

        // Month -1: PO 3 Disepakati (5,000,000) & PO 4 DalamPengiriman (4,000,000) -> InExecution = 2
        // Also 1 Matched Invoice (4,000,000) & 1 GoodsReceipt
        $po3 = PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-OWN-STAT-03',
            'perusahaan_buyer_id' => $tenant->id,
            'vendor_id' => $vendor->id,
            'created_by' => $this->owner->id,
            'status' => StatusPurchaseOrder::Disepakati->value,
            'tanggal_po' => now()->startOfMonth()->subMonths(1)->toDateString(),
        ]);
        $po3->created_at = now()->startOfMonth()->subMonths(1)->addDays(3);
        $po3->save();
        PoItem::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po3->id,
            'komoditas_id' => $commodity->id,
            'jumlah_pesanan' => 500,
            'estimated_unit_price' => 10000,
            'harga_satuan_po' => 10000,
            'subtotal' => 5000000,
        ]);

        $po4 = PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-OWN-STAT-04',
            'perusahaan_buyer_id' => $tenant->id,
            'vendor_id' => $vendor->id,
            'created_by' => $this->owner->id,
            'status' => StatusPurchaseOrder::DalamPengiriman->value,
            'tanggal_po' => now()->startOfMonth()->subMonths(1)->toDateString(),
        ]);
        $po4->created_at = now()->startOfMonth()->subMonths(1)->addDays(8);
        $po4->save();
        PoItem::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po4->id,
            'komoditas_id' => $commodity->id,
            'jumlah_pesanan' => 400,
            'estimated_unit_price' => 10000,
            'harga_satuan_po' => 10000,
            'subtotal' => 4000000,
        ]);

        $inv1 = Invoice::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po4->id,
            'vendor_id' => $vendor->id,
            'nomor_invoice_vendor' => 'INV-OWN-MATCHED',
            'jumlah_tagihan' => 4000000,
            'tanggal_invoice' => now()->startOfMonth()->subMonths(1)->toDateString(),
            'status_matching' => StatusMatching::Matched->value,
        ]);
        $inv1->created_at = now()->startOfMonth()->subMonths(1)->addDays(10);
        $inv1->save();

        $shipment1 = Pengiriman::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po4->id,
            'route' => 'Darat',
            'carrier_name' => 'Kurir Express',
            'vehicle_plate_number' => 'B 7777 ABC',
            'estimated_arrival_date' => now()->startOfMonth()->subMonths(1)->addDays(9)->toDateString(),
            'status' => 'arrived',
            'reported_by' => $this->owner->id,
        ]);
        $shipment1->created_at = now()->startOfMonth()->subMonths(1)->addDays(9);
        $shipment1->save();

        $receipt1 = PenerimaanBarang::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po4->id,
            'shipment_id' => $shipment1->id,
            'inspected_by' => $this->owner->id,
            'inspected_at' => now()->startOfMonth()->subMonths(1)->addDays(9),
        ]);
        $receipt1->created_at = now()->startOfMonth()->subMonths(1)->addDays(9);
        $receipt1->save();

        // Month 0: PO 5 Ditagih (6M), PO 6 ReadyToPay (7M), PO 7 Lunas (8M), PO 8 Sengketa (1.5M), PO 9 Dibatalkan (1M)
        $po5 = PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-OWN-STAT-05',
            'perusahaan_buyer_id' => $tenant->id,
            'vendor_id' => $vendor->id,
            'created_by' => $this->owner->id,
            'status' => StatusPurchaseOrder::Ditagih->value,
            'tanggal_po' => now()->toDateString(),
        ]);
        PoItem::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po5->id,
            'komoditas_id' => $commodity->id,
            'jumlah_pesanan' => 600,
            'estimated_unit_price' => 10000,
            'harga_satuan_po' => 10000,
            'subtotal' => 6000000,
        ]);

        $po6 = PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-OWN-STAT-06',
            'perusahaan_buyer_id' => $tenant->id,
            'vendor_id' => $vendor->id,
            'created_by' => $this->owner->id,
            'status' => StatusPurchaseOrder::ReadyToPay->value,
            'tanggal_po' => now()->toDateString(),
        ]);
        PoItem::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po6->id,
            'komoditas_id' => $commodity->id,
            'jumlah_pesanan' => 700,
            'estimated_unit_price' => 10000,
            'harga_satuan_po' => 10000,
            'subtotal' => 7000000,
        ]);

        $po7 = PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-OWN-STAT-07',
            'perusahaan_buyer_id' => $tenant->id,
            'vendor_id' => $vendor->id,
            'created_by' => $this->owner->id,
            'status' => StatusPurchaseOrder::Lunas->value,
            'tanggal_po' => now()->toDateString(),
        ]);
        PoItem::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po7->id,
            'komoditas_id' => $commodity->id,
            'jumlah_pesanan' => 800,
            'estimated_unit_price' => 10000,
            'harga_satuan_po' => 10000,
            'subtotal' => 8000000,
        ]);

        $po8 = PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-OWN-STAT-08',
            'perusahaan_buyer_id' => $tenant->id,
            'vendor_id' => $vendor->id,
            'created_by' => $this->owner->id,
            'status' => StatusPurchaseOrder::Sengketa->value,
            'tanggal_po' => now()->toDateString(),
        ]);
        PoItem::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po8->id,
            'komoditas_id' => $commodity->id,
            'jumlah_pesanan' => 150,
            'estimated_unit_price' => 10000,
            'harga_satuan_po' => 10000,
            'subtotal' => 1500000,
        ]);

        $po9 = PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-OWN-STAT-09',
            'perusahaan_buyer_id' => $tenant->id,
            'vendor_id' => $vendor->id,
            'created_by' => $this->owner->id,
            'status' => StatusPurchaseOrder::Dibatalkan->value,
            'tanggal_po' => now()->toDateString(),
        ]);
        PoItem::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po9->id,
            'komoditas_id' => $commodity->id,
            'jumlah_pesanan' => 100,
            'estimated_unit_price' => 10000,
            'subtotal' => 1000000,
        ]);

        // Invoices in Month 0
        Invoice::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po5->id,
            'vendor_id' => $vendor->id,
            'nomor_invoice_vendor' => 'INV-OWN-PENDING',
            'jumlah_tagihan' => 6000000,
            'tanggal_invoice' => now()->toDateString(),
            'status_matching' => StatusMatching::Pending->value,
        ]);

        Invoice::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po8->id,
            'vendor_id' => $vendor->id,
            'nomor_invoice_vendor' => 'INV-OWN-MISMATCH',
            'jumlah_tagihan' => 1500000,
            'tanggal_invoice' => now()->toDateString(),
            'status_matching' => StatusMatching::Mismatched->value,
        ]);

        $this->actingAs($this->owner)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('is_owner', true)
                // Total orders: 9
                ->where('owner_dashboard.summary.procurement.total_orders', 9)
                ->where('owner_dashboard.summary.procurement.draft_and_negotiation', 2)
                ->where('owner_dashboard.summary.procurement.in_execution', 2)
                ->where('owner_dashboard.summary.procurement.invoiced_and_ready_to_pay', 2)
                ->where('owner_dashboard.summary.procurement.completed_paid', 1)
                ->where('owner_dashboard.summary.procurement.disputed_orders', 1)
                ->where('owner_dashboard.summary.procurement.cancelled_orders', 1)
                // total_agreed_value only includes PO3 (5M), PO4 (4M), PO5 (6M), PO6 (7M), PO7 (8M) = 30M
                ->where('owner_dashboard.summary.procurement.total_agreed_value', 30000000)
                // Invoices: 3 total (1 pending, 1 matched, 1 mismatched)
                ->where('owner_dashboard.summary.invoices.total', 3)
                ->where('owner_dashboard.summary.invoices.pending', 1)
                ->where('owner_dashboard.summary.invoices.matched', 1)
                ->where('owner_dashboard.summary.invoices.mismatched', 1)
                ->where('owner_dashboard.summary.invoices.total_invoiced_amount', 11500000)
                ->where('owner_dashboard.summary.invoices.matched_amount', 4000000)
                // Monthly trend:
                // Month 3 (2 months ago): 2 POs (2M + 3M = 5M)
                ->where('owner_dashboard.monthly_trend.3.month_key', $m3Key)
                ->where('owner_dashboard.monthly_trend.3.purchase_orders_count', 2)
                ->where('owner_dashboard.monthly_trend.3.po_value', 5000000)
                ->where('owner_dashboard.monthly_trend.3.invoices_count', 0)
                ->where('owner_dashboard.monthly_trend.3.qc_inspections_count', 0)
                // Month 4 (1 month ago): 2 POs (5M + 4M = 9M), 1 invoice, 1 QC inspection
                ->where('owner_dashboard.monthly_trend.4.month_key', $m4Key)
                ->where('owner_dashboard.monthly_trend.4.purchase_orders_count', 2)
                ->where('owner_dashboard.monthly_trend.4.po_value', 9000000)
                ->where('owner_dashboard.monthly_trend.4.invoices_count', 1)
                ->where('owner_dashboard.monthly_trend.4.qc_inspections_count', 1)
                // Month 5 (current month): 5 POs (6M + 7M + 8M + 1.5M + 1M = 23.5M), 2 invoices, 0 QC
                ->where('owner_dashboard.monthly_trend.5.month_key', $m5Key)
                ->where('owner_dashboard.monthly_trend.5.purchase_orders_count', 5)
                ->where('owner_dashboard.monthly_trend.5.po_value', 23500000)
                ->where('owner_dashboard.monthly_trend.5.invoices_count', 2)
                ->where('owner_dashboard.monthly_trend.5.qc_inspections_count', 0)
            );
    }

    public function test_qc_gudang_calculates_acceptance_rate_and_rejection_counts_accurately(): void
    {
        $tenant = PerusahaanBuyer::where('company_name', 'PT Pangan Makmur Sejahtera')->firstOrFail();

        $qcUser = User::factory()->create([
            'name' => 'Petugas QC Karakterisasi',
            'email' => 'qc-karakterisasi@pagora.test',
            'role_id' => Role::where('name', RoleName::QcGudang->value)->value('id'),
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $vendor = Vendor::create([
            'company_name' => 'PT Pemasok QC Rasio',
            'slug' => 'pt-pemasok-qc-rasio-'.Str::lower(Str::random(5)),
            'business_entity' => 'PT',
            'contact_email' => 'qc-rasio@vendor.test',
            'phone' => '081234560789',
            'verification_status' => VerificationStatus::Approved->value,
            'is_active' => true,
        ]);

        $commodity = Komoditas::firstOrFail();

        // 1 PO in transit (pending delivery)
        PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-QC-TRANSIT',
            'perusahaan_buyer_id' => $tenant->id,
            'vendor_id' => $vendor->id,
            'created_by' => $this->owner->id,
            'status' => StatusPurchaseOrder::DalamPengiriman->value,
            'tanggal_po' => now()->toDateString(),
        ]);

        // 1 PO received at warehouse
        $poReceived = PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-QC-RASIO-01',
            'perusahaan_buyer_id' => $tenant->id,
            'vendor_id' => $vendor->id,
            'created_by' => $this->owner->id,
            'status' => StatusPurchaseOrder::DiterimaGudang->value,
            'tanggal_po' => now()->toDateString(),
        ]);

        $poItem1 = PoItem::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $poReceived->id,
            'komoditas_id' => $commodity->id,
            'jumlah_pesanan' => 300,
            'estimated_unit_price' => 10000,
            'harga_satuan_po' => 10000,
            'subtotal' => 3000000,
        ]);

        $poItem2 = PoItem::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $poReceived->id,
            'komoditas_id' => $commodity->id,
            'jumlah_pesanan' => 100,
            'estimated_unit_price' => 10000,
            'harga_satuan_po' => 10000,
            'subtotal' => 1000000,
        ]);

        $shipment = Pengiriman::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $poReceived->id,
            'route' => 'Darat',
            'carrier_name' => 'PT Logistik Nusantara',
            'vehicle_plate_number' => 'B 5555 XYZ',
            'estimated_arrival_date' => now()->toDateString(),
            'status' => 'arrived',
            'reported_by' => $this->owner->id,
        ]);

        $receipt = PenerimaanBarang::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $poReceived->id,
            'shipment_id' => $shipment->id,
            'inspected_by' => $qcUser->id,
            'inspected_at' => now(),
            'notes' => 'Pemeriksaan campuran terima dan tolak.',
        ]);

        // 1 item Accepted
        PenerimaanBarangItem::create([
            'uuid' => (string) Str::uuid(),
            'goods_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $poItem1->id,
            'received_quantity' => 300,
            'status' => StatusItemPenerimaan::Accepted->value,
        ]);

        // 1 item Rejected
        PenerimaanBarangItem::create([
            'uuid' => (string) Str::uuid(),
            'goods_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $poItem2->id,
            'received_quantity' => 100,
            'status' => StatusItemPenerimaan::Rejected->value,
            'notes' => 'Barang berjamur dan kemasan bolong.',
            'rejection_photos' => ['photos/reject_mold.jpg'],
        ]);

        $this->actingAs($qcUser)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('is_qc_gudang', true)
                ->where('qc_dashboard.summary.inspections.total_receipts', 1)
                ->where('qc_dashboard.summary.inspections.in_transit_orders', 1)
                ->where('qc_dashboard.summary.inspections.accepted_items', 1)
                ->where('qc_dashboard.summary.inspections.rejected_items', 1)
                ->where('qc_dashboard.summary.inspections.total_items_inspected', 2)
                ->where('qc_dashboard.summary.inspections.acceptance_rate', 50)
                ->has('qc_dashboard.pending_deliveries', 1)
                ->where('qc_dashboard.pending_deliveries.0.po_number', 'PO-QC-TRANSIT')
                ->has('qc_dashboard.recent_inspections', 1)
                ->where('qc_dashboard.recent_inspections.0.po_number', 'PO-QC-RASIO-01')
                ->where('qc_dashboard.recent_inspections.0.accepted_count', 1)
                ->where('qc_dashboard.recent_inspections.0.rejected_count', 1)
                ->where('qc_dashboard.recent_inspections.0.total_items', 2)
            );
    }

    public function test_purchasing_receives_buyer_dashboard_metrics_and_excludes_other_tenants(): void
    {
        $tenantA = PerusahaanBuyer::where('company_name', 'PT Pangan Makmur Sejahtera')->firstOrFail();
        $tenantB = PerusahaanBuyer::factory()->create([
            'company_name' => 'PT Kompetitor Buyer Isolasi',
            'verification_status' => VerificationStatus::Approved->value,
            'is_active' => true,
        ]);

        $purchasingUser = User::factory()->create([
            'name' => 'Staf Purchasing Karakterisasi',
            'email' => 'purchasing-test@pagora.test',
            'role_id' => Role::where('name', RoleName::Purchasing->value)->value('id'),
            'tenant_id' => $tenantA->id,
            'is_active' => true,
        ]);

        $vendor = Vendor::create([
            'company_name' => 'PT Rekanan Purchasing',
            'slug' => 'pt-rekanan-purchasing-'.Str::lower(Str::random(5)),
            'business_entity' => 'PT',
            'contact_email' => 'rekanan@vendor.test',
            'phone' => '081234560123',
            'verification_status' => VerificationStatus::Approved->value,
            'is_active' => true,
        ]);

        // Partnership for Tenant A
        KerjaSama::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantA->id,
            'vendor_id' => $vendor->id,
            'requested_by' => 'tenant',
            'status' => StatusKerjaSama::Agreed->value,
        ]);

        $commodity = Komoditas::firstOrFail();

        // PO for Tenant A
        $poA = PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-BUYER-01',
            'perusahaan_buyer_id' => $tenantA->id,
            'vendor_id' => $vendor->id,
            'created_by' => $purchasingUser->id,
            'status' => StatusPurchaseOrder::Disepakati->value,
            'tanggal_po' => now()->toDateString(),
        ]);
        PoItem::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $poA->id,
            'komoditas_id' => $commodity->id,
            'jumlah_pesanan' => 500,
            'estimated_unit_price' => 10000,
            'harga_satuan_po' => 10000,
            'subtotal' => 5000000,
        ]);

        // Pending Invoice for Tenant A
        Invoice::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $poA->id,
            'vendor_id' => $vendor->id,
            'nomor_invoice_vendor' => 'INV-BUYER-01',
            'jumlah_tagihan' => 5000000,
            'tanggal_invoice' => now()->toDateString(),
            'status_matching' => StatusMatching::Pending->value,
        ]);

        // PO & Invoice for Tenant B (isolated)
        $poB = PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-BUYER-B-ISO',
            'perusahaan_buyer_id' => $tenantB->id,
            'vendor_id' => $vendor->id,
            'created_by' => $this->owner->id,
            'status' => StatusPurchaseOrder::Disepakati->value,
            'tanggal_po' => now()->toDateString(),
        ]);
        Invoice::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $poB->id,
            'vendor_id' => $vendor->id,
            'nomor_invoice_vendor' => 'INV-BUYER-B-ISO',
            'jumlah_tagihan' => 99000000,
            'tanggal_invoice' => now()->toDateString(),
            'status_matching' => StatusMatching::Pending->value,
        ]);

        $this->actingAs($purchasingUser)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('is_purchasing', true)
                ->where('buyer_dashboard.summary.orders.total', 1)
                ->where('buyer_dashboard.summary.orders.in_execution', 1)
                ->where('buyer_dashboard.summary.invoices.pending', 1)
                ->where('buyer_dashboard.summary.invoices.matched', 0)
                ->where('buyer_dashboard.summary.invoices.mismatched', 0)
                ->where('buyer_dashboard.summary.invoices.total_invoiced_amount', 5000000)
                ->where('buyer_dashboard.summary.partnerships.active', 1)
                ->where('buyer_dashboard.summary.disputes.active', 0)
                ->has('buyer_dashboard.recent_purchase_orders', 1)
                ->where('buyer_dashboard.recent_purchase_orders.0.po_number', 'PO-BUYER-01')
                ->has('buyer_dashboard.pending_invoices', 1)
                ->where('buyer_dashboard.pending_invoices.0.invoice_number', 'INV-BUYER-01')
            );
    }
}
