<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Komoditas;
use App\Models\PerusahaanBuyer;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private PerusahaanBuyer $tenant;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->tenant = PerusahaanBuyer::factory()->create();
        $this->vendor = Vendor::create([
            'company_name' => 'PT Mitra Tani',
            'slug' => 'pt-mitra-tani-'.Str::lower(Str::random(6)),
            'contact_email' => 'kontak@mitratani.test',
            'phone' => '081234567890',
            'kategori_komoditas' => 'Beras',
            'verification_status' => 'approved',
            'is_active' => true,
        ]);
    }

    private function userWithRole(RoleName $role): User
    {
        return User::factory()->create([
            'role_id' => Role::where('name', $role->value)->value('id'),
            'tenant_id' => in_array($role, RoleName::tenantRoles(), true) ? $this->tenant->id : null,
            'vendor_id' => $role === RoleName::Vendor ? $this->vendor->id : null,
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, array{RoleName, array<string, bool>}>
     */
    public static function navigationMatrix(): array
    {
        $none = [
            'purchase_order.view_any' => false,
            'komoditas.view_any' => false,
            'vendor.view_any' => false,
            'dokumen_legalitas.view_any' => false,
            'tenant.view_any' => false,
            'pengguna.view_any' => false,
            'pengaturan.view_any' => false,
            'role.view_any' => false,
        ];

        return [
            'superadmin' => [RoleName::Superadmin, [
                ...$none,
                'komoditas.view_any' => true,
                'vendor.view_any' => true,
                'dokumen_legalitas.view_any' => true,
                'tenant.view_any' => true,
                'pengguna.view_any' => true,
                'pengaturan.view_any' => true,
                'role.view_any' => true,
            ]],
            'owner' => [RoleName::Owner, [
                ...$none,
                'purchase_order.view_any' => true,
                'vendor.view_any' => true,
                'pengguna.view_any' => true,
            ]],
            'manajemen' => [RoleName::Manajemen, [...$none, 'vendor.view_any' => true]],
            'purchasing' => [RoleName::Purchasing, [...$none, 'purchase_order.view_any' => true]],
            'qc_gudang' => [RoleName::QcGudang, $none],
            'vendor' => [RoleName::Vendor, [
                ...$none,
                'purchase_order.view_any' => true,
                'pengguna.view_any' => true,
            ]],
        ];
    }

    /**
     * @param  array<string, bool>  $expected
     */
    #[DataProvider('navigationMatrix')]
    public function test_shared_navigation_permissions_follow_role(RoleName $role, array $expected): void
    {
        $this->actingAs($this->userWithRole($role))
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.can', $expected)
                ->where('auth.role.name', $role->value)
                ->where('auth.role.label', $role->label())
            );
    }

    /**
     * @return array<string, array{RoleName, string}>
     */
    public static function forbiddenPages(): array
    {
        return [
            'owner opens master komoditas' => [RoleName::Owner, '/master/komoditas'],
            'manajemen opens pengguna' => [RoleName::Manajemen, '/manajemen/pengguna'],
            'purchasing opens vendor list' => [RoleName::Purchasing, '/master/vendor'],
            'qc opens purchase order' => [RoleName::QcGudang, '/purchase-order'],
            'vendor opens create purchase order' => [RoleName::Vendor, '/purchase-order/create'],
            'vendor opens vendor list' => [RoleName::Vendor, '/master/vendor'],
            'owner opens verifikasi dokumen' => [RoleName::Owner, '/master/vendor/verifikasi-dokumen'],
            'owner opens verifikasi tenant' => [RoleName::Owner, '/master/tenant/verifikasi-dokumen'],
            'manajemen opens pengaturan' => [RoleName::Manajemen, '/pengaturan/profil'],
        ];
    }

    #[DataProvider('forbiddenPages')]
    public function test_direct_url_without_permission_returns_403(RoleName $role, string $url): void
    {
        $this->actingAs($this->userWithRole($role))->get($url)->assertForbidden();
    }

    public function test_non_superadmin_cannot_mutate_master_komoditas(): void
    {
        $owner = $this->userWithRole(RoleName::Owner);
        $komoditas = Komoditas::factory()->create();

        $this->actingAs($owner)->post('/master/komoditas', ['name' => 'Gula'])->assertForbidden();
        $this->actingAs($owner)->put("/master/komoditas/{$komoditas->uuid}", ['name' => 'Gula'])->assertForbidden();
        $this->actingAs($owner)->deleteJson("/master/komoditas/{$komoditas->uuid}")->assertForbidden();

        $this->assertNotSoftDeleted($komoditas);
    }

    public function test_komoditas_actions_are_exposed_only_to_superadmin(): void
    {
        Komoditas::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::Superadmin))
            ->get('/master/komoditas')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.create', true)
                ->where('komoditas.data.0.can', ['update' => true, 'delete' => true])
            );
    }

    public function test_purchasing_cannot_block_or_invite_vendor_but_owner_can_block(): void
    {
        $purchasing = $this->userWithRole(RoleName::Purchasing);

        $this->actingAs($purchasing)
            ->postJson("/master/vendor/{$this->vendor->uuid}/blokir", ['block_reason' => 'Kualitas barang buruk berulang'])
            ->assertForbidden();
        $this->actingAs($purchasing)
            ->postJson('/master/vendor/undang', ['email' => 'calon@vendor.test'])
            ->assertForbidden();
        $this->assertTrue($this->vendor->fresh()->is_active);

        $this->actingAs($this->userWithRole(RoleName::Owner))
            ->postJson("/master/vendor/{$this->vendor->uuid}/blokir", ['block_reason' => 'Kualitas barang buruk berulang'])
            ->assertOk();
        $this->assertFalse($this->vendor->fresh()->is_active);
    }

    public function test_role_change_takes_effect_on_next_request(): void
    {
        $user = $this->userWithRole(RoleName::Purchasing);

        $this->actingAs($user)->get('/manajemen/pengguna')->assertForbidden();

        $user->update(['role_id' => Role::where('name', RoleName::Owner->value)->value('id')]);

        $this->actingAs($user->fresh())->get('/manajemen/pengguna')->assertOk();
    }

    public function test_owner_cannot_escalate_privileges(): void
    {
        $owner = $this->userWithRole(RoleName::Owner);
        $staff = $this->userWithRole(RoleName::Purchasing);

        // Promote staff to superadmin or owner
        foreach (['superadmin', 'owner', 'vendor'] as $role) {
            $this->actingAs($owner)->put("/manajemen/pengguna/{$staff->uuid}", [
                'name' => $staff->name,
                'email' => $staff->email,
                'role' => $role,
                'is_active' => true,
            ])->assertSessionHasErrors('role');
        }

        // Promote own account
        $this->actingAs($owner)->put("/manajemen/pengguna/{$owner->uuid}", [
            'name' => $owner->name,
            'email' => $owner->email,
            'role' => 'superadmin',
            'is_active' => true,
        ])->assertSessionHasErrors('role');

        $this->assertSame(RoleName::Purchasing, $staff->fresh()->roleName());
        $this->assertSame(RoleName::Owner, $owner->fresh()->roleName());
    }

    public function test_owner_cannot_touch_accounts_outside_own_tenant_or_superadmin(): void
    {
        $owner = $this->userWithRole(RoleName::Owner);
        $superadmin = $this->userWithRole(RoleName::Superadmin);
        $otherTenantStaff = User::factory()->create([
            'role_id' => Role::where('name', RoleName::Purchasing->value)->value('id'),
            'tenant_id' => PerusahaanBuyer::factory()->create()->id,
        ]);

        foreach ([$superadmin, $otherTenantStaff] as $target) {
            $this->actingAs($owner)->getJson("/manajemen/pengguna/{$target->uuid}")->assertForbidden();
            $this->actingAs($owner)->putJson("/manajemen/pengguna/{$target->uuid}", [
                'name' => 'Diretas',
                'email' => $target->email,
                'role' => 'qc_gudang',
                'is_active' => true,
            ])->assertForbidden();
            $this->actingAs($owner)->deleteJson("/manajemen/pengguna/{$target->uuid}")->assertForbidden();

            $this->assertNotSame('Diretas', $target->fresh()->name);
            $this->assertNotSoftDeleted($target);
        }
    }

    public function test_pengguna_rows_expose_policy_flags(): void
    {
        $owner = $this->userWithRole(RoleName::Owner);
        $this->userWithRole(RoleName::QcGudang);

        $this->actingAs($owner)
            ->get('/manajemen/pengguna?sort=name')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.create', true)
                ->has('pegawai.data', 2)
                ->where('pegawai.data', fn ($rows) => collect($rows)->every(
                    fn (array $row) => $row['uuid'] === $owner->uuid
                        ? $row['can'] === ['update' => true, 'delete' => false]
                        : $row['can'] === ['update' => true, 'delete' => true]
                ))
            );
    }

    public function test_superadmin_sees_role_matrix_with_user_counts(): void
    {
        $superadmin = $this->userWithRole(RoleName::Superadmin);
        $this->userWithRole(RoleName::Owner);

        $this->actingAs($superadmin)
            ->get('/manajemen/role-akses')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Manajemen/RoleAkses/Index')
                ->has('roles.data', 6)
                ->where('roles.data', fn ($roles) => collect($roles)->firstWhere('name', 'owner')['users_count'] === 1)
                ->has('permission_groups', 8)
            );
    }

    public function test_non_superadmin_cannot_open_or_update_role_access(): void
    {
        $owner = $this->userWithRole(RoleName::Owner);

        $this->actingAs($owner)->get('/manajemen/role-akses')->assertForbidden();
        $this->actingAs($owner)->putJson('/manajemen/role-akses/owner', [
            'permissions' => ['role.view_any'],
        ])->assertForbidden();
    }

    public function test_granted_permission_opens_page_and_revoking_closes_it_immediately(): void
    {
        $superadmin = $this->userWithRole(RoleName::Superadmin);
        $qc = $this->userWithRole(RoleName::QcGudang);

        $this->actingAs($qc)->get('/master/vendor')->assertForbidden();

        $this->actingAs($superadmin)->put('/manajemen/role-akses/qc_gudang', [
            'description' => 'QC dengan akses data vendor',
            'permissions' => ['vendor.view_any'],
        ])->assertRedirect('/manajemen/role-akses')->assertSessionHasNoErrors();

        $this->actingAs($qc->fresh())->get('/master/vendor')->assertOk();
        $this->assertSame('QC dengan akses data vendor', Role::where('name', 'qc_gudang')->value('description'));

        $this->actingAs($superadmin)->put('/manajemen/role-akses/qc_gudang', [
            'permissions' => [],
        ])->assertSessionHasNoErrors();

        $this->actingAs($qc->fresh())->get('/master/vendor')->assertForbidden();
    }

    public function test_platform_permission_cannot_be_given_to_tenant_role(): void
    {
        $superadmin = $this->userWithRole(RoleName::Superadmin);

        $this->actingAs($superadmin)->put('/manajemen/role-akses/owner', [
            'permissions' => ['pengguna.view_any', 'pengaturan.update', 'role.update'],
        ])->assertSessionHasErrors('permissions');

        $owner = $this->userWithRole(RoleName::Owner);
        $this->actingAs($owner)->get('/pengaturan/profil')->assertForbidden();
        $this->assertFalse(Role::where('name', 'owner')->first()->hasPermission('role.update'));
    }

    public function test_unknown_permission_is_rejected(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Superadmin))
            ->put('/manajemen/role-akses/purchasing', ['permissions' => ['sistem.hapus_semua']])
            ->assertSessionHasErrors('permissions');
    }

    public function test_superadmin_cannot_revoke_own_role_management_access(): void
    {
        $superadmin = $this->userWithRole(RoleName::Superadmin);

        $this->actingAs($superadmin)->put('/manajemen/role-akses/superadmin', [
            'permissions' => ['komoditas.view_any'],
        ])->assertSessionHasErrors('permissions');

        $this->actingAs($superadmin->fresh())->get('/manajemen/role-akses')->assertOk();
    }

    public function test_unknown_role_in_url_returns_404(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Superadmin))
            ->put('/manajemen/role-akses/admin', ['permissions' => []])
            ->assertNotFound();
    }
}
