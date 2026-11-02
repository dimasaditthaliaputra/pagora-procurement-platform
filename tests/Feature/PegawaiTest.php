<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PerusahaanBuyer;
use App\Models\User;
use App\Models\Vendor;
use App\Notifications\AkunPegawaiDibuatNotification;
use App\Services\PegawaiService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PegawaiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create([
            'role_id' => 1, // Superadmin
            'name' => 'Admin Manajemen',
            'email' => 'admin@perusahaan.com',
            'is_active' => true,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_manajemen_pengguna(): void
    {
        $response = $this->get('/manajemen/pengguna');
        $response->assertRedirect('/login');

        $response = $this->post('/manajemen/pengguna', [
            'name' => 'Budi Purchasing',
            'email' => 'budi@perusahaan.com',
            'role' => 'purchasing',
        ]);
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_internal_employee_index(): void
    {
        // Create 2 internal employees
        User::factory()->create([
            'name' => 'Siti Buyer',
            'email' => 'siti@perusahaan.com',
            'role_id' => 2, // purchasing
            'vendor_id' => null,
            'is_active' => true,
        ]);

        User::factory()->create([
            'name' => 'Joko QC',
            'email' => 'joko@perusahaan.com',
            'role_id' => 3, // qc_gudang
            'vendor_id' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get('/manajemen/pengguna');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Manajemen/Pengguna/Index')
            ->has('pegawai.data', 3) // admin + 2 employees
            ->has('role_options')
            ->has('default_password')
        );
    }

    public function test_can_filter_and_search_pegawai(): void
    {
        User::factory()->create([
            'name' => 'Dimas Purchasing',
            'email' => 'dimas@perusahaan.com',
            'role_id' => 2, // purchasing
            'vendor_id' => null,
            'is_active' => true,
        ]);

        User::factory()->create([
            'name' => 'Enes QC',
            'email' => 'enes@perusahaan.com',
            'role_id' => 3, // qc_gudang
            'vendor_id' => null,
            'is_active' => false,
        ]);

        // Search test
        $response = $this->actingAs($this->admin)->get('/manajemen/pengguna?search=Dimas');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Manajemen/Pengguna/Index')
            ->has('pegawai.data', 1)
            ->where('pegawai.data.0.name', 'Dimas Purchasing')
        );

        // Role filter test
        $response = $this->actingAs($this->admin)->get('/manajemen/pengguna?role=qc_gudang');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Manajemen/Pengguna/Index')
            ->has('pegawai.data', 1)
            ->where('pegawai.data.0.name', 'Enes QC')
        );

        // Status filter test
        $response = $this->actingAs($this->admin)->get('/manajemen/pengguna?is_active=false');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Manajemen/Pengguna/Index')
            ->has('pegawai.data', 1)
            ->where('pegawai.data.0.name', 'Enes QC')
        );
    }

    public function test_can_create_new_employee_with_default_password_and_notification(): void
    {
        Notification::fake();

        $payload = [
            'name' => 'Andi Wijaya',
            'email' => 'andi.wijaya@perusahaan.com',
            'role' => 'purchasing',
        ];

        $response = $this->actingAs($this->admin)->post('/manajemen/pengguna', $payload);

        $response->assertRedirect('/manajemen/pengguna');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'name' => 'Andi Wijaya',
            'email' => 'andi.wijaya@perusahaan.com',
            'role_id' => 2,
            'is_active' => false,
            'email_verified_at' => null,
            'deleted_at' => null,
        ]);

        $createdUser = User::where('email', 'andi.wijaya@perusahaan.com')->first();
        $this->assertNotNull($createdUser);

        Notification::assertSentTo(
            $createdUser,
            AkunPegawaiDibuatNotification::class,
            function (AkunPegawaiDibuatNotification $notification) use ($createdUser) {
                $this->assertNotEmpty($notification->defaultPassword);
                $this->assertGreaterThanOrEqual(8, strlen($notification->defaultPassword));
                $this->assertTrue(Hash::check($notification->defaultPassword, $createdUser->password));

                return str_contains($notification->roleLabel, 'Purchasing');
            }
        );
    }

    public function test_validation_fails_when_form_incomplete_or_email_already_registered(): void
    {
        // 1. Incomplete form
        $response = $this->actingAs($this->admin)->post('/manajemen/pengguna', [
            'name' => '',
            'email' => 'invalid-email',
            'role' => '',
        ]);

        $response->assertSessionHasErrors(['name', 'email', 'role']);

        // 2. Email already registered
        User::factory()->create([
            'email' => 'existing@perusahaan.com',
            'role_id' => 2,
        ]);

        $responseExisting = $this->actingAs($this->admin)->post('/manajemen/pengguna', [
            'name' => 'Pegawai Baru',
            'email' => 'existing@perusahaan.com',
            'role' => 'purchasing',
        ]);

        $responseExisting->assertSessionHasErrors(['email']);
    }

    public function test_can_view_employee_detail_by_uuid(): void
    {
        $pegawai = User::factory()->create([
            'name' => 'Rina QC',
            'email' => 'rina.qc@perusahaan.com',
            'role_id' => 3, // qc_gudang
            'vendor_id' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->getJson("/manajemen/pengguna/{$pegawai->uuid}");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'uuid' => $pegawai->uuid,
                'name' => 'Rina QC',
                'email' => 'rina.qc@perusahaan.com',
                'role' => [
                    'name' => 'qc_gudang',
                    'label' => 'QC Gudang',
                ],
                'is_active' => true,
            ],
        ]);
    }

    public function test_can_update_employee_credentials_and_password(): void
    {
        $pegawai = User::factory()->create([
            'name' => 'Bambang Staff',
            'email' => 'bambang@perusahaan.com',
            'role_id' => 2, // purchasing
            'vendor_id' => null,
            'is_active' => true,
        ]);

        $updatePayload = [
            'name' => 'Bambang Sukses, S.E.',
            'email' => 'bambang.baru@perusahaan.com',
            'role' => 'qc_gudang',
            'password' => 'NewSecretPass123!',
            'is_active' => false,
        ];

        $response = $this->actingAs($this->admin)->put("/manajemen/pengguna/{$pegawai->uuid}", $updatePayload);

        $response->assertRedirect('/manajemen/pengguna');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'uuid' => $pegawai->uuid,
            'name' => 'Bambang Sukses, S.E.',
            'email' => 'bambang.baru@perusahaan.com',
            'role_id' => 3, // qc_gudang
            'is_active' => false,
        ]);

        $updatedUser = $pegawai->fresh();
        $this->assertTrue(Hash::check('NewSecretPass123!', $updatedUser->password));
    }

    public function test_can_soft_delete_employee_account(): void
    {
        $pegawai = User::factory()->create([
            'name' => 'Eko Hendra',
            'email' => 'eko@perusahaan.com',
            'role_id' => 2,
            'vendor_id' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete("/manajemen/pengguna/{$pegawai->uuid}");

        $response->assertRedirect('/manajemen/pengguna');
        $response->assertSessionHas('success');

        // Verify soft delete
        $this->assertSoftDeleted('users', [
            'uuid' => $pegawai->uuid,
            'email' => 'eko@perusahaan.com',
        ]);
    }

    public function test_cannot_delete_own_authenticated_account(): void
    {
        $response = $this->actingAs($this->admin)->delete("/manajemen/pengguna/{$this->admin->uuid}");

        $response->assertSessionHasErrors(['error']);
        $this->assertNotSoftDeleted('users', [
            'id' => $this->admin->id,
        ]);
    }

    public function test_role_options_match_authorization_rules_for_each_role(): void
    {
        $superadmin = $this->admin; // role_id = 1 (superadmin)

        $tenant = PerusahaanBuyer::factory()->create();

        $owner = User::factory()->create([
            'role_id' => 6, // owner
            'tenant_id' => $tenant->id,
            'name' => 'Pak Owner',
            'email' => 'owner@perusahaan.com',
            'is_active' => true,
        ]);

        $manajemen = User::factory()->create([
            'role_id' => 5, // manajemen
            'tenant_id' => $tenant->id,
            'name' => 'Bu Manajemen',
            'email' => 'manajemen@perusahaan.com',
            'is_active' => true,
        ]);

        /** @var PegawaiService $service */
        $service = app(PegawaiService::class);

        // 1. Superadmin has all roles
        $superadminOptions = collect($service->getRoleOptionsForUser($superadmin))->pluck('value')->all();
        $this->assertEquals(['superadmin', 'owner', 'manajemen', 'purchasing', 'qc_gudang', 'vendor'], $superadminOptions);

        // 2. Owner has manajemen, purchasing, qc_gudang
        $ownerOptions = collect($service->getRoleOptionsForUser($owner))->pluck('value')->all();
        $this->assertEquals(['manajemen', 'purchasing', 'qc_gudang'], $ownerOptions);

        // 3. Manajemen cannot manage accounts (UC-08 is Owner only)
        $this->assertSame([], $service->getRoleOptionsForUser($manajemen));
    }

    public function test_superadmin_can_create_any_role(): void
    {
        Notification::fake();

        $rolesToTest = ['owner', 'manajemen', 'purchasing', 'qc_gudang', 'superadmin', 'vendor'];

        foreach ($rolesToTest as $index => $role) {
            $payload = [
                'name' => "User {$role}",
                'email' => "user.{$role}.{$index}@perusahaan.com",
                'role' => $role,
            ];

            $response = $this->actingAs($this->admin)->post('/manajemen/pengguna', $payload);
            $response->assertSessionHasNoErrors();
            $response->assertRedirect('/manajemen/pengguna');

            $this->assertDatabaseHas('users', [
                'email' => "user.{$role}.{$index}@perusahaan.com",
            ]);
        }
    }

    public function test_owner_can_create_manajemen_purchasing_and_qc_gudang_only(): void
    {
        Notification::fake();

        $perusahaan = PerusahaanBuyer::create([
            'company_name' => 'PT Pagora Owner Test',
            'tax_number' => '01.111.222.3-444.000',
            'address' => 'Jl. Industri No. 10',
            'tipe_paket' => 'enterprise',
            'is_active' => true,
        ]);

        $owner = User::factory()->create([
            'role_id' => 6, // owner
            'tenant_id' => $perusahaan->id,
            'name' => 'Pak Owner',
            'email' => 'owner.test@perusahaan.com',
            'is_active' => true,
        ]);

        // Allowed roles for Owner
        $allowedRoles = ['manajemen', 'purchasing', 'qc_gudang'];
        foreach ($allowedRoles as $role) {
            $response = $this->actingAs($owner)->post('/manajemen/pengguna', [
                'name' => "Staff {$role}",
                'email' => "owner.created.{$role}@perusahaan.com",
                'role' => $role,
            ]);

            $response->assertSessionHasNoErrors();
            $response->assertRedirect('/manajemen/pengguna');
            $this->assertDatabaseHas('users', [
                'email' => "owner.created.{$role}@perusahaan.com",
                'tenant_id' => $perusahaan->id,
            ]);
        }

        // Disallowed roles for Owner: superadmin, owner, vendor
        $disallowedRoles = ['superadmin', 'owner', 'vendor'];
        foreach ($disallowedRoles as $role) {
            $response = $this->actingAs($owner)->post('/manajemen/pengguna', [
                'name' => "Disallowed {$role}",
                'email' => "owner.disallowed.{$role}@perusahaan.com",
                'role' => $role,
            ]);

            $response->assertSessionHasErrors(['role']);
            $this->assertDatabaseMissing('users', ['email' => "owner.disallowed.{$role}@perusahaan.com"]);
        }
    }

    public function test_manajemen_cannot_access_or_create_accounts(): void
    {
        $perusahaan = PerusahaanBuyer::factory()->create();

        $manajemen = User::factory()->create([
            'role_id' => 5, // manajemen
            'tenant_id' => $perusahaan->id,
            'is_active' => true,
        ]);

        $this->actingAs($manajemen)->get('/manajemen/pengguna')->assertForbidden();

        $this->actingAs($manajemen)->post('/manajemen/pengguna', [
            'name' => 'Staff Purchasing',
            'email' => 'manajemen.created@perusahaan.com',
            'role' => 'purchasing',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'manajemen.created@perusahaan.com']);
    }

    public function test_perusahaan_buyer_only_sees_and_manages_own_company_users(): void
    {
        Notification::fake();

        $companyA = PerusahaanBuyer::create([
            'company_name' => 'PT Alpha Manufaktur',
            'tax_number' => '11.111.111.1-111.000',
            'address' => 'Surabaya',
            'tipe_paket' => 'enterprise',
            'is_active' => true,
        ]);

        $companyB = PerusahaanBuyer::create([
            'company_name' => 'PT Beta Pangan',
            'tax_number' => '22.222.222.2-222.000',
            'address' => 'Jakarta',
            'tipe_paket' => 'enterprise',
            'is_active' => true,
        ]);

        $ownerA = User::factory()->create([
            'role_id' => 6,
            'tenant_id' => $companyA->id,
            'name' => 'Owner Alpha',
            'email' => 'owner@alpha.test',
        ]);

        $buyerA = User::factory()->create([
            'role_id' => 2,
            'tenant_id' => $companyA->id,
            'name' => 'Buyer Alpha',
            'email' => 'buyer@alpha.test',
        ]);

        $ownerB = User::factory()->create([
            'role_id' => 6,
            'tenant_id' => $companyB->id,
            'name' => 'Owner Beta',
            'email' => 'owner@beta.test',
        ]);

        $buyerB = User::factory()->create([
            'role_id' => 2,
            'tenant_id' => $companyB->id,
            'name' => 'Buyer Beta',
            'email' => 'buyer@beta.test',
        ]);

        // 1. Listing only shows Company A's 2 users (excludes Superadmin and Company B)
        $this->actingAs($ownerA)
            ->get('/manajemen/pengguna')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Manajemen/Pengguna/Index')
                ->has('pegawai.data', 2)
                ->where('is_superadmin', false)
                ->where('current_perusahaan.uuid', $companyA->uuid)
                ->where('perusahaan_filter_options', [])
                ->missing('pegawai.data.0.id')
                ->missing('pegawai.data.0.tenant_id')
            );

        // 2. Spoofing ?perusahaan=CompanyB UUID still only returns Company A's users
        $this->actingAs($ownerA)
            ->get("/manajemen/pengguna?perusahaan={$companyB->uuid}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('pegawai.data', 2));

        // 3. Creating a user while spoofing tenant_uuid of Company B still binds to Company A
        $this->actingAs($ownerA)
            ->post('/manajemen/pengguna', [
                'name' => 'QC Alpha Baru',
                'email' => 'qc.baru@alpha.test',
                'role' => 'qc_gudang',
                'tenant_uuid' => $companyB->uuid,
            ])
            ->assertRedirect('/manajemen/pengguna');

        $this->assertDatabaseHas('users', [
            'email' => 'qc.baru@alpha.test',
            'tenant_id' => $companyA->id,
        ]);

        // 4. Cannot view, update, or delete Company B's user or Superadmin
        $this->actingAs($ownerA)
            ->getJson("/manajemen/pengguna/{$buyerB->uuid}")
            ->assertForbidden();

        $this->actingAs($ownerA)
            ->getJson("/manajemen/pengguna/{$this->admin->uuid}")
            ->assertForbidden();

        $this->actingAs($ownerA)
            ->putJson("/manajemen/pengguna/{$buyerB->uuid}", [
                'name' => 'Bajak Beta',
                'email' => 'buyer@beta.test',
                'role' => 'purchasing',
                'is_active' => false,
            ])
            ->assertForbidden();

        $this->actingAs($ownerA)
            ->deleteJson("/manajemen/pengguna/{$buyerB->uuid}")
            ->assertForbidden();

        $this->assertNotSoftDeleted('users', ['uuid' => $buyerB->uuid]);

        // 5. Can update and delete own company's subordinate user
        $this->actingAs($ownerA)
            ->put("/manajemen/pengguna/{$buyerA->uuid}", [
                'name' => 'Buyer Alpha Updated',
                'email' => 'buyer@alpha.test',
                'role' => 'purchasing',
                'is_active' => true,
            ])
            ->assertRedirect('/manajemen/pengguna');

        $this->assertDatabaseHas('users', [
            'uuid' => $buyerA->uuid,
            'name' => 'Buyer Alpha Updated',
            'tenant_id' => $companyA->id,
        ]);

        // Suppress unused variable notice for $ownerB while keeping realistic multi-tenant fixture
        $this->assertNotNull($ownerB->uuid);
    }

    public function test_manajemen_cannot_modify_or_delete_owner_or_fellow_manajemen_in_same_company(): void
    {
        $company = PerusahaanBuyer::create([
            'company_name' => 'PT Hirarki Test',
            'tax_number' => '33.333.333.3-333.000',
            'address' => 'Bandung',
            'tipe_paket' => 'enterprise',
            'is_active' => true,
        ]);

        $owner = User::factory()->create([
            'role_id' => 6,
            'tenant_id' => $company->id,
            'email' => 'owner@hirarki.test',
        ]);

        $manajemen1 = User::factory()->create([
            'role_id' => 5,
            'tenant_id' => $company->id,
            'email' => 'manajemen1@hirarki.test',
        ]);

        $manajemen2 = User::factory()->create([
            'role_id' => 5,
            'tenant_id' => $company->id,
            'email' => 'manajemen2@hirarki.test',
        ]);

        $this->actingAs($manajemen1)
            ->putJson("/manajemen/pengguna/{$owner->uuid}", [
                'name' => 'Ubah Owner',
                'email' => 'owner@hirarki.test',
                'role' => 'owner',
                'is_active' => false,
            ])
            ->assertForbidden();

        $this->actingAs($manajemen1)
            ->deleteJson("/manajemen/pengguna/{$owner->uuid}")
            ->assertForbidden();

        $this->actingAs($manajemen1)
            ->deleteJson("/manajemen/pengguna/{$manajemen2->uuid}")
            ->assertForbidden();
    }

    public function test_vendor_company_only_sees_and_manages_own_vendor_users(): void
    {
        Notification::fake();

        $vendor1 = Vendor::create([
            'slug' => 'vendor-satu',
            'company_name' => 'CV Vendor Satu',
            'contact_email' => 'kontak@vendor1.test',
            'phone' => '0811111111',
            'address' => 'Malang',
            'kategori_komoditas' => 'Pangan Pokok',
            'verification_status' => 'approved',
            'is_active' => true,
        ]);

        $vendor2 = Vendor::create([
            'slug' => 'vendor-dua',
            'company_name' => 'CV Vendor Dua',
            'contact_email' => 'kontak@vendor2.test',
            'phone' => '0822222222',
            'address' => 'Kediri',
            'kategori_komoditas' => 'Pangan Pokok',
            'verification_status' => 'approved',
            'is_active' => true,
        ]);

        $userV1 = User::factory()->create([
            'role_id' => 4,
            'vendor_id' => $vendor1->id,
            'email' => 'admin@vendor1.test',
        ]);

        $userV2 = User::factory()->create([
            'role_id' => 4,
            'vendor_id' => $vendor2->id,
            'email' => 'admin@vendor2.test',
        ]);

        $this->actingAs($userV1)
            ->get('/manajemen/pengguna')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('pegawai.data', 1)
                ->where('pegawai.data.0.uuid', $userV1->uuid)
            );

        $this->actingAs($userV1)
            ->post('/manajemen/pengguna', [
                'name' => 'Rekan Vendor Satu',
                'email' => 'staff@vendor1.test',
                'role' => 'vendor',
                'vendor_uuid' => $vendor2->uuid,
            ])
            ->assertRedirect('/manajemen/pengguna');

        $this->assertDatabaseHas('users', [
            'email' => 'staff@vendor1.test',
            'vendor_id' => $vendor1->id,
            'tenant_id' => null,
        ]);

        $this->actingAs($userV1)
            ->deleteJson("/manajemen/pengguna/{$userV2->uuid}")
            ->assertForbidden();
    }

    public function test_superadmin_can_filter_and_manage_users_across_all_companies(): void
    {
        Notification::fake();

        $companyA = PerusahaanBuyer::create([
            'company_name' => 'PT Super Buyer A',
            'tax_number' => '44.444.444.4-444.000',
            'address' => 'Semarang',
            'tipe_paket' => 'enterprise',
            'is_active' => true,
        ]);

        $vendorA = Vendor::create([
            'slug' => 'pt-super-vendor-a',
            'company_name' => 'PT Super Vendor A',
            'contact_email' => 'info@supervendora.test',
            'phone' => '0833333333',
            'address' => 'Solo',
            'kategori_komoditas' => 'Pangan Pokok',
            'verification_status' => 'approved',
            'is_active' => true,
        ]);

        // Superadmin creates a Buyer Company Owner assigned to Company A via UUID
        $this->actingAs($this->admin)
            ->post('/manajemen/pengguna', [
                'name' => 'Owner Super Buyer A',
                'email' => 'owner@superbuyera.test',
                'role' => 'owner',
                'tenant_uuid' => $companyA->uuid,
            ])
            ->assertRedirect('/manajemen/pengguna');

        $this->assertDatabaseHas('users', [
            'email' => 'owner@superbuyera.test',
            'tenant_id' => $companyA->id,
            'vendor_id' => null,
        ]);

        // Superadmin creates a Vendor account assigned to Vendor A via UUID
        $this->actingAs($this->admin)
            ->post('/manajemen/pengguna', [
                'name' => 'PIC Super Vendor A',
                'email' => 'pic@supervendora.test',
                'role' => 'vendor',
                'vendor_uuid' => $vendorA->uuid,
            ])
            ->assertRedirect('/manajemen/pengguna');

        $this->assertDatabaseHas('users', [
            'email' => 'pic@supervendora.test',
            'vendor_id' => $vendorA->id,
            'tenant_id' => null,
        ]);

        // Filter: All companies (superadmin + owner + vendor = 3)
        $this->actingAs($this->admin)
            ->get('/manajemen/pengguna')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('pegawai.data', 3)
                ->where('is_superadmin', true)
                ->has('perusahaan_filter_options', 2)
            );

        // Filter: Buyer company A only
        $this->actingAs($this->admin)
            ->get("/manajemen/pengguna?perusahaan={$companyA->uuid}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('pegawai.data', 1)
                ->where('pegawai.data.0.email', 'owner@superbuyera.test')
                ->where('pegawai.data.0.perusahaan.uuid', $companyA->uuid)
            );

        // Filter: Vendor company A only
        $this->actingAs($this->admin)
            ->get("/manajemen/pengguna?perusahaan={$vendorA->uuid}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('pegawai.data', 1)
                ->where('pegawai.data.0.email', 'pic@supervendora.test')
                ->where('pegawai.data.0.perusahaan.uuid', $vendorA->uuid)
            );

        // Filter: Tanpa perusahaan (Internal Platform)
        $this->actingAs($this->admin)
            ->get('/manajemen/pengguna?perusahaan=tanpa_perusahaan')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('pegawai.data', 1)
                ->where('pegawai.data.0.uuid', $this->admin->uuid)
            );
    }

    public function test_staff_roles_purchasing_and_qc_cannot_access_manajemen_pengguna(): void
    {
        $company = PerusahaanBuyer::create([
            'company_name' => 'PT Staff Test',
            'tax_number' => '55.555.555.5-555.000',
            'address' => 'Yogyakarta',
            'tipe_paket' => 'enterprise',
            'is_active' => true,
        ]);

        $buyer = User::factory()->create([
            'role_id' => 2,
            'tenant_id' => $company->id,
            'email' => 'staff.buyer@test.com',
        ]);

        $qc = User::factory()->create([
            'role_id' => 3,
            'tenant_id' => $company->id,
            'email' => 'staff.qc@test.com',
        ]);

        $this->actingAs($buyer)->getJson('/manajemen/pengguna')->assertForbidden();
        $this->actingAs($qc)->getJson('/manajemen/pengguna')->assertForbidden();
    }

    public function test_unique_default_password_is_generated_for_each_new_employee(): void
    {
        Notification::fake();

        $this->actingAs($this->admin)->post('/manajemen/pengguna', [
            'name' => 'Pegawai Satu',
            'email' => 'satu@perusahaan.com',
            'role' => 'purchasing',
        ])->assertRedirect();

        $this->actingAs($this->admin)->post('/manajemen/pengguna', [
            'name' => 'Pegawai Dua',
            'email' => 'dua@perusahaan.com',
            'role' => 'qc_gudang',
        ])->assertRedirect();

        $user1 = User::where('email', 'satu@perusahaan.com')->first();
        $user2 = User::where('email', 'dua@perusahaan.com')->first();

        $password1 = null;
        $password2 = null;

        Notification::assertSentTo($user1, AkunPegawaiDibuatNotification::class, function ($n) use (&$password1) {
            $password1 = $n->defaultPassword;

            return true;
        });

        Notification::assertSentTo($user2, AkunPegawaiDibuatNotification::class, function ($n) use (&$password2) {
            $password2 = $n->defaultPassword;

            return true;
        });

        $this->assertNotEmpty($password1);
        $this->assertNotEmpty($password2);
        $this->assertNotEquals($password1, $password2);
        $this->assertTrue(Hash::check($password1, $user1->password));
        $this->assertTrue(Hash::check($password2, $user2->password));
    }

    public function test_owner_can_invite_employee_and_notification_includes_company_name(): void
    {
        Notification::fake();

        $company = PerusahaanBuyer::create([
            'company_name' => 'PT Nusantara Sejahtera',
            'tax_number' => '77.777.777.7-777.000',
            'address' => 'Surabaya',
            'tipe_paket' => 'enterprise',
            'is_active' => true,
        ]);

        $owner = User::factory()->create([
            'role_id' => 6, // owner
            'tenant_id' => $company->id,
            'name' => 'Bapak Direktur',
            'email' => 'direktur@nusantara.test',
        ]);

        $response = $this->actingAs($owner)->post('/manajemen/pengguna', [
            'name' => 'Staf Baru Purchasing',
            'email' => 'staf.baru@nusantara.test',
            'role' => 'purchasing',
        ]);

        $response->assertRedirect('/manajemen/pengguna');
        $response->assertSessionHas('success');

        $newUser = User::where('email', 'staf.baru@nusantara.test')->first();
        $this->assertNotNull($newUser);
        $this->assertEquals($company->id, $newUser->tenant_id);

        Notification::assertSentTo(
            $newUser,
            AkunPegawaiDibuatNotification::class,
            function (AkunPegawaiDibuatNotification $notification) {
                return $notification->companyName === 'PT Nusantara Sejahtera'
                    && str_contains($notification->roleLabel, 'Purchasing');
            }
        );

        $notification = new AkunPegawaiDibuatNotification(
            $newUser,
            'Pgr-AbCdEf-1234',
            'Purchasing / Finance',
            $company->company_name
        );
        $mail = $notification->toMail($newUser);

        $this->assertStringContainsString('Undangan Bergabung & Kredensial Akun Internal - PT Nusantara Sejahtera', $mail->subject);
        $renderedHtml = $mail->render();
        $this->assertStringContainsString('PT Nusantara Sejahtera', $renderedHtml);
        $this->assertStringContainsString('Pgr-AbCdEf-1234', $renderedHtml);
        $this->assertStringContainsString('Purchasing / Finance', $renderedHtml);
        $this->assertStringContainsString('staf.baru@nusantara.test', $renderedHtml);
    }
}
