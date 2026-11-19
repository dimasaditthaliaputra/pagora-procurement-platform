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
}
