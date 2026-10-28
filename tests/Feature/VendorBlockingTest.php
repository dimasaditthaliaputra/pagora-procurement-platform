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
            'nama_lengkap' => 'Super Administrator',
            'email' => 'superadmin@pagora.test',
            'status_aktif' => true,
        ]);

        $this->manajemen = User::factory()->create([
            'role_id' => 5, // Manajemen
            'nama_lengkap' => 'Admin Manajemen',
            'email' => 'manajemen@pagora.test',
            'status_aktif' => true,
        ]);

        $this->buyer = User::factory()->create([
            'role_id' => 2, // Buyer
            'nama_lengkap' => 'Staff Buyer',
            'email' => 'buyer@pagora.test',
            'status_aktif' => true,
        ]);
    }

    private function createVendor(array $attributes = []): Vendor
    {
        $name = $attributes['nama_perusahaan'] ?? 'PT Mitra Tani Makmur';
        $slug = Str::slug($name).'-'.Str::lower(Str::random(6));

        return Vendor::create(array_merge([
            'nama_perusahaan' => $name,
            'slug' => $slug,
            'email_kontak' => 'kontak@mitratani.test',
            'no_telepon' => '081234567890',
            'kategori_komoditas' => 'Beras & Jagung',
            'status_verifikasi' => 'terverifikasi',
            'status_aktif' => true,
            'alasan_pemblokiran' => null,
            'blocked_by' => null,
            'diblokir_pada' => null,
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
            'nama_lengkap' => 'Staff Vendor',
            'email' => 'staff@mitratani.test',
            'status_aktif' => true,
        ]);

        $response = $this->actingAs($this->manajemen)->post(
            "/master/vendor/{$vendor->uuid}/blokir",
            [
                'alasan_pemblokiran' => 'Ditemukan pelanggaran kontrak pada PO-2026-009.',
            ]
        );

        $response->assertSessionHas('success');

        // Check vendor record in DB
        $vendor->refresh();
        $this->assertFalse($vendor->status_aktif);
        $this->assertSame('Ditemukan pelanggaran kontrak pada PO-2026-009.', $vendor->alasan_pemblokiran);
        $this->assertSame($this->manajemen->id, $vendor->blocked_by);
        $this->assertNotNull($vendor->diblokir_pada);

        // Check associated vendor member accounts are also deactivated
        $vendorUser->refresh();
        $this->assertFalse($vendorUser->status_aktif);
    }

    public function test_blocking_requires_valid_reason(): void
    {
        $vendor = $this->createVendor();

        // Empty reason
        $responseEmpty = $this->actingAs($this->manajemen)->post(
            "/master/vendor/{$vendor->uuid}/blokir",
            [
                'alasan_pemblokiran' => '',
            ]
        );
        $responseEmpty->assertSessionHasErrors(['alasan_pemblokiran']);

        // Too short (< 5 chars)
        $responseShort = $this->actingAs($this->manajemen)->post(
            "/master/vendor/{$vendor->uuid}/blokir",
            [
                'alasan_pemblokiran' => 'bad',
            ]
        );
        $responseShort->assertSessionHasErrors(['alasan_pemblokiran']);

        $vendor->refresh();
        $this->assertTrue($vendor->status_aktif);
    }

    public function test_cannot_block_already_blocked_vendor(): void
    {
        $vendor = $this->createVendor([
            'status_aktif' => false,
            'alasan_pemblokiran' => 'Sudah diblokir sebelumnya.',
            'blocked_by' => $this->superadmin->id,
            'diblokir_pada' => now(),
        ]);

        $response = $this->actingAs($this->manajemen)->post(
            "/master/vendor/{$vendor->uuid}/blokir",
            [
                'alasan_pemblokiran' => 'Alasan pemblokiran kedua.',
            ]
        );

        $response->assertSessionHasErrors(['alasan_pemblokiran']);
    }

    public function test_non_management_user_cannot_block_vendor(): void
    {
        $vendor = $this->createVendor();

        $response = $this->actingAs($this->buyer)->post(
            "/master/vendor/{$vendor->uuid}/blokir",
            [
                'alasan_pemblokiran' => 'Percobaan pemblokiran oleh buyer.',
            ]
        );

        $response->assertForbidden();

        $vendor->refresh();
        $this->assertTrue($vendor->status_aktif);
    }

    public function test_management_user_can_unblock_blocked_vendor(): void
    {
        $vendor = $this->createVendor([
            'status_aktif' => false,
            'alasan_pemblokiran' => 'Sedang dalam evaluasi audit.',
            'blocked_by' => $this->superadmin->id,
            'diblokir_pada' => now()->subDays(3),
        ]);

        $vendorUser = User::factory()->create([
            'role_id' => 4,
            'vendor_id' => $vendor->id,
            'nama_lengkap' => 'Staff Vendor',
            'email' => 'staff2@mitratani.test',
            'status_aktif' => false,
        ]);

        $response = $this->actingAs($this->manajemen)->post(
            "/master/vendor/{$vendor->uuid}/buka-blokir"
        );

        $response->assertSessionHas('success');

        $vendor->refresh();
        $this->assertTrue($vendor->status_aktif);
        $this->assertNull($vendor->alasan_pemblokiran);
        $this->assertNull($vendor->blocked_by);
        $this->assertNull($vendor->diblokir_pada);

        // Check associated user accounts are reactivated
        $vendorUser->refresh();
        $this->assertTrue($vendorUser->status_aktif);
    }

    public function test_non_management_user_cannot_unblock_vendor(): void
    {
        $vendor = $this->createVendor([
            'status_aktif' => false,
            'alasan_pemblokiran' => 'Sedang dalam evaluasi audit.',
            'blocked_by' => $this->superadmin->id,
            'diblokir_pada' => now(),
        ]);

        $response = $this->actingAs($this->buyer)->post(
            "/master/vendor/{$vendor->uuid}/buka-blokir"
        );

        $response->assertForbidden();

        $vendor->refresh();
        $this->assertFalse($vendor->status_aktif);
    }
}
