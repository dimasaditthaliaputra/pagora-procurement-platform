<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\StatusKerjaSama;
use App\Enums\StatusPurchaseOrder;
use App\Models\KerjaSama;
use App\Models\Komoditas;
use App\Models\Notification;
use App\Models\PerusahaanBuyer;
use App\Models\PoItem;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\TenantCommodity;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $buyerUser;

    private User $vendorUser;

    private User $otherUser;

    private PerusahaanBuyer $tenant;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->tenant = PerusahaanBuyer::factory()->create([
            'company_name' => 'PT Pangan Makmur',
        ]);

        $this->vendor = Vendor::create([
            'company_name' => 'CV Mitra Sejahtera',
            'slug' => 'cv-mitra-sejahtera',
            'tax_number' => '01.234.567.8-123.000',
            'contact_email' => 'kontak@mitra-sejahtera.test',
            'phone' => '081234567890',
            'address' => 'Jl. Pangan No. 10',
            'verification_status' => 'approved',
            'is_active' => true,
        ]);

        $purchasingRole = Role::where('name', RoleName::Purchasing->value)->firstOrFail();
        $vendorRole = Role::where('name', RoleName::Vendor->value)->firstOrFail();

        $this->buyerUser = User::factory()->create([
            'role_id' => $purchasingRole->id,
            'tenant_id' => $this->tenant->id,
            'vendor_id' => null,
            'is_active' => true,
        ]);

        $this->vendorUser = User::factory()->create([
            'role_id' => $vendorRole->id,
            'tenant_id' => null,
            'vendor_id' => $this->vendor->id,
            'is_active' => true,
        ]);

        $this->otherUser = User::factory()->create([
            'role_id' => $purchasingRole->id,
            'is_active' => true,
        ]);
    }

    public function test_guest_cannot_access_notifications(): void
    {
        $response = $this->get('/notifikasi');
        $response->assertRedirect('/login');

        $apiResponse = $this->getJson('/notifications');
        $apiResponse->assertUnauthorized();
    }

    public function test_user_can_view_notification_index_page(): void
    {
        Notification::factory()->create([
            'user_id' => $this->buyerUser->id,
            'type' => 'purchase_order',
            'title' => 'Notifikasi Draf',
            'read_at' => null,
        ]);

        $response = $this->actingAs($this->buyerUser)->get('/notifikasi');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Notifikasi/Index')
            ->has('notifications.data', 1)
            ->where('unreadCount', 1)
            ->where('filter', 'all')
        );
    }

    public function test_user_can_fetch_recent_notifications_json(): void
    {
        Notification::factory()->count(3)->create([
            'user_id' => $this->buyerUser->id,
            'type' => 'purchase_order',
            'read_at' => null,
        ]);

        Notification::factory()->create([
            'user_id' => $this->otherUser->id,
            'type' => 'purchase_order',
            'read_at' => null,
        ]);

        $response = $this->actingAs($this->buyerUser)->getJson('/notifications?limit=5');

        $response->assertOk();
        $response->assertJsonStructure([
            'notifications' => [
                '*' => [
                    'uuid',
                    'type',
                    'title',
                    'message',
                    'action_url',
                    'can_redirect',
                    'is_reminder',
                    'is_read',
                    'created_at_human',
                ],
            ],
            'unread_count',
        ]);
        $response->assertJsonCount(3, 'notifications');
        $this->assertSame(3, $response->json('unread_count'));
    }

    public function test_user_can_fetch_unread_count(): void
    {
        Notification::factory()->count(2)->create([
            'user_id' => $this->buyerUser->id,
            'type' => 'negotiation',
            'read_at' => null,
        ]);

        Notification::factory()->create([
            'user_id' => $this->buyerUser->id,
            'type' => 'purchase_order',
            'read_at' => now(),
        ]);

        $response = $this->actingAs($this->buyerUser)->getJson('/notifications/unread-count');

        $response->assertOk();
        $response->assertExactJson(['unread_count' => 2]);
    }

    public function test_user_can_mark_notification_as_read_via_api(): void
    {
        $notification = Notification::factory()->create([
            'user_id' => $this->buyerUser->id,
            'type' => 'purchase_order',
            'read_at' => null,
            'action_url' => '/purchase-order',
        ]);

        $this->assertNull($notification->read_at);

        $response = $this->actingAs($this->buyerUser)->postJson("/notifications/{$notification->uuid}/read");

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'redirect_url' => '/purchase-order',
            'can_redirect' => true,
        ]);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_notification_as_read_via_web_redirect(): void
    {
        $notification = Notification::factory()->create([
            'user_id' => $this->buyerUser->id,
            'type' => 'purchase_order',
            'read_at' => null,
            'action_url' => '/purchase-order/PO-123',
        ]);

        $response = $this->actingAs($this->buyerUser)->post("/notifications/{$notification->uuid}/read");

        $response->assertRedirect('/purchase-order/PO-123');
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_inertia_request_receives_redirect_and_not_plain_json(): void
    {
        $notification = Notification::factory()->create([
            'user_id' => $this->buyerUser->id,
            'type' => 'negotiation',
            'read_at' => null,
            'action_url' => '/purchase-order/PO-123/negosiasi',
        ]);

        $response = $this->actingAs($this->buyerUser)
            ->withHeader('X-Inertia', 'true')
            ->post("/notifications/{$notification->uuid}/read");

        $response->assertRedirect('/purchase-order/PO-123/negosiasi');
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_role_scoping_filters_unrelated_notifications_for_purchasing(): void
    {
        // Purchasing role should see negotiation & purchase_order
        Notification::factory()->create([
            'user_id' => $this->buyerUser->id,
            'type' => 'purchase_order',
            'title' => 'PO Aktif',
            'read_at' => null,
        ]);

        // Purchasing role should NOT see partnership or verification
        Notification::factory()->create([
            'user_id' => $this->buyerUser->id,
            'type' => 'partnership',
            'title' => 'Undangan Mitra Masuk',
            'read_at' => null,
        ]);

        Notification::factory()->create([
            'user_id' => $this->buyerUser->id,
            'type' => 'verification',
            'title' => 'Verifikasi Legalitas',
            'read_at' => null,
        ]);

        $response = $this->actingAs($this->buyerUser)->getJson('/notifications?limit=10');

        $response->assertOk();
        // Only 1 notification should be returned because partnership and verification are filtered out for purchasing
        $response->assertJsonCount(1, 'notifications');
        $this->assertSame(1, $response->json('unread_count'));
    }

    public function test_reminder_notification_disables_redirect(): void
    {
        // A restricted URL for purchasing (e.g. user management is owner only)
        $notification = Notification::factory()->create([
            'user_id' => $this->buyerUser->id,
            'type' => 'system',
            'title' => 'Pembaruan Kebijakan Platform',
            'action_url' => '/manajemen/pengguna',
            'read_at' => null,
        ]);

        $response = $this->actingAs($this->buyerUser)->getJson('/notifications?limit=5');
        $response->assertOk();

        $item = $response->json('notifications.0');
        $this->assertFalse($item['can_redirect']);
        $this->assertTrue($item['is_reminder']);
        $this->assertNull($item['action_url']);

        // Marking as read via Inertia returns back(), not the forbidden redirect
        $readResponse = $this->actingAs($this->buyerUser)
            ->withHeader('X-Inertia', 'true')
            ->from('/notifikasi')
            ->post("/notifications/{$notification->uuid}/read");

        $readResponse->assertRedirect('/notifikasi');
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_cannot_mark_other_users_notification_as_read(): void
    {
        $notification = Notification::factory()->create([
            'user_id' => $this->otherUser->id,
            'type' => 'purchase_order',
            'read_at' => null,
        ]);

        $response = $this->actingAs($this->buyerUser)->postJson("/notifications/{$notification->uuid}/read");

        $response->assertForbidden();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        Notification::factory()->count(3)->create([
            'user_id' => $this->buyerUser->id,
            'type' => 'purchase_order',
            'read_at' => null,
        ]);

        Notification::factory()->create([
            'user_id' => $this->otherUser->id,
            'type' => 'purchase_order',
            'read_at' => null,
        ]);

        $response = $this->actingAs($this->buyerUser)->postJson('/notifications/read-all');

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertSame(0, Notification::where('user_id', $this->buyerUser->id)->unread()->count());
        $this->assertSame(1, Notification::where('user_id', $this->otherUser->id)->unread()->count());
    }

    public function test_po_events_trigger_in_app_notifications(): void
    {
        $komoditas = Komoditas::factory()->create(['name' => 'Beras Pandan Wangi']);
        TenantCommodity::create([
            'tenant_id' => $this->tenant->id,
            'commodity_id' => $komoditas->id,
            'is_active' => true,
        ]);

        KerjaSama::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $this->vendor->id,
            'status' => StatusKerjaSama::Agreed,
        ]);

        // Create PO
        $po = PurchaseOrder::create([
            'nomor_po' => 'PO-202611-9999',
            'perusahaan_buyer_id' => $this->tenant->id,
            'vendor_id' => $this->vendor->id,
            'created_by' => $this->buyerUser->id,
            'status' => StatusPurchaseOrder::Draft,
            'tanggal_po' => now(),
        ]);

        PoItem::create([
            'purchase_order_id' => $po->id,
            'komoditas_id' => $komoditas->id,
            'jumlah_pesanan' => 100,
            'harga_satuan_po' => 15000,
            'subtotal' => 1500000,
        ]);

        // Buyer konfirmasi (ajukan) PO -> should notify vendorUser
        $this->actingAs($this->buyerUser)->post("/purchase-order/{$po->uuid}/konfirmasi");

        $vendorNotif = Notification::where('user_id', $this->vendorUser->id)
            ->where('type', 'purchase_order')
            ->first();

        $this->assertNotNull($vendorNotif);
        $this->assertStringContainsString('PO-202611-9999', $vendorNotif->title);
        $this->assertFalse($vendorNotif->isRead());

        // Vendor kirim tawaran baru -> should notify buyerUser
        $this->actingAs($this->vendorUser)->postJson("/purchase-order/{$po->uuid}/tawar", [
            'nominal_tawar' => 1600000,
            'pesan_teks' => 'Harga dinaikkan sesuai stok.',
        ]);

        $buyerNotif = Notification::where('user_id', $this->buyerUser->id)
            ->where('type', 'negotiation')
            ->first();

        $this->assertNotNull($buyerNotif);
        $this->assertStringContainsString('PO-202611-9999', $buyerNotif->title);
        $this->assertFalse($buyerNotif->isRead());
    }
}
