<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CreditNoteStatus;
use App\Enums\PemicuSengketa;
use App\Enums\PurchaseReturnStatus;
use App\Enums\RoleName;
use App\Enums\StatusItemPenerimaan;
use App\Enums\StatusKerjaSama;
use App\Enums\StatusPengiriman;
use App\Enums\StatusPurchaseOrder;
use App\Enums\StatusSengketa;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\KerjaSama;
use App\Models\Komoditas;
use App\Models\PenerimaanBarang;
use App\Models\PenerimaanBarangItem;
use App\Models\Pengiriman;
use App\Models\PerusahaanBuyer;
use App\Models\PoItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\Role;
use App\Models\Sengketa;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Dispute settlement: reshipment, return, credit note, Vendor confirmation, resolve, and invoice
 * re-verification with credit notes (UC-18, FR-38, FR-40..42, BR-13, BR-15..17, BR-26, BR-27).
 */
class DisputeResolutionTest extends TestCase
{
    use RefreshDatabase;

    private PerusahaanBuyer $tenantA;

    private PerusahaanBuyer $tenantB;

    private Vendor $vendorA;

    private Vendor $vendorB;

    private Komoditas $commodity;

    private User $ownerA;

    private User $manajemenA;

    private User $purchasingA;

    private User $qcA;

    private User $manajemenB;

    private User $vendorUserA;

