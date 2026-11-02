<?php

namespace App\Http\Middleware;

use App\Models\DokumenLegalitasVendor;
use App\Models\Komoditas;
use App\Models\Pengaturan;
use App\Models\PerusahaanBuyer;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PengaturanService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'role' => fn () => $this->rolePayload($request->user()),
                'can' => fn () => $this->navigationPermissions($request->user()),
            ],
            'user' => $request->user(),
            'aplikasi' => fn () => app(PengaturanService::class)->profilPublik(),
            'status' => fn () => $request->session()->get('status'),
            'flash' => [
                'success' => fn () => $request->session()->get('success') ?? $request->session()->get('status'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }

    /**
     * @return array{name: string, label: string}|null
     */
    private function rolePayload(?User $user): ?array
    {
        $role = $user?->roleName();

        return $role === null ? null : ['name' => $role->value, 'label' => $role->label()];
    }

    /**
     * Page-level abilities used to build the sidebar, computed by the policies.
     *
     * @return array<string, bool>
     */
    private function navigationPermissions(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return [
            'purchase_order.view_any' => $user->can('viewAny', PurchaseOrder::class),
            'komoditas.view_any' => $user->can('viewAny', Komoditas::class),
            'vendor.view_any' => $user->can('viewAny', Vendor::class),
            'dokumen_legalitas.view_any' => $user->can('viewAny', DokumenLegalitasVendor::class),
            'tenant.view_any' => $user->can('viewAny', PerusahaanBuyer::class),
            'perusahaan.view' => ($user->isOwner() && $user->tenant_id !== null) || ($user->isVendor() && $user->vendor_id !== null),
            'pengguna.view_any' => $user->can('viewAny', User::class),
            'pengaturan.view_any' => $user->can('viewAny', Pengaturan::class),
            'role.view_any' => $user->can('viewAny', Role::class),
        ];
    }
}
