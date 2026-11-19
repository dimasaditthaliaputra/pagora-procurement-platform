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
     * Paginate disputes on the actor's side (buyer Tenant or PO Vendor) with search, status, trigger,
     * date range, and whitelisted sort.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateForActor(User $actor, array $filters = [], int $perPage = 10): LengthAwarePaginator;

    /**
     * Compute dispute summary statistics on the actor's side.
     *
     * @return array{
     *     total: int,
     *     open: int,
     *     awaiting_response: int,
     *     in_mediation: int,
     *     resolved: int,
     *     qc_triggered: int,
     *     invoice_triggered: int
     * }
     */
    public function getStatisticsForActor(User $actor): array;

    /**
     * Find a single dispute by UUID on the actor's side, eager-loading all related procurement records.
     */
    public function findByUuidForActor(User $actor, string $uuid): ?Sengketa;

    /**
     * Find a dispute on the actor's side and lock its row for the rest of the transaction.
     */
    public function lockByUuidForActor(User $actor, string $uuid): ?Sengketa;

    /**
     * Update resolution fields of a dispute that is not resolved yet (resolved disputes are immutable, BR-17).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Sengketa $sengketa, array $attributes): Sengketa;

    /**
     * Whether the invoice still has a dispute that is not `resolved` (BR-13).
     */
    public function hasOpenForInvoice(int $invoiceId): bool;

    /**
     * Transition a non-resolved dispute's status (e.g. `open` -> `in_mediation`).
     */
    public function updateStatus(Sengketa $sengketa, StatusSengketa $status): Sengketa;

    /**
     * Append an immutable entry to `dispute_logs` (`BR-17`, `RULES` §9.4 — append-only, no update/delete).
     *
     * @param  array{
     *     dispute_id: int,
     *     user_id: int|null,
     *     type: string,
     *     message: string,
     *     attachment_path?: string|null
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
