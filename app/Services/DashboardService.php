<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\DashboardRepositoryInterface;

class DashboardService
{
    public function __construct(
        private readonly DashboardRepositoryInterface $dashboardRepository,
        private readonly PengaturanService $pengaturanService,
    ) {}

    /**
     * Build dashboard payload tailored to the authenticated user's role.
     *
     * @return array<string, mixed>
     */
    public function buildForUser(?User $user): array
    {
        $isSuperadmin = $user !== null && $user->isSuperadmin();
        $isManajemen = $user !== null && $user->isManajemen() && $user->tenant_id !== null;
        $isOwner = $user !== null && $user->isOwner() && $user->tenant_id !== null;
        $isPurchasing = $user !== null && $user->isPurchasing() && $user->tenant_id !== null;

        return [
            'user' => $user,
            'is_superadmin' => $isSuperadmin,
            'superadmin_dashboard' => $isSuperadmin
                ? $this->buildSuperadminDashboard()
                : null,
            'is_manajemen' => $isManajemen,
            'manajemen_dashboard' => $isManajemen
                ? $this->buildManajemenDashboard((int) $user->tenant_id)
                : null,
            'is_owner' => $isOwner,
            'owner_dashboard' => $isOwner
                ? $this->buildOwnerDashboard((int) $user->tenant_id)
                : null,
            'is_purchasing' => $isPurchasing,
            'buyer_dashboard' => $isPurchasing
                ? $this->buildBuyerDashboard((int) $user->tenant_id)
                : null,
        ];
    }

    /**
     * Assemble all platform governance, verification queues, master data,
     * user role distribution, and integration metrics for Superadmin.
     *
     * @return array<string, mixed>
     */
    public function buildSuperadminDashboard(): array
    {
        $profil = $this->pengaturanService->profilPublik();
        $integrasi = $this->pengaturanService->grup(PengaturanService::GRUP_INTEGRASI);

        $pgClientKey = $integrasi['pg_client_key'] ?? null;
        $pgServerKey = $integrasi['pg_server_key'] ?? null;

        return [
            'summary' => $this->dashboardRepository->getSuperadminSummaryMetrics(),
            'pending_tenants' => $this->dashboardRepository->getPendingTenants(5),
            'pending_vendors' => $this->dashboardRepository->getPendingVendors(5),
            'blocked_vendors' => $this->dashboardRepository->getBlockedVendors(5),
            'role_distribution' => $this->dashboardRepository->getUserRoleDistribution(),
            'category_distribution' => $this->dashboardRepository->getCommodityCategoryDistribution(6),
            'monthly_growth' => $this->dashboardRepository->getMonthlyPlatformGrowth(6),
            'recent_activities' => $this->dashboardRepository->getRecentGovernanceActivities(8),
            'system_integrations' => [
                'app_name' => (string) ($profil['nama'] ?? 'PAGORA'),
                'tagline' => (string) ($profil['tagline'] ?? 'E-Procurement'),
                'bi_active' => (bool) ($integrasi['bi_aktif'] ?? true),
                'bi_base_url' => (string) ($integrasi['bi_base_url'] ?? 'https://www.bi.go.id/hargapangan'),
                'bi_sync_schedule' => (string) ($integrasi['bi_jadwal_sinkron'] ?? 'harian'),
                'pg_provider' => (string) ($integrasi['pg_provider'] ?? 'midtrans'),
                'pg_mode' => (string) ($integrasi['pg_mode'] ?? 'sandbox'),
                'pg_configured' => ! empty($pgClientKey) && ! empty($pgServerKey),
            ],
        ];
    }

    /**
     * Assemble tenant-scoped governance, dispute mediation, vendor partnerships,
     * QC inspection monitoring, and commodity tolerance metrics for role Manajemen.
     *
     * @return array<string, mixed>
     */
    public function buildManajemenDashboard(int $tenantId): array
    {
        return [
            'company' => $this->dashboardRepository->getTenantProfileForManajemen($tenantId),
            'summary' => $this->dashboardRepository->getManajemenSummaryMetrics($tenantId),
            'active_disputes' => $this->dashboardRepository->getManajemenActiveDisputes($tenantId, 5),
            'vendor_partnerships' => $this->dashboardRepository->getManajemenVendorPartnerships($tenantId, 5),
            'recent_qc_rejections' => $this->dashboardRepository->getManajemenRecentQcRejections($tenantId, 5),
            'tenant_commodities' => $this->dashboardRepository->getManajemenTenantCommodities($tenantId, 6),
            'monthly_trend' => $this->dashboardRepository->getManajemenMonthlyTrend($tenantId, 6),
        ];
    }

    /**
     * Assemble end-to-end tenant executive metrics for role Owner (REQ-TEN-03, UC-07..UC-20):
     * company profile, internal employees, procurement orders & value, 3-Way Matching invoices,
     * vendor partnerships, QC inspections, and active disputes.
     *
     * @return array<string, mixed>
     */
    public function buildOwnerDashboard(int $tenantId): array
    {
        return [
            'company' => $this->dashboardRepository->getTenantProfileForManajemen($tenantId),
            'summary' => $this->dashboardRepository->getOwnerSummaryMetrics($tenantId),
            'recent_purchase_orders' => $this->dashboardRepository->getOwnerRecentPurchaseOrders($tenantId, 5),
            'recent_invoices' => $this->dashboardRepository->getOwnerRecentInvoices($tenantId, 5),
            'internal_team' => $this->dashboardRepository->getOwnerInternalTeam($tenantId, 6),
            'active_disputes' => $this->dashboardRepository->getManajemenActiveDisputes($tenantId, 5),
            'monthly_trend' => $this->dashboardRepository->getOwnerMonthlyExecutionTrend($tenantId, 6),
        ];
    }

    /**
     * Assemble procurement-focused dashboard for role Purchasing / Finance (UC-11, UC-12, UC-17, UC-18).
     *
     * @return array<string, mixed>
     */
    public function buildBuyerDashboard(int $tenantId): array
    {
        return [
            'company' => $this->dashboardRepository->getTenantProfileForManajemen($tenantId),
            'summary' => $this->dashboardRepository->getBuyerSummaryMetrics($tenantId),
            'recent_purchase_orders' => $this->dashboardRepository->getBuyerRecentPurchaseOrders($tenantId, 5),
            'pending_invoices' => $this->dashboardRepository->getBuyerPendingInvoices($tenantId, 5),
            'active_disputes' => $this->dashboardRepository->getBuyerActiveDisputes($tenantId, 5),
        ];
    }
}
