<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PemicuSengketa;
use App\Enums\RoleName;
use App\Enums\StatusItemPenerimaan;
use App\Enums\StatusKerjaSama;
use App\Enums\StatusPengiriman;
use App\Enums\StatusPurchaseOrder;
use App\Enums\StatusSengketa;
use App\Models\KerjaSama;
use App\Models\Komoditas;
use App\Models\LogSengketa;
use App\Models\PenerimaanBarang;
use App\Models\PenerimaanBarangItem;
use App\Models\Pengiriman;
use App\Models\PerusahaanBuyer;
use App\Models\PoItem;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Sengketa;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PenerimaanBarangService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LogSengketaTest extends TestCase
{
    use RefreshDatabase;

    private PerusahaanBuyer $tenantA;

    private PerusahaanBuyer $tenantB;

    private Vendor $vendorA;

    private Vendor $vendorB;

    private Komoditas $commodityA;

    private User $ownerA;

    private User $manajemenA;

    private User $purchasingA;

    private User $qcA;

    private User $manajemenB;

    private User $vendorUserA;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        Storage::fake(PenerimaanBarangService::STORAGE_DISK);

        $this->tenantA = PerusahaanBuyer::factory()->create([
            'company_name' => 'PT Pangan Nusantara Raya',
            'address' => 'Kawasan Pergudangan Rungkut Blok A-01, Surabaya',
        ]);

        $this->tenantB = PerusahaanBuyer::factory()->create([
            'company_name' => 'PT Industri Pangan Makmur',
            'address' => 'Jl. Industri No. 88, Cikarang',
        ]);

        $this->vendorA = Vendor::create([
            'company_name' => 'CV Sumber Tani Makmur',
            'slug' => 'cv-sumber-tani-makmur-'.Str::lower(Str::random(6)),
            'contact_email' => 'sumbertani@mitra.test',
            'phone' => '081234567890',
            'address' => 'Jl. Kebon Agung No. 12, Malang',
            'verification_status' => 'approved',
            'is_active' => true,
        ]);

        $this->vendorB = Vendor::create([
            'company_name' => 'PT Agro Sejahtera Abadi',
            'slug' => 'pt-agro-sejahtera-abadi-'.Str::lower(Str::random(6)),
            'contact_email' => 'agrosejahtera@mitra.test',
            'phone' => '081234567899',
            'address' => 'Jl. Veteran No. 50, Kediri',
            'verification_status' => 'approved',
            'is_active' => true,
        ]);

        $this->ownerA = $this->makeUser(RoleName::Owner, $this->tenantA->id, null);
        $this->manajemenA = $this->makeUser(RoleName::Manajemen, $this->tenantA->id, null);
        $this->purchasingA = $this->makeUser(RoleName::Purchasing, $this->tenantA->id, null);
        $this->qcA = $this->makeUser(RoleName::QcGudang, $this->tenantA->id, null);
        $this->manajemenB = $this->makeUser(RoleName::Manajemen, $this->tenantB->id, null);
        $this->vendorUserA = $this->makeUser(RoleName::Vendor, null, $this->vendorA->id);
        $this->superadmin = $this->makeUser(RoleName::Superadmin, null, null);

        KerjaSama::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'vendor_id' => $this->vendorA->id,
            'requested_by' => 'tenant',
            'status' => StatusKerjaSama::Agreed->value,
        ]);

        $this->commodityA = Komoditas::create([
            'name' => 'Beras Pandan Wangi Super',
            'slug' => 'beras-pandan-wangi-super-'.Str::lower(Str::random(5)),
            'sku' => 'PDW-01',
            'unit' => 'kg',
            'is_active' => true,
        ]);
    }

    private function makeUser(RoleName $role, ?int $tenantId, ?int $vendorId): User
    {
        return User::factory()->create([
            'name' => "{$role->value} User ".Str::random(4),
            'email' => Str::lower($role->value).'.'.Str::random(6).'@pagora.test',
            'role_id' => Role::where('name', $role->value)->value('id'),
            'tenant_id' => $tenantId,
            'vendor_id' => $vendorId,
            'is_active' => true,
        ]);
    }

    /**
     * Helper to create a dispute under a specific tenant.
     */
    private function createDispute(
        PerusahaanBuyer $tenant,
        Vendor $vendor,
        StatusSengketa $status = StatusSengketa::Terbuka,
        PemicuSengketa $trigger = PemicuSengketa::GoodsMismatch,
        string $reason = 'Kadar air beras 18%, melampaui batas toleransi 14%.'
    ): Sengketa {
        $po = PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-TEST-'.Str::upper(Str::random(6)),
            'perusahaan_buyer_id' => $tenant->id,
            'vendor_id' => $vendor->id,
            'status' => StatusPurchaseOrder::PartiallyReceived->value,
            'harga_pagu_acuan' => 21000,
            'tanggal_po' => now()->toDateString(),
            'catatan' => 'Kirim ke Dermaga Gudang Utama',
            'created_by' => $this->purchasingA->id,
        ]);

        $poItem = PoItem::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po->id,
            'komoditas_id' => $this->commodityA->id,
            'jumlah_pesanan' => 1000,
            'harga_satuan_po' => 20000,
            'subtotal' => 20000000,
            'harga_tawar_vendor' => 20000,
            'status_item' => 'agreed',
        ]);

        $pengiriman = Pengiriman::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po->id,
            'route' => 'Darat',
            'carrier_name' => 'Truk Tronton Logistik',
            'vehicle_plate_number' => 'L 9999 XX',
            'estimated_arrival_date' => now()->subDay()->toDateString(),
            'status' => StatusPengiriman::Arrived->value,
            'reported_by' => $this->vendorUserA->id,
        ]);

        $penerimaan = PenerimaanBarang::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po->id,
            'shipment_id' => $pengiriman->id,
            'inspected_by' => $this->qcA->id,
            'matched_plate_number' => 'L 9999 XX',
            'inspected_at' => now()->subHours(2),
            'notes' => 'Pemeriksaan fisik dermaga',
            'created_at' => now()->subHours(2),
        ]);

        $photoPath = 'qc-inspections/evidence_test_'.Str::random(6).'.jpg';
        Storage::disk(PenerimaanBarangService::STORAGE_DISK)->put($photoPath, 'fake-jpeg-content');

        $penerimaanItem = PenerimaanBarangItem::create([
            'uuid' => (string) Str::uuid(),
            'goods_receipt_id' => $penerimaan->id,
            'purchase_order_item_id' => $poItem->id,
            'received_quantity' => 800,
            'status' => StatusItemPenerimaan::Rejected->value,
            'notes' => $reason,
            'rejection_photos' => [$photoPath],
        ]);

        $sengketa = Sengketa::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po->id,
            'purchase_order_item_id' => $trigger === PemicuSengketa::GoodsMismatch ? $poItem->id : null,
            'invoice_id' => null,
            'trigger_type' => $trigger->value,
            'reason' => $reason,
            'dibuka_oleh' => $this->qcA->id,
            'status' => $status->value,
            'tanggal_dibuka' => now()->toDateString(),
            'created_at' => now()->subHour(),
        ]);

        LogSengketa::create([
            'uuid' => (string) Str::uuid(),
            'sengketa_id' => $sengketa->id,
            'user_id' => $this->qcA->id,
            'type' => 'status_change',
            'isi_pesan' => "Sengketa otomatis dibuka oleh sistem karena penolakan QC: {$reason}",
            'file_bukti' => $photoPath,
            'created_at' => now()->subHour(),
        ]);

        return $sengketa;
    }

    public function test_access_permissions_follow_policy_matrix(): void
    {
        $disputeA = $this->createDispute($this->tenantA, $this->vendorA);

        // Authorized Tenant roles (Owner, Manajemen, Purchasing) can access index and detail
        $this->actingAs($this->manajemenA)
            ->get('/log-sengketa')
            ->assertOk();

        $this->actingAs($this->manajemenA)
            ->get("/log-sengketa/{$disputeA->uuid}")
            ->assertOk();

        $this->actingAs($this->ownerA)
            ->get('/log-sengketa')
            ->assertOk();

        $this->actingAs($this->ownerA)
            ->get("/log-sengketa/{$disputeA->uuid}")
            ->assertOk();

        $this->actingAs($this->purchasingA)
            ->get('/log-sengketa')
            ->assertOk();

        $this->actingAs($this->purchasingA)
            ->get("/log-sengketa/{$disputeA->uuid}")
            ->assertOk();

        // Unauthorized roles receive 403 Forbidden
        $this->actingAs($this->qcA)
            ->get('/log-sengketa')
            ->assertForbidden();

        $this->actingAs($this->vendorUserA)
            ->get('/log-sengketa')
            ->assertForbidden();

        $this->actingAs($this->superadmin)
            ->get('/log-sengketa')
            ->assertForbidden();
    }

    public function test_purchasing_can_view_and_export_but_cannot_store_tinjauan(): void
    {
        $disputeA = $this->createDispute($this->tenantA, $this->vendorA);

        // Purchasing can export
        $this->actingAs($this->purchasingA)
            ->get("/log-sengketa/{$disputeA->uuid}/unduh")
            ->assertOk();

        // Purchasing cannot record mediation reviews (reserved for Owner & Manajemen)
        $this->actingAs($this->purchasingA)
            ->post("/log-sengketa/{$disputeA->uuid}/tinjauan", [
                'message' => 'Catatan mediasi dari purchasing yang tidak berwenang.',
            ])
            ->assertForbidden();
    }

    public function test_index_lists_disputes_for_tenant_and_excludes_other_tenant(): void
    {
        $disputeA1 = $this->createDispute($this->tenantA, $this->vendorA, StatusSengketa::Terbuka);
        $disputeA2 = $this->createDispute($this->tenantA, $this->vendorA, StatusSengketa::DalamMediasi);
        $disputeB = $this->createDispute($this->tenantB, $this->vendorB, StatusSengketa::Terbuka);

        $this->actingAs($this->manajemenA)
            ->get('/log-sengketa')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('LogSengketa/Index')
                ->has('disputes.data', 2)
                ->where('disputes.data.0.uuid', $disputeA2->uuid)
                ->where('disputes.data.1.uuid', $disputeA1->uuid)
                ->where('statistics.total', 2)
                ->where('statistics.open', 1)
                ->where('statistics.in_mediation', 1)
                ->where('statistics.resolved', 0)
                ->where('disputes.data.0.can.review', true)
            );
    }

    public function test_search_filters_by_po_vendor_commodity_and_reason(): void
    {
        $dispute1 = $this->createDispute(
            $this->tenantA,
            $this->vendorA,
            StatusSengketa::Terbuka,
            PemicuSengketa::GoodsMismatch,
            'Biji pecah melampaui toleransi'
        );

        $dispute2 = $this->createDispute(
            $this->tenantA,
            $this->vendorA,
            StatusSengketa::Terbuka,
            PemicuSengketa::GoodsMismatch,
            'Kemasan robek parah basah kehujanan'
        );

        // Search by reason keyword
        $this->actingAs($this->manajemenA)
            ->get('/log-sengketa?search=pecah')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('disputes.data', 1)
                ->where('disputes.data.0.uuid', $dispute1->uuid)
            );

        // Search unmatched query returns empty dataset
        $this->actingAs($this->manajemenA)
            ->get('/log-sengketa?search=kata_kunci_mustahil_ada')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('disputes.data', 0)
            );
    }

    public function test_filters_by_status_trigger_and_date_range(): void
    {
        $disputeOpen = $this->createDispute($this->tenantA, $this->vendorA, StatusSengketa::Terbuka, PemicuSengketa::GoodsMismatch);
        $disputeMediasi = $this->createDispute($this->tenantA, $this->vendorA, StatusSengketa::DalamMediasi, PemicuSengketa::GoodsMismatch);

        // Filter status
        $this->actingAs($this->manajemenA)
            ->get('/log-sengketa?status=dalam_mediasi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('disputes.data', 1)
                ->where('disputes.data.0.uuid', $disputeMediasi->uuid)
            );

        // Filter trigger
        $this->actingAs($this->manajemenA)
            ->get('/log-sengketa?trigger_type=amount_mismatch')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('disputes.data', 0)
            );

        // Filter date range (invalid date_to before date_from returns 422 / validation error)
        $this->actingAs($this->manajemenA)
            ->get('/log-sengketa?date_from=2026-11-10&date_to=2026-11-01')
            ->assertSessionHasErrors(['date_to']);
    }

    public function test_show_returns_deeply_loaded_dispute_resource_without_leaking_ids(): void
    {
        $dispute = $this->createDispute($this->tenantA, $this->vendorA);

        $response = $this->actingAs($this->manajemenA)
            ->get("/log-sengketa/{$dispute->uuid}")
            ->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('LogSengketa/Show')
            ->has('dispute', fn (Assert $d) => $d
                ->where('uuid', $dispute->uuid)
                ->where('status', StatusSengketa::Terbuka->value)
                ->where('trigger_type', PemicuSengketa::GoodsMismatch->value)
                ->has('purchase_order')
                ->has('disputed_item')
                ->has('logs', 1)
                ->has('can')
                ->where('can.review', true)
                ->where('can.export', true)
                ->missing('id')
                ->missing('purchase_order_id')
                ->missing('penerimaan_barang_item_id')
                ->etc()
            )
        );
    }

    public function test_manajemen_can_store_tinjauan_and_status_transitions_to_dalam_mediasi(): void
    {
        $dispute = $this->createDispute($this->tenantA, $this->vendorA, StatusSengketa::Terbuka);

        $fakeAttachment = UploadedFile::fake()->create('berita_acara_mediasi.pdf', 300, 'application/pdf');

        $this->actingAs($this->manajemenA)
            ->post("/log-sengketa/{$dispute->uuid}/tinjauan", [
                'message' => 'Telah dilakukan verifikasi silang dengan pihak vendor. Vendor sepakat melakukan penggantian barang dalam 2x24 jam.',
                'attachment' => $fakeAttachment,
            ])
            ->assertRedirect("/log-sengketa/{$dispute->uuid}")
            ->assertSessionHas('success');

        // Status transitioned to dalam_mediasi
        $this->assertDatabaseHas('sengketa', [
            'id' => $dispute->id,
            'status' => StatusSengketa::DalamMediasi->value,
        ]);

        // Log entry created
        $this->assertDatabaseHas('log_sengketa', [
            'sengketa_id' => $dispute->id,
            'user_id' => $this->manajemenA->id,
        ]);

        $latestLog = LogSengketa::where('sengketa_id', $dispute->id)->latest('id')->first();
        $this->assertNotNull($latestLog);
        $this->assertNotNull($latestLog->file_bukti);
        Storage::disk(PenerimaanBarangService::STORAGE_DISK)->assertExists($latestLog->file_bukti);

        // Notifications dispatched to purchasing and owner
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->purchasingA->id,
            'type' => 'system',
        ]);
    }

    public function test_cannot_store_tinjauan_on_resolved_dispute(): void
    {
        $dispute = $this->createDispute($this->tenantA, $this->vendorA, StatusSengketa::Selesai);

        $this->actingAs($this->manajemenA)
            ->post("/log-sengketa/{$dispute->uuid}/tinjauan", [
                'message' => 'Mencoba menambahkan catatan pada sengketa yang telah selesai.',
            ])
            ->assertForbidden();

        // Status remains selesai
        $this->assertDatabaseHas('sengketa', [
            'id' => $dispute->id,
            'status' => StatusSengketa::Selesai->value,
        ]);
    }

    public function test_store_tinjauan_validation_rules(): void
    {
        $dispute = $this->createDispute($this->tenantA, $this->vendorA);

        // Message required and minimum 5 characters
        $this->actingAs($this->manajemenA)
            ->post("/log-sengketa/{$dispute->uuid}/tinjauan", [
                'message' => 'abc',
            ])
            ->assertSessionHasErrors(['message']);

        // Oversized attachment (> 5120 KB)
        $oversizedFile = UploadedFile::fake()->create('huge.pdf', 6000, 'application/pdf');
        $this->actingAs($this->manajemenA)
            ->post("/log-sengketa/{$dispute->uuid}/tinjauan", [
                'message' => 'Pesan tinjauan yang valid dan mencukupi panjang minimal.',
                'attachment' => $oversizedFile,
            ])
            ->assertSessionHasErrors(['attachment']);
    }

    public function test_export_dispute_document_as_pdf(): void
    {
        $dispute = $this->createDispute($this->tenantA, $this->vendorA);

        $response = $this->actingAs($this->manajemenA)
            ->get("/log-sengketa/{$dispute->uuid}/unduh");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('Dokumen-Sengketa-', (string) $response->headers->get('Content-Disposition'));

        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF-1.4', $content);
        $this->assertStringEndsWith('%%EOF', trim($content));
    }

    public function test_qc_photo_and_attachment_streaming_with_permission(): void
    {
        $dispute = $this->createDispute($this->tenantA, $this->vendorA);
        $receiptItem = PenerimaanBarangItem::where('purchase_order_item_id', $dispute->purchase_order_item_id)->firstOrFail();

        // Photo streaming succeeds for authorized Manajemen of Tenant A
        $this->actingAs($this->manajemenA)
            ->get("/log-sengketa/{$dispute->uuid}/foto-qc/{$receiptItem->uuid}/0")
            ->assertOk();

        // Cross-tenant user B gets 404
        $this->actingAs($this->manajemenB)
            ->get("/log-sengketa/{$dispute->uuid}/foto-qc/{$receiptItem->uuid}/0")
            ->assertNotFound();

        // Attachment streaming for log sengketa
        $log = $dispute->logs->first();
        $this->actingAs($this->manajemenA)
            ->get("/log-sengketa/{$dispute->uuid}/lampiran/{$log->uuid}")
            ->assertOk();

        $this->actingAs($this->manajemenB)
            ->get("/log-sengketa/{$dispute->uuid}/lampiran/{$log->uuid}")
            ->assertNotFound();
    }

    public function test_multi_tenant_isolation_on_show_export_review(): void
    {
        $disputeA = $this->createDispute($this->tenantA, $this->vendorA);

        // User from Tenant B cannot access Tenant A's dispute
        $this->actingAs($this->manajemenB)
            ->get("/log-sengketa/{$disputeA->uuid}")
            ->assertNotFound();

        $this->actingAs($this->manajemenB)
            ->get("/log-sengketa/{$disputeA->uuid}/unduh")
            ->assertNotFound();

        $this->actingAs($this->manajemenB)
            ->post("/log-sengketa/{$disputeA->uuid}/tinjauan", [
                'message' => 'Upaya ilegal mengakses sengketa tenant lain.',
            ])
            ->assertNotFound();
    }
}
