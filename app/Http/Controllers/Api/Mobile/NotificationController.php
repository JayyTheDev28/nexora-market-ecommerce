<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * List authenticated user's real database notifications.
     * GET /api/mobile/v1/notifications
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));
        $typeFilter = trim((string) $request->query('filter', 'all'));

        $query = $user->notifications()->latest();

        if ($typeFilter === 'unread') {
            $query->whereNull('read_at');
        }

        $paginated = $query->paginate($perPage)->withQueryString();

        $items = collect($paginated->items())->map(function ($notification) {
            $data = is_array($notification->data) ? $notification->data : [];

            return [
                'id' => $notification->id,
                'type' => $data['type'] ?? $data['event'] ?? class_basename((string) $notification->type),
                'title' => $data['title'] ?? 'Nexora Notification',
                'message' => $data['message'] ?? '',
                'order_id' => $data['order_id'] ?? null,
                'order_number' => $data['order_number'] ?? null,
                'status' => $data['status'] ?? null,
                'manifest_id' => $data['manifest_id'] ?? null,
                'incident_id' => $data['incident_id'] ?? null,
                'incident_number' => $data['incident_number'] ?? null,
                'read_at' => $notification->read_at?->toIso8601String(),
                'is_read' => $notification->read_at !== null,
                'created_at' => $notification->created_at?->toIso8601String(),
                'created_human' => $notification->created_at?->diffForHumans(),
            ];
        })->values();

        return response()->json([
            'data' => [
                'notifications' => $items,
                'unread_count' => $user->unreadNotifications()->count(),
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'last_page' => $paginated->lastPage(),
                ],
            ],
            'message' => 'Notifications retrieved successfully.',
        ]);
    }

    /**
     * Mark a single notification as read.
     * POST /api/mobile/v1/notifications/{id}/read
     */
    public function markRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $notification = $user->notifications()->where('id', $id)->firstOrFail();

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return response()->json([
            'data' => [
                'id' => $notification->id,
                'is_read' => true,
                'unread_count' => $user->unreadNotifications()->count(),
            ],
            'message' => 'Notification marked as read.',
        ]);
    }

    /**
     * Mark all notifications as read.
     * POST /api/mobile/v1/notifications/read-all
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'data' => [
                'unread_count' => 0,
            ],
            'message' => 'All notifications marked as read.',
        ]);
    }
}
