<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\RoleName;
use App\Enums\StatusItemPenerimaan;
use App\Enums\StatusKerjaSama;
use App\Enums\StatusMatching;
use App\Enums\StatusPurchaseOrder;
use App\Enums\StatusSengketa;
use App\Enums\StatusUndanganVendor;
use App\Enums\VerificationStatus;
use App\Models\CommodityCategory;
use App\Models\DokumenLegalitasVendor;
use App\Models\Invoice;
use App\Models\KerjaSama;
use App\Models\Komoditas;
use App\Models\PenerimaanBarang;
use App\Models\PenerimaanBarangItem;
use App\Models\PerusahaanBuyer;
use App\Models\PoItem;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Sengketa;
use App\Models\TenantCommodity;
use App\Models\UndanganVendor;
use App\Models\User;
use App\Models\Vendor;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
     * Get SQL expression for formatting date column to 'YYYY-MM' according to DB driver.
     */
    private function monthKeyExpression(string $column = 'created_at'): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            'sqlite' => "strftime('%Y-%m', {$column})",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    /**
     * Safely execute a grouped monthly count query, returning an array of ['YYYY-MM' => int].
     *
     * @param  callable(): array<string, int|numeric>  $callback
     * @return array<string, int>
     */
    private function safeMonthlyCounts(callable $callback): array
    {
        try {
            return (array) $callback();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Safely execute a grouped monthly metrics query, returning an iterable of rows.
     *
     * @param  callable(): mixed  $callback
     * @return iterable<mixed>
     */
    private function safeMonthlyMetrics(callable $callback): iterable
    {
        try {
            return $callback();
        } catch (Throwable) {
            return [];
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
            return Sengketa::query()
                ->whereIn('status', [
                    StatusSengketa::Open->value,
                    StatusSengketa::AwaitingResponse->value,
                    StatusSengketa::InMediation->value,
                ])
                ->count();
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

        $monthExpr = $this->monthKeyExpression('created_at');

        $tenantCounts = $this->safeMonthlyCounts(
            fn (): array => PerusahaanBuyer::query()
                ->where('created_at', '>=', $startDate)
                ->selectRaw("{$monthExpr} as month_key, COUNT(*) as aggregate")
                ->groupByRaw($monthExpr)
                ->pluck('aggregate', 'month_key')
                ->all()
        );

        $vendorCounts = $this->safeMonthlyCounts(
            fn (): array => Vendor::query()
                ->where('created_at', '>=', $startDate)
                ->selectRaw("{$monthExpr} as month_key, COUNT(*) as aggregate")
                ->groupByRaw($monthExpr)
                ->pluck('aggregate', 'month_key')
                ->all()
        );

        $poCounts = $this->safeMonthlyCounts(
            fn (): array => PurchaseOrder::query()
                ->where('created_at', '>=', $startDate)
                ->selectRaw("{$monthExpr} as month_key, COUNT(*) as aggregate")
                ->groupByRaw($monthExpr)
                ->pluck('aggregate', 'month_key')
                ->all()
        );

        $buckets = [];
        for ($i = 0; $i < $safeMonths; $i++) {
            $monthCursor = $startDate->copy()->addMonths($i);
            $key = $monthCursor->format('Y-m');
            $monthNum = (int) $monthCursor->format('n');
            $monthName = self::INDONESIAN_SHORT_MONTHS[$monthNum] ?? $monthCursor->format('M');

            $buckets[$key] = [
                'month_key' => $key,
                'label' => $monthName.' '.$monthCursor->format('Y'),
                'tenants_count' => (int) ($tenantCounts[$key] ?? 0),
                'vendors_count' => (int) ($vendorCounts[$key] ?? 0),
                'purchase_orders_count' => (int) ($poCounts[$key] ?? 0),
            ];
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

    /**
     * {@inheritDoc}
     */
    public function getTenantProfileForManajemen(int $tenantId): ?array
    {
        try {
            $tenant = PerusahaanBuyer::query()->withCount('legalDocuments')->find($tenantId);
            if ($tenant === null) {
                return null;
            }

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
                'verification_status' => $tenant->verification_status?->value ?? 'approved',
                'is_active' => (bool) $tenant->is_active,
                'verified_at' => $tenant->verified_at?->toISOString(),
                'documents_count' => $docsCount,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Count disputes for a specific tenant, falling back to `disputes` table if `sengketa` was renamed.
     *
     * @param  list<string>|null  $statuses
     */
    private function countTenantDisputes(int $tenantId, ?array $statuses = null): int
    {
        return $this->safeCount(static function () use ($tenantId, $statuses): int {
            try {
                $query = Sengketa::query()
                    ->whereHas('purchaseOrder', static fn ($q) => $q->where('perusahaan_buyer_id', $tenantId));

                if ($statuses !== null) {
                    $query->whereIn('status', $statuses);
                }

                return $query->count();
            } catch (Throwable) {
                $query = DB::table('disputes')
                    ->join('purchase_order', 'disputes.purchase_order_id', '=', 'purchase_order.id')
                    ->where('purchase_order.perusahaan_buyer_id', $tenantId);

                if ($statuses !== null) {
                    $query->whereIn('disputes.status', $statuses);
                }

                return $query->count();
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function getManajemenSummaryMetrics(int $tenantId): array
    {
        $disputesTotal = $this->countTenantDisputes($tenantId);
        $disputesOpen = $this->countTenantDisputes($tenantId, [
            StatusSengketa::Open->value,
            'terbuka',
        ]);
        $disputesInMediation = $this->countTenantDisputes($tenantId, [
            StatusSengketa::InMediation->value,
            StatusSengketa::AwaitingResponse->value,
            'dalam_mediasi',
        ]);
        $disputesResolved = $this->countTenantDisputes($tenantId, [
            StatusSengketa::Resolved->value,
            'selesai',
        ]);

        $partnershipsTotal = $this->safeCount(static fn (): int => KerjaSama::query()
            ->where('tenant_id', $tenantId)
            ->count());
        $partnershipsAgreed = $this->safeCount(static fn (): int => KerjaSama::query()
            ->where('tenant_id', $tenantId)
            ->where('status', StatusKerjaSama::Agreed->value)
            ->count());
        $partnershipsPendingIncoming = $this->safeCount(static fn (): int => KerjaSama::query()
            ->where('tenant_id', $tenantId)
            ->where('status', StatusKerjaSama::Pending->value)
            ->where('requested_by', 'vendor')
            ->count());
        $partnershipsPendingOutgoing = $this->safeCount(static fn (): int => KerjaSama::query()
            ->where('tenant_id', $tenantId)
            ->where('status', StatusKerjaSama::Pending->value)
            ->where('requested_by', 'tenant')
            ->count());
        $partnershipsBlocked = $this->safeCount(static fn (): int => KerjaSama::query()
            ->where('tenant_id', $tenantId)
            ->where('status', StatusKerjaSama::Blocked->value)
            ->count());
        $invitationsPending = $this->safeCount(static fn (): int => UndanganVendor::query()
            ->where('tenant_id', $tenantId)
            ->where('status', StatusUndanganVendor::Pending->value)
            ->where(static function ($q): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count());

        $totalReceipts = $this->safeCount(static fn (): int => PenerimaanBarang::query()
            ->whereHas('purchaseOrder', static fn ($q) => $q->where('perusahaan_buyer_id', $tenantId))
            ->count());
        $inTransitOrders = $this->safeCount(static fn (): int => PurchaseOrder::query()
            ->where('perusahaan_buyer_id', $tenantId)
            ->where('status', StatusPurchaseOrder::DalamPengiriman->value)
            ->count());
        $acceptedItems = $this->safeCount(static fn (): int => PenerimaanBarangItem::query()
            ->where('status', StatusItemPenerimaan::Accepted->value)
            ->whereHas('penerimaanBarang.purchaseOrder', static fn ($q) => $q->where('perusahaan_buyer_id', $tenantId))
            ->count());
        $rejectedItems = $this->safeCount(static fn (): int => PenerimaanBarangItem::query()
            ->where('status', StatusItemPenerimaan::Rejected->value)
            ->whereHas('penerimaanBarang.purchaseOrder', static fn ($q) => $q->where('perusahaan_buyer_id', $tenantId))
            ->count());

        $globalActiveCommodities = $this->safeCount(static fn (): int => Komoditas::query()
            ->where('is_active', true)
            ->count());
        $tenantActiveCommodities = $this->safeCount(static fn (): int => TenantCommodity::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->count());
        $customToleranceCount = $this->safeCount(static fn (): int => TenantCommodity::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNotNull('shrinkage_tolerance')
            ->count());
        $activePurchaseOrders = $this->safeCount(static fn (): int => PurchaseOrder::query()
            ->where('perusahaan_buyer_id', $tenantId)
            ->count());

        return [
            'disputes' => [
                'total' => $disputesTotal,
                'open' => $disputesOpen,
                'in_mediation' => $disputesInMediation,
                'resolved' => $disputesResolved,
            ],
            'partnerships' => [
                'total' => $partnershipsTotal,
                'agreed' => $partnershipsAgreed,
                'pending_incoming' => $partnershipsPendingIncoming,
                'pending_outgoing' => $partnershipsPendingOutgoing,
                'blocked' => $partnershipsBlocked,
                'invitations_pending' => $invitationsPending,
            ],
            'qc_inspections' => [
                'total_receipts' => $totalReceipts,
                'in_transit_orders' => $inTransitOrders,
                'accepted_items' => $acceptedItems,
                'rejected_items' => $rejectedItems,
            ],
            'commodities' => [
                'global_active_total' => $globalActiveCommodities,
                'tenant_active' => $tenantActiveCommodities,
                'custom_tolerance_count' => $customToleranceCount,
                'active_purchase_orders' => $activePurchaseOrders,
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getManajemenActiveDisputes(int $tenantId, int $limit = 5): array
    {
        try {
            return Sengketa::query()
                ->whereHas('purchaseOrder', static fn ($q) => $q->where('perusahaan_buyer_id', $tenantId))
                ->whereIn('status', [
                    StatusSengketa::Open->value,
                    StatusSengketa::AwaitingResponse->value,
                    StatusSengketa::InMediation->value,
                ])
                ->with([
                    'purchaseOrder.vendor:id,uuid,company_name',
                    'poItem.komoditas:id,uuid,name,unit',
                ])
                ->orderByDesc('opened_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->map(static function (Sengketa $sengketa): array {
                    $po = $sengketa->purchaseOrder;
                    $vendor = $po?->vendor;
                    $commodity = $sengketa->poItem?->komoditas;

                    return [
                        'uuid' => $sengketa->uuid,
                        'short_code' => '#'.Str::upper(substr($sengketa->uuid, 0, 8)),
                        'status' => $sengketa->status->value,
                        'status_label' => $sengketa->status->label(),
                        'trigger_type_label' => $sengketa->triggerLabel(),
                        'reason' => $sengketa->reason,
                        'po_number' => $po?->nomor_po ?? '-',
                        'vendor_name' => $vendor?->company_name ?? '-',
                        'commodity_name' => $commodity?->name,
                        'opened_at' => ($sengketa->opened_at ?? $sengketa->created_at)?->toISOString(),
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
    public function getManajemenVendorPartnerships(int $tenantId, int $limit = 5): array
    {
        try {
            return KerjaSama::query()
                ->where('tenant_id', $tenantId)
                ->with('vendor:id,uuid,company_name,business_entity,contact_email,phone')
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 WHEN status = 'blocked' THEN 1 ELSE 2 END")
                ->orderByDesc('updated_at')
                ->limit($limit)
                ->get()
                ->map(static function (KerjaSama $partnership): array {
                    $vendor = $partnership->vendor;

                    return [
                        'uuid' => $partnership->uuid,
                        'vendor_uuid' => $vendor?->uuid,
                        'vendor_name' => $vendor?->company_name ?? 'Vendor',
                        'vendor_entity' => $vendor?->business_entity,
                        'vendor_email' => $vendor?->contact_email,
                        'requested_by' => $partnership->requested_by,
                        'status' => $partnership->status->value,
                        'status_label' => $partnership->status->label(),
                        'block_reason' => $partnership->block_reason,
                        'updated_at' => ($partnership->blocked_at ?? $partnership->updated_at ?? $partnership->created_at)?->toISOString(),
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
    public function getManajemenRecentQcRejections(int $tenantId, int $limit = 5): array
    {
        try {
            return PenerimaanBarangItem::query()
                ->where('status', StatusItemPenerimaan::Rejected->value)
                ->whereHas('penerimaanBarang.purchaseOrder', static fn ($q) => $q->where('perusahaan_buyer_id', $tenantId))
                ->with([
                    'penerimaanBarang.purchaseOrder.vendor:id,uuid,company_name',
                    'poItem.komoditas:id,uuid,name,unit',
                ])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->map(static function (PenerimaanBarangItem $item): array {
                    $receipt = $item->penerimaanBarang;
                    $po = $receipt?->purchaseOrder;
                    $vendor = $po?->vendor;
                    $poItem = $item->poItem;
                    $commodity = $poItem?->komoditas;
                    $photos = is_array($item->rejection_photos) ? $item->rejection_photos : [];

                    return [
                        'uuid' => $item->uuid,
                        'receipt_uuid' => $receipt?->uuid,
                        'po_number' => $po?->nomor_po ?? '-',
                        'vendor_name' => $vendor?->company_name ?? '-',
                        'commodity_name' => $commodity?->name ?? 'Komoditas',
                        'unit' => $commodity?->unit ?? 'kg',
                        'ordered_quantity' => (float) ($poItem?->jumlah_pesanan ?? 0),
                        'received_quantity' => (float) ($item->received_quantity ?? 0),
                        'notes' => $item->notes,
                        'photos_count' => count($photos),
                        'inspected_at' => ($receipt?->inspected_at ?? $item->created_at)?->toISOString(),
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
    public function getManajemenTenantCommodities(int $tenantId, int $limit = 6): array
    {
        try {
            $tenantConfigs = TenantCommodity::query()
                ->where('tenant_id', $tenantId)
                ->get()
                ->keyBy('commodity_id');

            return Komoditas::query()
                ->where('is_active', true)
                ->with('category:id,uuid,name')
                ->orderBy('name')
                ->limit($limit)
                ->get()
                ->map(static function (Komoditas $commodity) use ($tenantConfigs): array {
                    /** @var TenantCommodity|null $tc */
                    $tc = $tenantConfigs->get($commodity->id);
                    $defaultTol = (float) ($commodity->default_shrinkage_tolerance ?? 0);
                    $effectiveTol = $tc !== null && $tc->shrinkage_tolerance !== null
                        ? (float) $tc->shrinkage_tolerance
                        : $defaultTol;

                    return [
                        'uuid' => $commodity->uuid,
                        'name' => $commodity->name,
                        'sku' => $commodity->sku,
                        'category_name' => $commodity->category?->name ?? 'Umum',
                        'unit' => $commodity->unit,
                        'is_active_for_tenant' => $tc !== null ? (bool) $tc->is_active : false,
                        'default_shrinkage_tolerance' => $defaultTol,
                        'effective_shrinkage_tolerance' => $effectiveTol,
                        'has_custom_tolerance' => $tc !== null && $tc->shrinkage_tolerance !== null,
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
    public function getManajemenMonthlyTrend(int $tenantId, int $months = 6): array
    {
        $safeMonths = max(1, min($months, 12));
        $startDate = Carbon::now()->startOfMonth()->subMonths($safeMonths - 1);

        $poMonthExpr = $this->monthKeyExpression('created_at');
        $poCounts = $this->safeMonthlyCounts(
            fn (): array => PurchaseOrder::query()
                ->where('perusahaan_buyer_id', $tenantId)
                ->where('created_at', '>=', $startDate)
                ->selectRaw("{$poMonthExpr} as month_key, COUNT(*) as aggregate")
                ->groupByRaw($poMonthExpr)
                ->pluck('aggregate', 'month_key')
                ->all()
        );

        $qcMonthExpr = $this->monthKeyExpression('goods_receipts.created_at');
        $qcCounts = $this->safeMonthlyCounts(
            fn (): array => PenerimaanBarang::query()
                ->whereHas('purchaseOrder', static fn ($q) => $q->where('perusahaan_buyer_id', $tenantId))
                ->where('goods_receipts.created_at', '>=', $startDate)
                ->selectRaw("{$qcMonthExpr} as month_key, COUNT(*) as aggregate")
                ->groupByRaw($qcMonthExpr)
                ->pluck('aggregate', 'month_key')
                ->all()
        );

        $disputeMonthExpr = $this->monthKeyExpression('disputes.created_at');
        $disputeCounts = $this->safeMonthlyCounts(
            fn (): array => Sengketa::query()
                ->whereHas('purchaseOrder', static fn ($q) => $q->where('perusahaan_buyer_id', $tenantId))
                ->where('disputes.created_at', '>=', $startDate)
                ->selectRaw("{$disputeMonthExpr} as month_key, COUNT(*) as aggregate")
                ->groupByRaw($disputeMonthExpr)
                ->pluck('aggregate', 'month_key')
                ->all()
        );

        $buckets = [];
        for ($i = 0; $i < $safeMonths; $i++) {
            $monthCursor = $startDate->copy()->addMonths($i);
            $key = $monthCursor->format('Y-m');
            $monthNum = (int) $monthCursor->format('n');
            $monthName = self::INDONESIAN_SHORT_MONTHS[$monthNum] ?? $monthCursor->format('M');

            $buckets[$key] = [
                'month_key' => $key,
                'label' => $monthName.' '.$monthCursor->format('Y'),
                'purchase_orders_count' => (int) ($poCounts[$key] ?? 0),
                'qc_inspections_count' => (int) ($qcCounts[$key] ?? 0),
                'disputes_count' => (int) ($disputeCounts[$key] ?? 0),
            ];
        }

        return array_values($buckets);
    }

    /**
     * {@inheritDoc}
     */
    public function getOwnerSummaryMetrics(int $tenantId): array
    {
        $totalOrders = $this->safeCount(static fn (): int => PurchaseOrder::query()
            ->where('perusahaan_buyer_id', $tenantId)
            ->count());

        $draftAndNegotiation = $this->safeCount(static fn (): int => PurchaseOrder::query()
            ->where('perusahaan_buyer_id', $tenantId)
            ->whereIn('status', [
                StatusPurchaseOrder::Draft->value,
                StatusPurchaseOrder::MenungguTanggapan->value,
                StatusPurchaseOrder::Negosiasi->value,
            ])
            ->count());

        $inExecution = $this->safeCount(static fn (): int => PurchaseOrder::query()
            ->where('perusahaan_buyer_id', $tenantId)
            ->whereIn('status', [
                StatusPurchaseOrder::Disepakati->value,
                StatusPurchaseOrder::DalamPengiriman->value,
                StatusPurchaseOrder::PartiallyReceived->value,
                StatusPurchaseOrder::DiterimaGudang->value,
            ])
            ->count());

        $invoicedAndReadyToPay = $this->safeCount(static fn (): int => PurchaseOrder::query()
            ->where('perusahaan_buyer_id', $tenantId)
            ->whereIn('status', [
                StatusPurchaseOrder::Ditagih->value,
                StatusPurchaseOrder::ReadyToPay->value,
            ])
            ->count());

        $completedPaid = $this->safeCount(static fn (): int => PurchaseOrder::query()
            ->where('perusahaan_buyer_id', $tenantId)
            ->where('status', StatusPurchaseOrder::Lunas->value)
            ->count());

        $disputedOrders = $this->safeCount(static fn (): int => PurchaseOrder::query()
            ->where('perusahaan_buyer_id', $tenantId)
            ->where('status', StatusPurchaseOrder::Sengketa->value)
            ->count());

        $cancelledOrders = $this->safeCount(static fn (): int => PurchaseOrder::query()
            ->where('perusahaan_buyer_id', $tenantId)
            ->where('status', StatusPurchaseOrder::Dibatalkan->value)
            ->count());

        $totalAgreedValue = 0.0;
        try {
            $agreedStatuses = [
                StatusPurchaseOrder::Disepakati->value,
                StatusPurchaseOrder::DalamPengiriman->value,
                StatusPurchaseOrder::PartiallyReceived->value,
                StatusPurchaseOrder::DiterimaGudang->value,
                StatusPurchaseOrder::Ditagih->value,
                StatusPurchaseOrder::ReadyToPay->value,
                StatusPurchaseOrder::Lunas->value,
            ];

            $items = PoItem::query()
                ->whereHas('purchaseOrder', static fn ($q) => $q
                    ->where('perusahaan_buyer_id', $tenantId)
                    ->whereIn('status', $agreedStatuses))
                ->get(['jumlah_pesanan', 'harga_satuan_po', 'estimated_unit_price', 'subtotal']);

            foreach ($items as $item) {
                $subtotal = (float) ($item->subtotal ?? 0);
                if ($subtotal > 0) {
                    $totalAgreedValue += $subtotal;
                } else {
                    $qty = (float) ($item->jumlah_pesanan ?? 0);
                    $unitPrice = (float) ($item->harga_satuan_po ?? $item->estimated_unit_price ?? 0);
                    $totalAgreedValue += $qty * $unitPrice;
                }
            }
        } catch (Throwable) {
            $totalAgreedValue = 0.0;
        }

        // Invoices & 3-Way Matching
        $invoicesTotal = $this->safeCount(static fn (): int => Invoice::query()
            ->whereHas('purchaseOrder', static fn ($q) => $q->where('perusahaan_buyer_id', $tenantId))
            ->count());
        $invoicesPending = $this->safeCount(static fn (): int => Invoice::query()
            ->whereHas('purchaseOrder', static fn ($q) => $q->where('perusahaan_buyer_id', $tenantId))
            ->where('status_matching', StatusMatching::Pending->value)
            ->count());
        $invoicesMatched = $this->safeCount(static fn (): int => Invoice::query()
            ->whereHas('purchaseOrder', static fn ($q) => $q->where('perusahaan_buyer_id', $tenantId))
            ->where('status_matching', StatusMatching::Matched->value)
            ->count());
        $invoicesMismatched = $this->safeCount(static fn (): int => Invoice::query()
            ->whereHas('purchaseOrder', static fn ($q) => $q->where('perusahaan_buyer_id', $tenantId))
            ->where('status_matching', StatusMatching::Mismatched->value)
            ->count());

        $totalInvoicedAmount = 0.0;
        $matchedAmount = 0.0;
        try {
            $totalInvoicedAmount = (float) Invoice::query()
                ->whereHas('purchaseOrder', static fn ($q) => $q->where('perusahaan_buyer_id', $tenantId))
                ->sum('jumlah_tagihan');
            $matchedAmount = (float) Invoice::query()
                ->whereHas('purchaseOrder', static fn ($q) => $q->where('perusahaan_buyer_id', $tenantId))
                ->where('status_matching', StatusMatching::Matched->value)
                ->sum('jumlah_tagihan');
        } catch (Throwable) {
            $totalInvoicedAmount = 0.0;
            $matchedAmount = 0.0;
        }

        // Internal Employees (UC-08)
        $employeesTotal = $this->safeCount(static fn (): int => User::query()
            ->where('tenant_id', $tenantId)
            ->count());
        $employeesActive = $this->safeCount(static fn (): int => User::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->count());
        $ownerCount = $this->safeCount(static fn (): int => User::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('role', static fn ($q) => $q->where('name', RoleName::Owner->value))
            ->count());
        $manajemenCount = $this->safeCount(static fn (): int => User::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('role', static fn ($q) => $q->where('name', RoleName::Manajemen->value))
            ->count());
        $purchasingCount = $this->safeCount(static fn (): int => User::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('role', static fn ($q) => $q->where('name', RoleName::Purchasing->value))
            ->count());
        $qcGudangCount = $this->safeCount(static fn (): int => User::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('role', static fn ($q) => $q->where('name', RoleName::QcGudang->value))
            ->count());

        // Reuse governance counts from Manajemen summary
        $manajemenSummary = $this->getManajemenSummaryMetrics($tenantId);
        $disputesOpen = (int) ($manajemenSummary['disputes']['open'] ?? 0);
        $disputesMediation = (int) ($manajemenSummary['disputes']['in_mediation'] ?? 0);
        $disputesResolved = (int) ($manajemenSummary['disputes']['resolved'] ?? 0);

        return [
            'procurement' => [
                'total_orders' => $totalOrders,
                'draft_and_negotiation' => $draftAndNegotiation,
                'in_execution' => $inExecution,
                'invoiced_and_ready_to_pay' => $invoicedAndReadyToPay,
                'completed_paid' => $completedPaid,
                'disputed_orders' => $disputedOrders,
                'cancelled_orders' => $cancelledOrders,
                'total_agreed_value' => round($totalAgreedValue, 2),
            ],
            'invoices' => [
                'total' => $invoicesTotal,
                'pending' => $invoicesPending,
                'matched' => $invoicesMatched,
                'mismatched' => $invoicesMismatched,
                'ready_to_pay_orders' => $invoicedAndReadyToPay,
                'total_invoiced_amount' => round($totalInvoicedAmount, 2),
                'matched_amount' => round($matchedAmount, 2),
            ],
            'employees' => [
                'total' => $employeesTotal,
                'active' => $employeesActive,
                'inactive' => max(0, $employeesTotal - $employeesActive),
                'owner_count' => $ownerCount,
                'manajemen_count' => $manajemenCount,
                'purchasing_count' => $purchasingCount,
                'qc_gudang_count' => $qcGudangCount,
            ],
            'governance' => [
                'partnerships_total' => (int) ($manajemenSummary['partnerships']['total'] ?? 0),
                'partnerships_agreed' => (int) ($manajemenSummary['partnerships']['agreed'] ?? 0),
                'partnerships_pending' => (int) (($manajemenSummary['partnerships']['pending_incoming'] ?? 0) + ($manajemenSummary['partnerships']['pending_outgoing'] ?? 0)),
                'partnerships_blocked' => (int) ($manajemenSummary['partnerships']['blocked'] ?? 0),
                'invitations_pending' => (int) ($manajemenSummary['partnerships']['invitations_pending'] ?? 0),
                'disputes_total' => (int) ($manajemenSummary['disputes']['total'] ?? 0),
                'disputes_open' => $disputesOpen,
                'disputes_in_mediation' => $disputesMediation,
                'disputes_active' => $disputesOpen + $disputesMediation,
                'disputes_resolved' => $disputesResolved,
                'qc_total_receipts' => (int) ($manajemenSummary['qc_inspections']['total_receipts'] ?? 0),
                'qc_in_transit' => (int) ($manajemenSummary['qc_inspections']['in_transit_orders'] ?? 0),
                'qc_accepted_items' => (int) ($manajemenSummary['qc_inspections']['accepted_items'] ?? 0),
                'qc_rejected_items' => (int) ($manajemenSummary['qc_inspections']['rejected_items'] ?? 0),
                'tenant_active_commodities' => (int) ($manajemenSummary['commodities']['tenant_active'] ?? 0),
                'global_active_commodities' => (int) ($manajemenSummary['commodities']['global_active_total'] ?? 0),
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getOwnerRecentPurchaseOrders(int $tenantId, int $limit = 5): array
    {
        try {
            return PurchaseOrder::query()
                ->where('perusahaan_buyer_id', $tenantId)
                ->with([
                    'vendor:id,uuid,company_name,business_entity',
                    'pembuat:id,uuid,name',
                    'poItems:id,purchase_order_id,jumlah_pesanan,harga_satuan_po,estimated_unit_price,subtotal',
                ])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->map(static function (PurchaseOrder $po): array {
                    $totalAmount = 0.0;
                    foreach ($po->poItems as $item) {
                        $sub = (float) ($item->subtotal ?? 0);
                        if ($sub > 0) {
                            $totalAmount += $sub;
                        } else {
                            $qty = (float) ($item->jumlah_pesanan ?? 0);
                            $unitPrice = (float) ($item->harga_satuan_po ?? $item->estimated_unit_price ?? 0);
                            $totalAmount += $qty * $unitPrice;
                        }
                    }

                    $statusEnum = $po->status instanceof StatusPurchaseOrder
                        ? $po->status
                        : StatusPurchaseOrder::tryFrom((string) $po->status);

                    return [
                        'uuid' => $po->uuid,
                        'po_number' => $po->nomor_po,
                        'vendor_name' => $po->vendor?->company_name ?? 'Vendor',
                        'vendor_entity' => $po->vendor?->business_entity,
                        'created_by_name' => $po->pembuat?->name ?? 'Internal Tenant',
                        'status' => $statusEnum?->value ?? (string) $po->status,
                        'status_label' => $statusEnum?->label() ?? (string) $po->status,
                        'items_count' => $po->poItems->count(),
                        'total_amount' => round($totalAmount, 2),
                        'tanggal_po' => $po->tanggal_po?->toDateString(),
                        'created_at' => $po->created_at?->toISOString(),
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
    public function getOwnerRecentInvoices(int $tenantId, int $limit = 5): array
    {
        try {
            return Invoice::query()
                ->whereHas('purchaseOrder', static fn ($q) => $q->where('perusahaan_buyer_id', $tenantId))
                ->with([
                    'purchaseOrder:id,uuid,nomor_po,vendor_id,status',
                    'purchaseOrder.vendor:id,uuid,company_name',
                    'vendor:id,uuid,company_name',
                    'verifikator:id,uuid,name',
                ])
                ->orderByRaw("CASE WHEN status_matching = 'pending' THEN 0 WHEN status_matching = 'mismatched' THEN 1 ELSE 2 END")
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->map(static function (Invoice $invoice): array {
                    $po = $invoice->purchaseOrder;
                    $vendor = $invoice->vendor ?? $po?->vendor;
                    $matchingEnum = $invoice->statusMatchingEnum();

                    return [
                        'uuid' => $invoice->uuid,
                        'invoice_number' => $invoice->nomor_invoice_vendor,
                        'po_uuid' => $po?->uuid,
                        'po_number' => $po?->nomor_po ?? '-',
                        'vendor_name' => $vendor?->company_name ?? 'Vendor',
                        'amount' => round((float) ($invoice->jumlah_tagihan ?? 0), 2),
                        'matching_status' => $matchingEnum?->value ?? (string) ($invoice->status_matching ?? 'pending'),
                        'matching_status_label' => $matchingEnum?->label() ?? 'Menunggu Verifikasi',
                        'verified_by_name' => $invoice->verifikator?->name,
                        'verified_at' => $invoice->diverifikasi_pada?->toISOString(),
                        'invoice_date' => $invoice->tanggal_invoice?->toDateString(),
                        'created_at' => $invoice->created_at?->toISOString(),
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
    public function getOwnerInternalTeam(int $tenantId, int $limit = 6): array
    {
        try {
            return User::query()
                ->where('tenant_id', $tenantId)
                ->with('role:id,name')
                ->orderByDesc('is_active')
                ->orderBy('id')
                ->limit($limit)
                ->get()
                ->map(static function (User $member): array {
                    $roleEnum = $member->roleName();

                    return [
                        'uuid' => $member->uuid,
                        'name' => $member->name,
                        'email' => $member->email,
                        'role' => $roleEnum?->value ?? ($member->role?->name ?? 'purchasing'),
                        'role_label' => $roleEnum?->label() ?? 'Pegawai Internal',
                        'is_active' => (bool) $member->is_active,
                        'email_verified' => $member->email_verified_at !== null,
                        'created_at' => $member->created_at?->toISOString(),
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
    public function getOwnerMonthlyExecutionTrend(int $tenantId, int $months = 6): array
    {
        $safeMonths = max(1, min($months, 12));
        $startDate = Carbon::now()->startOfMonth()->subMonths($safeMonths - 1);

        $buckets = [];
        for ($i = 0; $i < $safeMonths; $i++) {
            $monthCursor = $startDate->copy()->addMonths($i);
            $key = $monthCursor->format('Y-m');
            $monthNum = (int) $monthCursor->format('n');
            $monthName = self::INDONESIAN_SHORT_MONTHS[$monthNum] ?? $monthCursor->format('M');

            $buckets[$key] = [
                'month_key' => $key,
                'label' => $monthName.' '.$monthCursor->format('Y'),
                'purchase_orders_count' => 0,
                'invoices_count' => 0,
                'qc_inspections_count' => 0,
                'po_value' => 0.0,
            ];
        }

        $poMonthExpr = $this->monthKeyExpression('purchase_order.created_at');
        $itemValueExpr = 'CASE WHEN po_item.subtotal IS NOT NULL AND po_item.subtotal > 0 THEN po_item.subtotal ELSE (COALESCE(po_item.jumlah_pesanan, 0) * COALESCE(po_item.harga_satuan_po, po_item.estimated_unit_price, 0)) END';

        $poTrends = $this->safeMonthlyMetrics(
            fn () => PurchaseOrder::query()
                ->leftJoin('po_item', 'po_item.purchase_order_id', '=', 'purchase_order.id')
                ->where('purchase_order.perusahaan_buyer_id', $tenantId)
                ->where('purchase_order.created_at', '>=', $startDate)
                ->selectRaw("{$poMonthExpr} as month_key, COUNT(DISTINCT purchase_order.id) as orders_count, COALESCE(SUM({$itemValueExpr}), 0) as po_value")
                ->groupByRaw($poMonthExpr)
                ->get()
        );

        foreach ($poTrends as $row) {
            $key = (string) $row->month_key;
            if (isset($buckets[$key])) {
                $buckets[$key]['purchase_orders_count'] = (int) $row->orders_count;
                $buckets[$key]['po_value'] = round((float) $row->po_value, 2);
            }
        }

        $invoiceMonthExpr = $this->monthKeyExpression('invoice.created_at');
        $invoiceCounts = $this->safeMonthlyCounts(
            fn (): array => Invoice::query()
                ->whereHas('purchaseOrder', static fn ($q) => $q->where('perusahaan_buyer_id', $tenantId))
                ->where('invoice.created_at', '>=', $startDate)
                ->selectRaw("{$invoiceMonthExpr} as month_key, COUNT(*) as aggregate")
                ->groupByRaw($invoiceMonthExpr)
                ->pluck('aggregate', 'month_key')
                ->all()
        );

        foreach ($invoiceCounts as $monthKey => $count) {
            if (isset($buckets[$monthKey])) {
                $buckets[$monthKey]['invoices_count'] = (int) $count;
            }
        }

        $qcMonthExpr = $this->monthKeyExpression('goods_receipts.created_at');
        $qcCounts = $this->safeMonthlyCounts(
            fn (): array => PenerimaanBarang::query()
                ->whereHas('purchaseOrder', static fn ($q) => $q->where('perusahaan_buyer_id', $tenantId))
                ->where('goods_receipts.created_at', '>=', $startDate)
                ->selectRaw("{$qcMonthExpr} as month_key, COUNT(*) as aggregate")
                ->groupByRaw($qcMonthExpr)
                ->pluck('aggregate', 'month_key')
                ->all()
        );

        foreach ($qcCounts as $monthKey => $count) {
            if (isset($buckets[$monthKey])) {
                $buckets[$monthKey]['qc_inspections_count'] = (int) $count;
            }
        }

        return array_values($buckets);
    }

    // =========================================================================
    // Buyer (Purchasing / Finance) Dashboard
    // =========================================================================

    /**
     * {@inheritDoc}
     */
    public function getBuyerSummaryMetrics(int $tenantId): array
    {
        $totalOrders = $this->safeCount(static fn (): int => PurchaseOrder::query()
            ->where('perusahaan_buyer_id', $tenantId)
            ->count());

        $draftNegotiation = $this->safeCount(static fn (): int => PurchaseOrder::query()
            ->where('perusahaan_buyer_id', $tenantId)
            ->whereIn('status', [
                StatusPurchaseOrder::Draft->value,
                StatusPurchaseOrder::MenungguTanggapan->value,
                StatusPurchaseOrder::Negosiasi->value,
            ])
            ->count());

        $inExecution = $this->safeCount(static fn (): int => PurchaseOrder::query()
            ->where('perusahaan_buyer_id', $tenantId)
            ->whereIn('status', [
                StatusPurchaseOrder::Disepakati->value,
                StatusPurchaseOrder::DalamPengiriman->value,
                StatusPurchaseOrder::PartiallyReceived->value,
                StatusPurchaseOrder::DiterimaGudang->value,
            ])
            ->count());

        $pendingPayment = $this->safeCount(static fn (): int => PurchaseOrder::query()
            ->where('perusahaan_buyer_id', $tenantId)
            ->whereIn('status', [
                StatusPurchaseOrder::Ditagih->value,
                StatusPurchaseOrder::ReadyToPay->value,
            ])
            ->count());

        $completedPaid = $this->safeCount(static fn (): int => PurchaseOrder::query()
            ->where('perusahaan_buyer_id', $tenantId)
            ->where('status', StatusPurchaseOrder::Lunas->value)
            ->count());

        $disputed = $this->safeCount(static fn (): int => PurchaseOrder::query()
            ->where('perusahaan_buyer_id', $tenantId)
            ->where('status', StatusPurchaseOrder::Sengketa->value)
            ->count());

        // Invoices & 3-Way Matching
        $invoicePending = $this->safeCount(static fn (): int => Invoice::query()
            ->whereHas('purchaseOrder', static fn ($q) => $q->where('perusahaan_buyer_id', $tenantId))
            ->where('status_matching', StatusMatching::Pending->value)
            ->count());

        $invoiceMatched = $this->safeCount(static fn (): int => Invoice::query()
            ->whereHas('purchaseOrder', static fn ($q) => $q->where('perusahaan_buyer_id', $tenantId))
            ->where('status_matching', StatusMatching::Matched->value)
            ->count());

        $invoiceMismatched = $this->safeCount(static fn (): int => Invoice::query()
            ->whereHas('purchaseOrder', static fn ($q) => $q->where('perusahaan_buyer_id', $tenantId))
            ->where('status_matching', StatusMatching::Mismatched->value)
            ->count());

        $totalInvoicedAmount = 0.0;
        try {
            $totalInvoicedAmount = (float) Invoice::query()
                ->whereHas('purchaseOrder', static fn ($q) => $q->where('perusahaan_buyer_id', $tenantId))
                ->sum('jumlah_tagihan');
        } catch (Throwable) {
            $totalInvoicedAmount = 0.0;
        }

        // Active disputes
        $activeDisputes = $this->safeCount(static fn (): int => Sengketa::query()
            ->whereHas('purchaseOrder', static fn ($q) => $q->where('perusahaan_buyer_id', $tenantId))
            ->whereIn('status', [
                StatusSengketa::Open->value,
                StatusSengketa::AwaitingResponse->value,
                StatusSengketa::InMediation->value,
            ])
            ->count());

        // Active vendor partnerships
        $activePartnerships = $this->safeCount(static fn (): int => KerjaSama::query()
            ->where('tenant_id', $tenantId)
            ->where('status', StatusKerjaSama::Agreed->value)
            ->count());

        return [
            'orders' => [
                'total' => $totalOrders,
                'draft_negotiation' => $draftNegotiation,
                'in_execution' => $inExecution,
                'pending_payment' => $pendingPayment,
                'completed_paid' => $completedPaid,
                'disputed' => $disputed,
            ],
            'invoices' => [
                'pending' => $invoicePending,
                'matched' => $invoiceMatched,
                'mismatched' => $invoiceMismatched,
                'total_invoiced_amount' => round($totalInvoicedAmount, 2),
            ],
            'disputes' => [
                'active' => $activeDisputes,
            ],
            'partnerships' => [
                'active' => $activePartnerships,
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getBuyerRecentPurchaseOrders(int $tenantId, int $limit = 5): array
    {
        try {
            return PurchaseOrder::query()
                ->where('perusahaan_buyer_id', $tenantId)
                ->with([
                    'vendor:id,uuid,company_name,business_entity',
                    'pembuat:id,uuid,name',
                    'poItems:id,purchase_order_id,jumlah_pesanan,harga_satuan_po,estimated_unit_price,subtotal',
                ])
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get()
                ->map(static function (PurchaseOrder $po): array {
                    $totalAmount = 0.0;
                    foreach ($po->poItems as $item) {
                        $sub = (float) ($item->subtotal ?? 0);
                        if ($sub > 0) {
                            $totalAmount += $sub;
                        } else {
                            $qty = (float) ($item->jumlah_pesanan ?? 0);
                            $unitPrice = (float) ($item->harga_satuan_po ?? $item->estimated_unit_price ?? 0);
                            $totalAmount += $qty * $unitPrice;
                        }
                    }

                    $statusEnum = $po->status instanceof StatusPurchaseOrder
                        ? $po->status
                        : StatusPurchaseOrder::tryFrom((string) $po->status);

                    return [
                        'uuid' => $po->uuid,
                        'po_number' => $po->nomor_po,
                        'vendor_name' => $po->vendor?->company_name ?? 'Vendor',
                        'vendor_entity' => $po->vendor?->business_entity,
                        'created_by_name' => $po->pembuat?->name ?? 'Internal',
                        'status' => $statusEnum?->value ?? (string) $po->status,
                        'status_label' => $statusEnum?->label() ?? (string) $po->status,
                        'items_count' => $po->poItems->count(),
                        'total_amount' => round($totalAmount, 2),
                        'tanggal_po' => $po->tanggal_po?->toDateString(),
                        'created_at' => $po->created_at?->toISOString(),
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
    public function getBuyerPendingInvoices(int $tenantId, int $limit = 5): array
    {
        try {
            return Invoice::query()
                ->whereHas('purchaseOrder', static fn ($q) => $q->where('perusahaan_buyer_id', $tenantId))
                ->whereIn('status_matching', [
                    StatusMatching::Pending->value,
                    StatusMatching::Mismatched->value,
                ])
                ->with([
                    'purchaseOrder:id,uuid,nomor_po,vendor_id',
                    'purchaseOrder.vendor:id,uuid,company_name',
                    'vendor:id,uuid,company_name',
                ])
                ->orderByRaw("CASE WHEN status_matching = 'mismatched' THEN 0 ELSE 1 END")
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get()
                ->map(static function (Invoice $invoice): array {
                    $po = $invoice->purchaseOrder;
                    $vendor = $invoice->vendor ?? $po?->vendor;
                    $matchingEnum = $invoice->statusMatchingEnum();

                    return [
                        'uuid' => $invoice->uuid,
                        'invoice_number' => $invoice->nomor_invoice_vendor,
                        'po_number' => $po?->nomor_po ?? '-',
                        'po_uuid' => $po?->uuid,
                        'vendor_name' => $vendor?->company_name ?? 'Vendor',
                        'amount' => round((float) ($invoice->jumlah_tagihan ?? 0), 2),
                        'matching_status' => $matchingEnum?->value ?? (string) ($invoice->status_matching ?? 'pending'),
                        'matching_status_label' => $matchingEnum?->label() ?? 'Menunggu Verifikasi',
                        'invoice_date' => $invoice->tanggal_invoice?->toDateString(),
                        'created_at' => $invoice->created_at?->toISOString(),
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
    public function getBuyerActiveDisputes(int $tenantId, int $limit = 5): array
    {
        return $this->getManajemenActiveDisputes($tenantId, $limit);
    }

    // =========================================================================
    // QC Gudang Dashboard
    // =========================================================================

    /**
     * {@inheritDoc}
     */
    public function getQcSummaryMetrics(int $tenantId): array
    {
        $totalReceipts = $this->safeCount(static fn (): int => PenerimaanBarang::query()
            ->whereHas('purchaseOrder', static fn ($q) => $q->where('perusahaan_buyer_id', $tenantId))
            ->count());

        $inTransitOrders = $this->safeCount(static fn (): int => PurchaseOrder::query()
            ->where('perusahaan_buyer_id', $tenantId)
            ->where('status', StatusPurchaseOrder::DalamPengiriman->value)
            ->count());

        $acceptedItems = $this->safeCount(static fn (): int => PenerimaanBarangItem::query()
            ->whereHas('penerimaanBarang.purchaseOrder', static fn ($q) => $q->where('perusahaan_buyer_id', $tenantId))
            ->where('status', StatusItemPenerimaan::Accepted->value)
            ->count());

        $rejectedItems = $this->safeCount(static fn (): int => PenerimaanBarangItem::query()
            ->whereHas('penerimaanBarang.purchaseOrder', static fn ($q) => $q->where('perusahaan_buyer_id', $tenantId))
            ->where('status', StatusItemPenerimaan::Rejected->value)
            ->count());

        $completedReceipts = $totalReceipts;
        $pendingReceipts = $inTransitOrders;

        $totalItems = $acceptedItems + $rejectedItems;
        $acceptanceRate = $totalItems > 0 ? round(($acceptedItems / $totalItems) * 100, 1) : 0.0;

        return [
            'inspections' => [
                'total_receipts' => $totalReceipts,
                'pending_receipts' => $pendingReceipts,
                'completed_receipts' => $completedReceipts,
                'in_transit_orders' => $inTransitOrders,
                'accepted_items' => $acceptedItems,
                'rejected_items' => $rejectedItems,
                'total_items_inspected' => $totalItems,
                'acceptance_rate' => $acceptanceRate,
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getQcPendingDeliveries(int $tenantId, int $limit = 5): array
    {
        try {
            return PurchaseOrder::query()
                ->where('perusahaan_buyer_id', $tenantId)
                ->whereIn('status', [
                    StatusPurchaseOrder::DalamPengiriman->value,
                    StatusPurchaseOrder::PartiallyReceived->value,
                ])
                ->with([
                    'vendor:id,uuid,company_name',
                    'poItems:id,purchase_order_id,komoditas_id,jumlah_pesanan',
                    'poItems.komoditas:id,uuid,name,unit',
                ])
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get()
                ->map(static function (PurchaseOrder $po): array {
                    $statusEnum = $po->status instanceof StatusPurchaseOrder
                        ? $po->status
                        : StatusPurchaseOrder::tryFrom((string) $po->status);

                    $commodityNames = $po->poItems
                        ->map(static fn ($item) => $item->komoditas?->name)
                        ->filter()
                        ->values()
                        ->all();

                    return [
                        'uuid' => $po->uuid,
                        'po_number' => $po->nomor_po,
                        'vendor_name' => $po->vendor?->company_name ?? 'Vendor',
                        'status' => $statusEnum?->value ?? (string) $po->status,
                        'status_label' => $statusEnum?->label() ?? (string) $po->status,
                        'items_count' => $po->poItems->count(),
                        'commodity_names' => $commodityNames,
                        'tanggal_po' => $po->tanggal_po?->toDateString(),
                        'created_at' => $po->created_at?->toISOString(),
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
    public function getQcRecentInspections(int $tenantId, int $limit = 5): array
    {
        try {
            return PenerimaanBarang::query()
                ->whereHas('purchaseOrder', static fn ($q) => $q->where('perusahaan_buyer_id', $tenantId))
                ->with([
                    'purchaseOrder:id,uuid,nomor_po,vendor_id',
                    'purchaseOrder.vendor:id,uuid,company_name',
                    'inspector:id,uuid,name',
                    'items:id,goods_receipt_id,status',
                ])
                ->orderByDesc('inspected_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->map(static function (PenerimaanBarang $receipt): array {
                    $po = $receipt->purchaseOrder;
                    $vendor = $po?->vendor;
                    $accepted = $receipt->items
                        ->filter(static fn ($item): bool => ($item->status->value ?? $item->status) === StatusItemPenerimaan::Accepted->value)
                        ->count();
                    $rejected = $receipt->items
                        ->filter(static fn ($item): bool => ($item->status->value ?? $item->status) === StatusItemPenerimaan::Rejected->value)
                        ->count();

                    return [
                        'uuid' => $receipt->uuid,
                        'po_number' => $po?->nomor_po ?? '-',
                        'po_uuid' => $po?->uuid,
                        'vendor_name' => $vendor?->company_name ?? 'Vendor',
                        'inspector_name' => $receipt->inspector?->name ?? '-',
                        'accepted_count' => $accepted,
                        'rejected_count' => $rejected,
                        'total_items' => $accepted + $rejected,
                        'inspected_at' => ($receipt->inspected_at ?? $receipt->created_at)?->toISOString(),
                    ];
                })
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    // =========================================================================
    // Vendor Dashboard
    // =========================================================================

    /**
     * {@inheritDoc}
     */
    public function getVendorSummaryMetrics(int $vendorId): array
    {
        $totalOrders = $this->safeCount(static fn (): int => PurchaseOrder::query()
            ->where('vendor_id', $vendorId)
            ->where('status', '!=', StatusPurchaseOrder::Draft->value)
            ->count());

        $pendingResponse = $this->safeCount(static fn (): int => PurchaseOrder::query()
            ->where('vendor_id', $vendorId)
            ->whereIn('status', [
                StatusPurchaseOrder::MenungguTanggapan->value,
                StatusPurchaseOrder::Negosiasi->value,
            ])
            ->count());

        $inExecution = $this->safeCount(static fn (): int => PurchaseOrder::query()
            ->where('vendor_id', $vendorId)
            ->whereIn('status', [
                StatusPurchaseOrder::Disepakati->value,
                StatusPurchaseOrder::DalamPengiriman->value,
                StatusPurchaseOrder::PartiallyReceived->value,
                StatusPurchaseOrder::DiterimaGudang->value,
            ])
            ->count());

        $completedPaid = $this->safeCount(static fn (): int => PurchaseOrder::query()
            ->where('vendor_id', $vendorId)
            ->where('status', StatusPurchaseOrder::Lunas->value)
            ->count());

        $disputed = $this->safeCount(static fn (): int => PurchaseOrder::query()
            ->where('vendor_id', $vendorId)
            ->where('status', StatusPurchaseOrder::Sengketa->value)
            ->count());

        $invoicesTotal = $this->safeCount(static fn (): int => Invoice::query()
            ->where('vendor_id', $vendorId)
            ->count());

        $invoicesPending = $this->safeCount(static fn (): int => Invoice::query()
            ->where('vendor_id', $vendorId)
            ->where('status_matching', StatusMatching::Pending->value)
            ->count());

        $invoicesMatched = $this->safeCount(static fn (): int => Invoice::query()
            ->where('vendor_id', $vendorId)
            ->where('status_matching', StatusMatching::Matched->value)
            ->count());

        $totalRevenue = 0.0;
        try {
            $totalRevenue = (float) Invoice::query()
                ->where('vendor_id', $vendorId)
                ->where('status_matching', StatusMatching::Matched->value)
                ->sum('jumlah_tagihan');
        } catch (Throwable) {
            $totalRevenue = 0.0;
        }

        $activePartnerships = $this->safeCount(static fn (): int => KerjaSama::query()
            ->where('vendor_id', $vendorId)
            ->where('status', StatusKerjaSama::Agreed->value)
            ->count());

        $pendingPartnerships = $this->safeCount(static fn (): int => KerjaSama::query()
            ->where('vendor_id', $vendorId)
            ->where('status', StatusKerjaSama::Pending->value)
            ->count());

        return [
            'orders' => [
                'total' => $totalOrders,
                'pending_response' => $pendingResponse,
                'in_execution' => $inExecution,
                'completed_paid' => $completedPaid,
                'disputed' => $disputed,
            ],
            'invoices' => [
                'total' => $invoicesTotal,
                'pending' => $invoicesPending,
                'matched' => $invoicesMatched,
                'total_revenue' => round($totalRevenue, 2),
            ],
            'partnerships' => [
                'active' => $activePartnerships,
                'pending' => $pendingPartnerships,
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getVendorRecentOrders(int $vendorId, int $limit = 5): array
    {
        try {
            return PurchaseOrder::query()
                ->where('vendor_id', $vendorId)
                ->where('status', '!=', StatusPurchaseOrder::Draft->value)
                ->with([
                    'perusahaanBuyer:id,uuid,company_name',
                    'poItems:id,purchase_order_id,jumlah_pesanan,harga_satuan_po,harga_tawar_vendor,subtotal',
                ])
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get()
                ->map(static function (PurchaseOrder $po): array {
                    $totalAmount = 0.0;
                    foreach ($po->poItems as $item) {
                        $sub = (float) ($item->subtotal ?? 0);
                        if ($sub > 0) {
                            $totalAmount += $sub;
                        } else {
                            $qty = (float) ($item->jumlah_pesanan ?? 0);
                            // Never expose Buyer internal estimated_unit_price to Vendor (BR-02, UC-13)
                            $unitPrice = (float) ($item->harga_satuan_po ?? $item->harga_tawar_vendor ?? 0);
                            $totalAmount += $qty * $unitPrice;
                        }
                    }

                    $statusEnum = $po->status instanceof StatusPurchaseOrder
                        ? $po->status
                        : StatusPurchaseOrder::tryFrom((string) $po->status);

                    return [
                        'uuid' => $po->uuid,
                        'po_number' => $po->nomor_po,
                        'buyer_name' => $po->perusahaanBuyer?->company_name ?? 'Buyer',
                        'status' => $statusEnum?->value ?? (string) $po->status,
                        'status_label' => $statusEnum?->label() ?? (string) $po->status,
                        'items_count' => $po->poItems->count(),
                        'total_amount' => round($totalAmount, 2),
                        'tanggal_po' => $po->tanggal_po?->toDateString(),
                        'created_at' => $po->created_at?->toISOString(),
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
    public function getVendorPartnerships(int $vendorId, int $limit = 5): array
    {
        try {
            return KerjaSama::query()
                ->where('vendor_id', $vendorId)
                ->whereIn('status', [
                    StatusKerjaSama::Agreed->value,
                    StatusKerjaSama::Pending->value,
                ])
                ->with('perusahaanBuyer:id,uuid,company_name,address')
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->orderByDesc('updated_at')
                ->limit($limit)
                ->get()
                ->map(static function (KerjaSama $ks): array {
                    return [
                        'uuid' => $ks->uuid,
                        'buyer_name' => $ks->perusahaanBuyer?->company_name ?? 'Buyer',
                        'buyer_city' => $ks->perusahaanBuyer?->address,
                        'status' => $ks->status instanceof StatusKerjaSama ? $ks->status->value : (string) $ks->status,
                        'status_label' => $ks->status instanceof StatusKerjaSama ? $ks->status->label() : (string) $ks->status,
                        'agreed_at' => $ks->updated_at?->toISOString(),
                        'created_at' => $ks->created_at?->toISOString(),
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
    public function getVendorRecentInvoices(int $vendorId, int $limit = 5): array
    {
        try {
            return Invoice::query()
                ->where('vendor_id', $vendorId)
                ->with([
                    'purchaseOrder:id,uuid,nomor_po,perusahaan_buyer_id',
                    'purchaseOrder.perusahaanBuyer:id,uuid,company_name',
                ])
                ->orderByRaw("CASE WHEN status_matching = 'pending' THEN 0 WHEN status_matching = 'mismatched' THEN 1 ELSE 2 END")
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get()
                ->map(static function (Invoice $invoice): array {
                    $po = $invoice->purchaseOrder;
                    $buyer = $po?->perusahaanBuyer;
                    $matchingEnum = $invoice->statusMatchingEnum();

                    return [
                        'uuid' => $invoice->uuid,
                        'invoice_number' => $invoice->nomor_invoice_vendor,
                        'po_number' => $po?->nomor_po ?? '-',
                        'po_uuid' => $po?->uuid,
                        'buyer_name' => $buyer?->company_name ?? 'Buyer',
                        'amount' => round((float) ($invoice->jumlah_tagihan ?? 0), 2),
                        'matching_status' => $matchingEnum?->value ?? (string) ($invoice->status_matching ?? 'pending'),
                        'matching_status_label' => $matchingEnum?->label() ?? 'Menunggu Verifikasi',
                        'invoice_date' => $invoice->tanggal_invoice?->toDateString(),
                        'created_at' => $invoice->created_at?->toISOString(),
                    ];
                })
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }
}
