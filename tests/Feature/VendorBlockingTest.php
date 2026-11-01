<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VendorBlockingTest extends TestCase
{
    use RefreshDatabase;

    private User $manajemen;

    private User $superadmin;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->superadmin = User::factory()->create([
            'role_id' => 1, // Superadmin
            'name' => 'Super Administrator',
            'email' => 'superadmin@pagora.test',
            'is_active' => true,
        ]);

        $this->manajemen = User::factory()->create([
            'role_id' => 5, // Manajemen
            'name' => 'Admin Manajemen',
            'email' => 'manajemen@pagora.test',
            'is_active' => true,
        ]);

        $this->buyer = User::factory()->create([
            'role_id' => 2, // Buyer
            'name' => 'Staff Buyer',
            'email' => 'buyer@pagora.test',
            'is_active' => true,
        ]);
    }

    private function createVendor(array $attributes = []): Vendor
    {
        $name = $attributes['company_name'] ?? 'PT Mitra Tani Makmur';
        $slug = Str::slug($name).'-'.Str::lower(Str::random(6));

        return Vendor::create(array_merge([
            'company_name' => $name,
            'slug' => $slug,
            'contact_email' => 'kontak@mitratani.test',
            'phone' => '081234567890',
            'kategori_komoditas' => 'Beras & Jagung',
            'verification_status' => 'approved',
            'is_active' => true,
            'block_reason' => null,
            'blocked_by' => null,
            'blocked_at' => null,
        ], $attributes));
    }

    public function test_unauthenticated_user_cannot_access_vendor_index(): void
    {
        $response = $this->get('/master/vendor');
        $response->assertRedirect('/login');
    }

    public function test_management_user_can_view_vendor_index(): void
    {
        $vendor = $this->createVendor();

        $response = $this->actingAs($this->manajemen)->get('/master/vendor');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Master/Vendor/Index')
            ->has('vendors.data', 1)
            ->where('can_manage', true)
        );
    }

    public function test_management_user_can_block_active_vendor_with_valid_reason(): void
    {
        $vendor = $this->createVendor();

        // Create a vendor member user
        $vendorUser = User::factory()->create([
            'role_id' => 4, // Vendor role
            'vendor_id' => $vendor->id,
            'name' => 'Staff Vendor',
            'email' => 'staff@mitratani.test',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->manajemen)->post(
            "/master/vendor/{$vendor->uuid}/blokir",
            [
                'block_reason' => 'Ditemukan pelanggaran kontrak pada PO-2026-009.',
            ]
        );

        $response->assertSessionHas('success');

        // Check vendor record in DB
        $vendor->refresh();
        $this->assertFalse($vendor->is_active);
        $this->assertSame('Ditemukan pelanggaran kontrak pada PO-2026-009.', $vendor->block_reason);
        $this->assertSame($this->manajemen->id, $vendor->blocked_by);
        $this->assertNotNull($vendor->blocked_at);

        // Check associated vendor member accounts are also deactivated
        $vendorUser->refresh();
        $this->assertFalse($vendorUser->is_active);
    }

    public function test_blocking_requires_valid_reason(): void
    {
        $vendor = $this->createVendor();

        // Empty reason
        $responseEmpty = $this->actingAs($this->manajemen)->post(
            "/master/vendor/{$vendor->uuid}/blokir",
            [
                'block_reason' => '',
            ]
        );
        $responseEmpty->assertSessionHasErrors(['block_reason']);

        // Too short (< 5 chars)
        $responseShort = $this->actingAs($this->manajemen)->post(
            "/master/vendor/{$vendor->uuid}/blokir",
            [
                'block_reason' => 'bad',
            ]
        );
        $responseShort->assertSessionHasErrors(['block_reason']);

        $vendor->refresh();
        $this->assertTrue($vendor->is_active);
    }

    public function test_cannot_block_already_blocked_vendor(): void
    {
        $vendor = $this->createVendor([
            'is_active' => false,
            'block_reason' => 'Sudah diblokir sebelumnya.',
            'blocked_by' => $this->superadmin->id,
            'blocked_at' => now(),
        ]);

        $response = $this->actingAs($this->manajemen)->post(
            "/master/vendor/{$vendor->uuid}/blokir",
            [
                'block_reason' => 'Alasan pemblokiran kedua.',
            ]
        );

        $response->assertSessionHasErrors(['block_reason']);
    }

    public function test_non_management_user_cannot_block_vendor(): void
    {
        $vendor = $this->createVendor();

        $response = $this->actingAs($this->buyer)->post(
            "/master/vendor/{$vendor->uuid}/blokir",
            [
                'block_reason' => 'Percobaan pemblokiran oleh buyer.',
            ]
        );

        $response->assertForbidden();

        $vendor->refresh();
        $this->assertTrue($vendor->is_active);
    }

    public function test_management_user_can_unblock_blocked_vendor(): void
    {
        $vendor = $this->createVendor([
            'is_active' => false,
            'block_reason' => 'Sedang dalam evaluasi audit.',
            'blocked_by' => $this->superadmin->id,
            'blocked_at' => now()->subDays(3),
        ]);

        $vendorUser = User::factory()->create([
            'role_id' => 4,
            'vendor_id' => $vendor->id,
            'name' => 'Staff Vendor',
            'email' => 'staff2@mitratani.test',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->manajemen)->post(
            "/master/vendor/{$vendor->uuid}/buka-blokir"
        );

        $response->assertSessionHas('success');

        $vendor->refresh();
        $this->assertTrue($vendor->is_active);
        $this->assertNull($vendor->block_reason);
        $this->assertNull($vendor->blocked_by);
        $this->assertNull($vendor->blocked_at);

        // Check associated user accounts are reactivated
        $vendorUser->refresh();
        $this->assertTrue($vendorUser->is_active);
    }

    public function test_non_management_user_cannot_unblock_vendor(): void
    {
        $vendor = $this->createVendor([
            'is_active' => false,
            'block_reason' => 'Sedang dalam evaluasi audit.',
            'blocked_by' => $this->superadmin->id,
            'blocked_at' => now(),
        ]);

        $response = $this->actingAs($this->buyer)->post(
            "/master/vendor/{$vendor->uuid}/buka-blokir"
        );

        $response->assertForbidden();

        $vendor->refresh();
        $this->assertFalse($vendor->is_active);
    }
}
