<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RoleName;
use App\Models\Notification;
use App\Models\User;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class NotificationService
{
    public function __construct(
        protected NotificationRepositoryInterface $notificationRepository,
    ) {}

    /**
     * Get recent notifications for user dropdown/popover.
     *
     * @return Collection<int, Notification>
     */
    public function getRecentNotifications(User $user, int $limit = 10): Collection
    {
        return $this->notificationRepository->getRecentForUser($user, $limit);
    }

    /**
     * Get paginated notifications for the notification page.
     *
     * @return LengthAwarePaginator<Notification>
     */
    public function getPaginatedNotifications(User $user, ?string $filter = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->notificationRepository->paginateForUser($user, $filter, $perPage);
    }

    /**
     * Get unread notification count.
     */
    public function getUnreadCount(User $user): int
    {
        return $this->notificationRepository->countUnreadForUser($user);
    }

    /**
     * Mark a specific notification as read by uuid.
     *
     * @throws NotFoundHttpException
     * @throws AuthorizationException
     */
    public function markAsRead(User $user, string $uuid): Notification
    {
        $notification = $this->notificationRepository->findByUuid($uuid);

        if (! $notification) {
            throw new NotFoundHttpException('Notifikasi tidak ditemukan.');
        }

        if ($notification->user_id !== $user->id) {
            throw new AuthorizationException('Anda tidak memiliki akses ke notifikasi ini.');
        }

        return $this->notificationRepository->markAsRead($notification);
    }

    /**
     * Mark all notifications as read for the user.
     */
    public function markAllAsRead(User $user): int
    {
        return $this->notificationRepository->markAllAsReadForUser($user);
    }

    /**
     * Determine whether the given user can access the target URL based on their role.
     * If false, the notification serves as a reminder only (redirect disabled).
     */
    public function canUserAccessUrl(User $user, ?string $url): bool
    {
        if (blank($url) || $url === '#' || $url === '') {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH) ?? $url;

        if ($path === '/dashboard') {
            return true;
        }

        $roleName = $user->roleName();
        if ($roleName === null) {
            return false;
        }

        // Purchase Order & Negosiasi: Only Owner, Purchasing, and Vendor
        if (str_starts_with($path, '/purchase-order') || str_starts_with($path, '/purchase-orders')) {
            return in_array($roleName, [RoleName::Owner, RoleName::Purchasing, RoleName::Vendor], true);
        }

        // Pengiriman Logistik: Owner, Purchasing, QC Gudang, and Vendor
        if (str_starts_with($path, '/pengiriman')) {
            return in_array($roleName, [RoleName::Owner, RoleName::Purchasing, RoleName::QcGudang, RoleName::Vendor], true);
        }

        // Inspeksi QC Gudang: Tenant internal roles and Vendor
        if (str_starts_with($path, '/inspeksi-qc')) {
            return in_array($roleName, [RoleName::Owner, RoleName::Manajemen, RoleName::Purchasing, RoleName::QcGudang, RoleName::Vendor], true);
        }

        // Log Sengketa & Kendala Pengadaan: Owner, Manajemen, and Purchasing
        if (str_starts_with($path, '/log-sengketa')) {
            return in_array($roleName, [RoleName::Owner, RoleName::Manajemen, RoleName::Purchasing], true);
        }

        // Verifikasi Tagihan (3-Way Matching): Owner and Purchasing
        if (str_starts_with($path, '/verifikasi-tagihan')) {
            return in_array($roleName, [RoleName::Owner, RoleName::Purchasing], true);
        }

        // Kerja Sama / Kemitraan: Only Owner, Manajemen, and Vendor
        if (str_starts_with($path, '/kerja-sama') || str_starts_with($path, '/master/vendor')) {
            return in_array($roleName, [RoleName::Owner, RoleName::Manajemen, RoleName::Vendor, RoleName::Superadmin], true);
        }

        // Manajemen Pengguna & Pegawai: Only Owner and Superadmin
        if (str_starts_with($path, '/manajemen/pengguna') || str_starts_with($path, '/manajemen/role-akses')) {
            return in_array($roleName, [RoleName::Owner, RoleName::Superadmin], true);
        }

        // Profil Perusahaan: Only Owner and Vendor
        if (str_starts_with($path, '/manajemen/perusahaan')) {
            return in_array($roleName, [RoleName::Owner, RoleName::Vendor], true);
        }

        // Pengaturan & Verifikasi Dokumen Superadmin: Only Superadmin
        if (str_starts_with($path, '/pengaturan') || str_starts_with($path, '/master/vendor/verifikasi-dokumen') || str_starts_with($path, '/master/tenant/verifikasi-dokumen')) {
            return $roleName === RoleName::Superadmin;
        }

        // Komoditas: Superadmin, Owner, Manajemen, Purchasing
        if (str_starts_with($path, '/master/komoditas')) {
            return in_array($roleName, [RoleName::Superadmin, RoleName::Owner, RoleName::Manajemen, RoleName::Purchasing], true);
        }

        return true;
    }

    /**
     * Send in-app notification to a single user.
     *
     * @param  array<string, mixed>|null  $data
     */
    public function send(
        User $recipient,
        string $type,
        string $title,
        string $message,
        ?string $actionUrl = null,
        ?array $data = null,
    ): Notification {
        return $this->notificationRepository->createForUser($recipient, [
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
            'data' => $data,
        ]);
    }

    /**
     * Send in-app notification to multiple users.
     *
     * @param  iterable<User>  $recipients
     * @param  array<string, mixed>|null  $data
     */
    public function sendToMany(
        iterable $recipients,
        string $type,
        string $title,
        string $message,
        ?string $actionUrl = null,
        ?array $data = null,
    ): void {
        DB::transaction(function () use ($recipients, $type, $title, $message, $actionUrl, $data) {
            foreach ($recipients as $recipient) {
                if ($recipient instanceof User) {
                    $this->send($recipient, $type, $title, $message, $actionUrl, $data);
                }
            }
        });
    }
}
