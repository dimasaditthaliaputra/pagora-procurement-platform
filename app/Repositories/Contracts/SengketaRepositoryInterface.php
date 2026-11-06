<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\StatusSengketa;
use App\Models\LogSengketa;
use App\Models\Sengketa;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Data access contract for Procurement Disputes & Log Sengketa (`UC-18`, `FR-31`, `RULES` §4.1 & §9.3).
 */
interface SengketaRepositoryInterface
{
    /**
     * Paginate disputes belonging to a specific Tenant with search, status, trigger, date range, and whitelisted sort.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateForTenant(int $tenantId, array $filters = [], int $perPage = 10): LengthAwarePaginator;

    /**
     * Compute dispute summary statistics scoped strictly to a Tenant.
     *
     * @return array{
     *     total: int,
     *     open: int,
     *     in_mediation: int,
     *     resolved: int,
     *     qc_triggered: int,
     *     invoice_triggered: int
     * }
     */
    public function getStatisticsForTenant(int $tenantId): array;

    /**
     * Find a single dispute by UUID strictly scoped to the given Tenant, eager-loading all related procurement records.
     */
    public function findByUuidForTenant(int $tenantId, string $uuid): ?Sengketa;

    /**
     * Transition a non-resolved dispute's status (e.g. `terbuka` -> `dalam_mediasi`).
     */
    public function updateStatus(Sengketa $sengketa, StatusSengketa $status): Sengketa;

    /**
     * Append an immutable entry to `log_sengketa` (`BR-17`, `RULES` §9.4 — append-only, no update/delete).
     *
     * @param  array{
     *     sengketa_id: int,
     *     user_id: int,
     *     type: string,
     *     isi_pesan: string,
     *     file_bukti?: string|null
     * }  $data
     */
    public function appendLog(array $data): LogSengketa;

    /**
     * Retrieve active stakeholder users (Tenant Owner/Manajemen/Purchasing + Vendor users) to notify on dispute updates.
     *
     * @return Collection<int, User>
     */
    public function getStakeholderRecipients(Sengketa $sengketa, int $excludeUserId): Collection;
}
