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
                ->where('manajemen_dashboard.summary.partnerships.total', 1)
                ->where('manajemen_dashboard.summary.partnerships.agreed', 1)
                ->where('manajemen_dashboard.summary.qc_inspections.in_transit_orders', 1)
                ->where('manajemen_dashboard.summary.commodities.tenant_active', 1)
                ->where('manajemen_dashboard.summary.commodities.custom_tolerance_count', 1)
                ->has('manajemen_dashboard.active_disputes', 1)
                ->where('manajemen_dashboard.active_disputes.0.po_number', 'PO-MJM-A001')
                ->missing('manajemen_dashboard.active_disputes.0.id')
                ->has('manajemen_dashboard.vendor_partnerships', 1)
                ->where('manajemen_dashboard.vendor_partnerships.0.vendor_name', 'PT Mitra Pemasok Utama')
                ->missing('manajemen_dashboard.vendor_partnerships.0.id')
                ->has('manajemen_dashboard.tenant_commodities', 1)
                ->has('manajemen_dashboard.monthly_trend', 6)
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
                ->where('owner_dashboard.summary.procurement.in_execution', 1)
                ->where('owner_dashboard.summary.procurement.total_agreed_value', 12500000)
                ->where('owner_dashboard.summary.invoices.total', 1)
                ->where('owner_dashboard.summary.invoices.pending', 1)
                ->where('owner_dashboard.summary.invoices.total_invoiced_amount', 12500000)
                ->where('owner_dashboard.summary.employees.total', 3)
                ->where('owner_dashboard.summary.employees.owner_count', 1)
                ->where('owner_dashboard.summary.employees.purchasing_count', 1)
                ->where('owner_dashboard.summary.employees.qc_gudang_count', 1)
                ->has('owner_dashboard.recent_purchase_orders', 1)
                ->where('owner_dashboard.recent_purchase_orders.0.po_number', 'PO-OWN-A001')
                ->missing('owner_dashboard.recent_purchase_orders.0.id')
                ->has('owner_dashboard.recent_invoices', 1)
                ->where('owner_dashboard.recent_invoices.0.invoice_number', 'INV-VND-001')
                ->missing('owner_dashboard.recent_invoices.0.id')
                ->has('owner_dashboard.internal_team', 3)
                ->missing('owner_dashboard.internal_team.0.id')
                ->has('owner_dashboard.monthly_trend', 6)
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
}
