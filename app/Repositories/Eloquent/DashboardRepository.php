<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\RoleName;
use App\Enums\StatusKerjaSama;
use App\Enums\StatusSengketa;
use App\Enums\VerificationStatus;
use App\Models\CommodityCategory;
use App\Models\DokumenLegalitasVendor;
use App\Models\KerjaSama;
use App\Models\Komoditas;
use App\Models\PerusahaanBuyer;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Sengketa;
use App\Models\User;
use App\Models\Vendor;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class DashboardRepository implements DashboardRepositoryInterface
{
    private const INDONESIAN_SHORT_MONTHS = [
        1 => 'Jan',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Apr',
        5 => 'Mei',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Agu',
        9 => 'Sep',
        10 => 'Okt',
        11 => 'Nov',
        12 => 'Des',
    ];

    /**
     * Safely execute a count query, returning 0 if the underlying table is not yet migrated or was renamed.
     *
     * @param  callable(): int  $callback
     */
    private function safeCount(callable $callback): int
    {
        try {
            return (int) $callback();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getSuperadminSummaryMetrics(): array
    {
        $tenantsTotal = $this->safeCount(static fn (): int => PerusahaanBuyer::query()->count());
        $tenantsPending = $this->safeCount(
            static fn (): int => PerusahaanBuyer::query()
                ->where('verification_status', VerificationStatus::Pending->value)
                ->count()
        );
        $tenantsApproved = $this->safeCount(
            static fn (): int => PerusahaanBuyer::query()
                ->where('verification_status', VerificationStatus::Approved->value)
                ->count()
        );
        $tenantsRejected = $this->safeCount(
            static fn (): int => PerusahaanBuyer::query()
                ->where('verification_status', VerificationStatus::Rejected->value)
                ->count()
        );
        $tenantsActive = $this->safeCount(
            static fn (): int => PerusahaanBuyer::query()
                ->where('verification_status', VerificationStatus::Approved->value)
                ->where('is_active', true)
                ->count()
        );

        $vendorsTotal = $this->safeCount(static fn (): int => Vendor::query()->count());
        $vendorsPending = $this->safeCount(
            static fn (): int => Vendor::query()
                ->where('verification_status', VerificationStatus::Pending->value)
                ->count()
        );
        $vendorsApproved = $this->safeCount(
            static fn (): int => Vendor::query()
                ->where('verification_status', VerificationStatus::Approved->value)
                ->count()
        );
        $vendorsRejected = $this->safeCount(
            static fn (): int => Vendor::query()
                ->where('verification_status', VerificationStatus::Rejected->value)
                ->count()
        );
        $vendorsBlocked = $this->safeCount(
            static fn (): int => Vendor::query()
                ->where(function ($query): void {
                    $query->where('is_active', false)
                        ->orWhereNotNull('blocked_at');
                })
                ->count()
        );
        $vendorsActive = $this->safeCount(
            static fn (): int => Vendor::query()
                ->where('verification_status', VerificationStatus::Approved->value)
                ->where('is_active', true)
                ->whereNull('blocked_at')
                ->count()
        );
        $vendorPendingDocuments = $this->safeCount(
            static fn (): int => DokumenLegalitasVendor::query()
                ->where('verification_status', VerificationStatus::Pending->value)
                ->whereHas('vendor')
                ->count()
        );

        $commoditiesTotal = $this->safeCount(static fn (): int => Komoditas::query()->count());
        $commoditiesActive = $this->safeCount(
            static fn (): int => Komoditas::query()->where('is_active', true)->count()
        );
        $commoditiesWithApiCode = $this->safeCount(
            static fn (): int => Komoditas::query()
                ->whereNotNull('api_reference_code')
                ->where('api_reference_code', '!=', '')
                ->count()
        );
        $categoriesTotal = $this->safeCount(static fn (): int => CommodityCategory::query()->count());
        $categoriesActive = $this->safeCount(
            static fn (): int => CommodityCategory::query()->where('is_active', true)->count()
        );

        $usersTotal = $this->safeCount(static fn (): int => User::query()->count());
        $usersActive = $this->safeCount(
            static fn (): int => User::query()->where('is_active', true)->count()
        );

        $partnershipsTotal = $this->safeCount(static fn (): int => KerjaSama::query()->count());
        $partnershipsAgreed = $this->safeCount(
            static fn (): int => KerjaSama::query()
                ->where('status', StatusKerjaSama::Agreed->value)
                ->count()
        );
        $partnershipsPending = $this->safeCount(
            static fn (): int => KerjaSama::query()
                ->where('status', StatusKerjaSama::Pending->value)
                ->count()
        );

        $purchaseOrdersTotal = $this->safeCount(static function (): int {
            try {
                return PurchaseOrder::query()->count();
            } catch (Throwable) {
                return DB::table('purchase_orders')->whereNull('deleted_at')->count();
            }
        });

        $disputesOpen = $this->safeCount(static function (): int {
            try {
                return Sengketa::query()
                    ->whereIn('status', [
                        StatusSengketa::Terbuka->value,
                        StatusSengketa::DalamMediasi->value,
                    ])
                    ->count();
            } catch (Throwable) {
                return DB::table('disputes')
                    ->whereIn('status', ['open', 'in_mediation', 'terbuka', 'dalam_mediasi'])
                    ->count();
            }
        });

        return [
            'tenants' => [
                'total' => $tenantsTotal,
                'pending' => $tenantsPending,
                'approved' => $tenantsApproved,
                'rejected' => $tenantsRejected,
                'active' => $tenantsActive,
            ],
            'vendors' => [
                'total' => $vendorsTotal,
                'pending' => $vendorsPending,
                'approved' => $vendorsApproved,
                'rejected' => $vendorsRejected,
                'active' => $vendorsActive,
                'blocked' => $vendorsBlocked,
                'pending_documents' => $vendorPendingDocuments,
            ],
            'commodities' => [
                'total' => $commoditiesTotal,
                'active' => $commoditiesActive,
                'inactive' => max(0, $commoditiesTotal - $commoditiesActive),
                'with_api_code' => $commoditiesWithApiCode,
                'categories_total' => $categoriesTotal,
                'categories_active' => $categoriesActive,
            ],
            'users' => [
                'total' => $usersTotal,
                'active' => $usersActive,
                'inactive' => max(0, $usersTotal - $usersActive),
            ],
            'ecosystem' => [
                'partnerships_total' => $partnershipsTotal,
                'partnerships_agreed' => $partnershipsAgreed,
                'partnerships_pending' => $partnershipsPending,
                'purchase_orders_total' => $purchaseOrdersTotal,
                'disputes_open' => $disputesOpen,
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getPendingTenants(int $limit = 5): array
    {
        try {
            return PerusahaanBuyer::query()
                ->where('verification_status', VerificationStatus::Pending->value)
                ->with([
                    'users' => static function ($query): void {
                        $query->with('role')->orderBy('id');
                    },
                ])
                ->withCount('legalDocuments')
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get()
                ->map(static function (PerusahaanBuyer $tenant): array {
                    $owner = $tenant->users->first(
                        static fn (User $u): bool => $u->roleName() === RoleName::Owner
                    ) ?? $tenant->users->first();

                    $docsCount = (int) ($tenant->legal_documents_count ?? 0);
                    if ($docsCount === 0 && ! empty($tenant->legal_document_path)) {
                        $docsCount = 1;
                    }

                    return [
                        'uuid' => $tenant->uuid,
                        'company_name' => $tenant->company_name,
                        'tax_number' => $tenant->tax_number,
                        'phone' => $tenant->phone,
                        'address' => $tenant->address,
                        'owner_name' => $owner?->name,
                        'owner_email' => $owner?->email,
                        'documents_count' => $docsCount,
                        'created_at' => $tenant->created_at?->toISOString(),
                    ];
                })
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getPendingVendors(int $limit = 5): array
    {
        try {
            return Vendor::query()
                ->where(function ($query): void {
                    $query->where('verification_status', VerificationStatus::Pending->value)
                        ->orWhereHas('legalDocuments', static function ($docQuery): void {
                            $docQuery->where('verification_status', VerificationStatus::Pending->value);
                        });
                })
                ->with(['commodityCategories:id,uuid,name'])
                ->withCount([
                    'legalDocuments as total_documents_count',
                    'legalDocuments as pending_documents_count' => static function ($docQuery): void {
                        $docQuery->where('verification_status', VerificationStatus::Pending->value);
                    },
                ])
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get()
                ->map(static function (Vendor $vendor): array {
                    $categoryNames = $vendor->commodityCategories
                        ->pluck('name')
                        ->filter()
                        ->values()
                        ->all();

                    if (empty($categoryNames) && ! empty($vendor->kategori_komoditas)) {
                        $categoryNames = array_values(array_filter(
                            array_map('trim', explode(',', (string) $vendor->kategori_komoditas))
                        ));
                    }

                    return [
                        'uuid' => $vendor->uuid,
                        'company_name' => $vendor->company_name,
                        'business_entity' => $vendor->business_entity,
                        'contact_email' => $vendor->contact_email,
                        'phone' => $vendor->phone,
                        'is_pkp' => (bool) $vendor->is_pkp,
                        'commodity_categories' => $categoryNames,
                        'pending_documents_count' => (int) ($vendor->pending_documents_count ?? 0),
                        'total_documents_count' => (int) ($vendor->total_documents_count ?? 0),
                        'created_at' => $vendor->created_at?->toISOString(),
                    ];
                })
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getBlockedVendors(int $limit = 5): array
    {
        try {
            return Vendor::query()
                ->where(function ($query): void {
                    $query->where('is_active', false)
                        ->orWhereNotNull('blocked_at');
                })
                ->with('blockedByUser:id,uuid,name')
                ->orderByDesc('blocked_at')
                ->orderByDesc('updated_at')
                ->limit($limit)
                ->get()
                ->map(static function (Vendor $vendor): array {
                    return [
                        'uuid' => $vendor->uuid,
                        'company_name' => $vendor->company_name,
                        'contact_email' => $vendor->contact_email,
                        'block_reason' => $vendor->block_reason ?: 'Dinonaktifkan secara global oleh Superadmin.',
                        'blocked_by_name' => $vendor->blockedByUser?->name ?? 'Superadmin',
                        'blocked_at' => ($vendor->blocked_at ?? $vendor->updated_at)?->toISOString(),
                    ];
                })
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getUserRoleDistribution(): array
    {
        try {
            $roles = Role::query()
                ->withCount([
                    'users as total_users_count',
                    'users as active_users_count' => static function ($query): void {
                        $query->where('is_active', true);
                    },
                ])
                ->get()
                ->keyBy('name');
        } catch (Throwable) {
            $roles = collect();
        }

        $distribution = [];

        foreach (RoleName::cases() as $roleCase) {
            /** @var Role|null $roleModel */
            $roleModel = $roles->get($roleCase->value);

            $distribution[] = [
                'role' => $roleCase->value,
                'label' => $roleCase->label(),
                'total' => (int) ($roleModel?->total_users_count ?? 0),
                'active' => (int) ($roleModel?->active_users_count ?? 0),
            ];
        }

        return $distribution;
    }

    /**
     * {@inheritDoc}
     */
    public function getCommodityCategoryDistribution(int $limit = 6): array
    {
        try {
            return CommodityCategory::query()
                ->withCount(['commodities', 'vendors'])
                ->orderByDesc('commodities_count')
                ->orderBy('name')
                ->limit($limit)
                ->get()
                ->map(static function (CommodityCategory $category): array {
                    return [
                        'uuid' => $category->uuid,
                        'name' => $category->name,
                        'is_active' => (bool) $category->is_active,
                        'commodities_count' => (int) ($category->commodities_count ?? 0),
                        'vendors_count' => (int) ($category->vendors_count ?? 0),
                    ];
                })
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getMonthlyPlatformGrowth(int $months = 6): array
    {
        $safeMonths = max(1, min($months, 12));
        $startDate = Carbon::now()->startOfMonth()->subMonths($safeMonths - 1);

        try {
            $tenantDates = PerusahaanBuyer::query()
                ->where('created_at', '>=', $startDate)
                ->pluck('created_at');
        } catch (Throwable) {
            $tenantDates = collect();
        }

        try {
            $vendorDates = Vendor::query()
                ->where('created_at', '>=', $startDate)
                ->pluck('created_at');
        } catch (Throwable) {
            $vendorDates = collect();
        }

        try {
            $poDates = PurchaseOrder::query()
                ->where('created_at', '>=', $startDate)
                ->pluck('created_at');
        } catch (Throwable) {
            try {
                $poDates = DB::table('purchase_orders')
                    ->whereNull('deleted_at')
                    ->where('created_at', '>=', $startDate)
                    ->pluck('created_at');
            } catch (Throwable) {
                $poDates = collect();
            }
        }

        $buckets = [];
        for ($i = 0; $i < $safeMonths; $i++) {
            $monthCursor = $startDate->copy()->addMonths($i);
            $key = $monthCursor->format('Y-m');
            $monthNum = (int) $monthCursor->format('n');
            $monthName = self::INDONESIAN_SHORT_MONTHS[$monthNum] ?? $monthCursor->format('M');

            $buckets[$key] = [
                'month_key' => $key,
                'label' => $monthName.' '.$monthCursor->format('Y'),
                'tenants_count' => 0,
                'vendors_count' => 0,
                'purchase_orders_count' => 0,
            ];
        }

        foreach ($tenantDates as $dt) {
            if ($dt !== null) {
                $key = Carbon::parse($dt)->format('Y-m');
                if (isset($buckets[$key])) {
                    $buckets[$key]['tenants_count']++;
                }
            }
        }

        foreach ($vendorDates as $dt) {
            if ($dt !== null) {
                $key = Carbon::parse($dt)->format('Y-m');
                if (isset($buckets[$key])) {
                    $buckets[$key]['vendors_count']++;
                }
            }
        }

        foreach ($poDates as $dt) {
            if ($dt !== null) {
                $key = Carbon::parse($dt)->format('Y-m');
                if (isset($buckets[$key])) {
                    $buckets[$key]['purchase_orders_count']++;
                }
            }
        }

        return array_values($buckets);
    }

    /**
     * {@inheritDoc}
     */
    public function getRecentGovernanceActivities(int $limit = 8): array
    {
        $events = collect();

        try {
            $recentTenants = PerusahaanBuyer::query()
                ->orderByDesc('updated_at')
                ->limit(4)
                ->get();

            foreach ($recentTenants as $tenant) {
                $status = $tenant->verification_status;
                if ($status === VerificationStatus::Approved) {
                    $title = "Tenant Disetujui: {$tenant->company_name}";
                    $desc = 'Verifikasi akun dan dokumen legalitas perusahaan buyer telah disetujui.';
                    $tone = 'success';
                } elseif ($status === VerificationStatus::Rejected) {
                    $title = "Tenant Ditolak: {$tenant->company_name}";
                    $desc = $tenant->verification_note ?: 'Pengajuan registrasi tenant ditolak.';
                    $tone = 'danger';
                } else {
                    $title = "Registrasi Tenant Baru: {$tenant->company_name}";
                    $desc = 'Menunggu verifikasi profil perusahaan dan dokumen legalitas oleh Superadmin.';
                    $tone = 'warning';
                }

                $events->push([
                    'uuid' => 'tenant-'.$tenant->uuid,
                    'category' => 'Verifikasi Tenant',
                    'title' => $title,
                    'description' => $desc,
                    'status' => $tone,
                    'action_url' => '/master/tenant/verifikasi-dokumen',
                    'occurred_at' => ($tenant->verified_at ?? $tenant->updated_at ?? $tenant->created_at)?->toISOString(),
                ]);
            }
        } catch (Throwable) {
            // Skip if table is unavailable
        }

        try {
            $recentVendors = Vendor::query()
                ->orderByDesc('updated_at')
                ->limit(4)
                ->get();

            foreach ($recentVendors as $vendor) {
                if (! $vendor->is_active || $vendor->blocked_at !== null) {
                    $title = "Vendor Diblokir Global: {$vendor->company_name}";
                    $desc = $vendor->block_reason ?: 'Akses vendor dicabut secara global oleh Superadmin.';
                    $tone = 'danger';
                    $url = '/master/vendor';
                } elseif ($vendor->verification_status === VerificationStatus::Approved) {
                    $title = "Legalitas Vendor Terverifikasi: {$vendor->company_name}";
                    $desc = 'Perusahaan pemasok telah disetujui dan aktif bertransaksi di platform.';
                    $tone = 'success';
                    $url = '/master/vendor/verifikasi-dokumen';
                } elseif ($vendor->verification_status === VerificationStatus::Rejected) {
                    $title = "Verifikasi Vendor Ditolak: {$vendor->company_name}";
                    $desc = 'Pengajuan dokumen legalitas pemasok ditolak.';
                    $tone = 'danger';
                    $url = '/master/vendor/verifikasi-dokumen';
                } else {
                    $title = "Pendaftaran Vendor Baru: {$vendor->company_name}";
                    $desc = 'Calon rekanan vendor mengunggah dokumen legalitas dan menunggu verifikasi.';
                    $tone = 'warning';
                    $url = '/master/vendor/verifikasi-dokumen';
                }

                $events->push([
                    'uuid' => 'vendor-'.$vendor->uuid,
                    'category' => 'Tata Kelola Vendor',
                    'title' => $title,
                    'description' => $desc,
                    'status' => $tone,
                    'action_url' => $url,
                    'occurred_at' => ($vendor->blocked_at ?? $vendor->updated_at ?? $vendor->created_at)?->toISOString(),
                ]);
            }
        } catch (Throwable) {
            // Skip if table is unavailable
        }

        try {
            $recentCommodities = Komoditas::query()
                ->with('category:id,uuid,name')
                ->orderByDesc('updated_at')
                ->limit(3)
                ->get();

            foreach ($recentCommodities as $commodity) {
                $categoryName = $commodity->category?->name ?? 'Tanpa Kategori';
                $events->push([
                    'uuid' => 'commodity-'.$commodity->uuid,
                    'category' => 'Master Komoditas',
                    'title' => "Komoditas Global: {$commodity->name} ({$commodity->sku})",
                    'description' => "Kategori {$categoryName} • Satuan {$commodity->unit} • Toleransi susut {$commodity->default_shrinkage_tolerance}%",
                    'status' => $commodity->is_active ? 'info' : 'neutral',
                    'action_url' => '/master/komoditas',
                    'occurred_at' => ($commodity->updated_at ?? $commodity->created_at)?->toISOString(),
                ]);
            }
        } catch (Throwable) {
            // Skip if table is unavailable
        }

        return $events
            ->sortByDesc(static fn (array $item): string => (string) ($item['occurred_at'] ?? ''))
            ->take($limit)
            ->values()
            ->all();
    }
}
