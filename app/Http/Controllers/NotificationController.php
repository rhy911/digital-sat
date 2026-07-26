<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Full notifications feed page.
     */
    public function index(Request $request)
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(20);

        return view('notifications.index', [
            'user' => $request->user(),
            'notifications' => $notifications,
        ]);
    }

    /**
     * Unread count + latest few, consumed by the header bell poller.
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        $recent = $user->notifications()
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? 'Notification',
                'body' => $n->data['body'] ?? '',
                'url' => $n->data['url'] ?? null,
                'icon' => $n->data['icon'] ?? 'default',
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at->diffForHumans(),
            ]);

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'recent' => $recent,
        ]);
    }

    /**
     * Mark a single notification read (ownership enforced by relationship scope).
     */
    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        return response()->json(['ok' => true]);
    }

    /**
     * Mark all of the current user's notifications read.
     */
    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back();
    }
}