    private User $vendorUserB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);

        $this->tenantA = PerusahaanBuyer::factory()->create();
        $this->tenantB = PerusahaanBuyer::factory()->create();
        $this->vendorA = $this->makeVendor('CV Sumber Tani');
        $this->vendorB = $this->makeVendor('PT Agro Abadi');

        $this->ownerA = $this->makeUser(RoleName::Owner, $this->tenantA->id, null);
        $this->manajemenA = $this->makeUser(RoleName::Manajemen, $this->tenantA->id, null);
        $this->purchasingA = $this->makeUser(RoleName::Purchasing, $this->tenantA->id, null);
        $this->qcA = $this->makeUser(RoleName::QcGudang, $this->tenantA->id, null);
        $this->manajemenB = $this->makeUser(RoleName::Manajemen, $this->tenantB->id, null);
        $this->vendorUserA = $this->makeUser(RoleName::Vendor, null, $this->vendorA->id);
        $this->vendorUserB = $this->makeUser(RoleName::Vendor, null, $this->vendorB->id);

        KerjaSama::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'vendor_id' => $this->vendorA->id,
            'requested_by' => 'tenant',
            'status' => StatusKerjaSama::Agreed->value,
        ]);

        $this->commodity = Komoditas::create([
            'name' => 'Beras Medium',
            'slug' => 'beras-medium-'.Str::lower(Str::random(5)),
            'sku' => 'BRS-01',
            'unit' => 'kg',
            'is_active' => true,
        ]);
    }

    public function test_owner_issues_credit_note_and_vendor_confirms_it(): void
    {
        ['dispute' => $dispute, 'invoice' => $invoice] = $this->invoiceDispute();

        $this->actingAs($this->ownerA)
            ->post("/log-sengketa/{$dispute->uuid}/penyelesaian", [
                'resolution_type' => 'credit_note',
                'resolution_notes' => 'Selisih harga satuan Rp 1.000/kg dipotong dari tagihan.',
                'invoice_uuid' => $invoice->uuid,
                'amount' => '1000000',
            ])
            ->assertRedirect("/log-sengketa/{$dispute->uuid}");

        $creditNote = CreditNote::query()->sole();
        $this->assertSame(CreditNoteStatus::AwaitingVendorConfirmation, $creditNote->status);
        $this->assertSame('CN-'.now()->format('Y').'-0001', $creditNote->credit_note_number);
        $this->assertSame(StatusSengketa::AwaitingResponse, $dispute->fresh()->status);
        $this->assertDatabaseHas('dispute_logs', ['dispute_id' => $dispute->id, 'type' => 'resolution']);

        $this->actingAs($this->vendorUserA)
            ->post("/log-sengketa/{$dispute->uuid}/credit-note/{$creditNote->uuid}/keputusan", ['decision' => 'accept'])
            ->assertRedirect("/log-sengketa/{$dispute->uuid}");

        $creditNote->refresh();
        $dispute->refresh();
        $this->assertSame(CreditNoteStatus::Confirmed, $creditNote->status);
        $this->assertSame($this->vendorUserA->id, $creditNote->vendor_confirmed_by);
        $this->assertSame(StatusSengketa::InMediation, $dispute->status);
        $this->assertNotNull($dispute->vendor_confirmed_at);
    }

    public function test_credit_note_cannot_exceed_remaining_invoice_value(): void
    {
        ['dispute' => $dispute, 'invoice' => $invoice] = $this->invoiceDispute();

        $this->actingAs($this->ownerA)
            ->postJson("/log-sengketa/{$dispute->uuid}/penyelesaian", [
                'resolution_type' => 'credit_note',
                'resolution_notes' => 'Potongan melebihi nilai tagihan.',
                'invoice_uuid' => $invoice->uuid,
                'amount' => '99000000',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');

        $this->assertDatabaseCount('credit_notes', 0);
    }

    public function test_proposal_validation_requires_type_specific_fields(): void
    {
        ['dispute' => $dispute] = $this->invoiceDispute();

        $this->actingAs($this->ownerA)
            ->postJson("/log-sengketa/{$dispute->uuid}/penyelesaian", ['resolution_type' => 'credit_note'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['resolution_notes', 'invoice_uuid', 'amount']);

        $this->actingAs($this->ownerA)
            ->postJson("/log-sengketa/{$dispute->uuid}/penyelesaian", ['resolution_type' => 'return', 'resolution_notes' => 'Retur barang.'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $this->actingAs($this->ownerA)
            ->postJson("/log-sengketa/{$dispute->uuid}/penyelesaian", ['resolution_type' => 'refund', 'resolution_notes' => 'Tidak dikenal.'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('resolution_type');
    }

    public function test_only_one_document_may_await_the_vendor(): void
    {
        ['dispute' => $dispute, 'invoice' => $invoice] = $this->invoiceDispute();
        $payload = [
            'resolution_type' => 'credit_note',
            'resolution_notes' => 'Potongan tagihan tahap pertama.',
            'invoice_uuid' => $invoice->uuid,
            'amount' => '500000',
        ];

        $this->actingAs($this->ownerA)->postJson("/log-sengketa/{$dispute->uuid}/penyelesaian", $payload)->assertOk();
        $this->actingAs($this->ownerA)
            ->postJson("/log-sengketa/{$dispute->uuid}/penyelesaian", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('resolution_type');
    }

    public function test_vendor_rejects_credit_note_with_reason(): void
    {
        ['dispute' => $dispute] = $this->invoiceDispute();
        $creditNote = $this->awaitingCreditNote($dispute);

        $this->actingAs($this->vendorUserA)
            ->postJson("/log-sengketa/{$dispute->uuid}/credit-note/{$creditNote->uuid}/keputusan", ['decision' => 'reject'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->actingAs($this->vendorUserA)
            ->postJson("/log-sengketa/{$dispute->uuid}/credit-note/{$creditNote->uuid}/keputusan", [
                'decision' => 'reject',
                'reason' => 'Nominal potongan tidak sesuai kesepakatan lisan.',
            ])
            ->assertOk();

        $this->assertSame(CreditNoteStatus::Rejected, $creditNote->fresh()->status);
        $this->assertSame(StatusSengketa::InMediation, $dispute->fresh()->status);
        $this->assertNull($dispute->fresh()->vendor_confirmed_at);
    }

    public function test_resolve_requires_vendor_confirmation_then_becomes_immutable(): void
    {
        ['dispute' => $dispute, 'invoice' => $invoice] = $this->invoiceDispute();
        $creditNote = $this->awaitingCreditNote($dispute);

        $this->actingAs($this->manajemenA)
            ->postJson("/log-sengketa/{$dispute->uuid}/selesai")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('resolution');

        $this->actingAs($this->vendorUserA)
            ->postJson("/log-sengketa/{$dispute->uuid}/credit-note/{$creditNote->uuid}/keputusan", ['decision' => 'accept'])
            ->assertOk();

        $this->actingAs($this->manajemenA)
            ->postJson("/log-sengketa/{$dispute->uuid}/selesai", ['note' => 'Potongan sudah disepakati kedua pihak.'])
            ->assertOk();

        $dispute->refresh();
        $this->assertSame(StatusSengketa::Resolved, $dispute->status);
        $this->assertSame($this->manajemenA->id, $dispute->resolved_by);
        $this->assertNotNull($dispute->resolved_at);

        // BR-17: nothing changes on a resolved dispute any more.
        $this->actingAs($this->ownerA)
            ->postJson("/log-sengketa/{$dispute->uuid}/penyelesaian", [
                'resolution_type' => 'credit_note',
                'resolution_notes' => 'Potongan tambahan setelah selesai.',
                'invoice_uuid' => $invoice->uuid,
                'amount' => '1000',
            ])
            ->assertForbidden();
        $this->actingAs($this->ownerA)->postJson("/log-sengketa/{$dispute->uuid}/selesai")->assertForbidden();
    }

    public function test_settlement_actions_follow_the_policy_matrix(): void
    {
        ['dispute' => $dispute, 'invoice' => $invoice] = $this->invoiceDispute();
        $payload = [
            'resolution_type' => 'credit_note',
            'resolution_notes' => 'Potongan tagihan atas selisih harga.',
            'invoice_uuid' => $invoice->uuid,
            'amount' => '1000',
        ];

        // Purchasing may open & view disputes but not settle them; the Vendor never settles (BR-15).
        $this->actingAs($this->purchasingA)->postJson("/log-sengketa/{$dispute->uuid}/penyelesaian", $payload)->assertForbidden();
        $this->actingAs($this->vendorUserA)->postJson("/log-sengketa/{$dispute->uuid}/penyelesaian", $payload)->assertForbidden();
        $this->actingAs($this->vendorUserA)->postJson("/log-sengketa/{$dispute->uuid}/selesai")->assertForbidden();
        $this->actingAs($this->qcA)->postJson("/log-sengketa/{$dispute->uuid}/penyelesaian", $payload)->assertForbidden();
        $this->actingAs($this->purchasingA)->get("/log-sengketa/{$dispute->uuid}/penyelesaian")->assertForbidden();
        $this->actingAs($this->ownerA)->get("/log-sengketa/{$dispute->uuid}/penyelesaian")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('LogSengketa/Penyelesaian')
                ->has('form.creditable_invoices', 1)
                ->where('form.creditable_invoices.0.uuid', $invoice->uuid));

        // Other parties do not see the dispute at all (RULES §9.3).
        $this->actingAs($this->manajemenB)->postJson("/log-sengketa/{$dispute->uuid}/penyelesaian", $payload)->assertNotFound();
        $this->actingAs($this->vendorUserB)->get("/log-sengketa/{$dispute->uuid}")->assertNotFound();

        $creditNote = $this->awaitingCreditNote($dispute);
        $this->actingAs($this->vendorUserB)
            ->postJson("/log-sengketa/{$dispute->uuid}/credit-note/{$creditNote->uuid}/keputusan", ['decision' => 'accept'])
            ->assertNotFound();
        $this->actingAs($this->ownerA)
            ->postJson("/log-sengketa/{$dispute->uuid}/credit-note/{$creditNote->uuid}/keputusan", ['decision' => 'accept'])
            ->assertNotFound();
        $this->assertSame(CreditNoteStatus::AwaitingVendorConfirmation, $creditNote->fresh()->status);
    }

    public function test_return_flow_with_vendor_confirmation_and_completion(): void
    {
        ['dispute' => $dispute, 'rejectedLine' => $line] = $this->qcDispute();

        $this->actingAs($this->ownerA)
            ->postJson("/log-sengketa/{$dispute->uuid}/penyelesaian", [
                'resolution_type' => 'return',
                'resolution_notes' => 'Barang basah dikembalikan ke Vendor.',
                'items' => [['receipt_item_uuid' => $line->uuid, 'quantity' => '250']],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.quantity');

        $this->actingAs($this->ownerA)
            ->postJson("/log-sengketa/{$dispute->uuid}/penyelesaian", [
                'resolution_type' => 'return',
                'resolution_notes' => 'Barang basah dikembalikan ke Vendor.',
                'items' => [['receipt_item_uuid' => $line->uuid, 'quantity' => '200', 'notes' => 'Karung basah']],
            ])
            ->assertOk();

        $purchaseReturn = PurchaseReturn::query()->with('items')->sole();
        $this->assertSame(PurchaseReturnStatus::AwaitingVendorConfirmation, $purchaseReturn->status);
        $this->assertEquals(200, (float) $purchaseReturn->items->sole()->quantity);

        // Completing before the Vendor confirmed is not allowed.
        $this->actingAs($this->vendorUserA)
            ->postJson("/log-sengketa/{$dispute->uuid}/retur/{$purchaseReturn->uuid}/selesai")
            ->assertForbidden();

        $this->actingAs($this->vendorUserA)
            ->postJson("/log-sengketa/{$dispute->uuid}/retur/{$purchaseReturn->uuid}/keputusan", ['decision' => 'accept'])
            ->assertOk();
        $this->assertSame(PurchaseReturnStatus::Confirmed, $purchaseReturn->fresh()->status);

        $this->actingAs($this->manajemenA)->postJson("/log-sengketa/{$dispute->uuid}/selesai")->assertOk();
        $this->assertSame(StatusSengketa::Resolved, $dispute->fresh()->status);

        // The goods may arrive back after the dispute is resolved: `confirmed` → `completed`.
        $this->actingAs($this->vendorUserA)
            ->postJson("/log-sengketa/{$dispute->uuid}/retur/{$purchaseReturn->uuid}/selesai")
            ->assertOk();
        $this->assertSame(PurchaseReturnStatus::Completed, $purchaseReturn->fresh()->status);
        $this->assertDatabaseHas('dispute_logs', ['dispute_id' => $dispute->id, 'user_id' => $this->vendorUserA->id, 'type' => 'resolution']);
    }

    public function test_reshipment_needs_vendor_acceptance(): void
    {
        ['dispute' => $dispute] = $this->qcDispute();

        $this->actingAs($this->ownerA)
            ->postJson("/log-sengketa/{$dispute->uuid}/penyelesaian", [
                'resolution_type' => 'reshipment',
                'resolution_notes' => 'Vendor mengirim ulang 200 kg beras pengganti.',
            ])
            ->assertOk();

        $this->actingAs($this->vendorUserA)
            ->postJson("/log-sengketa/{$dispute->uuid}/pengiriman-ulang/keputusan", [
                'decision' => 'reject',
                'reason' => 'Stok beras medium sedang kosong.',
            ])
            ->assertOk();
        $dispute->refresh();
        $this->assertNull($dispute->resolution_type);
        $this->assertSame(StatusSengketa::InMediation, $dispute->status);

        $this->actingAs($this->ownerA)
            ->postJson("/log-sengketa/{$dispute->uuid}/penyelesaian", [
                'resolution_type' => 'reshipment',
                'resolution_notes' => 'Pengiriman ulang minggu depan.',
            ])
            ->assertOk();
        $this->actingAs($this->vendorUserA)
            ->postJson("/log-sengketa/{$dispute->uuid}/pengiriman-ulang/keputusan", ['decision' => 'accept'])
            ->assertOk();

        $this->actingAs($this->ownerA)->postJson("/log-sengketa/{$dispute->uuid}/selesai")->assertOk();
        $this->assertSame(StatusSengketa::Resolved, $dispute->fresh()->status);
        // The PO status is untouched; the Vendor ships again from Diterima Sebagian (BR-14).
        $this->assertSame(StatusPurchaseOrder::PartiallyReceived, $dispute->purchaseOrder()->first()->status);
    }

    public function test_reshipment_is_rejected_when_po_has_no_rejected_goods(): void
    {
        ['dispute' => $dispute] = $this->invoiceDispute();

        $this->actingAs($this->ownerA)
            ->postJson("/log-sengketa/{$dispute->uuid}/penyelesaian", [
                'resolution_type' => 'reshipment',
                'resolution_notes' => 'Kirim ulang barang.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('resolution_type');
    }

    public function test_vendor_response_and_request_for_response_move_the_status(): void
    {
        ['dispute' => $dispute] = $this->qcDispute();

        $this->actingAs($this->manajemenA)
            ->post("/log-sengketa/{$dispute->uuid}/tinjauan", [
                'message' => 'Mohon Vendor menjelaskan kondisi karung yang basah.',
                'request_vendor_response' => true,
            ])
            ->assertRedirect();
        $this->assertSame(StatusSengketa::AwaitingResponse, $dispute->fresh()->status);

        $this->actingAs($this->vendorUserA)
            ->post("/log-sengketa/{$dispute->uuid}/tinjauan", [
                'message' => 'Hujan saat bongkar muat, kami siap mengganti.',
            ])
            ->assertRedirect();
        $this->assertSame(StatusSengketa::InMediation, $dispute->fresh()->status);
        $this->assertDatabaseHas('dispute_logs', ['dispute_id' => $dispute->id, 'user_id' => $this->vendorUserA->id]);

        $this->actingAs($this->vendorUserB)
            ->post("/log-sengketa/{$dispute->uuid}/tinjauan", ['message' => 'Vendor lain mencoba menanggapi.'])
            ->assertNotFound();
    }

    public function test_mismatched_invoice_is_verified_again_with_confirmed_credit_note(): void
    {
        ['dispute' => $dispute, 'invoice' => $invoice] = $this->invoiceDispute();

        // While the dispute is open the invoice cannot be verified again (BR-13).
        $this->actingAs($this->purchasingA)
            ->postJson("/verifikasi-tagihan/{$invoice->uuid}/verifikasi")
            ->assertForbidden();

        $this->resolveWithCreditNote($dispute, $invoice, '1000000');

        $this->actingAs($this->purchasingA)
            ->get("/verifikasi-tagihan/{$invoice->uuid}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('can_verify', true)
                ->where('matching_evaluation.is_matched', true)
                ->where('matching_evaluation.summary.settled_by_credit_note', true)
                ->where('invoice.data.confirmed_credit_total', 1000000));

        $this->actingAs($this->purchasingA)
            ->postJson("/verifikasi-tagihan/{$invoice->uuid}/verifikasi")
            ->assertOk()
            ->assertJsonPath('data.status', 'matched');

        $this->assertSame('matched', $invoice->fresh()->status_matching);
        $this->assertSame(StatusPurchaseOrder::ReadyToPay, $invoice->purchaseOrder()->first()->status);
    }

    public function test_credit_note_too_small_keeps_the_invoice_mismatched(): void
    {
        ['dispute' => $dispute, 'invoice' => $invoice] = $this->invoiceDispute();
        $this->resolveWithCreditNote($dispute, $invoice, '400000');

        $this->actingAs($this->purchasingA)
            ->postJson("/verifikasi-tagihan/{$invoice->uuid}/verifikasi")
            ->assertOk()
            ->assertJsonPath('data.status', 'mismatched');

        $this->assertSame(2, Sengketa::query()->where('invoice_id', $invoice->id)->count());
    }

    public function test_pending_invoice_with_open_dispute_cannot_be_verified(): void
    {
        ['dispute' => $dispute, 'invoice' => $invoice] = $this->invoiceDispute();
        $invoice->update(['status_matching' => 'pending']);

        $this->actingAs($this->purchasingA)
            ->postJson("/verifikasi-tagihan/{$invoice->uuid}/verifikasi")
            ->assertUnprocessable();

        $this->assertSame(StatusSengketa::Open, $dispute->fresh()->status);
    }

    /**
     * A PO of 1000 kg agreed at Rp 20.000/kg, fully accepted by QC, billed at Rp 21.000/kg (mismatched).
     * A confirmed credit note of exactly Rp 1.000.000 settles the overcharge.
     *
     * @return array{dispute: Sengketa, invoice: Invoice}
     */
    private function invoiceDispute(): array
    {
        [$po, $poItem, $receipt] = $this->purchaseOrderWithReceipt(StatusPurchaseOrder::Ditagih);
        PenerimaanBarangItem::create([
            'uuid' => (string) Str::uuid(),
            'goods_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $poItem->id,
            'received_quantity' => 1000,
            'status' => StatusItemPenerimaan::Accepted->value,
        ]);

        $invoice = Invoice::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po->id,
            'vendor_id' => $this->vendorA->id,
            'nomor_invoice_vendor' => 'INV-'.Str::upper(Str::random(6)),
            'jumlah_tagihan' => 21000000,
            'tanggal_invoice' => now()->toDateString(),
            'status_matching' => 'mismatched',
        ]);
        InvoiceItem::create([
            'uuid' => (string) Str::uuid(),
            'invoice_id' => $invoice->id,
            'purchase_order_item_id' => $poItem->id,
            'quantity' => 1000,
            'unit_price' => 21000,
            'subtotal' => 21000000,
        ]);

        $dispute = Sengketa::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po->id,
            'purchase_order_item_id' => $poItem->id,
            'invoice_id' => $invoice->id,
            'trigger_type' => PemicuSengketa::AmountMismatch->value,
            'reason' => 'Harga satuan invoice tidak sesuai harga kesepakatan PO.',
            'opened_by' => $this->purchasingA->id,
            'status' => StatusSengketa::Open->value,
            'opened_at' => now(),
        ]);

        return ['dispute' => $dispute, 'invoice' => $invoice];
    }

    /**
     * A PO with 200 kg rejected by QC (Diterima Sebagian) and its QC dispute.
     *
     * @return array{dispute: Sengketa, rejectedLine: PenerimaanBarangItem}
     */
    private function qcDispute(): array
    {
        [$po, $poItem, $receipt] = $this->purchaseOrderWithReceipt(StatusPurchaseOrder::PartiallyReceived);
        PenerimaanBarangItem::create([
            'uuid' => (string) Str::uuid(),
            'goods_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $poItem->id,
            'received_quantity' => 800,
            'status' => StatusItemPenerimaan::Accepted->value,
        ]);
        $rejected = PenerimaanBarangItem::create([
            'uuid' => (string) Str::uuid(),
            'goods_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $poItem->id,
            'received_quantity' => 200,
            'status' => StatusItemPenerimaan::Rejected->value,
            'notes' => 'Karung basah.',
            'rejection_photos' => ['qc-inspections/basah.jpg'],
        ]);

        $dispute = Sengketa::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po->id,
            'purchase_order_item_id' => $poItem->id,
            'trigger_type' => PemicuSengketa::GoodsMismatch->value,
            'reason' => '200 kg beras basah ditolak QC.',
            'opened_by' => $this->qcA->id,
            'status' => StatusSengketa::Open->value,
            'opened_at' => now(),
        ]);

        return ['dispute' => $dispute, 'rejectedLine' => $rejected];
    }

    /**
     * @return array{0: PurchaseOrder, 1: PoItem, 2: PenerimaanBarang}
     */
    private function purchaseOrderWithReceipt(StatusPurchaseOrder $status): array
    {
        $po = PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-TEST-'.Str::upper(Str::random(6)),
            'perusahaan_buyer_id' => $this->tenantA->id,
            'vendor_id' => $this->vendorA->id,
            'status' => $status->value,
            'tanggal_po' => now()->toDateString(),
            'created_by' => $this->purchasingA->id,
            'locked_at' => now(),
        ]);

        $poItem = PoItem::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po->id,
            'komoditas_id' => $this->commodity->id,
            'jumlah_pesanan' => 1000,
            'estimated_unit_price' => 20000,
            'harga_satuan_po' => 20000,
            'subtotal' => 20000000,
            'status_item' => 'agreed',
        ]);

        $shipment = Pengiriman::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po->id,
            'route' => 'Darat',
            'carrier_name' => 'Truk Vendor',
            'vehicle_plate_number' => 'L 1234 AB',
            'estimated_arrival_date' => now()->toDateString(),
            'status' => StatusPengiriman::Arrived->value,
            'reported_by' => $this->vendorUserA->id,
        ]);

        $receipt = PenerimaanBarang::create([
            'uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po->id,
            'shipment_id' => $shipment->id,
            'inspected_by' => $this->qcA->id,
            'matched_plate_number' => 'L 1234 AB',
            'inspected_at' => now(),
        ]);

        return [$po, $poItem, $receipt];
    }

    private function awaitingCreditNote(Sengketa $dispute): CreditNote
    {
        $this->actingAs($this->ownerA)
            ->postJson("/log-sengketa/{$dispute->uuid}/penyelesaian", [
                'resolution_type' => 'credit_note',
                'resolution_notes' => 'Selisih harga dipotong dari tagihan.',
                'invoice_uuid' => $dispute->invoice()->first()->uuid,
                'amount' => '1000000',
            ])
            ->assertOk();

        return CreditNote::query()->latest('id')->firstOrFail();
    }

    private function resolveWithCreditNote(Sengketa $dispute, Invoice $invoice, string $amount): void
    {
        $this->actingAs($this->ownerA)
            ->postJson("/log-sengketa/{$dispute->uuid}/penyelesaian", [
                'resolution_type' => 'credit_note',
                'resolution_notes' => 'Selisih harga dipotong dari tagihan.',
                'invoice_uuid' => $invoice->uuid,
                'amount' => $amount,
            ])
            ->assertOk();
        $creditNote = CreditNote::query()->latest('id')->firstOrFail();

        $this->actingAs($this->vendorUserA)
            ->postJson("/log-sengketa/{$dispute->uuid}/credit-note/{$creditNote->uuid}/keputusan", ['decision' => 'accept'])
            ->assertOk();
        $this->actingAs($this->ownerA)->postJson("/log-sengketa/{$dispute->uuid}/selesai")->assertOk();
    }

    private function makeVendor(string $name): Vendor
    {
        return Vendor::create([
            'company_name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'contact_email' => Str::lower(Str::random(6)).'@mitra.test',
            'phone' => '0812'.random_int(10000000, 99999999),
            'address' => 'Jl. Raya No. 1',
            'verification_status' => 'approved',
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
}
