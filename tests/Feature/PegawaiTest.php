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
            'nama_lengkap' => 'Admin Manajemen',
            'email' => 'admin@perusahaan.com',
            'status_aktif' => true,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_manajemen_pengguna(): void
    {
        $response = $this->get('/manajemen/pengguna');
        $response->assertRedirect('/login');

        $response = $this->post('/manajemen/pengguna', [
            'nama_lengkap' => 'Budi Purchasing',
            'email' => 'budi@perusahaan.com',
            'role' => 'buyer',
        ]);
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_internal_employee_index(): void
    {
        // Create 2 internal employees
        User::factory()->create([
            'nama_lengkap' => 'Siti Buyer',
            'email' => 'siti@perusahaan.com',
            'role_id' => 2, // buyer
            'vendor_id' => null,
            'status_aktif' => true,
        ]);

        User::factory()->create([
            'nama_lengkap' => 'Joko QC',
            'email' => 'joko@perusahaan.com',
            'role_id' => 3, // qc_gudang
            'vendor_id' => null,
            'status_aktif' => true,
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
            'nama_lengkap' => 'Dimas Purchasing',
            'email' => 'dimas@perusahaan.com',
            'role_id' => 2, // buyer
            'vendor_id' => null,
            'status_aktif' => true,
        ]);

        User::factory()->create([
            'nama_lengkap' => 'Enes QC',
            'email' => 'enes@perusahaan.com',
            'role_id' => 3, // qc_gudang
            'vendor_id' => null,
            'status_aktif' => false,
        ]);

        // Search test
        $response = $this->actingAs($this->admin)->get('/manajemen/pengguna?search=Dimas');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Manajemen/Pengguna/Index')
            ->has('pegawai.data', 1)
            ->where('pegawai.data.0.nama_lengkap', 'Dimas Purchasing')
        );

        // Role filter test
        $response = $this->actingAs($this->admin)->get('/manajemen/pengguna?role=qc_gudang');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Manajemen/Pengguna/Index')
            ->has('pegawai.data', 1)
            ->where('pegawai.data.0.nama_lengkap', 'Enes QC')
        );

        // Status filter test
        $response = $this->actingAs($this->admin)->get('/manajemen/pengguna?status_aktif=false');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Manajemen/Pengguna/Index')
            ->has('pegawai.data', 1)
            ->where('pegawai.data.0.nama_lengkap', 'Enes QC')
        );
    }

    public function test_can_create_new_employee_with_default_password_and_notification(): void
    {
        Notification::fake();

        $payload = [
            'nama_lengkap' => 'Andi Wijaya',
            'email' => 'andi.wijaya@perusahaan.com',
            'role' => 'buyer',
        ];

        $response = $this->actingAs($this->admin)->post('/manajemen/pengguna', $payload);

        $response->assertRedirect('/manajemen/pengguna');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'nama_lengkap' => 'Andi Wijaya',
            'email' => 'andi.wijaya@perusahaan.com',
            'role_id' => 2,
            'status_aktif' => false,
            'email_verified_at' => null,
            'deleted_at' => null,
        ]);

        $createdUser = User::where('email', 'andi.wijaya@perusahaan.com')->first();
        $this->assertNotNull($createdUser);
        $this->assertTrue(Hash::check(PegawaiService::DEFAULT_PASSWORD, $createdUser->password));

        Notification::assertSentTo(
            $createdUser,
            AkunPegawaiDibuatNotification::class,
            function (AkunPegawaiDibuatNotification $notification) {
                return $notification->defaultPassword === PegawaiService::DEFAULT_PASSWORD
                    && str_contains($notification->roleLabel, 'Buyer');
            }
        );
    }

    public function test_validation_fails_when_form_incomplete_or_email_already_registered(): void
    {
        // 1. Incomplete form
        $response = $this->actingAs($this->admin)->post('/manajemen/pengguna', [
            'nama_lengkap' => '',
            'email' => 'invalid-email',
            'role' => '',
        ]);

        $response->assertSessionHasErrors(['nama_lengkap', 'email', 'role']);

        // 2. Email already registered
        User::factory()->create([
            'email' => 'existing@perusahaan.com',
            'role_id' => 2,
        ]);

        $responseExisting = $this->actingAs($this->admin)->post('/manajemen/pengguna', [
            'nama_lengkap' => 'Pegawai Baru',
            'email' => 'existing@perusahaan.com',
            'role' => 'buyer',
        ]);

        $responseExisting->assertSessionHasErrors(['email']);
    }

    public function test_can_view_employee_detail_by_uuid(): void
    {
        $pegawai = User::factory()->create([
            'nama_lengkap' => 'Rina QC',
            'email' => 'rina.qc@perusahaan.com',
            'role_id' => 3, // qc_gudang
            'vendor_id' => null,
            'status_aktif' => true,
        ]);

        $response = $this->actingAs($this->admin)->getJson("/manajemen/pengguna/{$pegawai->uuid}");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'uuid' => $pegawai->uuid,
                'nama_lengkap' => 'Rina QC',
                'email' => 'rina.qc@perusahaan.com',
                'role' => [
                    'name' => 'qc_gudang',
                    'label' => 'QC Gudang',
                ],
                'status_aktif' => true,
            ],
        ]);
    }

    public function test_can_update_employee_credentials_and_password(): void
    {
        $pegawai = User::factory()->create([
            'nama_lengkap' => 'Bambang Staff',
            'email' => 'bambang@perusahaan.com',
            'role_id' => 2, // buyer
            'vendor_id' => null,
            'status_aktif' => true,
        ]);

        $updatePayload = [
            'nama_lengkap' => 'Bambang Sukses, S.E.',
            'email' => 'bambang.baru@perusahaan.com',
            'role' => 'qc_gudang',
            'password' => 'NewSecretPass123!',
            'status_aktif' => false,
        ];

        $response = $this->actingAs($this->admin)->put("/manajemen/pengguna/{$pegawai->uuid}", $updatePayload);

        $response->assertRedirect('/manajemen/pengguna');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'uuid' => $pegawai->uuid,
            'nama_lengkap' => 'Bambang Sukses, S.E.',
            'email' => 'bambang.baru@perusahaan.com',
            'role_id' => 3, // qc_gudang
            'status_aktif' => false,
        ]);

        $updatedUser = $pegawai->fresh();
        $this->assertTrue(Hash::check('NewSecretPass123!', $updatedUser->password));
    }

    public function test_can_soft_delete_employee_account(): void
    {
        $pegawai = User::factory()->create([
            'nama_lengkap' => 'Eko Hendra',
            'email' => 'eko@perusahaan.com',
            'role_id' => 2,
            'vendor_id' => null,
            'status_aktif' => true,
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

        $owner = User::factory()->create([
            'role_id' => 6, // owner
            'nama_lengkap' => 'Pak Owner',
            'email' => 'owner@perusahaan.com',
            'status_aktif' => true,
        ]);

        $manajemen = User::factory()->create([
            'role_id' => 5, // manajemen
            'nama_lengkap' => 'Bu Manajemen',
            'email' => 'manajemen@perusahaan.com',
            'status_aktif' => true,
        ]);

        /** @var PegawaiService $service */
        $service = app(PegawaiService::class);

        // 1. Superadmin has all roles
        $superadminOptions = collect($service->getRoleOptionsForUser($superadmin))->pluck('value')->all();
        $this->assertEquals(['superadmin', 'owner', 'manajemen', 'buyer', 'qc_gudang', 'vendor'], $superadminOptions);

        // 2. Owner has manajemen, buyer, qc_gudang
        $ownerOptions = collect($service->getRoleOptionsForUser($owner))->pluck('value')->all();
        $this->assertEquals(['manajemen', 'buyer', 'qc_gudang'], $ownerOptions);

        // 3. Manajemen has buyer, qc_gudang
        $manajemenOptions = collect($service->getRoleOptionsForUser($manajemen))->pluck('value')->all();
        $this->assertEquals(['buyer', 'qc_gudang'], $manajemenOptions);
    }

    public function test_superadmin_can_create_any_role(): void
    {
        Notification::fake();

        $rolesToTest = ['owner', 'manajemen', 'buyer', 'qc_gudang', 'superadmin', 'vendor'];

        foreach ($rolesToTest as $index => $role) {
            $payload = [
                'nama_lengkap' => "User {$role}",
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

    public function test_owner_can_create_manajemen_buyer_and_qc_gudang_only(): void
    {
        Notification::fake();

        $perusahaan = PerusahaanBuyer::create([
            'nama_perusahaan' => 'PT Pagora Owner Test',
            'nomor_npwp' => '01.111.222.3-444.000',
            'alamat' => 'Jl. Industri No. 10',
            'tipe_paket' => 'enterprise',
            'status_aktif' => true,
        ]);

        $owner = User::factory()->create([
            'role_id' => 6, // owner
            'perusahaan_id' => $perusahaan->id,
            'nama_lengkap' => 'Pak Owner',
            'email' => 'owner.test@perusahaan.com',
            'status_aktif' => true,
        ]);

        // Allowed roles for Owner
        $allowedRoles = ['manajemen', 'buyer', 'qc_gudang'];
        foreach ($allowedRoles as $role) {
            $response = $this->actingAs($owner)->post('/manajemen/pengguna', [
                'nama_lengkap' => "Staff {$role}",
                'email' => "owner.created.{$role}@perusahaan.com",
                'role' => $role,
            ]);

            $response->assertSessionHasNoErrors();
            $response->assertRedirect('/manajemen/pengguna');
            $this->assertDatabaseHas('users', [
                'email' => "owner.created.{$role}@perusahaan.com",
                'perusahaan_id' => $perusahaan->id,
            ]);
        }

        // Disallowed roles for Owner: superadmin, owner, vendor
        $disallowedRoles = ['superadmin', 'owner', 'vendor'];
        foreach ($disallowedRoles as $role) {
            $response = $this->actingAs($owner)->post('/manajemen/pengguna', [
                'nama_lengkap' => "Disallowed {$role}",
                'email' => "owner.disallowed.{$role}@perusahaan.com",
                'role' => $role,
            ]);

            $response->assertSessionHasErrors(['role']);
            $this->assertDatabaseMissing('users', ['email' => "owner.disallowed.{$role}@perusahaan.com"]);
        }
    }

    public function test_manajemen_can_create_buyer_and_qc_gudang_only(): void
    {
        Notification::fake();

        $perusahaan = PerusahaanBuyer::create([
            'nama_perusahaan' => 'PT Pagora Manajemen Test',
            'nomor_npwp' => '02.111.222.3-444.000',
            'alamat' => 'Jl. Industri No. 20',
            'tipe_paket' => 'enterprise',
            'status_aktif' => true,
        ]);

        $manajemen = User::factory()->create([
            'role_id' => 5, // manajemen
            'perusahaan_id' => $perusahaan->id,
            'nama_lengkap' => 'Bu Manajemen',
            'email' => 'manajemen.test@perusahaan.com',
            'status_aktif' => true,
        ]);

        // Allowed roles for Manajemen
        $allowedRoles = ['buyer', 'qc_gudang'];
        foreach ($allowedRoles as $role) {
            $response = $this->actingAs($manajemen)->post('/manajemen/pengguna', [
                'nama_lengkap' => "Staff {$role}",
                'email' => "manajemen.created.{$role}@perusahaan.com",
                'role' => $role,
            ]);

            $response->assertSessionHasNoErrors();
            $response->assertRedirect('/manajemen/pengguna');
            $this->assertDatabaseHas('users', [
                'email' => "manajemen.created.{$role}@perusahaan.com",
                'perusahaan_id' => $perusahaan->id,
            ]);
        }

        // Disallowed roles for Manajemen: manajemen, owner, superadmin, vendor
        $disallowedRoles = ['manajemen', 'owner', 'superadmin', 'vendor'];
        foreach ($disallowedRoles as $role) {
            $response = $this->actingAs($manajemen)->post('/manajemen/pengguna', [
                'nama_lengkap' => "Disallowed {$role}",
                'email' => "manajemen.disallowed.{$role}@perusahaan.com",
                'role' => $role,
            ]);

            $response->assertSessionHasErrors(['role']);
            $this->assertDatabaseMissing('users', ['email' => "manajemen.disallowed.{$role}@perusahaan.com"]);
        }
    }

    public function test_perusahaan_buyer_only_sees_and_manages_own_company_users(): void
    {
        Notification::fake();

        $companyA = PerusahaanBuyer::create([
            'nama_perusahaan' => 'PT Alpha Manufaktur',
            'nomor_npwp' => '11.111.111.1-111.000',
            'alamat' => 'Surabaya',
            'tipe_paket' => 'enterprise',
            'status_aktif' => true,
        ]);

        $companyB = PerusahaanBuyer::create([
            'nama_perusahaan' => 'PT Beta Pangan',
            'nomor_npwp' => '22.222.222.2-222.000',
            'alamat' => 'Jakarta',
            'tipe_paket' => 'enterprise',
            'status_aktif' => true,
        ]);

        $ownerA = User::factory()->create([
            'role_id' => 6,
            'perusahaan_id' => $companyA->id,
            'nama_lengkap' => 'Owner Alpha',
            'email' => 'owner@alpha.test',
        ]);

        $buyerA = User::factory()->create([
            'role_id' => 2,
            'perusahaan_id' => $companyA->id,
            'nama_lengkap' => 'Buyer Alpha',
            'email' => 'buyer@alpha.test',
        ]);

        $ownerB = User::factory()->create([
            'role_id' => 6,
            'perusahaan_id' => $companyB->id,
            'nama_lengkap' => 'Owner Beta',
            'email' => 'owner@beta.test',
        ]);

        $buyerB = User::factory()->create([
            'role_id' => 2,
            'perusahaan_id' => $companyB->id,
            'nama_lengkap' => 'Buyer Beta',
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
                ->missing('pegawai.data.0.perusahaan_id')
            );

        // 2. Spoofing ?perusahaan=CompanyB UUID still only returns Company A's users
        $this->actingAs($ownerA)
            ->get("/manajemen/pengguna?perusahaan={$companyB->uuid}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('pegawai.data', 2));

        // 3. Creating a user while spoofing perusahaan_uuid of Company B still binds to Company A
        $this->actingAs($ownerA)
            ->post('/manajemen/pengguna', [
                'nama_lengkap' => 'QC Alpha Baru',
                'email' => 'qc.baru@alpha.test',
                'role' => 'qc_gudang',
                'perusahaan_uuid' => $companyB->uuid,
            ])
            ->assertRedirect('/manajemen/pengguna');

        $this->assertDatabaseHas('users', [
            'email' => 'qc.baru@alpha.test',
            'perusahaan_id' => $companyA->id,
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
                'nama_lengkap' => 'Bajak Beta',
                'email' => 'buyer@beta.test',
                'role' => 'buyer',
                'status_aktif' => false,
            ])
            ->assertForbidden();

        $this->actingAs($ownerA)
            ->deleteJson("/manajemen/pengguna/{$buyerB->uuid}")
            ->assertForbidden();

        $this->assertNotSoftDeleted('users', ['uuid' => $buyerB->uuid]);

        // 5. Can update and delete own company's subordinate user
        $this->actingAs($ownerA)
            ->put("/manajemen/pengguna/{$buyerA->uuid}", [
                'nama_lengkap' => 'Buyer Alpha Updated',
                'email' => 'buyer@alpha.test',
                'role' => 'buyer',
                'status_aktif' => true,
            ])
            ->assertRedirect('/manajemen/pengguna');

        $this->assertDatabaseHas('users', [
            'uuid' => $buyerA->uuid,
            'nama_lengkap' => 'Buyer Alpha Updated',
            'perusahaan_id' => $companyA->id,
        ]);

        // Suppress unused variable notice for $ownerB while keeping realistic multi-tenant fixture
        $this->assertNotNull($ownerB->uuid);
    }

    public function test_manajemen_cannot_modify_or_delete_owner_or_fellow_manajemen_in_same_company(): void
    {
        $company = PerusahaanBuyer::create([
            'nama_perusahaan' => 'PT Hirarki Test',
            'nomor_npwp' => '33.333.333.3-333.000',
            'alamat' => 'Bandung',
            'tipe_paket' => 'enterprise',
            'status_aktif' => true,
        ]);

        $owner = User::factory()->create([
            'role_id' => 6,
            'perusahaan_id' => $company->id,
            'email' => 'owner@hirarki.test',
        ]);

        $manajemen1 = User::factory()->create([
            'role_id' => 5,
            'perusahaan_id' => $company->id,
            'email' => 'manajemen1@hirarki.test',
        ]);

        $manajemen2 = User::factory()->create([
            'role_id' => 5,
            'perusahaan_id' => $company->id,
            'email' => 'manajemen2@hirarki.test',
        ]);

        $this->actingAs($manajemen1)
            ->putJson("/manajemen/pengguna/{$owner->uuid}", [
                'nama_lengkap' => 'Ubah Owner',
                'email' => 'owner@hirarki.test',
                'role' => 'owner',
                'status_aktif' => false,
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
            'nama_perusahaan' => 'CV Vendor Satu',
            'email_kontak' => 'kontak@vendor1.test',
            'no_telepon' => '0811111111',
            'alamat' => 'Malang',
            'kategori_komoditas' => 'Pangan Pokok',
            'status_verifikasi' => 'approved',
            'status_aktif' => true,
        ]);

        $vendor2 = Vendor::create([
            'slug' => 'vendor-dua',
            'nama_perusahaan' => 'CV Vendor Dua',
            'email_kontak' => 'kontak@vendor2.test',
            'no_telepon' => '0822222222',
            'alamat' => 'Kediri',
            'kategori_komoditas' => 'Pangan Pokok',
            'status_verifikasi' => 'approved',
            'status_aktif' => true,
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
                'nama_lengkap' => 'Rekan Vendor Satu',
                'email' => 'staff@vendor1.test',
                'role' => 'vendor',
                'vendor_uuid' => $vendor2->uuid,
            ])
            ->assertRedirect('/manajemen/pengguna');

        $this->assertDatabaseHas('users', [
            'email' => 'staff@vendor1.test',
            'vendor_id' => $vendor1->id,
            'perusahaan_id' => null,
        ]);

        $this->actingAs($userV1)
            ->deleteJson("/manajemen/pengguna/{$userV2->uuid}")
            ->assertForbidden();
    }

    public function test_superadmin_can_filter_and_manage_users_across_all_companies(): void
    {
        Notification::fake();

        $companyA = PerusahaanBuyer::create([
            'nama_perusahaan' => 'PT Super Buyer A',
            'nomor_npwp' => '44.444.444.4-444.000',
            'alamat' => 'Semarang',
            'tipe_paket' => 'enterprise',
            'status_aktif' => true,
        ]);

        $vendorA = Vendor::create([
            'slug' => 'pt-super-vendor-a',
            'nama_perusahaan' => 'PT Super Vendor A',
            'email_kontak' => 'info@supervendora.test',
            'no_telepon' => '0833333333',
            'alamat' => 'Solo',
            'kategori_komoditas' => 'Pangan Pokok',
            'status_verifikasi' => 'approved',
            'status_aktif' => true,
        ]);

        // Superadmin creates a Buyer Company Owner assigned to Company A via UUID
        $this->actingAs($this->admin)
            ->post('/manajemen/pengguna', [
                'nama_lengkap' => 'Owner Super Buyer A',
                'email' => 'owner@superbuyera.test',
                'role' => 'owner',
                'perusahaan_uuid' => $companyA->uuid,
            ])
            ->assertRedirect('/manajemen/pengguna');

        $this->assertDatabaseHas('users', [
            'email' => 'owner@superbuyera.test',
            'perusahaan_id' => $companyA->id,
            'vendor_id' => null,
        ]);

        // Superadmin creates a Vendor account assigned to Vendor A via UUID
        $this->actingAs($this->admin)
            ->post('/manajemen/pengguna', [
                'nama_lengkap' => 'PIC Super Vendor A',
                'email' => 'pic@supervendora.test',
                'role' => 'vendor',
                'vendor_uuid' => $vendorA->uuid,
            ])
            ->assertRedirect('/manajemen/pengguna');

        $this->assertDatabaseHas('users', [
            'email' => 'pic@supervendora.test',
            'vendor_id' => $vendorA->id,
            'perusahaan_id' => null,
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

    public function test_staff_roles_buyer_and_qc_cannot_access_manajemen_pengguna(): void
    {
        $company = PerusahaanBuyer::create([
            'nama_perusahaan' => 'PT Staff Test',
            'nomor_npwp' => '55.555.555.5-555.000',
            'alamat' => 'Yogyakarta',
            'tipe_paket' => 'enterprise',
            'status_aktif' => true,
        ]);

        $buyer = User::factory()->create([
            'role_id' => 2,
            'perusahaan_id' => $company->id,
            'email' => 'staff.buyer@test.com',
        ]);

        $qc = User::factory()->create([
            'role_id' => 3,
            'perusahaan_id' => $company->id,
            'email' => 'staff.qc@test.com',
        ]);

        $this->actingAs($buyer)->getJson('/manajemen/pengguna')->assertForbidden();
        $this->actingAs($qc)->getJson('/manajemen/pengguna')->assertForbidden();
    }
}
