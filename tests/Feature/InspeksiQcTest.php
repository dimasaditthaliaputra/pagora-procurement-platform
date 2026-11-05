<?php

declare(strict_types=1);

namespace Tests\Feature;

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
use App\Models\User;
use App\Models\Vendor;
use App\Services\PenerimaanBarangService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InspeksiQcTest extends TestCase
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
        $this->seed(RoleSeeder::class);
        Storage::fake(PenerimaanBarangService::STORAGE_DISK);

        $this->tenantA = PerusahaanBuyer::factory()->create([
            'company_name' => 'PT Pangan Utama Nusantara',
            'address' => 'Kawasan Pergudangan Rungkut Blok C-12, Surabaya',
        ]);

        $this->tenantB = PerusahaanBuyer::factory()->create([
            'company_name' => 'PT Industri Pangan Lain',
            'address' => 'Jl. Raya Cikarang No. 45',
        ]);

        $this->vendorA = Vendor::create([
            'company_name' => 'CV Sumber Hasil Bumi',
            'slug' => 'cv-sumber-hasil-bumi-'.Str::lower(Str::random(6)),
            'contact_email' => 'vendor-a@mitra.test',
            'phone' => '081234567891',
            'address' => 'Jl. Raya Malang No. 18',
            'verification_status' => 'approved',
            'is_active' => true,
        ]);

        $this->vendorB = Vendor::create([
            'company_name' => 'PT Agro Niaga Lain',
            'slug' => 'pt-agro-niaga-lain-'.Str::lower(Str::random(6)),
            'contact_email' => 'vendor-b@mitra.test',
            'phone' => '081234567892',
            'address' => 'Jl. Raya Kediri No. 20',
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
            'name' => 'Beras Kepala Super',
            'slug' => 'beras-kepala-super-'.Str::lower(Str::random(5)),
            'sku' => 'BRS-SUP-01',
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $this->commodityB = Komoditas::create([
            'name' => 'Gula Kristal Putih',
            'slug' => 'gula-kristal-putih-'.Str::lower(Str::random(5)),
            'sku' => 'GLA-KRS-02',
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

    /**
     * @return array{0: PurchaseOrder, 1: Pengiriman, 2: PoItem, 3: PoItem}
     */
    private function createInTransitOrderWithTwoItems(
        ?PerusahaanBuyer $tenant = null,
        ?Vendor $vendor = null,
        ?User $creator = null,
        ?User $reporter = null,
        StatusPurchaseOrder $status = StatusPurchaseOrder::DalamPengiriman,
    ): array {
        $tenant ??= $this->tenantA;
        $vendor ??= $this->vendorA;
        $creator ??= $this->purchasingA;
        $reporter ??= $this->vendorUserA;

        $po = PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-QC-'.Str::upper(Str::random(6)),
            'vendor_id' => $vendor->id,
            'created_by' => $creator->id,
            'perusahaan_buyer_id' => $tenant->id,
            'harga_pagu_acuan' => 16000,
            'status' => $status->value,
            'tanggal_po' => now()->toDateString(),
            'catatan' => 'Kirim ke Dermaga Gudang Utama',
            'locked_at' => now()->subHours(5),
        ]);

        $itemA = PoItem::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po->id,
            'komoditas_id' => $this->commodityA->id,
            'jumlah_pesanan' => 500,
            'harga_satuan_po' => 14500,
            'subtotal' => 7250000,
            'harga_tawar_vendor' => 14500,
            'status_item' => 'agreed',
        ]);

        $itemB = PoItem::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po->id,
            'komoditas_id' => $this->commodityB->id,
            'jumlah_pesanan' => 300,
            'harga_satuan_po' => 15000,
            'subtotal' => 4500000,
            'harga_tawar_vendor' => 15000,
            'status_item' => 'agreed',
        ]);

        $shipment = Pengiriman::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po->id,
            'route' => 'Darat',
            'carrier_name' => 'Ekspedisi Nusantara Jaya',
            'vehicle_plate_number' => 'L 8821 PG',
            'estimated_arrival_date' => now()->addDay()->toDateString(),
            'status' => StatusPengiriman::InTransit->value,
            'reported_by' => $reporter->id,
        ]);

        return [$po, $shipment, $itemA, $itemB];
    }

    public function test_qc_gudang_can_view_index_with_in_transit_queue_and_open_create_form(): void
    {
        [$po, $shipment] = $this->createInTransitOrderWithTwoItems();

        $this->actingAs($this->qcA)
            ->get('/inspeksi-qc')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('InspeksiQc/Index')
                ->where('can.create', true)
                ->where('statistics.in_transit_orders', 1)
                ->has('inspectable_orders', 1)
                ->where('inspectable_orders.0.uuid', $po->uuid)
                ->where('inspectable_orders.0.shipment.vehicle_plate_number', $shipment->vehicle_plate_number)
            );

        $this->actingAs($this->qcA)
            ->get("/inspeksi-qc/create?po={$po->uuid}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('InspeksiQc/Create')
                ->where('selected_po_uuid', $po->uuid)
                ->has('purchase_order_options', 1)
                ->where('purchase_order_options.0.items_count', 2)
            );
    }

    public function test_qc_gudang_can_approve_all_items_and_transition_po_to_diterima_gudang(): void
    {
        [$po, $shipment, $itemA, $itemB] = $this->createInTransitOrderWithTwoItems();

        $response = $this->actingAs($this->qcA)->post('/inspeksi-qc', [
            'purchase_order_uuid' => $po->uuid,
            'matched_plate_number' => 'l 8821 pg',
            'notes' => 'Seluruh karung kering, segel utuh, kualitas sangat baik.',
            'items' => [
                [
                    'po_item_uuid' => $itemA->uuid,
                    'status' => StatusItemPenerimaan::Accepted->value,
                    'received_quantity' => 500,
                    'notes' => '',
                ],
                [
                    'po_item_uuid' => $itemB->uuid,
                    'status' => StatusItemPenerimaan::Accepted->value,
                    'received_quantity' => 300,
                    'notes' => '',
                ],
            ],
        ]);

        $receipt = PenerimaanBarang::where('purchase_order_id', $po->id)->first();
        $this->assertNotNull($receipt);

        $response->assertRedirect(route('inspeksi-qc.show', $receipt->uuid));
        $response->assertSessionHas('success');

        $this->assertSame($shipment->id, $receipt->shipment_id);
        $this->assertSame($this->qcA->id, $receipt->inspected_by);
        $this->assertSame('L 8821 PG', $receipt->matched_plate_number);

        $this->assertSame(2, PenerimaanBarangItem::where('goods_receipt_id', $receipt->id)->count());
        $this->assertSame(
            2,
            PenerimaanBarangItem::where('goods_receipt_id', $receipt->id)
                ->where('status', StatusItemPenerimaan::Accepted->value)
                ->count()
        );

        $this->assertSame(StatusPengiriman::Arrived, $shipment->fresh()->status);
        $this->assertSame(StatusPurchaseOrder::DiterimaGudang, $po->fresh()->status);
        $this->assertSame(0, Sengketa::count());

        // Notifications sent to Vendor, Owner, and Purchasing (excluding QC actor)
        $this->assertSame(1, Notification::where('user_id', $this->vendorUserA->id)->where('type', 'goods_receipt')->count());
        $this->assertSame(1, Notification::where('user_id', $this->ownerA->id)->where('type', 'goods_receipt')->count());
        $this->assertSame(1, Notification::where('user_id', $this->purchasingA->id)->where('type', 'goods_receipt')->count());
        $this->assertSame(0, Notification::where('user_id', $this->qcA->id)->count());
    }

    public function test_owner_can_also_perform_qc_inspection(): void
    {
        [$po, , $itemA, $itemB] = $this->createInTransitOrderWithTwoItems();

        $this->actingAs($this->ownerA)->post('/inspeksi-qc', [
            'purchase_order_uuid' => $po->uuid,
            'matched_plate_number' => 'L 8821 PG',
            'items' => [
                [
                    'po_item_uuid' => $itemA->uuid,
                    'status' => StatusItemPenerimaan::Accepted->value,
                    'received_quantity' => 500,
                ],
                [
                    'po_item_uuid' => $itemB->uuid,
                    'status' => StatusItemPenerimaan::Accepted->value,
                    'received_quantity' => 300,
                ],
            ],
        ])->assertRedirect();

        $this->assertSame(StatusPurchaseOrder::DiterimaGudang, $po->fresh()->status);
    }

    public function test_rejecting_item_saves_immutable_photos_opens_dispute_log_and_marks_po_partially_received(): void
    {
        [$po, $shipment, $itemA, $itemB] = $this->createInTransitOrderWithTwoItems();

        $photo1 = UploadedFile::fake()->image('karung-sobek-1.jpg', 800, 600)->size(600);
        $photo2 = UploadedFile::fake()->image('gula-lembap-2.png', 800, 600)->size(750);

        $response = $this->actingAs($this->qcA)->post('/inspeksi-qc', [
            'purchase_order_uuid' => $po->uuid,
            'matched_plate_number' => 'L 8821 PG',
            'notes' => 'Pemeriksaan di dermaga 2: beras sesuai, gula ditolak.',
            'items' => [
                [
                    'po_item_uuid' => $itemA->uuid,
                    'status' => StatusItemPenerimaan::Accepted->value,
                    'received_quantity' => 500,
                    'notes' => '',
                ],
                [
                    'po_item_uuid' => $itemB->uuid,
                    'status' => StatusItemPenerimaan::Rejected->value,
                    'received_quantity' => 0,
                    'notes' => 'Sebanyak 12 karung gula basah terkena rembesan air dan menggumpal.',
                    'photos' => [$photo1, $photo2],
                ],
            ],
        ]);

        $receipt = PenerimaanBarang::where('purchase_order_id', $po->id)->firstOrFail();
        $response->assertRedirect(route('inspeksi-qc.show', $receipt->uuid));

        // PO becomes PartiallyReceived (NOT cancelled — BR-10) & shipment becomes Arrived
        $this->assertSame(StatusPurchaseOrder::PartiallyReceived, $po->fresh()->status);
        $this->assertNotSame(StatusPurchaseOrder::Dibatalkan, $po->fresh()->status);
        $this->assertSame(StatusPengiriman::Arrived, $shipment->fresh()->status);

        $acceptedReceiptItem = PenerimaanBarangItem::where('goods_receipt_id', $receipt->id)
            ->where('purchase_order_item_id', $itemA->id)
            ->firstOrFail();
        $this->assertSame(StatusItemPenerimaan::Accepted, $acceptedReceiptItem->status);
        $this->assertNull($acceptedReceiptItem->rejection_photos);

        $rejectedReceiptItem = PenerimaanBarangItem::where('goods_receipt_id', $receipt->id)
            ->where('purchase_order_item_id', $itemB->id)
            ->firstOrFail();
        $this->assertSame(StatusItemPenerimaan::Rejected, $rejectedReceiptItem->status);
        $this->assertIsArray($rejectedReceiptItem->rejection_photos);
        $this->assertCount(2, $rejectedReceiptItem->rejection_photos);

        foreach ($rejectedReceiptItem->rejection_photos as $storedPath) {
            Storage::disk(PenerimaanBarangService::STORAGE_DISK)->assertExists($storedPath);
            $this->assertStringNotContainsString('karung-sobek', $storedPath);
        }

        // Dispute & immutable Dispute Logs recorded for rejected item
        $dispute = Sengketa::where('purchase_order_id', $po->id)
            ->where('purchase_order_item_id', $itemB->id)
            ->first();
        $this->assertNotNull($dispute);
        $this->assertSame('qc_rejected', $dispute->trigger_type);
        $this->assertSame(StatusSengketa::Terbuka, $dispute->status);
        $this->assertSame('Sebanyak 12 karung gula basah terkena rembesan air dan menggumpal.', $dispute->reason);

        $logs = LogSengketa::where('sengketa_id', $dispute->id)->get();
        $this->assertCount(2, $logs);
        $this->assertSame('status_change', $logs[0]->type);
        $this->assertSame($rejectedReceiptItem->rejection_photos[0], $logs[0]->file_bukti);
        $this->assertSame('evidence', $logs[1]->type);
        $this->assertSame($rejectedReceiptItem->rejection_photos[1], $logs[1]->file_bukti);

        // Manajemen also notified on rejection/dispute
        $this->assertSame(1, Notification::where('user_id', $this->manajemenA->id)->where('type', 'dispute')->count());
        $this->assertSame(1, Notification::where('user_id', $this->vendorUserA->id)->where('type', 'dispute')->count());

        // Vendor can view inspection result & stream rejection photo, without seeing internal `id` or `harga_pagu_acuan` (BR-02)
        $this->actingAs($this->vendorUserA)
            ->get("/inspeksi-qc/{$receipt->uuid}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('InspeksiQc/Show')
                ->where('receipt.data.uuid', $receipt->uuid)
                ->where('receipt.data.overall_result', 'rejected')
                ->where('receipt.data.accepted_count', 1)
                ->where('receipt.data.rejected_count', 1)
                ->missing('receipt.data.id')
                ->missing('receipt.data.purchase_order.harga_pagu_acuan')
            );

        $this->actingAs($this->vendorUserA)
            ->get("/inspeksi-qc/item/{$rejectedReceiptItem->uuid}/foto/0")
            ->assertOk();

        // Immutability check on goods_receipt_items (BR-17)
        $this->expectException(\LogicException::class);
        $rejectedReceiptItem->update(['notes' => 'Diubah paksa']);
    }

    public function test_reshipment_after_partial_rejection_inspects_remaining_item_and_completes_receipt(): void
    {
        [$po, , $itemA, $itemB] = $this->createInTransitOrderWithTwoItems();

        // First inspection: itemA accepted, itemB rejected
        $this->actingAs($this->qcA)->post('/inspeksi-qc', [
            'purchase_order_uuid' => $po->uuid,
            'matched_plate_number' => 'L 8821 PG',
            'items' => [
                [
                    'po_item_uuid' => $itemA->uuid,
                    'status' => StatusItemPenerimaan::Accepted->value,
                    'received_quantity' => 500,
                ],
                [
                    'po_item_uuid' => $itemB->uuid,
                    'status' => StatusItemPenerimaan::Rejected->value,
                    'received_quantity' => 0,
                    'notes' => 'Kemasan gula basah.',
                    'photos' => [UploadedFile::fake()->image('bukti-awal.jpg')],
                ],
            ],
        ])->assertRedirect();

        $this->assertSame(StatusPurchaseOrder::PartiallyReceived, $po->fresh()->status);
        $dispute = Sengketa::where('purchase_order_id', $po->id)->firstOrFail();

        // Vendor reports reshipment (UC-14)
        $this->actingAs($this->vendorUserA)->post('/pengiriman', [
            'purchase_order_uuid' => $po->uuid,
            'route' => 'Darat',
            'carrier_name' => 'Armada Pengganti Cepat',
            'vehicle_plate_number' => 'N 4455 XY',
            'estimated_arrival_date' => now()->addDay()->toDateString(),
        ])->assertRedirect();

        $this->assertSame(StatusPurchaseOrder::DalamPengiriman, $po->fresh()->status);

        // QC inspects only the remaining unaccepted item (itemB) and approves it
        $this->actingAs($this->qcA)->post('/inspeksi-qc', [
            'purchase_order_uuid' => $po->uuid,
            'matched_plate_number' => 'N 4455 XY',
            'items' => [
                [
                    'po_item_uuid' => $itemB->uuid,
                    'status' => StatusItemPenerimaan::Accepted->value,
                    'received_quantity' => 300,
                ],
            ],
        ])->assertRedirect();

        $this->assertSame(StatusPurchaseOrder::DiterimaGudang, $po->fresh()->status);
        $this->assertTrue(
            LogSengketa::where('sengketa_id', $dispute->id)
                ->where('type', 'note')
                ->exists()
        );
    }

    public function test_validation_rejects_missing_notes_or_photos_or_invalid_files_on_rejected_item(): void
    {
        [$po, , $itemA, $itemB] = $this->createInTransitOrderWithTwoItems();

        // 1. Rejected without notes & without photos, and accepted with 0 quantity
        $this->actingAs($this->qcA)->postJson('/inspeksi-qc', [
            'purchase_order_uuid' => $po->uuid,
            'items' => [
                [
                    'po_item_uuid' => $itemA->uuid,
                    'status' => StatusItemPenerimaan::Accepted->value,
                    'received_quantity' => 0,
                ],
                [
                    'po_item_uuid' => $itemB->uuid,
                    'status' => StatusItemPenerimaan::Rejected->value,
                    'received_quantity' => 0,
                    'notes' => '   ',
                ],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors([
                'items.0.received_quantity',
                'items.1.notes',
                'items.1.photos',
            ]);

        // 2. Photo > 5 MB
        $oversizedPhoto = UploadedFile::fake()->image('terlalu-besar.jpg')->size(5300);
        $this->actingAs($this->qcA)->postJson('/inspeksi-qc', [
            'purchase_order_uuid' => $po->uuid,
            'items' => [
                [
                    'po_item_uuid' => $itemA->uuid,
                    'status' => StatusItemPenerimaan::Accepted->value,
                    'received_quantity' => 500,
                ],
                [
                    'po_item_uuid' => $itemB->uuid,
                    'status' => StatusItemPenerimaan::Rejected->value,
                    'received_quantity' => 0,
                    'notes' => 'Barang rusak.',
                    'photos' => [$oversizedPhoto],
                ],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['items.1.photos.0']);

        // 3. Non-image file (.pdf)
        $pdfFile = UploadedFile::fake()->create('dokumen.pdf', 200, 'application/pdf');
        $this->actingAs($this->qcA)->postJson('/inspeksi-qc', [
            'purchase_order_uuid' => $po->uuid,
            'items' => [
                [
                    'po_item_uuid' => $itemA->uuid,
                    'status' => StatusItemPenerimaan::Accepted->value,
                    'received_quantity' => 500,
                ],
                [
                    'po_item_uuid' => $itemB->uuid,
                    'status' => StatusItemPenerimaan::Rejected->value,
                    'received_quantity' => 0,
                    'notes' => 'Barang rusak.',
                    'photos' => [$pdfFile],
                ],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['items.1.photos.0']);

        // 4. Incomplete item list (only 1 of 2 items submitted)
        $this->actingAs($this->qcA)->postJson('/inspeksi-qc', [
            'purchase_order_uuid' => $po->uuid,
            'items' => [
                [
                    'po_item_uuid' => $itemA->uuid,
                    'status' => StatusItemPenerimaan::Accepted->value,
                    'received_quantity' => 500,
                ],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['items']);

        $this->assertSame(0, PenerimaanBarang::count());
        $this->assertSame(StatusPurchaseOrder::DalamPengiriman, $po->fresh()->status);
    }

    /**
     * @return array<string, array{StatusPurchaseOrder}>
     */
    public static function nonInspectablePoStatuses(): array
    {
        return [
            'draft' => [StatusPurchaseOrder::Draft],
            'negosiasi' => [StatusPurchaseOrder::Negosiasi],
            'disepakati' => [StatusPurchaseOrder::Disepakati],
            'partially_received' => [StatusPurchaseOrder::PartiallyReceived],
            'diterima_gudang' => [StatusPurchaseOrder::DiterimaGudang],
            'ditagih' => [StatusPurchaseOrder::Ditagih],
            'lunas' => [StatusPurchaseOrder::Lunas],
            'dibatalkan' => [StatusPurchaseOrder::Dibatalkan],
        ];
    }

    #[DataProvider('nonInspectablePoStatuses')]
    public function test_cannot_inspect_po_not_in_dalam_pengiriman_status(StatusPurchaseOrder $status): void
    {
        [$po, , $itemA, $itemB] = $this->createInTransitOrderWithTwoItems(status: $status);

        $this->actingAs($this->qcA)->postJson('/inspeksi-qc', [
            'purchase_order_uuid' => $po->uuid,
            'items' => [
                [
                    'po_item_uuid' => $itemA->uuid,
                    'status' => StatusItemPenerimaan::Accepted->value,
                    'received_quantity' => 500,
                ],
                [
                    'po_item_uuid' => $itemB->uuid,
                    'status' => StatusItemPenerimaan::Accepted->value,
                    'received_quantity' => 300,
                ],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['purchase_order_uuid']);

        $this->assertSame(0, PenerimaanBarang::count());
    }

    public function test_non_qc_and_non_owner_roles_cannot_submit_inspection(): void
    {
        [$po, , $itemA, $itemB] = $this->createInTransitOrderWithTwoItems();

        $payload = [
            'purchase_order_uuid' => $po->uuid,
            'items' => [
                [
                    'po_item_uuid' => $itemA->uuid,
                    'status' => StatusItemPenerimaan::Accepted->value,
                    'received_quantity' => 500,
                ],
                [
                    'po_item_uuid' => $itemB->uuid,
                    'status' => StatusItemPenerimaan::Accepted->value,
                    'received_quantity' => 300,
                ],
            ],
        ];

        foreach ([$this->purchasingA, $this->manajemenA, $this->vendorUserA, $this->superadmin] as $unauthorizedUser) {
            $this->actingAs($unauthorizedUser)->get('/inspeksi-qc/create')->assertForbidden();
            $this->actingAs($unauthorizedUser)->postJson('/inspeksi-qc', $payload)->assertForbidden();
        }

        $this->assertSame(0, PenerimaanBarang::count());
    }

    public function test_multi_tenant_and_vendor_isolation_returns_404_for_other_company_data(): void
    {
        [$poA, , $itemA, $itemB] = $this->createInTransitOrderWithTwoItems();

        // Tenant B QC cannot preselect or submit inspection for Tenant A's PO -> 404
        $this->actingAs($this->qcB)
            ->get("/inspeksi-qc/create?po={$poA->uuid}")
            ->assertNotFound();

        $this->actingAs($this->qcB)
            ->postJson('/inspeksi-qc', [
                'purchase_order_uuid' => $poA->uuid,
                'items' => [
                    [
                        'po_item_uuid' => $itemA->uuid,
                        'status' => StatusItemPenerimaan::Accepted->value,
                        'received_quantity' => 500,
                    ],
                    [
                        'po_item_uuid' => $itemB->uuid,
                        'status' => StatusItemPenerimaan::Accepted->value,
                        'received_quantity' => 300,
                    ],
                ],
            ])
            ->assertNotFound();

        // Tenant A QC records inspection with a rejected item photo
        $this->actingAs($this->qcA)->post('/inspeksi-qc', [
            'purchase_order_uuid' => $poA->uuid,
            'items' => [
                [
                    'po_item_uuid' => $itemA->uuid,
                    'status' => StatusItemPenerimaan::Accepted->value,
                    'received_quantity' => 500,
                ],
                [
                    'po_item_uuid' => $itemB->uuid,
                    'status' => StatusItemPenerimaan::Rejected->value,
                    'received_quantity' => 0,
                    'notes' => 'Kualitas tidak sesuai.',
                    'photos' => [UploadedFile::fake()->image('bukti-tenant-a.jpg')],
                ],
            ],
        ])->assertRedirect();

        $receiptA = PenerimaanBarang::where('purchase_order_id', $poA->id)->firstOrFail();
        $rejectedItemA = PenerimaanBarangItem::where('goods_receipt_id', $receiptA->id)
            ->where('purchase_order_item_id', $itemB->id)
            ->firstOrFail();

        // Tenant B QC & Vendor B get 404 on receipt detail and photo endpoint
        $this->actingAs($this->qcB)->get("/inspeksi-qc/{$receiptA->uuid}")->assertNotFound();
        $this->actingAs($this->qcB)->get("/inspeksi-qc/item/{$rejectedItemA->uuid}/foto/0")->assertNotFound();

        $this->actingAs($this->vendorUserB)->get("/inspeksi-qc/{$receiptA->uuid}")->assertNotFound();
        $this->actingAs($this->vendorUserB)->get("/inspeksi-qc/item/{$rejectedItemA->uuid}/foto/0")->assertNotFound();

        // Superadmin gets 403 on transaction endpoints
        $this->actingAs($this->superadmin)->get("/inspeksi-qc/{$receiptA->uuid}")->assertForbidden();
        $this->actingAs($this->superadmin)->get("/inspeksi-qc/item/{$rejectedItemA->uuid}/foto/0")->assertForbidden();
    }

    public function test_index_limits_in_transit_summary_to_four_newest_desc_and_view_all_paginates_with_filters(): void
    {
        // Create 6 In Transit POs for Tenant A with distinct dates (oldest -> newest)
        $createdPos = [];
        for ($day = 1; $day <= 6; $day++) {
            [$po, $shipment] = $this->createInTransitOrderWithTwoItems();
            $po->update([
                'nomor_po' => sprintf('PO-QUEUE-%02d', $day),
                'tanggal_po' => now()->subDays(10 - $day)->toDateString(),
            ]);
            $shipment->update([
                'route' => $day === 6 ? 'Kereta Api' : 'Darat',
                'carrier_name' => $day === 6 ? 'GoTrain Logistik' : 'Ekspedisi Nusantara Jaya',
            ]);
            $createdPos[$day] = $po->fresh();
        }

        // Create 1 In Transit PO for Tenant B to verify multi-tenant isolation
        $this->createInTransitOrderWithTwoItems(
            tenant: $this->tenantB,
            vendor: $this->vendorB,
            creator: $this->qcB,
            reporter: $this->vendorUserB,
        );

        // 1. Summary on /inspeksi-qc returns ONLY the 4 newest DESC while statistics.in_transit_orders is 6
        $this->actingAs($this->qcA)
            ->get('/inspeksi-qc')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('InspeksiQc/Index')
                ->where('statistics.in_transit_orders', 6)
                ->has('inspectable_orders', 4)
                ->where('inspectable_orders.0.uuid', $createdPos[6]->uuid)
                ->where('inspectable_orders.1.uuid', $createdPos[5]->uuid)
                ->where('inspectable_orders.2.uuid', $createdPos[4]->uuid)
                ->where('inspectable_orders.3.uuid', $createdPos[3]->uuid)
            );

        // Create form still lists all 6 inspectable POs so older In Transit POs remain selectable
        $this->actingAs($this->qcA)
            ->get("/inspeksi-qc/create?po={$createdPos[1]->uuid}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('InspeksiQc/Create')
                ->where('selected_po_uuid', $createdPos[1]->uuid)
                ->has('purchase_order_options', 6)
            );

        // 2. Dedicated "View All" page (/inspeksi-qc/pesanan-in-transit) paginates cards cleanly
        $this->actingAs($this->qcA)
            ->get('/inspeksi-qc/pesanan-in-transit?per_page=4&page=1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('InspeksiQc/InTransitOrders')
                ->where('orders.meta.total', 6)
                ->where('orders.meta.last_page', 2)
                ->has('orders.data', 4)
                ->where('orders.data.0.uuid', $createdPos[6]->uuid)
            );

        $this->actingAs($this->qcA)
            ->get('/inspeksi-qc/pesanan-in-transit?per_page=4&page=2')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('InspeksiQc/InTransitOrders')
                ->where('orders.meta.current_page', 2)
                ->has('orders.data', 2)
                ->where('orders.data.0.uuid', $createdPos[2]->uuid)
                ->where('orders.data.1.uuid', $createdPos[1]->uuid)
            );

        // Filter by route & search on "View All"
        $this->actingAs($this->qcA)
            ->get('/inspeksi-qc/pesanan-in-transit?route=Kereta+Api&search=GoTrain')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('InspeksiQc/InTransitOrders')
                ->has('orders.data', 1)
                ->where('orders.data.0.uuid', $createdPos[6]->uuid)
            );

        // Unauthorized roles (Purchasing, Manajemen, Vendor, Superadmin) receive 403 on "View All"
        $this->actingAs($this->purchasingA)->get('/inspeksi-qc/pesanan-in-transit')->assertForbidden();
        $this->actingAs($this->manajemenA)->get('/inspeksi-qc/pesanan-in-transit')->assertForbidden();
        $this->actingAs($this->vendorUserA)->get('/inspeksi-qc/pesanan-in-transit')->assertForbidden();
        $this->actingAs($this->superadmin)->get('/inspeksi-qc/pesanan-in-transit')->assertForbidden();
    }
}
