<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\StatusKerjaSama;
use App\Enums\StatusPengiriman;
use App\Enums\StatusPurchaseOrder;
use App\Enums\StatusSengketa;
use App\Models\Invoice;
use App\Models\KerjaSama;
use App\Models\Komoditas;
use App\Models\Notification;
use App\Models\Pengiriman;
use App\Models\PerusahaanBuyer;
use App\Models\PoItem;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Sengketa;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PengirimanTest extends TestCase
{
    use RefreshDatabase;

    private PerusahaanBuyer $tenantA;

    private PerusahaanBuyer $tenantB;

    private Vendor $vendorA;

    private Vendor $vendorB;

    private Komoditas $commodity;

    private User $ownerA;

    private User $purchasingA;

    private User $qcA;

    private User $manajemenA;

    private User $ownerB;

    private User $vendorUserA;

    private User $vendorUserB;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->tenantA = PerusahaanBuyer::factory()->create([
            'company_name' => 'PT Pangan Mandiri Nusantara',
            'address' => 'Jl. Industri Raya No. 10, Sidoarjo',
        ]);

        $this->tenantB = PerusahaanBuyer::factory()->create([
            'company_name' => 'PT Agro Sejahtera Lain',
            'address' => 'Jl. Raya Gresik No. 22',
        ]);

        $this->vendorA = Vendor::create([
            'company_name' => 'CV Mitra Tani Utama',
            'slug' => 'cv-mitra-tani-utama-'.Str::lower(Str::random(6)),
            'contact_email' => 'mitra-a@vendor.test',
            'phone' => '081234567801',
            'address' => 'Jl. Pertanian No. 5, Malang',
            'verification_status' => 'approved',
            'is_active' => true,
        ]);

        $this->vendorB = Vendor::create([
            'company_name' => 'PT Sumber Pangan Lain',
            'slug' => 'pt-sumber-pangan-lain-'.Str::lower(Str::random(6)),
            'contact_email' => 'mitra-b@vendor.test',
            'phone' => '081234567802',
            'address' => 'Jl. Raya Jombang No. 8',
            'verification_status' => 'approved',
            'is_active' => true,
        ]);

        $this->ownerA = $this->makeUser(RoleName::Owner, $this->tenantA->id, null);
        $this->purchasingA = $this->makeUser(RoleName::Purchasing, $this->tenantA->id, null);
        $this->qcA = $this->makeUser(RoleName::QcGudang, $this->tenantA->id, null);
        $this->manajemenA = $this->makeUser(RoleName::Manajemen, $this->tenantA->id, null);
        $this->ownerB = $this->makeUser(RoleName::Owner, $this->tenantB->id, null);
        $this->vendorUserA = $this->makeUser(RoleName::Vendor, null, $this->vendorA->id);
        $this->vendorUserB = $this->makeUser(RoleName::Vendor, null, $this->vendorB->id);
        $this->superadmin = $this->makeUser(RoleName::Superadmin, null, null);

        KerjaSama::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'vendor_id' => $this->vendorA->id,
            'requested_by' => 'tenant',
            'status' => StatusKerjaSama::Agreed->value,
        ]);

        $this->commodity = Komoditas::create([
            'name' => 'Beras IR-64 Premium',
            'slug' => 'beras-ir64-'.Str::lower(Str::random(5)),
            'sku' => 'BRS-IR64',
            'unit' => 'kg',
            'is_active' => true,
        ]);
    }

    private function makeUser(RoleName $role, ?int $tenantId, ?int $vendorId): User
    {
        return User::factory()->create([
            'role_id' => Role::where('name', $role->value)->value('id'),
            'tenant_id' => $tenantId,
            'vendor_id' => $vendorId,
            'is_active' => true,
        ]);
    }

    private function createPo(
        PerusahaanBuyer $tenant,
        Vendor $vendor,
        User $creator,
        StatusPurchaseOrder $status = StatusPurchaseOrder::Disepakati
    ): PurchaseOrder {
        $po = PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-2026-'.Str::padLeft((string) random_int(1, 9999), 4, '0'),
            'vendor_id' => $vendor->id,
            'created_by' => $creator->id,
            'perusahaan_buyer_id' => $tenant->id,
            'harga_pagu_acuan' => 3200000.00,
            'status' => $status->value,
            'tanggal_po' => now()->toDateString(),
            'catatan' => 'Kirim pagi hari ke gudang utama.',
            'locked_at' => in_array($status, [
                StatusPurchaseOrder::Disepakati,
                StatusPurchaseOrder::DalamPengiriman,
                StatusPurchaseOrder::PartiallyReceived,
            ], true) ? now()->subHour() : null,
        ]);

        PoItem::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po->id,
            'komoditas_id' => $this->commodity->id,
            'jumlah_pesanan' => 200,
            'harga_satuan_po' => 14500.00,
            'subtotal' => 2900000.00,
            'status_item' => 'agreed',
        ]);

        return $po;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/pengiriman')->assertRedirect('/login');
        $this->get('/pengiriman/create')->assertRedirect('/login');
        $this->post('/pengiriman', [])->assertRedirect('/login');
    }

    public function test_vendor_sees_shippable_pos_on_create_form_without_ceiling_price_leak(): void
    {
        $agreedPo = $this->createPo($this->tenantA, $this->vendorA, $this->purchasingA, StatusPurchaseOrder::Disepakati);
        $this->createPo($this->tenantA, $this->vendorA, $this->purchasingA, StatusPurchaseOrder::Negosiasi);
        $this->createPo($this->tenantA, $this->vendorA, $this->purchasingA, StatusPurchaseOrder::Draft);
        $this->createPo($this->tenantA, $this->vendorB, $this->purchasingA, StatusPurchaseOrder::Disepakati);

        $response = $this->actingAs($this->vendorUserA)
            ->get("/pengiriman/create?po={$agreedPo->uuid}");

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Pengiriman/Create')
                ->where('selected_po_uuid', $agreedPo->uuid)
                ->has('purchase_order_options', 1)
                ->where('purchase_order_options.0.uuid', $agreedPo->uuid)
                ->where('purchase_order_options.0.po_number', $agreedPo->nomor_po)
                ->missing('purchase_order_options.0.harga_pagu_acuan')
                ->missing('purchase_order_options.0.items.0.harga_pagu')
                ->has('route_options', 4)
            );
    }

    public function test_vendor_submits_self_reporting_logistics_sets_in_transit_and_locks_po(): void
    {
        $po = $this->createPo($this->tenantA, $this->vendorA, $this->purchasingA, StatusPurchaseOrder::Disepakati);
        $eta = now()->addDays(3)->toDateString();

        $response = $this->actingAs($this->vendorUserA)->post('/pengiriman', [
            'purchase_order_uuid' => $po->uuid,
            'route' => 'Darat',
            'carrier_name' => 'Wahana Logistik Nusantara',
            'vehicle_plate_number' => 'l 5678 cd',
            'estimated_arrival_date' => $eta,
        ]);

        $shipment = Pengiriman::where('purchase_order_id', $po->id)->first();
        $this->assertNotNull($shipment);
        $response->assertRedirect(route('pengiriman.show', $shipment->uuid));

        $this->assertSame('Darat', $shipment->route);
        $this->assertSame('Wahana Logistik Nusantara', $shipment->carrier_name);
        $this->assertSame('L 5678 CD', $shipment->vehicle_plate_number);
        $this->assertSame($eta, $shipment->estimated_arrival_date->format('Y-m-d'));
        $this->assertSame(StatusPengiriman::InTransit, $shipment->status);
        $this->assertSame($this->vendorUserA->id, $shipment->reported_by);

        $freshPo = $po->fresh();
        $this->assertSame(StatusPurchaseOrder::DalamPengiriman, $freshPo->status);
        $this->assertNotNull($freshPo->locked_at);
        $this->assertTrue($freshPo->isQuantityLocked());

        // Locked PO cannot be updated, deleted, cancelled, or re-negotiated
        $this->actingAs($this->purchasingA)
            ->put("/purchase-order/{$po->uuid}", [
                'vendor_uuid' => $this->vendorA->uuid,
                'tanggal_po' => now()->toDateString(),
                'items' => [[
                    'komoditas_uuid' => $this->commodity->uuid,
                    'jumlah_pesanan' => 999,
                    'harga_satuan_po' => 14500,
                ]],
            ])
            ->assertForbidden();

        $this->actingAs($this->purchasingA)
            ->delete("/purchase-order/{$po->uuid}")
            ->assertForbidden();

        // Notifications sent to Tenant Owner, Purchasing, and QC Gudang only
        $this->assertSame(1, Notification::where('user_id', $this->ownerA->id)->where('type', 'shipment')->count());
        $this->assertSame(1, Notification::where('user_id', $this->purchasingA->id)->where('type', 'shipment')->count());
        $this->assertSame(1, Notification::where('user_id', $this->qcA->id)->where('type', 'shipment')->count());
        $this->assertSame(0, Notification::where('user_id', $this->manajemenA->id)->where('type', 'shipment')->count());
        $this->assertSame(0, Notification::where('user_id', $this->ownerB->id)->where('type', 'shipment')->count());

        // Detail page works and hides internal ceiling prices from Vendor
        $this->actingAs($this->vendorUserA)
            ->get("/pengiriman/{$shipment->uuid}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Pengiriman/Show')
                ->where('shipment.data.uuid', $shipment->uuid)
                ->where('shipment.data.status', 'in_transit')
                ->where('shipment.data.status_label', 'Dalam Pengiriman')
                ->where('shipment.data.vehicle_plate_number', 'L 5678 CD')
                ->where('shipment.data.purchase_order.is_locked', true)
                ->where('shipment.data.purchase_order.quantity_locked', true)
                ->missing('shipment.data.id')
                ->missing('shipment.data.purchase_order.harga_pagu_acuan')
            );
    }

    public function test_vendor_can_report_reshipment_for_partially_received_po_with_open_dispute(): void
    {
        $po = $this->createPo($this->tenantA, $this->vendorA, $this->purchasingA, StatusPurchaseOrder::PartiallyReceived);

        $invoice = Invoice::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po->id,
            'vendor_id' => $this->vendorA->id,
            'nomor_invoice_vendor' => 'INV-2026-001',
            'jumlah_tagihan' => 2900000,
            'tanggal_invoice' => now()->toDateString(),
            'status_matching' => 'gagal',
        ]);

        $dispute = Sengketa::create([
            'uuid' => (string) Str::uuid(),
            'invoice_id' => $invoice->id,
            'dibuka_oleh' => $this->purchasingA->id,
            'status' => StatusSengketa::Terbuka->value,
            'tanggal_dibuka' => now(),
        ]);

        $this->actingAs($this->vendorUserA)->post('/pengiriman', [
            'purchase_order_uuid' => $po->uuid,
            'route' => 'Darat',
            'carrier_name' => 'Armada Pengganti',
            'vehicle_plate_number' => 'N 9988 XY',
            'estimated_arrival_date' => now()->addDays(2)->toDateString(),
        ])->assertRedirect();

        $shipment = Pengiriman::where('purchase_order_id', $po->id)->first();
        $this->assertNotNull($shipment);
        $this->assertSame($dispute->id, $shipment->dispute_id);
        $this->assertSame(StatusPurchaseOrder::DalamPengiriman, $po->fresh()->status);
    }

    public function test_validation_rejects_invalid_self_reporting_inputs(): void
    {
        $po = $this->createPo($this->tenantA, $this->vendorA, $this->purchasingA, StatusPurchaseOrder::Disepakati);

        $this->actingAs($this->vendorUserA)->postJson('/pengiriman', [
            'purchase_order_uuid' => $po->uuid,
            'route' => 'Roket',
            'carrier_name' => 'A',
            'vehicle_plate_number' => 'L@123!',
            'estimated_arrival_date' => now()->subDay()->toDateString(),
        ])->assertStatus(422)
            ->assertJsonValidationErrors([
                'route',
                'carrier_name',
                'vehicle_plate_number',
                'estimated_arrival_date',
            ]);

        $this->assertSame(0, Pengiriman::count());
        $this->assertSame(StatusPurchaseOrder::Disepakati, $po->fresh()->status);
    }

    /**
     * @return array<string, array{StatusPurchaseOrder}>
     */
    public static function nonShippableStatuses(): array
    {
        return [
            'negosiasi' => [StatusPurchaseOrder::Negosiasi],
            'dalam_pengiriman' => [StatusPurchaseOrder::DalamPengiriman],
            'diterima_gudang' => [StatusPurchaseOrder::DiterimaGudang],
            'ditagih' => [StatusPurchaseOrder::Ditagih],
            'lunas' => [StatusPurchaseOrder::Lunas],
            'dibatalkan' => [StatusPurchaseOrder::Dibatalkan],
        ];
    }

    #[DataProvider('nonShippableStatuses')]
    public function test_cannot_ship_po_in_non_shippable_status(StatusPurchaseOrder $status): void
    {
        $po = $this->createPo($this->tenantA, $this->vendorA, $this->purchasingA, $status);

        $this->actingAs($this->vendorUserA)->postJson('/pengiriman', [
            'purchase_order_uuid' => $po->uuid,
            'route' => 'Darat',
            'carrier_name' => 'Ekspedisi Cepat',
            'vehicle_plate_number' => 'B 1234 XYZ',
            'estimated_arrival_date' => now()->addDays(2)->toDateString(),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['purchase_order_uuid']);

        $this->assertSame(0, Pengiriman::count());
    }

    public function test_tenant_draft_po_returns_404_when_vendor_attempts_shipment(): void
    {
        $draftPo = $this->createPo($this->tenantA, $this->vendorA, $this->purchasingA, StatusPurchaseOrder::Draft);

        $this->actingAs($this->vendorUserA)->postJson('/pengiriman', [
            'purchase_order_uuid' => $draftPo->uuid,
            'route' => 'Darat',
            'carrier_name' => 'Ekspedisi Cepat',
            'vehicle_plate_number' => 'B 1234 XYZ',
            'estimated_arrival_date' => now()->addDays(2)->toDateString(),
        ])->assertNotFound();
    }

    public function test_non_vendor_roles_are_forbidden_from_submitting_shipments(): void
    {
        $po = $this->createPo($this->tenantA, $this->vendorA, $this->purchasingA, StatusPurchaseOrder::Disepakati);

        $payload = [
            'purchase_order_uuid' => $po->uuid,
            'route' => 'Darat',
            'carrier_name' => 'Ekspedisi Cepat',
            'vehicle_plate_number' => 'B 1234 XYZ',
            'estimated_arrival_date' => now()->addDays(2)->toDateString(),
        ];

        foreach ([$this->ownerA, $this->purchasingA, $this->qcA, $this->manajemenA, $this->superadmin] as $nonVendor) {
            $this->actingAs($nonVendor)->get('/pengiriman/create')->assertForbidden();
            $this->actingAs($nonVendor)->postJson('/pengiriman', $payload)->assertForbidden();
        }

        $this->assertSame(0, Pengiriman::count());
    }

    public function test_tenant_owner_purchasing_and_qc_can_view_own_shipments_read_only(): void
    {
        $po = $this->createPo($this->tenantA, $this->vendorA, $this->purchasingA, StatusPurchaseOrder::DalamPengiriman);
        $shipment = Pengiriman::factory()->create([
            'purchase_order_id' => $po->id,
            'reported_by' => $this->vendorUserA->id,
            'vehicle_plate_number' => 'W 8877 QA',
        ]);

        foreach ([$this->ownerA, $this->purchasingA, $this->qcA] as $viewer) {
            $this->actingAs($viewer)
                ->get('/pengiriman')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Pengiriman/Index')
                    ->where('can.create', false)
                    ->has('shipments.data', 1)
                );

            $this->actingAs($viewer)
                ->get("/pengiriman/{$shipment->uuid}")
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Pengiriman/Show')
                    ->where('shipment.data.uuid', $shipment->uuid)
                    ->where('shipment.data.vehicle_plate_number', 'W 8877 QA')
                );
        }
    }

    public function test_multi_tenant_and_cross_vendor_isolation_returns_404(): void
    {
        $poA = $this->createPo($this->tenantA, $this->vendorA, $this->purchasingA, StatusPurchaseOrder::Disepakati);

        // Vendor B cannot report shipment for Vendor A's PO
        $this->actingAs($this->vendorUserB)->postJson('/pengiriman', [
            'purchase_order_uuid' => $poA->uuid,
            'route' => 'Darat',
            'carrier_name' => 'Armada B',
            'vehicle_plate_number' => 'S 1111 BB',
            'estimated_arrival_date' => now()->addDays(1)->toDateString(),
        ])->assertNotFound();

        $shipmentA = Pengiriman::factory()->create([
            'purchase_order_id' => $poA->id,
            'reported_by' => $this->vendorUserA->id,
        ]);

        // Vendor B & Tenant B receive 404 when accessing Tenant A / Vendor A shipment
        $this->actingAs($this->vendorUserB)->get("/pengiriman/{$shipmentA->uuid}")->assertNotFound();
        $this->actingAs($this->ownerB)->get("/pengiriman/{$shipmentA->uuid}")->assertNotFound();

        // Tenant B sees empty list on index
        $this->actingAs($this->ownerB)
            ->get('/pengiriman')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('shipments.data', 0));
    }

    public function test_server_side_search_status_and_route_filtering(): void
    {
        $po1 = $this->createPo($this->tenantA, $this->vendorA, $this->purchasingA, StatusPurchaseOrder::DalamPengiriman);
        $po2 = $this->createPo($this->tenantA, $this->vendorA, $this->purchasingA, StatusPurchaseOrder::DiterimaGudang);

        Pengiriman::factory()->create([
            'purchase_order_id' => $po1->id,
            'route' => 'Darat',
            'carrier_name' => 'Wahana Express',
            'vehicle_plate_number' => 'L 1111 AA',
            'status' => StatusPengiriman::InTransit,
            'reported_by' => $this->vendorUserA->id,
        ]);

        Pengiriman::factory()->arrived()->create([
            'purchase_order_id' => $po2->id,
            'route' => 'Laut',
            'carrier_name' => 'Meratus Line',
            'vehicle_plate_number' => 'B 2222 BB',
            'reported_by' => $this->vendorUserA->id,
        ]);

        $this->actingAs($this->vendorUserA)
            ->get('/pengiriman?search=Meratus')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('shipments.data', 1)
                ->where('shipments.data.0.carrier_name', 'Meratus Line')
            );

        $this->actingAs($this->vendorUserA)
            ->get('/pengiriman?status=in_transit&route=Darat')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('shipments.data', 1)
                ->where('shipments.data.0.vehicle_plate_number', 'L 1111 AA')
            );
    }
}
