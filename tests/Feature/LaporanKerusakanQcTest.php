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
use App\Models\Notification;
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
use App\Services\PenerimaanBarangService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LaporanKerusakanQcTest extends TestCase
{
    use RefreshDatabase;

    private PerusahaanBuyer $tenantA;

    private PerusahaanBuyer $tenantB;

    private Vendor $vendorA;

    private Vendor $vendorB;

    private Komoditas $commodityA;

    private Komoditas $commodityB;

    private User $ownerA;

    private User $purchasingA;

    private User $qcA;

    private User $manajemenA;

    private User $qcB;

    private User $vendorUserA;

    private User $vendorUserB;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        Storage::fake(PenerimaanBarangService::STORAGE_DISK);

        $this->tenantA = PerusahaanBuyer::factory()->create([
            'company_name' => 'PT Pangan Nusantara Utama',
            'address' => 'Kawasan Pergudangan Rungkut Blok D-08, Surabaya',
        ]);

        $this->tenantB = PerusahaanBuyer::factory()->create([
            'company_name' => 'PT Distribusi Pangan Lain',
            'address' => 'Jl. Industri Jababeka No. 22',
        ]);

        $this->vendorA = Vendor::create([
            'company_name' => 'CV Agro Makmur Sejahtera',
            'slug' => 'cv-agro-makmur-'.Str::lower(Str::random(6)),
            'contact_email' => 'vendor-agro@mitra.test',
            'phone' => '081230001111',
            'address' => 'Jl. Raya Batu Malang No. 12',
            'verification_status' => 'approved',
            'is_active' => true,
        ]);

        $this->vendorB = Vendor::create([
            'company_name' => 'PT Hasil Tani Lain',
            'slug' => 'pt-hasil-tani-'.Str::lower(Str::random(6)),
            'contact_email' => 'vendor-lain@mitra.test',
            'phone' => '081230002222',
            'address' => 'Jl. Raya Jember No. 40',
            'verification_status' => 'approved',
            'is_active' => true,
        ]);

        $this->ownerA = $this->makeUser(RoleName::Owner, $this->tenantA->id, null);
        $this->purchasingA = $this->makeUser(RoleName::Purchasing, $this->tenantA->id, null);
        $this->qcA = $this->makeUser(RoleName::QcGudang, $this->tenantA->id, null);
        $this->manajemenA = $this->makeUser(RoleName::Manajemen, $this->tenantA->id, null);
        $this->qcB = $this->makeUser(RoleName::QcGudang, $this->tenantB->id, null);
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

        $this->commodityA = Komoditas::create([
            'name' => 'Beras Pandan Wangi Premium',
            'slug' => 'beras-pandan-wangi-'.Str::lower(Str::random(5)),
            'sku' => 'BRS-PW-01',
            'unit' => 'kg',
            'default_shrinkage_tolerance' => 2.50,
            'is_active' => true,
        ]);

        $this->commodityB = Komoditas::create([
            'name' => 'Jagung Pipil Kering',
            'slug' => 'jagung-pipil-kering-'.Str::lower(Str::random(5)),
            'sku' => 'JGG-PK-02',
            'unit' => 'kg',
            'default_shrinkage_tolerance' => 3.00,
            'is_active' => true,
        ]);

        TenantCommodity::create([
            'tenant_id' => $this->tenantA->id,
            'commodity_id' => $this->commodityA->id,
            'is_active' => true,
            'shrinkage_tolerance' => 1.50,
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

    /**
     * @return array{0: PurchaseOrder, 1: Pengiriman, 2: PoItem, 3: PoItem}
     */
    private function createInTransitOrderWithTwoItems(
        ?PerusahaanBuyer $tenant = null,
        ?Vendor $vendor = null,
        ?User $creator = null,
        ?User $reporter = null,
    ): array {
        $tenant ??= $this->tenantA;
        $vendor ??= $this->vendorA;
        $creator ??= $this->purchasingA;
        $reporter ??= $this->vendorUserA;

        $po = PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-DMG-'.Str::upper(Str::random(6)),
            'vendor_id' => $vendor->id,
            'created_by' => $creator->id,
            'perusahaan_buyer_id' => $tenant->id,
            'harga_pagu_acuan' => 16500,
            'status' => StatusPurchaseOrder::DalamPengiriman->value,
            'tanggal_po' => now()->toDateString(),
            'catatan' => 'Pengiriman bahan baku gudang utama',
            'locked_at' => now()->subHours(3),
        ]);

        $itemA = PoItem::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po->id,
            'komoditas_id' => $this->commodityA->id,
            'jumlah_pesanan' => 1000,
            'harga_satuan_po' => 14800,
            'subtotal' => 14800000,
            'harga_tawar_vendor' => 14800,
            'status_item' => 'agreed',
        ]);

        $itemB = PoItem::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po->id,
            'komoditas_id' => $this->commodityB->id,
            'jumlah_pesanan' => 400,
            'harga_satuan_po' => 9200,
            'subtotal' => 3680000,
            'harga_tawar_vendor' => 9200,
            'status_item' => 'agreed',
        ]);

        $shipment = Pengiriman::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po->id,
            'route' => 'Darat',
            'carrier_name' => 'PT Lintas Logistik Jawa',
            'vehicle_plate_number' => 'W 9182 UQ',
            'estimated_arrival_date' => now()->addDay()->toDateString(),
            'status' => StatusPengiriman::InTransit->value,
            'reported_by' => $reporter->id,
        ]);

        return [$po, $shipment, $itemA, $itemB];
    }

    /**
     * @return array{0: PenerimaanBarang, 1: PenerimaanBarangItem, 2: Sengketa}
     */
    private function submitDamageInspectionReport(
        PurchaseOrder $po,
        PoItem $damagedPoItem,
        PoItem $acceptedPoItem,
        string $triggerType = 'goods_mismatch',
    ): array {
        $photo = UploadedFile::fake()->create('karung-robek-basah.jpg', 240, 'image/jpeg');

        $this->actingAs($this->qcA)->post('/inspeksi-qc', [
            'purchase_order_uuid' => $po->uuid,
            'matched_plate_number' => 'W 9182 UQ',
            'notes' => 'Ditemukan kerusakan fisik pada sebagian muatan.',
            'items' => [
                [
                    'po_item_uuid' => $damagedPoItem->uuid,
                    'status' => StatusItemPenerimaan::Rejected->value,
                    'trigger_type' => $triggerType,
                    'received_quantity' => 920,
                    'notes' => 'Sebanyak 8 karung pecah dan beras basah terkena rembesan air truk.',
                    'photos' => [$photo],
                ],
                [
                    'po_item_uuid' => $acceptedPoItem->uuid,
                    'status' => StatusItemPenerimaan::Accepted->value,
                    'received_quantity' => 400,
                    'notes' => 'Kondisi kering dan sesuai pesanan.',
                ],
            ],
        ])->assertRedirect();

        $receipt = PenerimaanBarang::where('purchase_order_id', $po->id)->latest('id')->firstOrFail();
        $rejectedItem = PenerimaanBarangItem::where('goods_receipt_id', $receipt->id)
            ->where('purchase_order_item_id', $damagedPoItem->id)
            ->firstOrFail();
        $dispute = Sengketa::where('purchase_order_item_id', $damagedPoItem->id)->latest('id')->firstOrFail();

        return [$receipt, $rejectedItem, $dispute];
    }

    public function test_qc_gudang_can_open_damage_mode_form_with_effective_shrinkage_tolerance_br23(): void
    {
        [$po] = $this->createInTransitOrderWithTwoItems();

        $this->actingAs($this->qcA)
            ->get("/inspeksi-qc/create?po={$po->uuid}&mode=damage")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('InspeksiQc/Create')
                ->where('selected_po_uuid', $po->uuid)
                ->where('damage_mode', true)
                ->has('trigger_options', 2)
                ->where('trigger_options.0.value', PemicuSengketa::GoodsMismatch->value)
                ->where('trigger_options.1.value', PemicuSengketa::QcRejected->value)
                // Commodity A uses Tenant override 1.50% (min acceptable = 985 kg of 1000 kg)
                ->where('purchase_order_options.0.items.0.shrinkage_tolerance', 1.5)
                ->where('purchase_order_options.0.items.0.min_acceptable_quantity', 985)
                // Commodity B falls back to Commodity default 3.00% (min acceptable = 388 kg of 400 kg)
                ->where('purchase_order_options.0.items.1.shrinkage_tolerance', 3)
                ->where('purchase_order_options.0.items.1.min_acceptable_quantity', 388)
            );
    }

    public function test_qc_gudang_can_report_goods_mismatch_damage_and_persist_trigger_type_on_sengketa(): void
    {
        [$po, , $itemA, $itemB] = $this->createInTransitOrderWithTwoItems();

        [$receipt, $rejectedItem, $dispute] = $this->submitDamageInspectionReport(
            $po,
            $itemA,
            $itemB,
            PemicuSengketa::GoodsMismatch->value
        );

        $this->assertSame(StatusPurchaseOrder::PartiallyReceived, $po->fresh()->status);
        $this->assertSame(StatusItemPenerimaan::Rejected, $rejectedItem->status);
        $this->assertSame(PemicuSengketa::GoodsMismatch->value, $dispute->trigger_type);
        $this->assertSame(StatusSengketa::Terbuka, $dispute->status);
        $this->assertSame(
            'Sebanyak 8 karung pecah dan beras basah terkena rembesan air truk.',
            $dispute->reason
        );

        $initialLog = LogSengketa::where('sengketa_id', $dispute->id)->first();
        $this->assertNotNull($initialLog);
        $this->assertSame('status_change', $initialLog->type);
        $this->assertStringContainsString(
            PemicuSengketa::GoodsMismatch->label(),
            (string) $initialLog->isi_pesan
        );
        $this->assertNotNull($initialLog->file_bukti);
        Storage::disk(PenerimaanBarangService::STORAGE_DISK)->assertExists($initialLog->file_bukti);

        // Receipt show page includes damage_report_url and trigger_type_label
        $this->actingAs($this->qcA)
            ->get("/inspeksi-qc/{$receipt->uuid}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('InspeksiQc/Show')
                ->where('receipt.data.items.0.dispute.trigger_type', PemicuSengketa::GoodsMismatch->value)
                ->where('receipt.data.items.0.dispute.trigger_type_label', PemicuSengketa::GoodsMismatch->label())
                ->where('receipt.data.items.0.damage_report_url', route('inspeksi-qc.damage-reports.show', $rejectedItem->uuid))
            );
    }

    public function test_invalid_trigger_type_during_damage_report_is_rejected_with_validation_error(): void
    {
        [$po, , $itemA, $itemB] = $this->createInTransitOrderWithTwoItems();
        $photo = UploadedFile::fake()->create('kerusakan.jpg', 180, 'image/jpeg');

        $response = $this->actingAs($this->qcA)
            ->from("/inspeksi-qc/create?po={$po->uuid}&mode=damage")
            ->post('/inspeksi-qc', [
                'purchase_order_uuid' => $po->uuid,
                'items' => [
                    [
                        'po_item_uuid' => $itemA->uuid,
                        'status' => StatusItemPenerimaan::Rejected->value,
                        'trigger_type' => PemicuSengketa::AmountMismatch->value, // Not allowed in QC stage
                        'received_quantity' => 850,
                        'notes' => 'Barang rusak fisik saat bongkar muat.',
                        'photos' => [$photo],
                    ],
                    [
                        'po_item_uuid' => $itemB->uuid,
                        'status' => StatusItemPenerimaan::Accepted->value,
                        'received_quantity' => 400,
                    ],
                ],
            ]);

        $response->assertSessionHasErrors(['items.0.trigger_type']);
        $this->assertDatabaseCount('goods_receipts', 0);
        $this->assertDatabaseCount('sengketa', 0);
    }

    public function test_damage_reports_index_and_show_enforce_filters_and_br02_confidentiality(): void
    {
        [$po, , $itemA, $itemB] = $this->createInTransitOrderWithTwoItems();
        [, $rejectedItem] = $this->submitDamageInspectionReport(
            $po,
            $itemA,
            $itemB,
            PemicuSengketa::GoodsMismatch->value
        );

        // Tenant QC Gudang sees damage reports index, statistics, and Tenant shrinkage tolerance
        $this->actingAs($this->qcA)
            ->get('/inspeksi-qc/laporan-kerusakan?trigger_type=goods_mismatch&dispute_status=terbuka')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('InspeksiQc/DamageReports')
                ->where('statistics.total_damage_reports', 1)
                ->where('statistics.goods_mismatch_count', 1)
                ->where('statistics.qc_rejected_count', 0)
                ->where('statistics.open_disputes_count', 1)
                ->has('damage_reports.data', 1)
                ->where('damage_reports.data.0.uuid', $rejectedItem->uuid)
                ->where('damage_reports.data.0.shrinkage_tolerance', 1.5)
                ->where('damage_reports.data.0.exceeds_shrinkage_tolerance', true)
            );

        // Filtering by qc_rejected returns 0 rows for this goods_mismatch item
        $this->actingAs($this->qcA)
            ->get('/inspeksi-qc/laporan-kerusakan?trigger_type=qc_rejected')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('InspeksiQc/DamageReports')
                ->has('damage_reports.data', 0)
            );

        // Vendor A can view their damage report detail, WITHOUT Tenant shrinkage_tolerance or internal id (BR-02, RULES §5.1)
        $vendorJson = $this->actingAs($this->vendorUserA)
            ->getJson("/inspeksi-qc/laporan-kerusakan/{$rejectedItem->uuid}")
            ->assertOk()
            ->json('data');

        $this->assertSame($rejectedItem->uuid, $vendorJson['uuid']);
        $this->assertArrayNotHasKey('id', $vendorJson);
        $this->assertArrayNotHasKey('shrinkage_tolerance', $vendorJson);
        $this->assertArrayNotHasKey('exceeds_shrinkage_tolerance', $vendorJson);
        $this->assertArrayNotHasKey('harga_pagu_acuan', $vendorJson['purchase_order'] ?? []);
        $this->assertFalse($vendorJson['can']['report_additional_evidence']);

        // Accepted item UUID is not a damage report and returns 404
        $acceptedReceiptItem = PenerimaanBarangItem::where('purchase_order_item_id', $itemB->id)->firstOrFail();
        $this->actingAs($this->qcA)
            ->get("/inspeksi-qc/laporan-kerusakan/{$acceptedReceiptItem->uuid}")
            ->assertNotFound();
    }

    public function test_qc_gudang_and_owner_can_append_immutable_damage_evidence_and_stream_log_photo(): void
    {
        [$po, , $itemA, $itemB] = $this->createInTransitOrderWithTwoItems();
        [, $rejectedItem, $dispute] = $this->submitDamageInspectionReport(
            $po,
            $itemA,
            $itemB,
            PemicuSengketa::GoodsMismatch->value
        );

        $initialLogsCount = LogSengketa::where('sengketa_id', $dispute->id)->count();
        $originalNotes = $rejectedItem->notes;
        $originalPhotos = $rejectedItem->rejection_photos;

        $extraPhoto1 = UploadedFile::fake()->create('detail-jamur-karung-1.png', 210, 'image/png');
        $extraPhoto2 = UploadedFile::fake()->create('detail-segel-rusak-2.jpg', 195, 'image/jpeg');

        $this->actingAs($this->qcA)
            ->post("/inspeksi-qc/laporan-kerusakan/{$rejectedItem->uuid}/bukti", [
                'message' => 'Pemeriksaan laboratorium gudang menemukan kadar air 19% akibat karung rembes.',
                'photos' => [$extraPhoto1, $extraPhoto2],
            ])
            ->assertRedirect(route('inspeksi-qc.damage-reports.show', $rejectedItem->uuid));

        // Original goods_receipt_items record remains untouched (immutable — BR-17 & RULES §9.4)
        $rejectedItem->refresh();
        $this->assertSame($originalNotes, $rejectedItem->notes);
        $this->assertSame($originalPhotos, $rejectedItem->rejection_photos);

        // Two new append-only log_sengketa records created (1 with note+photo1, 1 with photo2)
        $allLogs = LogSengketa::where('sengketa_id', $dispute->id)->orderBy('id')->get();
        $this->assertCount($initialLogsCount + 2, $allLogs);

        $latestEvidenceLog = $allLogs->last();
        $this->assertNotNull($latestEvidenceLog);
        $this->assertSame('evidence', $latestEvidenceLog->type);
        $this->assertNotNull($latestEvidenceLog->file_bukti);
        Storage::disk(PenerimaanBarangService::STORAGE_DISK)->assertExists($latestEvidenceLog->file_bukti);

        // Both Tenant QC and Vendor A can stream the supplementary dispute log photo; Tenant B QC gets 404
        $this->actingAs($this->qcA)
            ->get("/inspeksi-qc/log-sengketa/{$latestEvidenceLog->uuid}/bukti")
            ->assertOk();

        $this->actingAs($this->vendorUserA)
            ->get("/inspeksi-qc/log-sengketa/{$latestEvidenceLog->uuid}/bukti")
            ->assertOk();

        $this->actingAs($this->qcB)
            ->get("/inspeksi-qc/log-sengketa/{$latestEvidenceLog->uuid}/bukti")
            ->assertNotFound();

        $this->actingAs($this->vendorUserB)
            ->get("/inspeksi-qc/log-sengketa/{$latestEvidenceLog->uuid}/bukti")
            ->assertNotFound();

        // Owner of Tenant A can also append a follow-up note without photo
        $this->actingAs($this->ownerA)
            ->post("/inspeksi-qc/laporan-kerusakan/{$rejectedItem->uuid}/bukti", [
                'message' => 'Mohon segera dikirimkan barang pengganti sesuai jadwal gudang.',
            ])
            ->assertRedirect(route('inspeksi-qc.damage-reports.show', $rejectedItem->uuid));

        $this->assertCount($initialLogsCount + 3, LogSengketa::where('sengketa_id', $dispute->id)->get());

        // Vendor A received in-app notification pointing to the damage report
        $this->assertTrue(
            Notification::where('user_id', $this->vendorUserA->id)
                ->where('action_url', "/inspeksi-qc/laporan-kerusakan/{$rejectedItem->uuid}")
                ->exists()
        );
    }

    public function test_cannot_append_damage_evidence_when_dispute_is_resolved_or_message_invalid(): void
    {
        [$po, , $itemA, $itemB] = $this->createInTransitOrderWithTwoItems();
        [, $rejectedItem, $dispute] = $this->submitDamageInspectionReport($po, $itemA, $itemB);

        // Validation error on short/empty message
        $this->actingAs($this->qcA)
            ->from("/inspeksi-qc/laporan-kerusakan/{$rejectedItem->uuid}")
            ->post("/inspeksi-qc/laporan-kerusakan/{$rejectedItem->uuid}/bukti", [
                'message' => 'abc',
            ])
            ->assertSessionHasErrors(['message']);

        // Mark dispute resolved (StatusSengketa::Selesai) -> immutable (BR-17)
        $dispute->update([
            'status' => StatusSengketa::Selesai->value,
            'tanggal_selesai' => now(),
        ]);

        $logsBefore = LogSengketa::where('sengketa_id', $dispute->id)->count();

        $this->actingAs($this->qcA)
            ->postJson("/inspeksi-qc/laporan-kerusakan/{$rejectedItem->uuid}/bukti", [
                'message' => 'Mencoba menambah catatan setelah sengketa ditutup.',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['message']);

        $this->assertSame($logsBefore, LogSengketa::where('sengketa_id', $dispute->id)->count());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unauthorizedEvidenceRolesProvider(): array
    {
        return [
            'purchasing cannot append damage evidence' => ['purchasing'],
            'manajemen cannot append damage evidence' => ['manajemen'],
            'vendor cannot append damage evidence' => ['vendor'],
            'superadmin cannot append damage evidence' => ['superadmin'],
        ];
    }

    #[DataProvider('unauthorizedEvidenceRolesProvider')]
    public function test_unauthorized_roles_receive_403_and_other_tenant_qc_receives_404_on_append_evidence(string $actorKey): void
    {
        [$po, , $itemA, $itemB] = $this->createInTransitOrderWithTwoItems();
        [, $rejectedItem] = $this->submitDamageInspectionReport($po, $itemA, $itemB);

        $actor = match ($actorKey) {
            'purchasing' => $this->purchasingA,
            'manajemen' => $this->manajemenA,
            'vendor' => $this->vendorUserA,
            'superadmin' => $this->superadmin,
        };

        $this->actingAs($actor)
            ->post("/inspeksi-qc/laporan-kerusakan/{$rejectedItem->uuid}/bukti", [
                'message' => 'Percobaan tambah bukti tanpa hak akses.',
            ])
            ->assertForbidden();

        // Another Tenant's QC Gudang receives 404 (Multi-Tenant Isolation — RULES §9.3)
        $this->actingAs($this->qcB)
            ->post("/inspeksi-qc/laporan-kerusakan/{$rejectedItem->uuid}/bukti", [
                'message' => 'Percobaan lintas tenant oleh QC Gudang lain.',
            ])
            ->assertNotFound();
    }
}
