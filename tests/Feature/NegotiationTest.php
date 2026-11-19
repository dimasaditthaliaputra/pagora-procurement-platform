<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\NegotiationStatus;
use App\Enums\RoleName;
use App\Enums\StatusKerjaSama;
use App\Enums\StatusPurchaseOrder;
use App\Models\ChatNegosiasi;
use App\Models\KerjaSama;
use App\Models\Komoditas;
use App\Models\Notification;
use App\Models\PerusahaanBuyer;
use App\Models\PoItem;
use App\Models\PrediksiHarga;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\TestCase;

/**
 * Smart Negotiation Chat per item (UC-12, UC-13, FR-19..21, BR-02, BR-06, BR-08, NFR-07, NFR-12).
 */
class NegotiationTest extends TestCase
{
    use RefreshDatabase;

    private PerusahaanBuyer $tenant;

    private Vendor $vendor;

    private User $purchasing;

    private User $owner;

    private User $vendorUser;

    private Komoditas $beras;

    private Komoditas $cabai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->tenant = PerusahaanBuyer::factory()->create();
        $this->purchasing = $this->tenantUser(RoleName::Purchasing);
        $this->owner = $this->tenantUser(RoleName::Owner);
        $this->vendor = $this->makeVendor('CV Mitra Tani');
        $this->vendorUser = $this->vendorUserFor($this->vendor);

