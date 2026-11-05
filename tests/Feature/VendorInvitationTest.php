<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\StatusKerjaSama;
use App\Models\KerjaSama;
use App\Models\PerusahaanBuyer;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VendorInvitationTest extends TestCase
{
    use RefreshDatabase;

    private PerusahaanBuyer $tenant;
    private Vendor $vendor;
    private Vendor $otherVendor;
    private User $vendorUser;
    private User $otherVendorUser;
    private User $ownerUser;
    private User $managementUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->tenant = PerusahaanBuyer::factory()->create();

        $this->vendor = $this->createVendor(
            'PT Vendor Utama',
            'vendor-utama',
            'utama@vendor.test'
        );

        $this->otherVendor = $this->createVendor(
            'PT Vendor Lain',
            'vendor-lain',
            'lain@vendor.test'
        );

        $vendorRole = Role::where('name', RoleName::Vendor->value)->firstOrFail();
        $ownerRole = Role::where('name', RoleName::Owner->value)->firstOrFail();
        $managementRole = Role::where('name', RoleName::Manajemen->value)->firstOrFail();

        $this->vendorUser = User::factory()->create([
            'role_id' => $vendorRole->id,
            'vendor_id' => $this->vendor->id,
            'tenant_id' => null,
            'is_active' => true,
        ]);

        $this->otherVendorUser = User::factory()->create([
            'role_id' => $vendorRole->id,
            'vendor_id' => $this->otherVendor->id,
            'tenant_id' => null,
            'is_active' => true,
        ]);

        $this->ownerUser = User::factory()->create([
            'role_id' => $ownerRole->id,
            'tenant_id' => $this->tenant->id,
            'vendor_id' => null,
            'is_active' => true,
        ]);

        $this->managementUser = User::factory()->create([
            'role_id' => $managementRole->id,
            'tenant_id' => $this->tenant->id,
            'vendor_id' => null,
            'is_active' => true,
        ]);
    }

    private function createVendor(
        string $name,
        string $slug,
        string $email
    ): Vendor {
        return Vendor::create([
            'company_name' => $name,
            'slug' => $slug,
            'contact_email' => $email,
            'phone' => '081234567890',
            'address' => 'Jl. Pengujian No. 1',
            'kategori_komoditas' => 'Pangan Pokok',
            'verification_status' => 'approved',
            'is_active' => true,
        ]);
    }

    private function createInvitation(
        ?Vendor $vendor = null,
        StatusKerjaSama $status = StatusKerjaSama::Pending,
        string $requestedBy = 'tenant',
        ?PerusahaanBuyer $tenant = null
    ): KerjaSama {
        return KerjaSama::create([
            'tenant_id' => ($tenant ?? $this->tenant)->id,
            'vendor_id' => ($vendor ?? $this->vendor)->id,
            'requested_by' => $requestedBy,
            'status' => $status,
        ]);
    }

    public function test_vendor_only_sees_its_own_pending_invitations(): void
    {
        $this->createInvitation();

        $this->createInvitation($this->otherVendor);

        // Gunakan tenant berbeda agar tidak melanggar unique tenant_id + vendor_id.
        $anotherTenant = PerusahaanBuyer::factory()->create();

        $this->createInvitation(
            $this->vendor,
            StatusKerjaSama::Agreed,
            'tenant',
            $anotherTenant
        );

        $response = $this->actingAs($this->vendorUser)
            ->get('/kerja-sama/undangan-masuk');

        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('KerjaSama/UndanganMasuk/Index')
            ->has('invitations.data', 1)
        );
    }

    public function test_vendor_can_view_its_own_invitation_detail(): void
    {
        $invitation = $this->createInvitation();

        $response = $this->actingAs($this->vendorUser)
            ->get("/kerja-sama/undangan-masuk/{$invitation->uuid}");

        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('KerjaSama/UndanganMasuk/Show')
            ->where('invitation.uuid', $invitation->uuid)
        );
    }

    public function test_vendor_can_accept_invitation_and_buyer_receives_notifications(): void
    {
        $invitation = $this->createInvitation();

        $response = $this->actingAs($this->vendorUser)
            ->from('/kerja-sama/undangan-masuk')
            ->post("/kerja-sama/undangan-masuk/{$invitation->uuid}/tanggapi", [
                'action' => 'accept',
            ]);

        $response->assertRedirect('/kerja-sama/undangan-masuk');

        $this->assertSame(
            StatusKerjaSama::Agreed,
            $invitation->fresh()->status
        );

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->ownerUser->id,
            'type' => 'partnership',
            'title' => 'Undangan Kerja Sama Diterima',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->managementUser->id,
            'type' => 'partnership',
            'title' => 'Undangan Kerja Sama Diterima',
        ]);
    }

    public function test_vendor_can_reject_invitation_with_reason(): void
    {
        $invitation = $this->createInvitation();

        $response = $this->actingAs($this->vendorUser)
            ->from('/kerja-sama/undangan-masuk')
            ->post("/kerja-sama/undangan-masuk/{$invitation->uuid}/tanggapi", [
                'action' => 'reject',
                'rejection_reason' => 'Belum dapat memenuhi kebutuhan kerja sama.',
            ]);

        $response->assertRedirect('/kerja-sama/undangan-masuk');

        $this->assertSame(
            StatusKerjaSama::Rejected,
            $invitation->fresh()->status
        );

        $this->assertSame(
            'Belum dapat memenuhi kebutuhan kerja sama.',
            $invitation->fresh()->rejection_reason
        );

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->ownerUser->id,
            'type' => 'partnership',
            'title' => 'Undangan Kerja Sama Ditolak',
        ]);
    }

    public function test_vendor_cannot_access_another_vendors_invitation(): void
    {
        $invitation = $this->createInvitation($this->otherVendor);

        $response = $this->actingAs($this->vendorUser)
            ->get("/kerja-sama/undangan-masuk/{$invitation->uuid}");

        $response->assertNotFound();
    }

    public function test_vendor_cannot_respond_to_another_vendors_invitation(): void
    {
        $invitation = $this->createInvitation($this->otherVendor);

        $response = $this->actingAs($this->vendorUser)
            ->post("/kerja-sama/undangan-masuk/{$invitation->uuid}/tanggapi", [
                'action' => 'accept',
            ]);

        $response->assertNotFound();

        $this->assertSame(
            StatusKerjaSama::Pending,
            $invitation->fresh()->status
        );
    }

    public function test_vendor_cannot_respond_to_an_invitation_twice(): void
    {
        $invitation = $this->createInvitation(
            $this->vendor,
            StatusKerjaSama::Agreed
        );

        $response = $this->actingAs($this->vendorUser)
            ->from('/kerja-sama/undangan-masuk')
            ->post("/kerja-sama/undangan-masuk/{$invitation->uuid}/tanggapi", [
                'action' => 'reject',
                'rejection_reason' => 'Terlambat.',
            ]);

        $response->assertSessionHasErrors('status');

        $this->assertSame(
            StatusKerjaSama::Agreed,
            $invitation->fresh()->status
        );
    }

    public function test_reject_requires_a_reason(): void
    {
        $invitation = $this->createInvitation();

        $response = $this->actingAs($this->vendorUser)
            ->from('/kerja-sama/undangan-masuk')
            ->post("/kerja-sama/undangan-masuk/{$invitation->uuid}/tanggapi", [
                'action' => 'reject',
                'rejection_reason' => '',
            ]);

        $response->assertSessionHasErrors('rejection_reason');

        $this->assertSame(
            StatusKerjaSama::Pending,
            $invitation->fresh()->status
        );
    }
}
