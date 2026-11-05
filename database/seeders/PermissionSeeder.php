<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    private const PLATFORM = ['superadmin'];

    private const TENANT = ['owner', 'manajemen', 'purchasing', 'qc_gudang'];

    /**
     * Permission catalog: name => [label, assignable roles, default roles].
     * Add new modules here; the role access matrix picks them up automatically.
     *
     * @var array<string, array{0: string, 1: list<string>, 2: list<string>}>
     */
    private const CATALOG = [
        'purchase_order.view_any' => ['Lihat purchase order', [...self::TENANT, 'vendor'], ['owner', 'purchasing', 'vendor']],
        'purchase_order.create' => ['Buat & ubah purchase order', self::TENANT, ['owner', 'purchasing']],

        'pengiriman.view_any' => ['Lihat pengiriman logistik', [...self::TENANT, 'vendor'], ['owner', 'purchasing', 'qc_gudang', 'vendor']],
        'pengiriman.create' => ['Laporkan pengiriman logistik fisik', ['vendor'], ['vendor']],

        'komoditas.view_any' => ['Lihat master komoditas', [...self::PLATFORM, ...self::TENANT], ['superadmin']],
        'komoditas.create' => ['Tambah komoditas', self::PLATFORM, ['superadmin']],
        'komoditas.update' => ['Ubah komoditas', self::PLATFORM, ['superadmin']],
        'komoditas.delete' => ['Hapus komoditas', self::PLATFORM, ['superadmin']],

        'vendor.view_any' => ['Lihat data rekanan vendor', [...self::PLATFORM, ...self::TENANT], ['superadmin', 'owner', 'manajemen']],
        'vendor.invite' => ['Undang vendor', [...self::PLATFORM, ...self::TENANT], ['superadmin', 'owner', 'manajemen']],
        'vendor.block' => ['Blokir vendor', [...self::PLATFORM, ...self::TENANT], ['superadmin', 'owner', 'manajemen']],
        'vendor.unblock' => ['Buka blokir vendor', [...self::PLATFORM, ...self::TENANT], ['superadmin', 'owner', 'manajemen']],

        'dokumen_legalitas.view_any' => ['Lihat dokumen legalitas vendor', self::PLATFORM, ['superadmin']],
        'dokumen_legalitas.verify' => ['Verifikasi dokumen legalitas', self::PLATFORM, ['superadmin']],

        'tenant.view_any' => ['Lihat data tenant & registrasi', self::PLATFORM, ['superadmin']],
        'tenant.verify' => ['Verifikasi akun & legalitas tenant', self::PLATFORM, ['superadmin']],

        'pengguna.view_any' => ['Lihat akun pengguna', ['superadmin', 'owner', 'vendor'], ['superadmin', 'owner', 'vendor']],
        'pengguna.create' => ['Tambah akun pengguna', ['superadmin', 'owner', 'vendor'], ['superadmin', 'owner', 'vendor']],
        'pengguna.update' => ['Ubah akun pengguna', ['superadmin', 'owner', 'vendor'], ['superadmin', 'owner', 'vendor']],
        'pengguna.delete' => ['Hapus akun pengguna', ['superadmin', 'owner', 'vendor'], ['superadmin', 'owner', 'vendor']],

        'pengaturan.view_any' => ['Lihat pengaturan aplikasi', self::PLATFORM, ['superadmin']],
        'pengaturan.update' => ['Ubah pengaturan aplikasi', self::PLATFORM, ['superadmin']],

        'role.view_any' => ['Lihat role & hak akses', self::PLATFORM, ['superadmin']],
        'role.update' => ['Atur hak akses role', self::PLATFORM, ['superadmin']],
    ];

    /**
     * Run the database seeds.
     *
     * Existing role assignments are kept; only newly created permissions get their default roles.
     */
    public function run(): void
    {
        $roles = Role::query()->pluck('id', 'name');
        $order = 0;

        foreach (self::CATALOG as $name => [$label, $assignable, $defaults]) {
            [$module, $action] = explode('.', $name, 2);

            $permission = Permission::updateOrCreate(
                ['name' => $name],
                [
                    'module' => $module,
                    'action' => $action,
                    'label' => $label,
                    'assignable_roles' => $assignable,
                    'sort_order' => $order++,
                ]
            );

            if ($permission->wasRecentlyCreated) {
                $permission->roles()->syncWithoutDetaching(
                    collect($defaults)->map(fn (string $role) => $roles[$role] ?? null)->filter()->all()
                );
            }

            // Drop grants that are no longer allowed after a catalog change.
            $permission->roles()->detach(
                $roles->except($assignable)->values()->all()
            );
        }

        // The superadmin must never lose access to this screen.
        Role::where('name', RoleName::Superadmin->value)->first()?->permissions()->syncWithoutDetaching(
            Permission::whereIn('name', ['role.view_any', 'role.update'])->pluck('id')->all()
        );
    }
}
