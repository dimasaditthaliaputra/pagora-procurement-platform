<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\RoleName;
use App\Models\Notification;
use App\Models\User;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class NotificationRepository implements NotificationRepositoryInterface
{
    /**
     * @return Collection<int, Notification>
     */
    public function getRecentForUser(User $user, int $limit = 10): Collection
    {
        return $this->applyRoleFilter(Notification::query(), $user)
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return LengthAwarePaginator<Notification>
     */
    public function paginateForUser(User $user, ?string $filter = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->applyRoleFilter(Notification::query(), $user);

        if ($filter === 'unread') {
            $query->unread();
        } elseif ($filter === 'read') {
            $query->read();
        }

        return $query->latest('created_at')->paginate($perPage)->withQueryString();
    }

    public function countUnreadForUser(User $user): int
    {
        return $this->applyRoleFilter(Notification::query(), $user)->unread()->count();
    }

    public function markAsRead(Notification $notification): Notification
    {
        $notification->markAsRead();

        return $notification->fresh();
    }

    public function markAllAsReadForUser(User $user): int
    {
        return $this->applyRoleFilter(Notification::query(), $user)
            ->unread()
            ->update(['read_at' => now()]);
    }

    /**
     * Scope query to only include notification types relevant to the user's role.
     *
     * @param  Builder<Notification>  $query
     * @return Builder<Notification>
     */
    protected function applyRoleFilter(Builder $query, User $user): Builder
    {
        $roleName = $user->roleName();

        if ($roleName === null) {
            return $query->where('user_id', $user->id);
        }

        $allowedTypes = match ($roleName) {
            RoleName::Superadmin => [
                'verification',
                'tenant',
                'vendor',
                'system',
            ],
            RoleName::Owner => [
                'purchase_order',
                'negotiation',
                'partnership',
                'employee',
                'verification',
                'system',
                'goods_receipt',
                'invoice',
                'dispute',
            ],
            RoleName::Manajemen => [
                'partnership',
                'employee',
                'commodity',
                'dispute',
                'system',
            ],
            RoleName::Purchasing => [
                'purchase_order',
                'negotiation',
                'invoice',
                'dispute',
                'system',
            ],
            RoleName::QcGudang => [
                'goods_receipt',
                'inspection',
                'dispute',
                'system',
            ],
            RoleName::Vendor => [
                'purchase_order',
                'negotiation',
                'partnership',
                'verification',
                'invoice',
                'shipment',
                'dispute',
                'system',
            ],
        };

        return $query->where('user_id', $user->id)->whereIn('type', $allowedTypes);
    }

    public function createForUser(User $user, array $data): Notification
    {
        return Notification::create([
            'user_id' => $user->id,
            'type' => $data['type'] ?? 'system',
            'title' => $data['title'],
            'message' => $data['message'],
            'action_url' => $data['action_url'] ?? null,
            'data' => $data['data'] ?? null,
        ]);
    }

    public function findByUuid(string $uuid): ?Notification
    {
        return Notification::where('uuid', $uuid)->first();
    }
}