        KerjaSama::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $this->vendor->id,
            'requested_by' => 'tenant',
            'status' => StatusKerjaSama::Agreed->value,
        ]);

        $this->beras = Komoditas::factory()->create(['name' => 'Beras Medium', 'unit' => 'kg']);
        $this->cabai = Komoditas::factory()->create(['name' => 'Cabai Rawit', 'unit' => 'kg']);
    }

    private function tenantUser(RoleName $role, ?PerusahaanBuyer $tenant = null): User
    {
        return User::factory()->create([
            'role_id' => Role::where('name', $role->value)->value('id'),
            'tenant_id' => ($tenant ?? $this->tenant)->id,
            'is_active' => true,
        ]);
    }

    private function makeVendor(string $name): Vendor
    {
        return Vendor::create([
            'company_name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'tax_number' => '01.234.567.8-'.random_int(100, 999).'.000',
            'contact_email' => Str::random(6).'@vendor.test',
            'phone' => '081234567890',
            'address' => 'Sleman',
            'verification_status' => 'approved',
            'is_active' => true,
        ]);
    }

    private function vendorUserFor(Vendor $vendor): User
    {
        return User::factory()->create([
            'role_id' => Role::where('name', RoleName::Vendor->value)->value('id'),
            'vendor_id' => $vendor->id,
            'is_active' => true,
        ]);
    }

    /**
     * @param  list<array{0: Komoditas, 1: float, 2: float}>  $items  [commodity, quantity, estimate]
     */
    private function negotiatingPo(array $items = [], StatusPurchaseOrder $status = StatusPurchaseOrder::Negosiasi): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            'uuid' => (string) Str::uuid(),
            'nomor_po' => 'PO-2026-'.random_int(1000, 9999),
            'vendor_id' => $this->vendor->id,
            'created_by' => $this->purchasing->id,
            'perusahaan_buyer_id' => $this->tenant->id,
            'status' => $status->value,
            'tanggal_po' => '2026-11-19',
        ]);

        foreach ($items ?: [[$this->beras, 100, 14000]] as [$komoditas, $quantity, $estimate]) {
            PoItem::create([
                'uuid' => (string) Str::uuid(),
                'purchase_order_id' => $po->id,
                'komoditas_id' => $komoditas->id,
                'jumlah_pesanan' => $quantity,
                'estimated_unit_price' => $estimate,
                'status_item' => NegotiationStatus::Pending,
            ]);
        }

        return $po->fresh('poItems');
    }

    private function itemUrl(PurchaseOrder $po, PoItem $item, string $action): string
    {
        return "/purchase-order/{$po->uuid}/negosiasi/item/{$item->uuid}/{$action}";
    }

    public function test_confirming_a_draft_opens_negotiation_without_revealing_the_estimate(): void
    {
        $po = $this->negotiatingPo(status: StatusPurchaseOrder::Draft);

        $this->actingAs($this->purchasing)->post("/purchase-order/{$po->uuid}/konfirmasi")->assertSessionHas('success');

        $this->assertSame(StatusPurchaseOrder::Negosiasi, $po->fresh()->status);
        $message = ChatNegosiasi::where('purchase_order_id', $po->id)->sole();
        $this->assertSame('system', $message->type->value);
        $this->assertNull($message->offer_amount);
    }

    public function test_tenant_room_shows_estimate_and_ceiling_but_vendor_room_never_does(): void
    {
        $po = $this->negotiatingPo();
        PrediksiHarga::create([
            'uuid' => (string) Str::uuid(),
            'commodity_id' => $this->beras->id,
            'prediction_date' => now()->addDay()->toDateString(),
            'predicted_price' => 15000,
            'recommended_ceiling_price' => 15750,
            'calculated_at' => now(),
        ]);

        $this->actingAs($this->purchasing)
            ->get("/purchase-order/{$po->uuid}/negosiasi")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PurchaseOrder/Negosiasi')
                ->where('negotiation.side', 'tenant')
                ->where('negotiation.items.0.estimated_unit_price', 14000)
                ->where('negotiation.items.0.ceiling_price', 15750)
                ->where('negotiation.items.0.can', ['offer' => true, 'accept' => false, 'remove' => true])
                ->where('negotiation.can.cancel', true)
                ->where('poll_interval_ms', 3000)
            );

        $response = $this->actingAs($this->vendorUser)->get("/purchase-order/{$po->uuid}/negosiasi");
        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('negotiation.side', 'vendor')
                ->missing('negotiation.items.0.estimated_unit_price')
                ->missing('negotiation.items.0.ceiling_price')
                ->where('negotiation.items.0.can', ['offer' => false, 'accept' => false, 'remove' => false])
                ->where('negotiation.can.cancel', false)
            );
        $this->assertStringNotContainsString('15750', $response->getContent());
        $this->assertStringNotContainsString('14000', $response->getContent());
    }

    public function test_full_item_negotiation_ends_in_an_agreed_and_locked_purchase_order(): void
    {
        $po = $this->negotiatingPo();
        $item = $po->poItems->first();

        $this->actingAs($this->purchasing)
            ->post($this->itemUrl($po, $item, 'tawar'), ['offer_amount' => 13000, 'message' => 'Harga sesuai anggaran.'])
            ->assertRedirect("/purchase-order/{$po->uuid}/negosiasi")
            ->assertSessionHas('success', 'Tawaran harga terkirim.');
        $this->assertEquals(13000, (float) $item->fresh()->harga_satuan_po);

        $this->actingAs($this->vendorUser)->post($this->itemUrl($po, $item, 'tawar'), ['offer_amount' => 13800]);
        $item->refresh();
        $this->assertSame(NegotiationStatus::Countered, $item->status_item);
        $this->assertEquals(13800, (float) $item->harga_tawar_vendor);

        $this->actingAs($this->purchasing)->post($this->itemUrl($po, $item, 'setujui'))->assertSessionHas('success');

        $item->refresh();
        $po->refresh();
        $this->assertSame(NegotiationStatus::Agreed, $item->status_item);
        $this->assertEquals(13800, (float) $item->harga_satuan_po);
        $this->assertEquals(1380000, (float) $item->subtotal);
        $this->assertSame(StatusPurchaseOrder::Disepakati, $po->status);
        $this->assertNotNull($po->locked_at);

        $types = ChatNegosiasi::where('purchase_order_id', $po->id)->orderBy('id')->get()->map(fn ($m) => $m->type->value)->all();
        $this->assertSame(['offer', 'offer', 'acceptance', 'system'], $types);

        // BR-08: chat is read-only once agreed.
        $this->actingAs($this->vendorUser)->postJson("/purchase-order/{$po->uuid}/negosiasi/pesan", ['message' => 'Halo?'])->assertForbidden();
        $this->actingAs($this->purchasing)->postJson($this->itemUrl($po, $item, 'tawar'), ['offer_amount' => 1])->assertForbidden();
        $this->actingAs($this->purchasing)
            ->post($this->itemUrl($po, $item, 'tawar'), ['offer_amount' => 1])
            ->assertSessionHas('error', 'Anda tidak dapat melakukan aksi ini pada negosiasi.');
    }

    public function test_vendor_can_accept_purchasing_proposal_directly(): void
    {
        $po = $this->negotiatingPo();
        $item = $po->poItems->first();
        $this->actingAs($this->purchasing)->post($this->itemUrl($po, $item, 'tawar'), ['offer_amount' => 13500]);

        $this->actingAs($this->vendorUser)->post($this->itemUrl($po, $item, 'setujui'))->assertSessionHas('success');

        $this->assertEquals(13500, (float) $item->fresh()->harga_satuan_po);
        $this->assertSame(StatusPurchaseOrder::Disepakati, $po->fresh()->status);
    }

    public function test_removed_items_are_left_out_and_the_rest_is_agreed(): void
    {
        $po = $this->negotiatingPo([[$this->beras, 100, 14000], [$this->cabai, 10, 70000]]);
        [$beras, $cabai] = [$po->poItems[0], $po->poItems[1]];

        $this->actingAs($this->purchasing)->post($this->itemUrl($po, $beras, 'tawar'), ['offer_amount' => 13500]);
        $this->actingAs($this->vendorUser)->post($this->itemUrl($po, $beras, 'setujui'));
        $this->assertSame(StatusPurchaseOrder::Negosiasi, $po->fresh()->status, 'One item is still open.');

        $this->actingAs($this->purchasing)
            ->post($this->itemUrl($po, $cabai, 'keluarkan'), ['reason' => 'Di atas anggaran.'])
            ->assertSessionHas('success');

        $this->assertSame(NegotiationStatus::Removed, $cabai->fresh()->status_item);
        $this->assertSame(StatusPurchaseOrder::Disepakati, $po->fresh()->status);
        $this->assertDatabaseHas('negotiation_messages', [
            'purchase_order_item_id' => $cabai->id,
            'type' => 'item_removed',
            'message' => 'Di atas anggaran.',
        ]);
    }

    public function test_purchase_order_is_cancelled_when_every_item_is_removed(): void
    {
        $po = $this->negotiatingPo();

        $this->actingAs($this->purchasing)->post($this->itemUrl($po, $po->poItems->first(), 'keluarkan'));

        $po->refresh();
        $this->assertSame(StatusPurchaseOrder::Dibatalkan, $po->status);
        $this->assertSame('Seluruh item dikeluarkan karena harga tidak disepakati.', $po->alasan_pembatalan);
        $this->assertTrue(
            Notification::where('user_id', $this->vendorUser->id)->where('title', 'like', 'PO dibatalkan%')->exists()
        );
    }

    public function test_actions_that_do_not_fit_the_item_status_are_rejected(): void
    {
        $po = $this->negotiatingPo();
        $item = $po->poItems->first();

        // Vendor cannot answer before Purchasing proposed a price.
        $this->actingAs($this->vendorUser)->post($this->itemUrl($po, $item, 'setujui'))->assertSessionHasErrors('item');
        $this->actingAs($this->vendorUser)->post($this->itemUrl($po, $item, 'tawar'), ['offer_amount' => 15000])->assertSessionHasErrors('item');

        // Purchasing cannot "accept" when there is no vendor counter-offer.
        $this->actingAs($this->purchasing)->post($this->itemUrl($po, $item, 'setujui'))->assertSessionHasErrors('item');

        $this->assertSame(NegotiationStatus::Pending, $item->fresh()->status_item);
        $this->assertSame(0, ChatNegosiasi::count());
    }

    public function test_only_purchasing_side_can_remove_items_or_cancel(): void
    {
        $po = $this->negotiatingPo();
        $item = $po->poItems->first();

        $this->actingAs($this->vendorUser)->postJson($this->itemUrl($po, $item, 'keluarkan'))->assertForbidden();
        $this->actingAs($this->vendorUser)->post("/purchase-order/{$po->uuid}/batalkan", ['alasan' => 'Vendor membatalkan.'])->assertForbidden();

        $this->assertSame(NegotiationStatus::Pending, $item->fresh()->status_item);
    }

    public function test_offer_and_message_input_is_validated(): void
    {
        $po = $this->negotiatingPo();
        $item = $po->poItems->first();

        foreach ([0, -5, 'abc', ''] as $amount) {
            $this->actingAs($this->purchasing)->post($this->itemUrl($po, $item, 'tawar'), ['offer_amount' => $amount])->assertSessionHasErrors('offer_amount');
        }
        $this->actingAs($this->purchasing)->post($this->itemUrl($po, $item, 'tawar'), ['offer_amount' => 100.123])->assertSessionHasErrors('offer_amount');
        $this->actingAs($this->purchasing)->post("/purchase-order/{$po->uuid}/negosiasi/pesan", ['message' => ''])->assertSessionHasErrors('message');
        $this->actingAs($this->purchasing)->post("/purchase-order/{$po->uuid}/negosiasi/pesan", ['message' => str_repeat('a', 1001)])->assertSessionHasErrors('message');
        $this->actingAs($this->purchasing)->getJson("/purchase-order/{$po->uuid}/negosiasi/pembaruan?after=bukan-uuid")->assertUnprocessable();

        $this->assertSame(0, ChatNegosiasi::count());
    }

    public function test_both_parties_exchange_text_messages_and_the_counterpart_is_notified(): void
    {
        $po = $this->negotiatingPo();

        $this->actingAs($this->vendorUser)->post("/purchase-order/{$po->uuid}/negosiasi/pesan", ['message' => 'Stok tersedia minggu depan.'])
            ->assertRedirect("/purchase-order/{$po->uuid}/negosiasi");
        $this->actingAs($this->owner)->post("/purchase-order/{$po->uuid}/negosiasi/pesan", ['message' => 'Baik, terima kasih.']);

        $this->assertSame(2, ChatNegosiasi::where('purchase_order_id', $po->id)->where('type', 'text')->count());
        $this->assertTrue(Notification::where('user_id', $this->purchasing->id)->where('type', 'negotiation')->exists());
        $this->assertTrue(Notification::where('user_id', $this->vendorUser->id)->where('type', 'negotiation')->exists());
    }

    public function test_chat_message_sent_as_json_returns_the_stored_message_without_a_page_visit(): void
    {
        $po = $this->negotiatingPo();

        $response = $this->actingAs($this->vendorUser)
            ->postJson("/purchase-order/{$po->uuid}/negosiasi/pesan", ['message' => '  Stok tersedia minggu depan.  '])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Pesan terkirim.')
            ->assertJsonPath('data.type', 'text')
            ->assertJsonPath('data.message', 'Stok tersedia minggu depan.')
            ->assertJsonPath('data.offer_amount', null)
            ->assertJsonPath('data.item', null)
            ->assertJsonPath('data.sender.side', 'vendor')
            ->assertJsonPath('data.sender.is_me', true)
            ->assertJsonMissingPath('data.id');

        $stored = ChatNegosiasi::sole();
        $this->assertSame($stored->uuid, $response->json('data.uuid'));
        $this->assertSame($this->vendorUser->id, $stored->sender_id);
        $this->assertTrue(Notification::where('user_id', $this->purchasing->id)->where('type', 'negotiation')->exists());

        // The counterpart sees the same message through polling, flagged as not theirs.
        $this->actingAs($this->purchasing)
            ->getJson("/purchase-order/{$po->uuid}/negosiasi/pembaruan")
            ->assertJsonPath('data.messages.0.uuid', $stored->uuid)
            ->assertJsonPath('data.messages.0.sender.is_me', false);
    }

    public function test_chat_message_sent_as_json_reports_validation_and_access_errors_as_json(): void
    {
        $po = $this->negotiatingPo();

        $this->actingAs($this->purchasing)
            ->postJson("/purchase-order/{$po->uuid}/negosiasi/pesan", ['message' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['message' => 'Pesan tidak boleh kosong.']);

        $closed = $this->negotiatingPo(status: StatusPurchaseOrder::Disepakati);
        $this->actingAs($this->purchasing)
            ->postJson("/purchase-order/{$closed->uuid}/negosiasi/pesan", ['message' => 'Terlambat'])
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->assertSame(0, ChatNegosiasi::count());
    }

    public function test_polling_returns_only_messages_after_the_cursor_and_current_item_state(): void
    {
        $po = $this->negotiatingPo();
        $item = $po->poItems->first();
        $this->actingAs($this->vendorUser)->post("/purchase-order/{$po->uuid}/negosiasi/pesan", ['message' => 'Pesan pertama']);
        $first = ChatNegosiasi::sole();
        $this->actingAs($this->purchasing)->post($this->itemUrl($po, $item, 'tawar'), ['offer_amount' => 13250]);

        $response = $this->actingAs($this->vendorUser)
            ->getJson("/purchase-order/{$po->uuid}/negosiasi/pembaruan?after={$first->uuid}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.messages')
            ->assertJsonPath('data.messages.0.type', 'offer')
            ->assertJsonPath('data.messages.0.offer_amount', 13250)
            ->assertJsonPath('data.messages.0.item.uuid', $item->uuid)
            ->assertJsonPath('data.messages.0.sender.side', 'tenant')
            ->assertJsonPath('data.negotiation.items.0.proposed_price', 13250)
            ->assertJsonPath('data.negotiation.items.0.can', ['offer' => true, 'accept' => true, 'remove' => false])
            ->assertJsonMissingPath('data.negotiation.items.0.estimated_unit_price')
            ->assertJsonMissingPath('data.messages.0.id');
        $this->assertStringNotContainsString('"id"', $response->getContent());

        $this->actingAs($this->vendorUser)
            ->getJson("/purchase-order/{$po->uuid}/negosiasi/pembaruan")
            ->assertJsonCount(2, 'data.messages');
    }

    public function test_other_tenants_and_vendors_cannot_reach_the_negotiation(): void
    {
        $po = $this->negotiatingPo();
        $item = $po->poItems->first();
        $otherTenantPurchasing = $this->tenantUser(RoleName::Purchasing, PerusahaanBuyer::factory()->create());
        $otherVendorUser = $this->vendorUserFor($this->makeVendor('CV Pesaing'));

        foreach ([$otherTenantPurchasing, $otherVendorUser] as $stranger) {
            $this->actingAs($stranger)->get("/purchase-order/{$po->uuid}/negosiasi")->assertNotFound();
            $this->actingAs($stranger)->getJson("/purchase-order/{$po->uuid}/negosiasi/pembaruan")->assertNotFound();
            $this->actingAs($stranger)->postJson("/purchase-order/{$po->uuid}/negosiasi/pesan", ['message' => 'Halo'])->assertNotFound();
            $this->actingAs($stranger)->postJson($this->itemUrl($po, $item, 'tawar'), ['offer_amount' => 1000])->assertNotFound();
        }

        $this->assertSame(0, ChatNegosiasi::count());
    }

    public function test_roles_without_purchase_order_access_are_forbidden(): void
    {
        $po = $this->negotiatingPo();
        $qc = $this->tenantUser(RoleName::QcGudang);

        $this->actingAs($qc)->get("/purchase-order/{$po->uuid}/negosiasi")->assertForbidden();
        $this->actingAs($qc)->getJson("/purchase-order/{$po->uuid}/negosiasi/pembaruan")->assertForbidden();
        $this->actingAs($qc)->postJson("/purchase-order/{$po->uuid}/negosiasi/pesan", ['message' => 'Halo'])->assertForbidden();
    }

    public function test_item_of_another_purchase_order_is_not_found(): void
    {
        $po = $this->negotiatingPo();
        $otherItem = $this->negotiatingPo()->poItems->first();

        $this->actingAs($this->purchasing)
            ->postJson($this->itemUrl($po, $otherItem, 'tawar'), ['offer_amount' => 13000])
            ->assertNotFound();
    }

    public function test_draft_purchase_order_has_no_negotiation_room_for_the_vendor(): void
    {
        $po = $this->negotiatingPo(status: StatusPurchaseOrder::Draft);

        $this->actingAs($this->vendorUser)->get("/purchase-order/{$po->uuid}/negosiasi")->assertForbidden();
    }

    public function test_negotiation_messages_are_immutable(): void
    {
        $po = $this->negotiatingPo();
        $this->actingAs($this->purchasing)->post("/purchase-order/{$po->uuid}/negosiasi/pesan", ['message' => 'Catatan permanen']);
        $message = ChatNegosiasi::sole();

        $this->expectException(LogicException::class);
        $message->update(['message' => 'Diubah']);
    }
}
