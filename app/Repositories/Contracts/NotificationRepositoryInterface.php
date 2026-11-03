<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface NotificationRepositoryInterface
{
    /**
     * Get recent notifications for a user.
     *
     * @return Collection<int, Notification>
     */
    public function getRecentForUser(User $user, int $limit = 10): Collection;

    /**
     * Paginate notifications for a user, with optional filter ('all', 'unread', 'read').
     *
     * @return LengthAwarePaginator<Notification>
     */
    public function paginateForUser(User $user, ?string $filter = null, int $perPage = 15): LengthAwarePaginator;

    /**
     * Count unread notifications for a user.
     */
    public function countUnreadForUser(User $user): int;

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Notification $notification): Notification;

    /**
     * Mark all unread notifications as read for a user.
     */
    public function markAllAsReadForUser(User $user): int;

    /**
     * Create a notification for a user.
     *
     * @param  array{type: string, title: string, message: string, action_url?: string|null, data?: array<string, mixed>|null}  $data
     */
    public function createForUser(User $user, array $data): Notification;

    /**
     * Find notification by UUID.
     */
    public function findByUuid(string $uuid): ?Notification;
}
