<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService,
    ) {}

    /**
     * Display the full notification listing page (Inertia).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filter = $request->query('filter', 'all');
        $notifications = $this->notificationService->getPaginatedNotifications($user, $filter, 15);
        $unreadCount = $this->notificationService->getUnreadCount($user);

        return Inertia::render('Notifikasi/Index', [
            'notifications' => NotificationResource::collection($notifications),
            'unreadCount' => $unreadCount,
            'filter' => $filter,
        ]);
    }

    /**
     * Return JSON listing of notifications (for popover & AJAX).
     */
    public function apiIndex(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($request->has('limit')) {
            $limit = (int) $request->input('limit', 10);
            $notifications = $this->notificationService->getRecentNotifications($user, $limit);
            $unreadCount = $this->notificationService->getUnreadCount($user);

            return response()->json([
                'notifications' => NotificationResource::collection($notifications),
                'unread_count' => $unreadCount,
            ]);
        }

        $paginated = $this->notificationService->getPaginatedNotifications(
            $user,
            $request->query('filter'),
            (int) $request->query('per_page', 15)
        );

        return NotificationResource::collection($paginated)->response();
    }

    /**
     * Mark a notification as read and return redirect URL.
     */
    public function markAsRead(Request $request, string $uuid): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $notification = $this->notificationService->markAsRead($user, $uuid);
        $canRedirect = $this->notificationService->canUserAccessUrl($user, $notification->action_url) && $notification->type !== 'system';

        // When triggered by Inertia (e.g. router.post)
        if ($request->header('X-Inertia')) {
            if ($canRedirect && $notification->action_url) {
                return redirect($notification->action_url);
            }

            return back()->with('success', 'Notifikasi telah ditandai sebagai dibaca.');
        }

        // When triggered by plain AJAX / API (e.g. background fetch in popover)
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'redirect_url' => $canRedirect ? $notification->action_url : null,
                'can_redirect' => $canRedirect,
                'notification' => new NotificationResource($notification),
            ]);
        }

        if ($canRedirect && $notification->action_url) {
            return redirect($notification->action_url);
        }

        return back()->with('success', 'Notifikasi telah ditandai sebagai dibaca.');
    }

    /**
     * Mark all notifications as read for current user.
     */
    public function markAllAsRead(Request $request): JsonResponse|RedirectResponse
    {
        $this->notificationService->markAllAsRead($request->user());

        if ($request->header('X-Inertia')) {
            return back()->with('success', 'Semua notifikasi telah ditandai sebagai dibaca.');
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Semua notifikasi telah ditandai sebagai dibaca.',
            ]);
        }

        return back()->with('success', 'Semua notifikasi telah ditandai sebagai dibaca.');
    }

    /**
     * Get unread notifications count.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $unreadCount = $this->notificationService->getUnreadCount($request->user());

        return response()->json([
            'unread_count' => $unreadCount,
        ]);
    }
}
