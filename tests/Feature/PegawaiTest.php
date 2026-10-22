<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
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
}
