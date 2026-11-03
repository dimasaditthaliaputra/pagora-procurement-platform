<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\StatusKerjaSama;
use App\Enums\StatusPurchaseOrder;
use App\Models\KerjaSama;
use App\Models\Komoditas;
use App\Models\PerusahaanBuyer;
use App\Models\PoItem;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\TenantCommodity;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PurchaseOrderPageTest extends TestCase
{
    use RefreshDatabase;

    private PerusahaanBuyer $tenantA;

    private PerusahaanBuyer $tenantB;

    private Vendor $vendor;

    private Komoditas $commodityBeras;

    private Komoditas $commodityJagung;

    private User $purchasingUser;

    private User $ownerUser;

    private User $vendorUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        // 1. Setup Tenant A & Users
        $this->tenantA = PerusahaanBuyer::factory()->create([
            'company_name' => 'PT Pangan Mandiri Nusantara',
        ]);

        $this->purchasingUser = User::factory()->create([
            'role_id' => Role::where('name', RoleName::Purchasing->value)->value('id'),
            'tenant_id' => $this->tenantA->id,
            'is_active' => true,
        ]);

        $this->ownerUser = User::factory()->create([
            'role_id' => Role::where('name', RoleName::Owner->value)->value('id'),
            'tenant_id' => $this->tenantA->id,
            'is_active' => true,
        ]);

        // 2. Setup Tenant B
        $this->tenantB = PerusahaanBuyer::factory()->create([
            'company_name' => 'PT Sumber Makmur Lain',
        ]);

        // 3. Setup Vendor & Vendor User
        $this->vendor = Vendor::create([
            'company_name' => 'CV Mitra Tani Berkah',
            'slug' => 'cv-mitra-tani-berkah-'.Str::lower(Str::random(6)),
            'tax_number' => '01.234.567.8-999.000',
            'contact_email' => 'kontak@mitratani.test',
            'phone' => '081234567890',
            'address' => 'Jl. Kebon Agung No. 12 Sleman',
            'verification_status' => 'approved',
            'is_active' => true,
        ]);

        $this->vendorUser = User::factory()->create([
            'role_id' => Role::where('name', RoleName::Vendor->value)->value('id'),
            'vendor_id' => $this->vendor->id,
            'is_active' => true,
        ]);

        // 4. Setup Partnership (Tenant A <-> Vendor) with status 'agreed'
        KerjaSama::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'vendor_id' => $this->vendor->id,
            'requested_by' => 'tenant',
            'status' => StatusKerjaSama::Agreed->value,
        ]);

        // 5. Setup Commodities & Tenant Commodities
        $this->commodityBeras = Komoditas::create([
            'name' => 'Beras Pandan Wangi',
            'slug' => 'beras-pandan-wangi-'.Str::lower(Str::random(5)),
            'sku' => 'BRS-PW',
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $this->commodityJagung = Komoditas::create([
            'name' => 'Jagung Pipil Kering',
            'slug' => 'jagung-pipil-kering-'.Str::lower(Str::random(5)),
            'sku' => 'JGG-PK',
            'unit' => 'kg',
            'is_active' => true,
        ]);

        TenantCommodity::create([
            'tenant_id' => $this->tenantA->id,
            'commodity_id' => $this->commodityBeras->id,
            'is_active' => true,
        ]);

        TenantCommodity::create([
            'tenant_id' => $this->tenantA->id,
            'commodity_id' => $this->commodityJagung->id,
            'is_active' => true,
        ]);
    }

    private function createPurchaseOrder(
        PerusahaanBuyer $tenant,
        Vendor $vendor,
        User $creator,
        StatusPurchaseOrder $status = StatusPurchaseOrder::Draft
    ): PurchaseOrder {
        $po = PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-2026-'.Str::padLeft((string) rand(1, 9999), 4, '0'),
            'vendor_id' => $vendor->id,
            'created_by' => $creator->id,
            'perusahaan_buyer_id' => $tenant->id,
            'harga_pagu_acuan' => 3000000.00,
            'status' => $status->value,
            'tanggal_po' => '2026-11-03',
            'catatan' => 'Catatan pesanan pengujian',
        ]);

        PoItem::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po->id,
            'komoditas_id' => $this->commodityBeras->id,
            'jumlah_pesanan' => 100,
            'harga_satuan_po' => 14000.00,
            'subtotal' => 1400000.00,
            'status_item' => 'menunggu',
        ]);

        return $po;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/purchase-order')->assertRedirect('/login');
        $this->get('/purchase-order/create')->assertRedirect('/login');
        $this->post('/purchase-order', [])->assertRedirect('/login');
    }

    public function test_purchasing_user_can_view_index_and_create_pages(): void
    {
        $po = $this->createPurchaseOrder($this->tenantA, $this->vendor, $this->purchasingUser);

        $this->actingAs($this->purchasingUser)
            ->get('/purchase-order')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PurchaseOrder/Index')
                ->has('purchase_orders.data', 1)
                ->where('can.create', true)
            );

        $this->actingAs($this->purchasingUser)
            ->get('/purchase-order/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PurchaseOrder/Create')
                ->has('vendor_options', 1)
                ->has('komoditas_options', 2)
            );

        $this->actingAs($this->purchasingUser)
            ->get("/purchase-order/{$po->uuid}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PurchaseOrder/Show')
                ->where('purchase_order.data.uuid', $po->uuid)
                ->where('purchase_order.data.status', 'draft')
            );
    }

    public function test_purchasing_user_can_create_draft_purchase_order_with_items(): void
    {
        $payload = [
            'vendor_uuid' => $this->vendor->uuid,
            'tanggal_po' => '2026-11-05',
            'catatan' => 'Pengiriman wajib menggunakan karung bersih.',
            'items' => [
                [
                    'komoditas_uuid' => $this->commodityBeras->uuid,
                    'jumlah_pesanan' => 250,
                    'harga_satuan_po' => 13500,
                ],
                [
                    'komoditas_uuid' => $this->commodityJagung->uuid,
                    'jumlah_pesanan' => 100,
                    'harga_satuan_po' => 8000,
                ],
            ],
        ];

        $response = $this->actingAs($this->purchasingUser)->post('/purchase-order', $payload);

        $this->assertDatabaseHas('purchase_order', [
            'perusahaan_buyer_id' => $this->tenantA->id,
            'vendor_id' => $this->vendor->id,
            'status' => 'draft',
            'catatan' => 'Pengiriman wajib menggunakan karung bersih.',
        ]);

        $createdPo = PurchaseOrder::where('perusahaan_buyer_id', $this->tenantA->id)->latest('id')->first();
        $this->assertNotNull($createdPo);
        $this->assertCount(2, $createdPo->poItems);

        $response->assertRedirect("/purchase-order/{$createdPo->uuid}");
        $response->assertSessionHas('success');
    }

    public function test_cannot_create_purchase_order_for_non_partner_vendor(): void
    {
        $strangerVendor = Vendor::create([
            'company_name' => 'CV Vendor Asing',
            'slug' => 'cv-vendor-asing-'.Str::lower(Str::random(6)),
            'tax_number' => '02.111.222.3-444.000',
            'contact_email' => 'asing@vendor.test',
            'verification_status' => 'approved',
            'is_active' => true,
        ]);

        $payload = [
            'vendor_uuid' => $strangerVendor->uuid,
            'tanggal_po' => '2026-11-05',
            'items' => [
                [
                    'komoditas_uuid' => $this->commodityBeras->uuid,
                    'jumlah_pesanan' => 100,
                    'harga_satuan_po' => 13000,
                ],
            ],
        ];

        $response = $this->actingAs($this->purchasingUser)->post('/purchase-order', $payload);
        $response->assertSessionHasErrors(['vendor_uuid']);
    }

    public function test_purchasing_user_can_update_draft_purchase_order(): void
    {
        $po = $this->createPurchaseOrder($this->tenantA, $this->vendor, $this->purchasingUser);

        $updatePayload = [
            'vendor_uuid' => $this->vendor->uuid,
            'tanggal_po' => '2026-11-10',
            'catatan' => 'Catatan revisi draf PO.',
            'items' => [
                [
                    'komoditas_uuid' => $this->commodityBeras->uuid,
                    'jumlah_pesanan' => 500,
                    'harga_satuan_po' => 13200,
                ],
            ],
        ];

        $response = $this->actingAs($this->purchasingUser)->put("/purchase-order/{$po->uuid}", $updatePayload);

        $response->assertRedirect("/purchase-order/{$po->uuid}");
        $response->assertSessionHas('success');

        $po->refresh();
        $this->assertSame('2026-11-10', $po->tanggal_po->format('Y-m-d'));
        $this->assertSame('Catatan revisi draf PO.', $po->catatan);

        $this->assertCount(1, $po->poItems);
        $this->assertEquals(500, (float) $po->poItems->first()->jumlah_pesanan);
    }

    public function test_purchasing_user_can_soft_delete_draft_purchase_order(): void
    {
        $po = $this->createPurchaseOrder($this->tenantA, $this->vendor, $this->purchasingUser);

        $response = $this->actingAs($this->purchasingUser)->delete("/purchase-order/{$po->uuid}");

        $response->assertRedirect('/purchase-order');
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('purchase_order', [
            'id' => $po->id,
        ]);
    }

    public function test_purchasing_user_can_confirm_and_submit_draft_to_negotiation(): void
    {
        $po = $this->createPurchaseOrder($this->tenantA, $this->vendor, $this->purchasingUser);
        $this->assertSame('draft', $po->status->value);

        $response = $this->actingAs($this->purchasingUser)->post("/purchase-order/{$po->uuid}/konfirmasi");

        $response->assertRedirect("/purchase-order/{$po->uuid}");
        $response->assertSessionHas('success');

        $po->refresh();
        $this->assertSame('negosiasi', $po->status->value);
    }

    public function test_cannot_update_or_delete_non_draft_purchase_order(): void
    {
        $po = $this->createPurchaseOrder(
            $this->tenantA,
            $this->vendor,
            $this->purchasingUser,
            StatusPurchaseOrder::Negosiasi
        );

        // Update forbidden
        $this->actingAs($this->purchasingUser)
            ->put("/purchase-order/{$po->uuid}", [
                'vendor_uuid' => $this->vendor->uuid,
                'tanggal_po' => '2026-11-10',
                'items' => [
                    [
                        'komoditas_uuid' => $this->commodityBeras->uuid,
                        'jumlah_pesanan' => 10,
                        'harga_satuan_po' => 10000,
                    ],
                ],
            ])
            ->assertForbidden();

        // Delete forbidden
        $this->actingAs($this->purchasingUser)
            ->delete("/purchase-order/{$po->uuid}")
            ->assertForbidden();
    }

    public function test_tenant_isolation_cannot_view_or_mutate_other_tenant_po(): void
    {
        $otherTenantUser = User::factory()->create([
            'role_id' => Role::where('name', RoleName::Purchasing->value)->value('id'),
            'tenant_id' => $this->tenantB->id,
            'is_active' => true,
        ]);

        $poA = $this->createPurchaseOrder($this->tenantA, $this->vendor, $this->purchasingUser);

        // Tenant B viewing Tenant A's PO returns 404
        $this->actingAs($otherTenantUser)
            ->get("/purchase-order/{$poA->uuid}")
            ->assertNotFound();

        // Tenant B updating Tenant A's PO returns 403 or 404
        $this->actingAs($otherTenantUser)
            ->put("/purchase-order/{$poA->uuid}", [
                'vendor_uuid' => $this->vendor->uuid,
                'tanggal_po' => '2026-11-10',
                'items' => [
                    [
                        'komoditas_uuid' => $this->commodityBeras->uuid,
                        'jumlah_pesanan' => 10,
                        'harga_satuan_po' => 10000,
                    ],
                ],
            ])
            ->assertForbidden();
    }

    public function test_vendor_can_view_submitted_po_but_cannot_view_draft_and_ceiling_price_is_hidden(): void
    {
        $draftPo = $this->createPurchaseOrder(
            $this->tenantA,
            $this->vendor,
            $this->purchasingUser,
            StatusPurchaseOrder::Draft
        );

        $submittedPo = $this->createPurchaseOrder(
            $this->tenantA,
            $this->vendor,
            $this->purchasingUser,
            StatusPurchaseOrder::Negosiasi
        );

        // 1. Vendor cannot view draft
        $this->actingAs($this->vendorUser)
            ->get("/purchase-order/{$draftPo->uuid}")
            ->assertForbidden();

        // 2. Vendor can view submitted PO
        $response = $this->actingAs($this->vendorUser)
            ->get("/purchase-order/{$submittedPo->uuid}");

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PurchaseOrder/Show')
                ->where('purchase_order.data.uuid', $submittedPo->uuid)
                ->where('purchase_order.data.harga_pagu_acuan', null)
                ->where('purchase_order.data.total_pagu', null)
                ->where('purchase_order.data.items.0.harga_pagu', null)
            );
    }

    public function test_non_uuid_parameter_is_rejected(): void
    {
        $this->actingAs($this->purchasingUser)->get('/purchase-order/123')->assertNotFound();
    }

    public function test_purchase_order_statistics_accurately_reflect_all_four_metrics(): void
    {
        // 1. Draf PO
        $draftPo = $this->createPurchaseOrder($this->tenantA, $this->vendor, $this->purchasingUser, StatusPurchaseOrder::Draft);

        // 2. PO Negosiasi dengan tawaran harga dari vendor (Masuk PO Aktif & Menunggu Tindakan)
        $negosiasiPo = $this->createPurchaseOrder($this->tenantA, $this->vendor, $this->purchasingUser, StatusPurchaseOrder::Negosiasi);
        $negosiasiPo->poItems()->first()->update([
            'harga_tawar_vendor' => 15000.00,
        ]);

        // 3. PO Disepakati dengan nilai 2.000.000 (Masuk PO Aktif & Total Nilai Berjalan)
        $disepakatiPo = $this->createPurchaseOrder($this->tenantA, $this->vendor, $this->purchasingUser, StatusPurchaseOrder::Disepakati);
        $disepakatiPo->poItems()->first()->update([
            'jumlah_pesanan' => 100,
            'harga_satuan_po' => 20000.00,
            'subtotal' => 2000000.00,
        ]);

        // 4. PO Sengketa (Masuk Perlu Perhatian / Sengketa)
        $sengketaPo = $this->createPurchaseOrder($this->tenantA, $this->vendor, $this->purchasingUser, StatusPurchaseOrder::Sengketa);

        // 5. PO Lunas (Tidak masuk PO Aktif, tidak masuk berjalan)
        $this->createPurchaseOrder($this->tenantA, $this->vendor, $this->purchasingUser, StatusPurchaseOrder::Lunas);

        // Verifikasi pada respons Inertia
        $response = $this->actingAs($this->purchasingUser)->get('/purchase-order');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PurchaseOrder/Index')
                ->where('statistics.total_aktif.count', 2) // Negosiasi & Disepakati
                ->where('statistics.total_aktif.draft_count', 1) // 1 draf
                ->where('statistics.menunggu_tindakan.count', 1) // Negosiasi dengan tawaran vendor
                ->where('statistics.total_nilai_berjalan.amount', 2000000) // 2.000.000
                ->where('statistics.perlu_perhatian.count', 1) // 1 sengketa
            );
    }

    public function test_purchase_order_statistics_remain_unchanged_when_filtered_by_status(): void
    {
        // Setup data
        $this->createPurchaseOrder($this->tenantA, $this->vendor, $this->purchasingUser, StatusPurchaseOrder::Draft);
        $negosiasiPo = $this->createPurchaseOrder($this->tenantA, $this->vendor, $this->purchasingUser, StatusPurchaseOrder::Negosiasi);
        $negosiasiPo->poItems()->first()->update(['harga_tawar_vendor' => 15000.00]);
        $disepakatiPo = $this->createPurchaseOrder($this->tenantA, $this->vendor, $this->purchasingUser, StatusPurchaseOrder::Disepakati);
        $disepakatiPo->poItems()->first()->update(['subtotal' => 3500000.00]);
        $this->createPurchaseOrder($this->tenantA, $this->vendor, $this->purchasingUser, StatusPurchaseOrder::Sengketa);

        // Filter tabel hanya untuk draf
        $response = $this->actingAs($this->purchasingUser)->get('/purchase-order?status=draft');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PurchaseOrder/Index')
                // Isi tabel terfilter menjadi hanya 1 (draf)
                ->has('purchase_orders.data', 1)
                ->where('purchase_orders.data.0.status', 'draft')
                // Namun kartu statistik tetap utuh menghitung keseluruhan
                ->where('statistics.total_aktif.count', 2)
                ->where('statistics.total_aktif.draft_count', 1)
                ->where('statistics.menunggu_tindakan.count', 1)
                ->where('statistics.total_nilai_berjalan.amount', 3500000)
                ->where('statistics.perlu_perhatian.count', 1)
            );
    }

    public function test_preset_status_filters_properly_filter_results(): void
    {
        $this->createPurchaseOrder($this->tenantA, $this->vendor, $this->purchasingUser, StatusPurchaseOrder::Draft);
        $negosiasiPo = $this->createPurchaseOrder($this->tenantA, $this->vendor, $this->purchasingUser, StatusPurchaseOrder::Negosiasi);
        $negosiasiPo->poItems()->first()->update(['harga_tawar_vendor' => 15000.00]);
        $this->createPurchaseOrder($this->tenantA, $this->vendor, $this->purchasingUser, StatusPurchaseOrder::Disepakati);
        $this->createPurchaseOrder($this->tenantA, $this->vendor, $this->purchasingUser, StatusPurchaseOrder::Sengketa);

        // 1. Filter aktif
        $this->actingAs($this->purchasingUser)
            ->get('/purchase-order?status=aktif')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('purchase_orders.data', 2));

        // 2. Filter menunggu_tindakan
        $this->actingAs($this->purchasingUser)
            ->get('/purchase-order?status=menunggu_tindakan')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('purchase_orders.data', 1));

        // 3. Filter sengketa
        $this->actingAs($this->purchasingUser)
            ->get('/purchase-order?status=sengketa')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('purchase_orders.data', 1));
    }
}
