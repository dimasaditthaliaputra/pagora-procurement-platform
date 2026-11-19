<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface DashboardRepositoryInterface
{
    /**
     * Get platform-wide summary metrics for Superadmin dashboard.
     *
     * @return array<string, array<string, int>>
     */
    public function getSuperadminSummaryMetrics(): array;

    /**
     * Get recent buyer tenants awaiting verification (UC-21, FR-36).
     *
     * @return list<array<string, mixed>>
     */
    public function getPendingTenants(int $limit = 5): array;

    /**
     * Get recent vendors awaiting legal document verification (UC-21, FR-36).
     *
     * @return list<array<string, mixed>>
     */
    public function getPendingVendors(int $limit = 5): array;

    /**
     * Get recently globally blocked vendors (UC-19, FR-37).
     *
     * @return list<array<string, mixed>>
     */
    public function getBlockedVendors(int $limit = 5): array;

    /**
     * Get user count breakdown per role.
     *
     * @return list<array{role: string, label: string, total: int, active: int}>
     */
    public function getUserRoleDistribution(): array;

    /**
     * Get global commodity categories with their commodity and vendor counts (UC-09, FR-11).
     *
     * @return list<array{uuid: string, name: string, is_active: bool, commodities_count: int, vendors_count: int}>
     */
    public function getCommodityCategoryDistribution(int $limit = 6): array;

    /**
     * Get monthly platform onboarding & PO volume over the last N months.
     *
     * @return list<array{month_key: string, label: string, tenants_count: int, vendors_count: int, purchase_orders_count: int}>
     */
    public function getMonthlyPlatformGrowth(int $months = 6): array;

    /**
     * Get recent platform governance activities across Tenants, Vendors, and Commodities.
     *
     * @return list<array{uuid: string, category: string, title: string, description: string, status: string, action_url: string, occurred_at: string|null}>
     */
    public function getRecentGovernanceActivities(int $limit = 8): array;

    /**
     * Get tenant profile summary for Manajemen dashboard header.
     *
     * @return array<string, mixed>|null
     */
    public function getTenantProfileForManajemen(int $tenantId): ?array;

    /**
     * Get tenant-scoped summary KPI metrics for role Manajemen.
     *
     * @return array<string, array<string, int>>
     */
    public function getManajemenSummaryMetrics(int $tenantId): array;

    /**
     * Get active (open or in_mediation) disputes for the given Tenant (UC-18, FR-31).
     *
     * @return list<array<string, mixed>>
     */
    public function getManajemenActiveDisputes(int $tenantId, int $limit = 5): array;

    /**
     * Get recent vendor partnerships & blocked vendors for the given Tenant (UC-10, UC-19).
     *
     * @return list<array<string, mixed>>
     */
    public function getManajemenVendorPartnerships(int $tenantId, int $limit = 5): array;

    /**
     * Get recent QC rejected items / damage reports for the given Tenant (UC-15).
     *
     * @return list<array<string, mixed>>
     */
    public function getManajemenRecentQcRejections(int $tenantId, int $limit = 5): array;

    /**
     * Get configured commodities & shrinkage tolerances for the given Tenant (UC-09, FR-35).
     *
     * @return list<array<string, mixed>>
     */
    public function getManajemenTenantCommodities(int $tenantId, int $limit = 6): array;

    /**
     * Get 6-month operational trend (PO, QC inspections, Disputes) for the given Tenant.
     *
     * @return list<array{month_key: string, label: string, purchase_orders_count: int, qc_inspections_count: int, disputes_count: int}>
     */
    public function getManajemenMonthlyTrend(int $tenantId, int $months = 6): array;

    /**
     * Get comprehensive tenant-scoped executive summary metrics for role Owner
     * covering Procurement (PO), 3-Way Matching Invoices, Internal Employees, and Governance.
     *
     * @return array<string, array<string, int|float>>
     */
    public function getOwnerSummaryMetrics(int $tenantId): array;

    /**
     * Get recent Purchase Orders for the given Tenant (UC-12).
     *
     * @return list<array<string, mixed>>
     */
    public function getOwnerRecentPurchaseOrders(int $tenantId, int $limit = 5): array;

    /**
     * Get recent vendor invoices prioritized by pending/mismatched 3-Way Matching status (UC-17).
     *
     * @return list<array<string, mixed>>
     */
    public function getOwnerRecentInvoices(int $tenantId, int $limit = 5): array;

    /**
     * Get internal company employees across roles for the given Tenant (UC-08).
     *
     * @return list<array<string, mixed>>
     */
    public function getOwnerInternalTeam(int $tenantId, int $limit = 6): array;

    /**
     * Get 6-month executive procurement & billing trend for the given Tenant.
     *
     * @return list<array{month_key: string, label: string, purchase_orders_count: int, invoices_count: int, qc_inspections_count: int, po_value: float}>
     */
    public function getOwnerMonthlyExecutionTrend(int $tenantId, int $months = 6): array;

    // -------------------------------------------------------------------------
    // Buyer (Purchasing / Finance) Dashboard
    // -------------------------------------------------------------------------

    /**
     * Get procurement & invoice KPI summary for role Purchasing (Buyer).
     *
     * @return array<string, array<string, int|float>>
     */
    public function getBuyerSummaryMetrics(int $tenantId): array;

    /**
     * Get recent Purchase Orders visible to Buyer for the given Tenant.
     *
     * @return list<array<string, mixed>>
     */
    public function getBuyerRecentPurchaseOrders(int $tenantId, int $limit = 5): array;

    /**
     * Get invoices pending 3-Way Matching verification for Buyer.
     *
     * @return list<array<string, mixed>>
     */
    public function getBuyerPendingInvoices(int $tenantId, int $limit = 5): array;

    /**
     * Get active disputes initiated from this Tenant (Buyer perspective).
     *
     * @return list<array<string, mixed>>
     */
    public function getBuyerActiveDisputes(int $tenantId, int $limit = 5): array;

    // -------------------------------------------------------------------------
    // QC Gudang Dashboard
    // -------------------------------------------------------------------------

    /**
     * Get QC inspection KPI summary for role QC Gudang.
     *
     * @return array<string, array<string, int|float>>
     */
    public function getQcSummaryMetrics(int $tenantId): array;

    /**
     * Get incoming PO deliveries awaiting QC inspection.
     *
     * @return list<array<string, mixed>>
     */
    public function getQcPendingDeliveries(int $tenantId, int $limit = 5): array;

    /**
     * Get recent QC inspection results (accepted & rejected).
     *
     * @return list<array<string, mixed>>
     */
    public function getQcRecentInspections(int $tenantId, int $limit = 5): array;
}
